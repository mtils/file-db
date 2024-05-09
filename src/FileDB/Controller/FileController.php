<?php namespace FileDB\Controller;

use Closure;
use Ems\Core\LocalFilesystem;
use Ems\Core\Patterns\Extendable;
use FileDB\Contracts\FileSystem\DependencyFinder;
use FileDB\Model\EloquentFile;
use FileDB\Model\FileDBModelInterface;
use FileDB\Model\FileInterface;
use FileDB\Model\NotInDbException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Controller;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Input;
use Lang;
use Redirect;
use RuntimeException;
use Session;
use function str_starts_with;
use function strnatcasecmp;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use URL;
use function usort;
use function is_array;
use function sys_get_temp_dir;
use Ems\Contracts\Core\Errors\NotFound;

class FileController extends Controller
{

    use Extendable;

    /**
     * @var string
     */
    protected $layout = 'layouts.popup';

    /**
     * @var string
     */
    protected $template = 'file-db::filemanager-popup';

    /**
     * @var string
     */
    protected $dependencyTemplate = 'file-db::partials.dependencies';

    /**
     * @var string
     */
    protected $destroyConfirmTemplate = 'file-db::files.destroy-confirm';

    /**
     * @var string
     */
    protected $defaultLinkClass = 'normal';

    /**
     * @var string
     */
    protected $context = 'inline';

    /**
     * @var string
     */
    public static $defaultRouteUrl = 'files';

    /**
     * @var string
     */
    protected $routePrefix = 'files';

    /**
     * @var FileDBModelInterface
     */
    protected $fileDB;

    /**
     * @var DependencyFinder
     */
    protected $dependencyFinder;

    /**
     * @var array
     */
    protected $passThruParams;

    /**
     * @var string
     */
    protected $tempDir;

    public function __construct(FileDBModelInterface $fileDB,
                                DependencyFinder $dependencyFinder)
    {
        $this->fileDB = $fileDB;
        $this->dependencyFinder = $dependencyFinder;
    }


    /**
     * List a directory or the root directory.
     *
     * @param ViewFactory $viewFactory
     * @param null $dirId (optional)
     *
     * @return mixed
     */
    public function index(ViewFactory $viewFactory, $dirId=null)
    {

        if ($dirId == 'index') {
            $dirId = NULL;
        }

        $parentDir = NULL;

        $params = $this->getPassThruParams();

        $dir = $this->getDirectory($dirId);

        if ( isset($params['type']) && $params['type'] == 'image') {
            $this->filterToImages($dir);
        }

        $this->sortChildren($dir);

        $viewParams = [
            'dir' => $dir,
            'parents' => $this->fileDB->getParents($dir),
            'params' => $params,
            'toRoute' => function ($action, $params=[]) {
                return $this->toRoute($action, $params);
             },
            'attributeSetter' => $this->getAttributeProvider($this->getContext())
        ];

        return $viewFactory->make($this->getTemplate(), $viewParams);
    }

    /**
     * Create a directory.
     *
     * @param Request $request
     * @param int $dirId
     *
     * @return mixed
     */
    public function store(Request $request, $dirId)
    {

        $parentDir = $this->getDirOrFail($dirId);

        if (!$dirName = $request->get('folderName')) {
            $this->flashMessage('dirname-missing');
            return $this->redirectTo('index', $dirId);
        }

        $dir = $this->fileDB->create();
        $dir->setMimeType('inode/directory');

        $dir->setDir($parentDir);
        $dir->setName($dirName);
        $this->fileDB->save($dir);

        return $this->redirectTo('index', [$dir->getId()]);

    }

    /**
     * Upload a file into the file db.
     *
     * @param Request $request
     * @param int     $dirId
     *
     * @return RedirectResponse
     */
    public function upload(Request $request, $dirId)
    {

        try {

            $folder = $this->getDirOrFail($dirId);

            if (!$request->hasFile('uploadedFile')) {
                throw new RuntimeException('Uploaded File not found');
            }

            $uploadedFile = $request->file('uploadedFile');

            /** @var UploadedFile $uploadedFile */
            $uploadedFile = is_array($uploadedFile) ? $uploadedFile[0] : $uploadedFile;

            $fileName = $uploadedFile->getClientOriginalName();

            $newPath = $this->getTempDir() . '/' . $fileName;

            $uploadedFile->move($this->getTempDir(), $fileName);

            $this->fileDB->importFile($newPath, $folder);

            return $this->redirectTo('index', $folder->getId());

        } catch(FileException $e) {
            $this->flashMessage('upload-failed','danger');
        } catch(RuntimeException $e) {
            $this->flashMessage('uploaded-file-missing','danger');
        }

        return $this->redirectTo('index');

    }

