<?php namespace FileDB\Model;

use App;
use File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ReflectionClass;
use RuntimeException;
use Illuminate\Filesystem\Filesystem;

class EloquentFileDBModel implements FileDBModelInterface{

    protected $fileClassName = '\FileDB\Model\EloquentFile';

    protected $baseUrl;

    protected $basePath;

    protected $mapper;

    protected $files;

    public function __construct(PathMapperInterface $mapper,
                                IdentifierInterface $hasher, Filesystem $files){
        $this->mapper = $mapper;
        $this->hasher = $hasher;
        $this->files = $files;
    }

    public function setFileClassName($className){
        $this->fileClassName = $className;
        return $this;
    }

    public function create(){
        $refl = new ReflectionClass($this->fileClassName);
        return $refl->newInstance();
    }

    public function getPathMapper(){
        return $this->mapper;
    }

    public function createFromPath($path){
        $file = $this->create();
        $this->fillByPath($file, $path);
        return $file;
    }

    protected function fillByPath(FileInterface $file, $path)
    {

        $absolutePath = $this->mapper->absolutePath($path);

        $file->setPath($this->trimPathForDb($path));
        $file->setName(ltrim(basename($absolutePath),'/'));

        if($this->files->isDirectory($absolutePath)){
            $mimeType = 'inode/directory';
            $hash = $this->hasher->dirId($absolutePath);
        }
        else{
            $mimeType = MimeTypeGuesser::getInstance()->guess($absolutePath);
            $hash = $this->hasher->fileId($absolutePath);
        }
        $file->setMimeType($mimeType);
        $file->hash = $hash;
    }

    public function getById($id, $depth=0){
        $method = array($this->fileClassName, 'where');
        $where = call_user_func($method,'id','=',$id);
        if($depth == 1){
            $where = $where->orWhere('parent_id','=',$id);
        }
        $dir = NULL;
        $result = $where->get()->all();
        foreach($result as $file){
            if($file->id == $id){
                $dir = $file;
                break;
            }
        }
        if(!$dir){
            throw new RuntimeException("No dir found for id $id");
        }
        foreach($result as $file){
            if($file->id != $dir->id){
                $dir->addChild($file);
                $file->setDir($dir);
            }
        }
        return $dir;
    }

    public function get($path, $depth=0)
    {

        $method = array($this->fileClassName, 'where');
        $where = call_user_func($method,'file_path','=','/');

        if(!$result = $where->get()->first()){
            throw new NotInDbException("Path '$path' is not in DB");
        }

        if($result->isDir() && $depth > 0 && !$result->isEmpty()){
            $where = call_user_func($method,'parent_id','=',$result->id);
            foreach($where->get() as $file){
                $result->addChild($file);
            }
        }
        return $result;
    }

    public function listDir(FileInterface $folder=NULL)
    {
        $method = array($this->fileClassName, 'where');
        if(!$folder){
            return $this->get('/');
        }
        return call_user_func($method, 'parent_id','=',$folder->id)->get();
    }

    public function save(FileInterface $file)
    {

        if($file->exists) {
            return;
        }

        $parentDir = $this->getParentDirOrFail($file);

        $path = $this->normalizeFilePath($file);

        $absPath = $this->mapper->absolutePath($path);

        if ($file->isDir()) {

            if (!$this->files->makeDirectory($absPath)) {
                throw new RuntimeException('Couldnt create directory in filesystem. (Access Rights?)');
            }

            $title = $file->title;
            $this->fillByPath($file, $path);
            if($title){
                $file->title = $title;
            }
            $file->parent_id = $parentDir->id;
            $file->save();

        } else{

            $title = $file->title;
            $this->fillByPath($file, $path);
            if($title){
                $file->title = $title;
            }
            $file->parent_id = $parentDir->id;
            $file->save();

        }

        if ($parentDir->isEmpty()) {
            $parentDir->is_empty = 0;
            $parentDir->save();
        }

    }

    public function syncWithFs(FileInterface $fileOrFolder, $depth=0){

        if (!$fileOrFolder->getMimeType()) {
            throw new RuntimeException('Assign a mimeType before syncing');
        }

        $isEmpty = 0;

        // No Directory
        if (!$fileOrFolder->isDir()) {
            $parentDir = $this->getOrCreateParent($fileOrFolder);
            $fileOrFolder->setDir($parentDir);
            $this->save($fileOrFolder);
            return;
        }

        // Until here it is sure that the passed $fileOrFolder is a dir

        $dir = $fileOrFolder;

        $fsChildren = $this->getFilesAndFoldersFromFS($dir);

        if (!count($fsChildren)) {
            $isEmpty = 1;
        }

        if (!$dir->exists) {
            if ($dir->getPath() == '/') {
                $dir->parent_id = NULL;
                $dir->is_empty = $isEmpty;
                $dir->save();
            } else {
                if ($parentDir = $this->getOrCreateParent($dir)) {
                    $dir->parent_id = $parentDir->id;
                    $dir->is_empty = $isEmpty;
                    $dir->save();
                }
            }
        }

        if (!$fsChildren && !$dir->isEmpty()) {
            $dir->is_empty = 1;
            $dir->save();
        }

        if( $depth < 1 || !$fsChildren){
            return;
        }

        $savedChildrenByPath = $this->getChildrenByPath($dir);

        foreach ($fsChildren as $filePath) {

            $relPath = $this->trimPathForDb($this->mapper->relativePath($filePath));
            $file = $this->createFromPath($relPath);

            // A file with this path exists in db
            if(isset($savedChildrenByPath[$relPath])) {

                // Its exactly the same (hash)
                if($this->isSame($savedChildrenByPath[$relPath], $file)){
                    continue;
                }

                // Update original file
                $this->fillByPath($savedChildrenByPath[$relPath], $relPath);
                $savedChildrenByPath[$relPath]->save();
                continue;

            }

            $dir->addChild($file);
            $file->setDir($dir);
            $file->parent_id = $dir->id;

            $this->syncWithFs($file, $depth-1);

        }

        if (!$dir->isEmpty()) {
            return;
        }

        $dir->is_empty = 0;
        $dir->save();

    }

