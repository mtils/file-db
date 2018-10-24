<?php
/**
 *  * Created by mtils on 22.09.18 at 15:13.
 **/

namespace FileDB\Model;


use Ems\Contracts\Core\Exceptions\TypeException;
use Ems\Contracts\Core\Filesystem;
use Ems\Contracts\Core\HasMethodHooks;
use Ems\Contracts\Core\Type;
use Ems\Contracts\Tree\CanHaveParent;
use Ems\Contracts\Tree\Node;
use Ems\Contracts\Tree\NodeRepository;
use Ems\Core\Collections\StringList;
use Ems\Core\Exceptions\DataIntegrityException;
use Ems\Core\Exceptions\ResourceNotFoundException;
use Ems\Core\Helper;
use Ems\Core\LocalFilesystem;
use Ems\Core\ManualMimeTypeProvider;
use Ems\Core\Patterns\HookableTrait;
use Ems\Core\Url;
use Ems\Model\Eloquent\NotFoundException;
use RuntimeException;
use function array_reverse;
use function in_array;
use function mb_strtolower;
use function str_replace;

/**
 * Class EmsFileDBModel
 *
 * Used properties: title, is_empty, exists, hash, parent_id
 *
 *
 * Strange used methods: ->is()? ->getFullPath() ->isEmpty() ->getUrl()
 *
 * @package FileDB\Model
 */
class EmsFileDBModel implements FileDBModelInterface, HasMethodHooks
{
    use HookableTrait;

    /**
     * @var Filesystem
     */
    protected $filesystem;

    /**
     * @var NodeRepository
     */
    protected $nodeRepository;

    /**
     * @var LocalFilesystem
     */
    protected $localFilesystem;

    /**
     * @var array
     */
    protected $nodeCache = [];

    /**
     * All of this files will not be mirrored inside the db.
     *
     * @var array
     */
    protected $excludeFromDb = [
        'web.config',
        'thumbs.db'
    ];

    /**
     * @var UrlMapperInterface
     */
    protected $urlMapper;

    /**
     * EmsFileDBModel constructor.
     *
     * @param Filesystem      $filesystem
     * @param NodeRepository  $nodeRepository
     * @param LocalFilesystem $localFilesystem (optional)
     */
    public function __construct(Filesystem $filesystem, NodeRepository $nodeRepository, LocalFilesystem $localFilesystem=null)
    {
        $this->filesystem = $filesystem;
        $this->nodeRepository = $nodeRepository;
        $this->localFilesystem = $localFilesystem ?: new LocalFilesystem();
    }