    /**
     * Synchronize the filesystem into the db in this folder.
     *
     * @param int $dirId
     *
     * @return RedirectResponse
     */
    public function sync($dirId)
    {
        $dir = $this->getDirOrFail($dirId);
        $this->fileDB->syncWithFs($dir, 1);
        return $this->redirectTo('index', [$dir->getId()]);
    }

    /**
     * Show a (modal) confirmation dialog to delete a file.
     *
     * @param ViewFactory $viewFactory
     * @param int $id
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function destroyConfirm(ViewFactory $viewFactory, $id)
    {

        $file = $this->fileDB->getById($id);

        $dependencies = $this->dependencyFinder->find($file);

        $data = [
            'resources'             => $dependencies,
            'file'                  => $file,
            'dependencies_template' => $this->dependencyTemplate
        ];

        return $viewFactory->make($this->destroyConfirmTemplate, $data);

    }

    /**
     * Delete a file. (Just the answer to an ajax DELETE request)
     *
     * @param int $id
     *
     * @return string
     */
    public function destroy($id)
    {
        try {
            $file = $this->fileDB->getById($id);
            $this->fileDB->deleteFile($file);
            return 'File deleted';
        } catch (\Exception $e) {
            \Log::error($e);
            $this->flashMessage('delete-failed','danger');
            return 'Error: File not deleted';
        }
    }

    /**
     * @return \Illuminate\Http\Response
     */
    public function jsConfig(ResponseFactory $responseFactory)
    {
        $content = 'window.fileroute = "' . $this->toRoute('index') . '";';
        return $responseFactory->make($content)->header('Content-Type', 'application/javascript');
    }

    /**
     * Return the temp directory where uploaded files are stored.
     *
     * @return string
     */
    public function getTempDir()
    {
        if (!$this->tempDir) {
            $this->tempDir = sys_get_temp_dir();
        }
        return $this->tempDir;
    }

    /**
     * Set the temp directory for moving local files into file db.
     *
     * @param string $dir
     *
     * @return self
     */
    public function setTempDir($dir)
    {
        $this->tempDir = $dir;
        return $this;
    }

    /**
     * @param string $action
     * @param array $params
     *
     * @return string
     */
    public function toRoute($action, $params=[])
    {
        $params = (array)$params;
        $url = URL::route($this->routePrefix . '.' . $action, $params);

        if ($passThruParams = $this->getPassThruParams()) {
            $url .= '?' . http_build_query($passThruParams);
        }

        return $url;

    }

    /**
     * Return the prefix for route names. (Default is files)
     * The URLs are generated with e.g. URL::route('files.index'). So if you
     * already have files routes or so overwrite it here.
     *
     * @return string
     */
    public function getRoutePrefix()
    {
        return $this->routePrefix;
    }

    /**
     * @see self::getRoutePrefix()
     *
     * @param string $prefix
     *
     * @return $this
     */
    public function setRoutePrefix($prefix)
    {
        $this->routePrefix = $prefix;
        return $this;
    }

    /**
     * Return the context of the file manager when it is displayed. A context
     * stands for "filemanager-for-tiny-mce" or stuff like that.
     *
     * @return string
     */
    public function getContext()
    {
        $params = $this->getPassThruParams();
        if (isset($params['context'])) {
            return $params['context'];
        }
        return 'inline';
    }

    /**
     * Return the name of the index template.
     *
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Change the name of the index template.
     *
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    /**
     * Assign a callable to manipulate the attributes for the "open" links to
     * open files.
     *
     * @param string $context
     * @param Closure $closure
     *
     * @return $this
     */
    public function provideOpenLinkAttributes($context, Closure $closure)
    {
        $closure->bindTo($this);
        $this->extend($this->getContextExtendName($context), $closure);
        return $this;
    }