    public function getOrCreateParent(FileInterface $fileOrFolder)
    {

        $parent = $fileOrFolder->getDir();

        if($parent && $parent->exists){
            return $parent;
        }

        if($fileOrFolder->getPath() == '/'){
            return;
        }

        // TODO Handle situation in which you have to create n parents
        $paths = $this->getParentPaths($fileOrFolder->getPath());

    }

    protected function getParentDirOrFail(FileInterface $file)
    {

        if ($parentDir = $file->getDir()) {
            return $parentDir;
        }

        if(!$file->parent_id){
            throw new RuntimeException('No parent_id of file setted');
        }

        if (!$parentDir = $this->getById($file->parent_id)) {
            throw new RuntimeException("Directory with id {$file->parent_id} not found");
        }

        $file->setDir($parentDir);

        return $parentDir;

    }

    protected function getFilesAndFoldersFromFS(FileInterface $dir)
    {
        $files = $this->files->files($dir->getFullPath());
        $dirs = $this->files->directories($dir->getFullPath());
        return array_merge($dirs, $files);
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

    public function getParents(FileInterface $file){
        if($file->getPath() != '/'){
            $paths = $this->getParentPaths($file->getPath());
            if(count($paths)){
                $method = array($this->fileClassName, 'whereIn');
                $result = call_user_func($method,'file_path',$paths)->get();
                $parentsById = array();
                foreach($result as $parent){
                    if($parent->id == $file->id){
                        $parentsById[$file->id] = $file;
                    }
                    else{
                        $parentsById[$parent->id] = $parent;
                    }
                }
                foreach($parentsById as $id=>$parent){
                    if(isset($parentsById[$parent->parent_id])){
                        $parentsById[$parent->parent_id]->addChild($parent);
                        $parent->setDir($parentsById[$parent->parent_id]);
                    }
                }
                $parents = array();
                $child = $file;
                while($parent = $child->getDir()){
                    $parents[] = $parent;
                    if($parent->id == $child->id){
                        break;
                    }
                    $child = $parent;
                }
                return array_reverse($parents);
            }
        }
        return array();
    }

    public function deleteFile(FileInterface $file)
    {

        if ($file->isDir() && !$file->isEmpty()) {
            throw new \BadMethodCallException('Currently only deleting of empty dirs is supported');
        }

        $absPath = $this->mapper->absolutePath($file->getPath());

        $method = $file->isDir() ? 'deleteDirectory' : 'delete';

        if (!$this->files->{$method}($absPath)) {
            throw new \RuntimeException("The local filesystem didnt delete the file ". $absPath);
        }

        $file->delete();

        if ($parent = $this->getById($file->parent_id)) {
            $this->syncWithFs($parent);
        }

        return $this;

    }

    public function getAttributes(FileInterface $file){

    }

    public function mapToUrl(FileInterface $file){
    
    }

    public function mapToFile($url){

    }

    public function moveIntoFolder(UploadedFile $uploadedFile, FileInterface $folder){
        if(!$folder->isDir()){
            throw new RuntimeException('Files can only be moved into directories');
        }
        $fileName = $uploadedFile->getClientOriginalName();
        $targetPath = $folder->getPath();
        $absPath = $this->mapper->absolutePath($targetPath);

        $uploadedFile->move($absPath, $fileName);
        $file = $this->createFromPath("$targetPath/$fileName");
        $file->setDir($folder);
        $folder->addChild($file);
        $this->save($file);
        return $file;
    }

    protected function getChildrenByPath(FileInterface $dir)
    {

        if(!$savedChildren = $dir->children()){
            $savedChildren = $this->listDir($dir);
        }

        $childrenByPath = [];

        foreach($savedChildren as $file){
            $childrenByPath[$this->trimPathForDb($file->getPath())] = $file;
        }

        return $childrenByPath;
    }

    protected function createDirectory(FileInterface $file)
    {
        
    }

    protected function normalizePath($path)
    {
        return trim($path,'/');
    }

    protected function normalizeFilePath(FileInterface $file)
    {
        $parentDir = $this->getParentDirOrFail($file);
        return trim($parentDir->getPath(),'/').'/'.trim($file->getName(),'/');
    }

    protected function trimPathForDb($path)
    {
        return '/'.trim($path, '/');
    }

    public function isSame(FileInterface $leftFile, FileInterface $rightFile){
        return ($leftFile->hash == $rightFile->hash);
    }

    public function getBaseUrl(){
        return $this->baseUrl;
    }

    public function getBasePath(){
        return $this->basePath;
    }
}