    /**
     * @param $id
     * @param int $depth
     *
     * @return FileInterface
     */
    public function getById($id, $depth = 0)
    {
        return $this->nodeRepoForDepth($depth)->getOrFail($id);
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     * @param int   $depth
     *
     * @return FileInterface
     */
    public function get($path, $depth = 0)
    {
        $node = $this->nodeRepoForDepth($depth)->getByPathOrFail($path);
        return $this->ensureEloquentFile($node);
    }

    /**
     * {@inheritdoc}
     *
     * @return FileInterface
     */
    public function create()
    {
        $file = $this->ensureEloquentFile($this->nodeRepository->make());
        $this->callBeforeListeners('create', [$file]);
        $this->callAfterListeners('create', [$file]);
        return $file;
    }

    /**
     * {@inheritdoc}
     *
     * @deprecated Use syncWithFs($path)
     *
     * @param string $path
     *
     * @return FileInterface
     */
    public function createFromPath($path)
    {
        return $this->createFromFs($path);
    }

    /**
     * {@inheritdoc}
     *
     * @param FileInterface|null $folder
     *
     * @return FileInterface[]
     */
    public function listDir(FileInterface $folder = null)
    {
        if ($folder) {
            $children = $this->nodeRepository->children($this->ensureEloquentFile($folder));
            return $this->ensureEloquentFiles($children);
        }

        /** @var Node $root */
        $root = $this->nodeRepoForDepth(1)->getByPathOrFail('/');

        return $this->ensureEloquentFiles($root->getChildren());
    }

    /**
     * {@inheritdoc}
     *
     * @param FileInterface $file
     *
     * @return bool
     */
    public function save(FileInterface $file)
    {
        $file = $this->ensureEloquentFile($file);

        $this->callBeforeListeners('save', [$file]);

        $path = $file->getPath();
        $parentDir = null;

        if ($path != '/') {
            $parentDir = $this->ensureParentIsInDb($file);
            $path = '/' .  $this->normalizeFilePath($file, $parentDir);
            $this->applyChangesInFs($file, $path);
        }

        // Save the title
        $title = $file->hasTitle() ? $file->getTitle() : '';
        $this->fillByPath($file, $path);

        if ($title) {
            $file->setTitle($title);
        }

        if ($parentDir) {
            $file->setParent($parentDir);
        }

        if (!$this->nodeRepository->save($file)) {
            return false;
        }

        $this->callAfterListeners('save', [$file]);

        return true;
    }

    /**
     * @inheritDoc
     */
    public function syncWithFs($fileOrFolder, $depth = 0)
    {

        if (!$fileOrFolder instanceof FileInterface) {
            $fileOrFolder = $this->getOrMakeByPath($fileOrFolder);
        }

        $fileOrFolder = $this->ensureEloquentFile($fileOrFolder);

        if (!$fileOrFolder->getMimeType()) {
            throw new RuntimeException("Before syncing, assign a mimetype to '" . $fileOrFolder->getPath() . "'");
        }

        // If not it is the root node
        if ($parentDir = $this->getOrCreateParent($fileOrFolder)) {
            $fileOrFolder->setDir($parentDir);
        }

        // Only save newly created nodes
        if (!$fileOrFolder->getId()) {
            $this->save($fileOrFolder);
        }

        // No Directory
        if (!$fileOrFolder->isDir() || $depth < 1) {
            return;
        }

        $dbChildren = $this->nodeRepository->children($fileOrFolder);

        // First delete all children that do not exist in fs
        $dbChildren = $this->removeDeletedFsChildrenFromDb($dbChildren);

        /** @var FileInterface[] $dbChildrenBySegment */
        $dbChildrenBySegment = $this->indexBySegment($dbChildren);

        // Until here it is sure that the passed $fileOrFolder is a dir
        $dir = $fileOrFolder;

        $fsChildren = $this->filesystem->listDirectory($dir->getPath());

        foreach ($fsChildren as $fsChildPath) {

            $fsChildName = $this->filesystem->basename($fsChildPath);
            $fsIsDirectory = $this->filesystem->isDirectory($fsChildPath);

            if ($this->isExcludedFromDb($fsChildName)) {
                continue;
            }

            $existsInDatabase = isset($dbChildrenBySegment[$fsChildName]);

            // If the db node is a directory and the fs node not or vice versa:
            // Just delete (and later recreate) the entry
            if ($existsInDatabase && $dbChildrenBySegment[$fsChildName]->isDir() !== $fsIsDirectory) {
                $this->nodeRepository->delete($this->ensureEloquentFile($dbChildrenBySegment[$fsChildName]));
                unset($dbChildrenBySegment[$fsChildName]);

                $existsInDatabase = false;
            }

            if ($existsInDatabase) {
                continue;
            }

            $dbChild = $this->createFromFs($fsChildPath);
            $dbChild->setDir($fileOrFolder);
            $this->syncWithFs($dbChild, $depth-1);

        }

    }

    /**
     * {@inheritdoc}
     *
     * @param string $localPath
     * @param FileInterface $folder (optional)
     *
     * @return FileInterface The new file
     */
    public function importFile($localPath, FileInterface $folder = null)
    {

        // If the file db points to / (NOT RECOMMENDED!) just create the file
        if ($this->isAbsoluteLocalFilesystem()) {
            return $this->getOrCreateByPath($localPath, $folder);
        }

        if ($this->isWithinFilesystem($localPath)) {

            // Handle special situation where somebody added a file inside
            // the filesystem and passes an absolute path
            $fsPath = $this->absoluteToFilesystemPath($localPath);

            return $this->getOrCreateByPath($fsPath, $folder);
        }

        if (!$this->localFilesystem->exists($localPath)) {
            throw new ResourceNotFoundException("Path '$localPath' not found.'");
        }

        $fileName = $this->localFilesystem->basename($localPath);

        $newPath = $folder ? $folder->getPath() . "/$fileName" : "/$fileName";
        $newPath = '/' . ltrim($newPath, '/');

        $this->filesystem->write($newPath, $this->localFilesystem->open($localPath, 'r'));

        return $this->getOrCreateByPath($newPath, $folder);
    }


    /**
     * @inheritDoc
     */
    public function deleteFile(FileInterface $file)
    {
        $file = $this->ensureEloquentFile($file);

        if ($file->isDir() && !$file->isEmpty()) {
            throw new \BadMethodCallException('Currently only deleting of empty dirs is supported');
        }

        if (!$this->filesystem->delete($file->getPath())) {
            throw new \RuntimeException("The local filesystem didn't delete the file ". $file->getPath());
        }

        $this->nodeRepository->delete($file);

        if ($parent = $this->getById($file->getParentId())) {
            $this->syncWithFs($parent);
        }

        return $this;
    }

    /**
     * @inheritDoc
     */
    protected function isSame(FileInterface $leftFile, FileInterface $rightFile)
    {
        return $leftFile->getPath() == $rightFile->getPath();
    }

    /**
     * @inheritDoc
     */
    public function getPathMapper()
    {
        return $this->urlMapper;
    }

    /**
     * @param UrlMapperInterface $urlMapper
     *
     * @return $this
     */
    public function setUrlMapper(UrlMapperInterface $urlMapper)
    {
        $this->urlMapper = $urlMapper;
        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getParents(FileInterface $file)
    {
        $ancestors = $this->nodeRepository->ancestors($this->ensureEloquentFile($file));

        return array_reverse(Type::toArray($ancestors));

    }

    /**
     * @inheritDoc
     */
    public function methodHooks()
    {
        return ['create', 'save'];
    }

    /**
     * {@inheritdoc}
     *
     * @param string $path
     *
     * @return FileInterface
     */
    protected function createFromFs($path)
    {
        return $this->fillByPath($this->create(), $path);
    }

    /**
     * Delete the non existing
     *
     * @param CanHaveParent[] $dbChildren
     *
     * @return array
     */
    protected function removeDeletedFsChildrenFromDb($dbChildren)
    {
        $existingChildren = [];

        // First delete all children that do not exist in fs
        foreach ($dbChildren as $child) {

            if (!$this->filesystem->exists($child->getPath())) {
                $this->nodeRepository->delete($child);
                continue;
            }
            $existingChildren[] = $child;
        }

        return $existingChildren;
    }

    /**
     * @param CanHaveParent[] $dbChildren
     *
     * @return array
     */
    protected function indexBySegment($dbChildren)
    {
        $bySegment = [];

        foreach ($dbChildren as $child) {
            $bySegment[$child->getPathSegment()] = $child;
        }

        return $bySegment;
    }

    /**
     * Get the NodeRepository for the desired $depth
     *
     * @param int $depth (default=0)
     *
     * @return \Ems\Contracts\Tree\NodeProvider|NodeRepository
     */
    protected function nodeRepoForDepth($depth=0)
    {
        if ($depth == 0) {
            return $this->nodeRepository;
        }
        return $this->nodeRepository->recursive($depth);
    }

    protected function fillByPath(FileInterface $file, $path)
    {
        $file->setPath($this->normalizePathForDb($path));
        $file->setName($this->filesystem->basename($path));

        if (!$mimeType = $this->filesystem->mimeType($path)) {
            $mimeType = ManualMimeTypeProvider::$fallbackMimeType;
        }
        $file->setMimeType($mimeType);
        return $file;

    }

    protected function getAndAssignParentDir(EloquentFile $file)
    {
        if ($parent = $file->getDir()) {
            return $parent;
        }

        if (!$parentId = $file->getParentId()) {
            throw new DataIntegrityException("Passed file " . $file->getPath() . ' has no parent id.');
        }

        if (!$parent = $this->getById($parentId)) {
            throw new NotFoundException("Parent directory of " . $file->getPath() . " with id #$parentId not found.");
        }

        $file->setDir($parent);

        return $parent;
    }

    protected function ensureParentIsInDb(EloquentFile $file)
    {
        $path = (new Url($file->getPath()))->path;

        // All direct children of the root directory are root nodes in db
        if (count($path) == 1) {
            return $this->get('/');
        }

        if (!$parent = $this->guessNodeParent($file)) {
            $parent = $this->createParentsByPath($path);
        }

        if (!$parent) {
            throw new RuntimeException("No valid parent could be found or created.");
        }

        // Parent seems to not exist
        if (!$parent->getId()) {
            throw new RuntimeException("Do not assign non existing parents when saving a node.");
        }

        return $parent;
    }

    protected function guessNodeParent(EloquentFile $node)
    {
        if ($parent = $node->getParent()) {
            return $parent;
        }

        if ($parentId = $node->getParentId()) {
            return $this->nodeRepository->getOrFail($parentId);
        }
    }

    /**
     * Cat the absolute part of a file path off to make a relative filesystem
     * path
     *
     * @param string $filePath
     *
     * @return string
     */
    protected function absoluteToFilesystemPath($filePath)
    {

        if (!$this->isWithinFilesystem($filePath)) {
            return $filePath;
        }

        $fsPath = (string)$this->filesystem->url()->path;

        return str_replace($fsPath,'', $filePath);

    }

    /**
     * Return true if the filesystem is a local filesystem pointing to /.
     * This is needed for some path calculations.
     *
     * @return bool
     */
    protected function isAbsoluteLocalFilesystem()
    {
        $url = $this->filesystem->url('/');
        return $url->equals('file:///', ['scheme', 'path']);
    }

    /**
     * Return true if a path is local and absolute.
     *
     * @param string $path
     *
     * @return bool
     */
    protected function isAbsoluteLocalPath($path)
    {
        return $this->localFilesystem->exists($path);
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    protected function isWithinFilesystem($path)
    {
        $pathUrl = new Url($path);
        $fsUrl = $this->filesystem->url();

        if (!$pathUrl->scheme) {
            $pathUrl = $pathUrl->scheme('file');
        }

        if (!$fsUrl->scheme) {
            $fsUrl = $fsUrl->scheme('file');
        }

        if ($pathUrl->scheme != $fsUrl->scheme) {
            return false;
        }

        if (Helper::startsWith("$pathUrl->path", "$fsUrl->path")) {
            return true;
        }

        return $this->filesystem->exists($path);
    }

    protected function createParentsByPath(StringList $path)
    {
        $pathStack = [];
        $nodesByPath = [];
        $lastPath = '';
        $node = null;

        foreach ($path as $segment) {

            $pathStack[] = $segment;
            $currentPath = '/' . implode('/', $pathStack);

            $parent = isset($pathStack[$lastPath]) ? $pathStack[$lastPath] : null;
            $node = $this->getOrCreateByPath($currentPath, $parent);

            $nodesByPath[$currentPath] = $node;
            $lastPath = $currentPath;
        }

        return $node;
    }

    /**
     * @param string $path
     * @param EloquentFile|null $parentNode
     *
     * @return EloquentFile
     */
    protected function getOrCreateByPath($path, EloquentFile $parentNode=null)
    {
        $node = $this->getOrMakeByPath($path, $parentNode);

        if (!$node->getId()) {
            $this->save($node);
        }

        return $node;

    }

    /**
     * @param string $path
     * @param EloquentFile|null $parentNode
     *
     * @return EloquentFile
     */
    protected function getOrMakeByPath($path, EloquentFile $parentNode=null)
    {
        if ($node = $this->nodeRepository->getByPath($path)) {
            return $this->ensureEloquentFile($node);
        }

        $nodeRepo = $parentNode ? $this->nodeRepository->asChildOf($parentNode) : $this->nodeRepository;

        $file = $this->ensureEloquentFile($nodeRepo->make());
        $this->fillByPath($file, $path);
        return $file;

    }

    protected function getOrCreateParent(FileInterface $fileOrFolder)
    {

        $parent = $fileOrFolder->getDir();

        if($parent && $parent->exists){
            return $parent;
        }

        if($fileOrFolder->getPath() == '/'){
            return null;
        }

        // TODO Handle situation in which you have to create n parents
        $paths = $this->getParentPaths($fileOrFolder->getPath());

    }

    protected function getParentPaths($path){
        $segments = explode('/', $path);
        $paths = array('/');
        $stack = array();
        foreach($segments as $segment){
            $stack[] = $segment;
            $paths[] = implode('/', $stack);
        }
        return $paths;
    }

    /**
     * Normalize the passed path before storing it.
     *
     * @param string $path
     *
     * @return string
     */
    protected function trimPath($path)
    {
        return trim($path, '/');
    }

    /**
     * Optimize a path for store it in the database.
     *
     * @param string $path
     *
     * @return string
     */
    protected function normalizePathForDb($path)
    {
        return '/' . $this->trimPath($path);
    }

    /**
     * @param EloquentFile $file
     * @param EloquentFile $dir (optional)
     *
     * @return string
     */
    protected function normalizeFilePath(EloquentFile $file, EloquentFile $dir=null)
    {
        $parentDir = $dir ?: $this->getAndAssignParentDir($file);
        $result = $this->trimPath($parentDir->getPath()) . '/' . $this->trimPath($file->getName());
        return $this->trimPath($result);
    }

    /**
     * @param EloquentFile $file
     * @param $path
     *
     * @return bool
     */
    protected function applyChangesInFs(EloquentFile $file, $path)
    {

        if ($file->exists) {
            if ($file->getPath() != $file->getOriginalPath()) {
                $this->filesystem->move($file->getOriginalPath(), $file->getPath());
                return true;
            }
            return false;
        }

        // else
        if ($file->isDir()) {
            return $this->filesystem->makeDirectory($path);
        }

        if (!$this->filesystem->exists($path)) {
            // Basically just touch the file if it does not exist
            $this->filesystem->write($path, '');
        }


        return true;
    }

    /**
     * @param mixed $file
     *
     * @return EloquentFile
     */
    protected function ensureEloquentFile($file)
    {

        if (!$file instanceof EloquentFile) {
            throw new TypeException("File has to be EloquentFile not " . Type::of($file));
        }

        if ($this->urlMapper) {
            $file->setUrlMapper($this->urlMapper);
        }

        return $file;

    }

    /**
     * @param array|\Traversable $children
     *
     * @return EloquentFile[]
     */
    protected function ensureEloquentFiles($children)
    {
        foreach ($children as $child) {
            $this->ensureEloquentFile($child);
        }
        return $children;
    }

    /**
     * Return true if the passed file should not be mirrored in the db.
     *
     * @param string $basename
     *
     * @return bool
     */
    protected function isExcludedFromDb($basename)
    {
        if (in_array(mb_strtolower($basename), $this->excludeFromDb)) {
            return true;
        }

        return Helper::startsWith($basename, '.') || Helper::startsWith($basename, '_');

    }
}
