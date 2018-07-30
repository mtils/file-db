<?php namespace FileDB\Controller;

use Closure;
use Illuminate\Routing\Controller;
use View;
use FileDB\Model\FileDBModelInterface;
use FileDB\Model\NotInDbException;
use FileDB\Contracts\FileSystem\DependencyFinder;
use RuntimeException;
use Input;
use Redirect;
use URL;
use Response;
use Session;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Lang;
use Ems\Core\Patterns\Extendable;

class FileController extends Controller
{

    use Extendable;

    protected $layout = 'layouts.popup';

    protected $template = 'file-db::filemanager-popup';

    protected $dependencyTemplate = 'file-db::partials.dependencies';

    protected $destroyConfirmTemplate = 'file-db::files.destroy-confirm';

    protected $defaultLinkClass = 'normal';

    protected $context = 'inline';

    public static $defaultRouteUrl = 'files';

    protected $routePrefix = 'files';

    protected $routeUrl;

    protected $fileDB;

    protected $dependencyFinder;

    protected $passThruParams;

    public function __construct(FileDBModelInterface $fileDB,
                                DependencyFinder $dependencyFinder)
    {
        $this->fileDB = $fileDB;
        $this->dependencyFinder = $dependencyFinder;
    }

    public function index($dirId=NULL){

        if($dirId == 'index'){
            $dirId = NULL;
        }

        $parentDir = NULL;

        $params = $this->getPassThruParams();

        if($dirId){
            $dir = $this->fileDB->getById($dirId, 1);

            if($dir->parent_id){
                $parentDir = $this->fileDB->getById($dir->parent_id);
            }
        }
        else{
            try{
                $dir = $this->fileDB->get('/',1);
            }
            catch(NotInDbException $e){
                $this->fileDB->syncWithFs($this->fileDB->createFromPath('/'),1);
                $dir = $this->fileDB->get('/',1);
            }
        }

        if( isset($params['type']) && $params['type'] == 'image'){
            $children = $dir->children();
            $dir->clearChildren();
            foreach($children as $child){
                if($child->getMimeType() == 'inode/directory' || starts_with($child->getMimeType(),'image')){
                    $dir->addChild($child);
                }
            }
        }

        $viewParams = [
            'dir' => $dir,
            'parents' => $this->fileDB->getParents($dir),
            'params' => $params,
            'toRoute' => function ($action, $params=[]) {
                return $this->toRoute($action, $params);
             },
            'attributeSetter' => $this->getAttributeProvider($this->getContext())
        ];

        return View::make($this->getTemplate(), $viewParams);
    }

    public function store($dirId){

        $parentDir = $this->getDirOrFail($dirId);

        if(!$dirName = Input::get('folderName')){
            $this->flashMessage('dirname-missing');
            return $this->redirectTo('index', $dirId);
        }

        $dir = $this->fileDB->create();
        $dir->mime_type = 'inode/directory';
        $dir->parent_id=Input::get('dirId');
        $dir->setDir($parentDir);
        $parentDir->addChild($dir);

        $dir->name = $dirName;
        $this->fileDB->save($dir);

        return $this->redirectTo('index', [$dir->id]);

    }

    public function upload($dirId)
    {
        try{

            $parentDir = $this->getDirOrFail($dirId);

            if(!Input::hasFile('uploadedFile')){
                throw new RuntimeException('Uploaded File not found');
            }

            $this->fileDB->moveIntoFolder(Input::file('uploadedFile'), $parentDir);

        } catch(FileException $e) {
            $this->flashMessage('upload-failed','danger');
        } catch(RuntimeException $e) {
            $this->flashMessage('uploaded-file-missing','danger');
        }

        return $this->redirectTo('index', $parentDir->id);
    }

    public function sync($dirId)
    {
        $dir = $this->getDirOrFail($dirId);
        $this->fileDB->syncWithFs($dir, 1);
        return $this->redirectTo('index', [$dir->id]);
    }

    public function destroyConfirm($id)
    {

        $file = $this->fileDB->getById($id);

        $dependencies = $this->dependencyFinder->find($file);

        $data = [
            'resources'             => $dependencies,
            'file'                  => $file,
            'dependencies_template' => $this->dependencyTemplate
        ];

        return view($this->destroyConfirmTemplate, $data);

    }

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

    public function jsConfig()
    {
        $content = 'window.fileroute = "' . $this->toRoute('index') . '";';
        return Response::make($content)->header('Content-Type', 'application/javascript');
    }

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

    protected function getPassThruParams(){

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

    public function toRoute($action, $params=[])
    {
        $params = (array)$params;
        $url = URL::route($this->routePrefix . '.' . $action, $params);

        if ($passThruParams = $this->getPassThruParams()) {
            $url .= '?' . http_build_query($passThruParams);
        }

        return $url;

    }

    protected function redirectTo($action, $params=[])
    {
        return Redirect::to($this->toRoute('index', $params));
    }

    public function getRoutePrefix()
    {
        return $this->routePrefix;
    }

    public function setRoutePrefix($prefix)
    {
        $this->routePrefix = $prefix;
        return $this;
    }

    public function getContext()
    {
        $params = $this->getPassThruParams();
        if (isset($params['context'])) {
            return $params['context'];
        }
        return 'inline';
    }

    public function getTemplate(){
        return $this->template;
    }

    public function setTemplate($template)
    {
        $this->template = $template;
        return $this;
    }

    public function provideOpenLinkAttributes($context, Closure $closure)
    {
        $closure->bindTo($this);
        $this->extend($this->getContextExtendName($context), $closure);
        return $this;
    }

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

    protected function getContextExtendName($context)
    {
        return "attribute-setter-$context";
    }

    protected function message($code)
    {
        return Lang::get("file-db::file-db.messages.$code");
    }

    protected function flashMessage($code, $state='success')
    {
        return $this->flash($this->message($code), $state);
    }

    protected function flash($message, $state='success')
    {
        Session::flash('file-db-message', [$message, $state]);
    }

}