    /**
     * Get some parameters that should be added to every link.
     *
     * @return array
     */
    protected function getPassThruParams()
    {

        if ($this->passThruParams !== null) {
            return $this->passThruParams;
        }

        $this->passThruParams = [];

        $all = Input::all();

        $filtered = array_except($all, ['sync','uploadedFile','folderName']);

        // Security check
        if (count($filtered) > 30) {
            throw new RuntimeException('Too many query params');
        }

        foreach ($filtered as $key=>$value) {
            $this->passThruParams[$key] = strip_tags($value);
        }

        return $this->passThruParams;

    }

    /**
     * Generate a redirect.
     *
     * @param string $action
     * @param array $params
     *
     * @return RedirectResponse
     */
    protected function redirectTo($action, $params=[])
    {
        return Redirect::to($this->toRoute('index', $params));
    }

    /**
     * @param int $id
     *
     * @return FileInterface
     */
    protected function getDirOrFail($id)
    {

        if(!is_numeric($id)){
            throw new BadRequestHttpException('DirId is no numeric');
        }

        if($dir = $this->fileDB->getById($id)) {
            return $dir;
        }
        throw new NotFoundHttpException("Dir with id $id not found");
    }

    /**
     * Return the attribute provider to provide attributes for the open links.
     *
     * @see self::provideOpenLinkAttributes()
     *
     * @param string $context
     *
     * @return callable
     */
    protected function getAttributeProvider($context)
    {

        $extendName = $this->getContextExtendName($context);

        if ($this->hasExtension($extendName)) {
            return $this->getExtension($extendName);
        }

        return function($file) {
            return [
                'href'  =>$file->url,
                'class'=>'inline',
                'onclick'=>"window.open($(this).attr('href'), 'imgViewer','width=600,height=400'); return false;"];
        };

    }

    /**
     * Generate a name for extension. Add minus to prevent direct calls by the
     * extendable trait.
     *
     * @param string $context
     *
     * @return string
     */
    protected function getContextExtendName($context)
    {
        return "attribute-setter-$context";
    }

    /**
     * Return a translation.
     *
     * @param string $code
     *
     * @return string
     */
    protected function message($code)
    {
        return Lang::get("file-db::file-db.messages.$code");
    }

    /**
     * @param string $code
     *
     * @param string $state
     */
    protected function flashMessage($code, $state='success')
    {
        $this->flash($this->message($code), $state);
    }

    /**
     * @param string $message
     * @param string $state
     */
    protected function flash($message, $state='success')
    {
        Session::flash('file-db-message', [$message, $state]);
    }

    /**
     * @param null $id
     *
     * @return \FileDB\Model\FileInterface
     */
    protected function getDirectory($id=null)
    {
        if($id) {
            return $this->fileDB->getById($id,1);
        }

        try{
            return $this->fileDB->get('/',1);
        } catch(NotInDbException $e) {
            $this->fileDB->syncWithFs('/', 1);
            return $this->fileDB->get('/',1);
        } catch(NotFound $e) {
            $this->fileDB->syncWithFs('/', 1);
            return $this->fileDB->get('/',1);
        }
    }

    /**
     * @param FileInterface $dir
     */
    protected function filterToImages(FileInterface $dir)
    {
        $children = [];
        foreach($dir->children() as $child) {
            $children[] = $child;
        }

        $dir->clearChildren();

        foreach ($children as $child) {
            if ($child->getMimeType() == LocalFilesystem::$directoryMimetype || str_starts_with($child->getMimeType(),
                    'image')) {
                $dir->addChild($child);
            }
        }
    }

    /**
     * @param FileInterface $dir
     */
    protected function sortChildren(FileInterface $dir)
    {
        $children = [];
        foreach($dir->children() as $child) {
            $children[] = $child;
        }

        $dir->clearChildren();

        $directories = [];
        $files = [];

        foreach ($children as $child) {
            if ($child->isDir()) {
                $directories[] = $child;
                continue;
            }
            $files[] = $child;
        }

        $this->sortEntries($directories);
        $this->sortEntries($files);

        foreach ($directories as $subDir) {
            $dir->addChild($subDir);
        }

        foreach ($files as $file) {
            $dir->addChild($file);
        }
    }

    protected function sortEntries(array &$entries)
    {
        usort($entries, function (EloquentFile $fileA, EloquentFile $fileB) {
            return strnatcasecmp($fileA->getTitle(), $fileB->getTitle());
        });
    }
}
