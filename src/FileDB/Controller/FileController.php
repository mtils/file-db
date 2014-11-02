<?php namespace FileDB\Controller;

use Controller;
use View;
use FileDB;
use FileDB\Model\NotInDbException;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Input;
use Redirect;
use URL;

class FileController extends Controller{

    protected $layout = 'layouts.popup';

    protected $template = 'filemanager-popup';

    protected $defaultLinkClass = 'normal';

    protected $context = 'inline';

    public static $defaultRouteUrl = 'files';

    protected $routeUrl;

    public function getIndex($dirId=NULL){

        $parentDir = NULL;


        $params = $this->getUrlParams(Input::all());

        if($dirId){
            $dir = FileDB::getById($dirId, 1);

            if(Input::get('sync')){
                FileDB::syncWithFs($dir,1);
            }

            if($dir->parent_id){
                $parentDir = FileDB::getById($dir->parent_id);
            }
        }
        else{
            try{
                $dir = FileDB::get('/',1);
            }
            catch(NotInDbException $e){
                FileDB::syncWithFs(FileDB::createFromPath('/'),1);
                $dir = FileDB::get('/',1);
            }
        }

        if($params['type'] == 'image'){
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
            'currentId' => $dir->id,
            'parentDir' => $parentDir,
            'parents' => FileDB::getParents($dir),
            'params' => $params,
            'routeUrl' => $this->getRouteUrl(),
            'linkClass' => $this->getLinkClass()
        ];

        return View::make($this->getTemplate(), $viewParams);
    }

    public function postIndex(){

        if(!Input::get('action') || !Input::get('dirId')){
            throw new RuntimeException('I need dirId and action');
        }

        if(!is_numeric(Input::get('dirId'))){
            throw new RuntimeException('DirId is no numeric');
        }

        if(!$parentDir = FileDB::getById(Input::get('dirId'))){
            throw new RuntimeException("Parent Dir with id $dirId not found");
        }

        if(!in_array(Input::get('action'), array('upload','newDir'))){
            throw new RuntimeException('Unknown action');
        }

        if(Input::input('action') == 'newDir'){
            $dir = FileDB::create();
            $dir->mime_type = 'inode/directory';
            $dir->parent_id=Input::get('dirId');
            $dir->setDir($parentDir);
            $parentDir->addChild($dir);

            if(!$dirName = Input::get('folderName')){
                throw new RuntimeException('Please assign a name to this directory');
            }
            $dir->name = Input::get('folderName');
            FileDB::save($dir);
            if($dir->exists){
                return Redirect::to(URL::to($this->getRouteUrl(),array('dirId'=>$dir->id)));
            }
            else{
                $currentId = Input::get('dirId');
                $dir = $parentDir;
            }
        }
        elseif(Input::input('action') == 'upload'){
            if(!Input::hasFile('uploadedFile')){
                throw new RuntimeException('Uploaded File not found');
            }
            if(FileDB::moveIntoFolder(Input::file('uploadedFile'), $parentDir)){
                return Redirect::to(URL::to($this->getRouteUrl(),array('dirId'=>$parentDir->id)));
            }
        }

        return View::make('filemanager',compact('dir','currentId','parentDir'));
    }

    protected function getUrlParams(array $params){

        $usedParams = array(
            'type' => '',
            'context' => $this->getContext()
        );

        if( isset($params['type']) && $params['type'] ){
            $usedParams['type'] = strip_tags($params['type']);
        }

        return $usedParams;

    }

    public function getRouteUrl(){

        if(!$this->routeUrl){
            return static::$defaultRouteUrl;
        }

        return $this->routeUrl;

    }

    public function setRouteUrl($url){

        $this->routeUrl = $url;
        return $this;

    }

    public function getContext(){
        return $this->context;
    }

    public function getLinkClass(){
        return $this->defaultLinkClass;
    }

    public function getTemplate(){
        return $this->template;
    }

}