<?php namespace FileDB\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use FileDB\Model\EloquentFileDBModel;
use FileDB\Model\UrlMapper;
use FileDB\Model\FileIdentifier;
use URL;

class FileDBServiceProvider extends ServiceProvider{

    protected $defer = false;

    public function register(){}

    public function boot(){

        $this->package('ems/file-db', 'ems/file-db', realpath(__DIR__.'/../../'));

        $this->registerFileDb();

        $this->registerRoutes();

    }

    protected function registerFileDb(){

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

    protected function registerRoutes(){

        $routePrefix = $this->app['config']->get('ems/file-db::route.prefix');
        $controller = $this->app['config']->get('ems/file-db::route.controller');

        $this->app['router']->get("$routePrefix/{id?}", [
            'as'=> "$routePrefix",
            'uses' => "$controller@index"
        ]);

        $this->app['router']->get("$routePrefix/index/{id?}", [
            'as'=> "$routePrefix-index",
            'uses' => "$controller@index"
        ]);

        $this->app['router']->post("$routePrefix/index/{id?}", [
            'as'=> "$routePrefix-store",
            'uses' => "$controller@store"
        ]);

    }

    public function provides(){
        return ['filedb.model'];
    }

}