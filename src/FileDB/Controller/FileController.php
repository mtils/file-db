<?php namespace FileDB\Controller;

use Controller;
use View;
use FsDb;
use FileDB\Model\NotInDbException;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;
use Input;
use Redirect;
use URL;

class FileController extends Controller{

    protected $layout = 'theme::layouts.popup';

    protected $template = 'theme::filemanager-popup';

    protected $defaultLinkClass = 'normal';

    public function getIndex($dirId=NULL){

        $parentDir = NULL;


        $params = $this->getUrlParams(Input::all());

        if($dirId){
            $dir = FsDb::getById($dirId, 1);

            if(Input::get('sync')){
                FsDb::syncWithFs($dir,1);
            }

            if($dir->parent_id){
                $parentDir = FsDb::getById($dir->parent_id);
            }
        }
        else{
            try{
                $dir = FsDb::get('/',1);
            }
            catch(NotInDbException $e){
                FsDb::syncWithFs(FsDb::createFromPath('/'),1);
                $dir = FsDb::get('/',1);
            }
        }

        $currentId = $dir->id;

        if($params['type'] == 'image'){
            $children = $dir->children();
            $dir->clearChildren();
            foreach($children as $child){
                if($child->getMimeType() == 'inode/directory' || starts_with($child->getMimeType(),'image')){
                    $dir->addChild($child);
                }
            }
        }

        $parents = FsDb::getParents($dir);
        $routeUrl = $this->getRouteUrl();

        return View::make($this->template, compact('dir','currentId','parentDir','parents','params','routeUrl'));
    }

    public function postIndex(){

        if(!Input::get('action') || !Input::get('dirId')){
            throw new RuntimeException('I need dirId and action');
        }

        if(!is_numeric(Input::get('dirId'))){
            throw new RuntimeException('DirId is no numeric');
        }

        if(!$parentDir = FsDb::getById(Input::get('dirId'))){
            throw new RuntimeException("Parent Dir with id $dirId not found");
        }

        if(!in_array(Input::get('action'), array('upload','newDir'))){
            throw new RuntimeException('Unknown action');
        }

        if(Input::input('action') == 'newDir'){
            $dir = FsDb::create();
            $dir->mime_type = 'inode/directory';
            $dir->parent_id=Input::get('dirId');
            $dir->setDir($parentDir);
            $parentDir->addChild($dir);

            if(!$dirName = Input::get('folderName')){
                throw new RuntimeException('Please assign a name to this directory');
            }
            $dir->name = Input::get('folderName');
            FsDb::save($dir);
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
            if(FsDb::moveIntoFolder(Input::file('uploadedFile'), $parentDir)){
                return Redirect::to(URL::to($this->getRouteUrl(),array('dirId'=>$parentDir->id)));
            }
        }

        return View::make('theme::filemanager',compact('dir','currentId','parentDir'));
    }

    protected function getUrlParams(array $params){

        $usedParams = array(
            'type' => '',
            'linkClass' => $this->defaultLinkClass
        );

        if( isset($params['type']) && $params['type'] ){
            $usedParams['type'] = strip_tags($params['type']);
        }

        if( isset($params['linkClass']) && $params['linkClass'] ){
            $usedParams['linkClass'] = strip_tags($params['linkClass']);
        }

        return $usedParams;

    }

    protected function getRouteUrl(){
        return 'admin/files';
    }

    protected function getFileOpener(){
        return function($file){
            return 'javascript: window.opener.CKEDITOR.tools.callFunction(1, \'' . $file->url . '\'); window.close();';
        };
    }

}