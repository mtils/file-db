<?php namespace FileDB\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use FileDB\Model\EloquentFileDBModel;
use FileDB\Model\UrlMapper;
use FileDB\Model\FileIdentifier;
use URL;

class FileDBServiceProvider extends ServiceProvider{

    protected $defer = true;

    public function register(){}

    public function boot(){

        $this->package('ems/file-db', 'ems/file-db', realpath(__DIR__.'/../../'));

        $this->app->singleton('filedb.model', function($app){

            $url = $this->app['config']->get('ems/file-db::url');
            $dir = $this->app['config']->get('ems/file-db::dir');

            if(!starts_with($url,'http')){
               $url = $app['url']->to($url);
            }

            $mapper = UrlMapper::create()->setBasePath($dir)->setBaseUrl($url);

            $model = $this->app['config']->get('ems/file-db::model');

            $fileDb = new EloquentFileDBModel($mapper, new FileIdentifier());
            $fileDb->setFileClassName($model);

            return $fileDb;

        });

    }

    public function provides(){
        return ['filedb.model'];
    }

}