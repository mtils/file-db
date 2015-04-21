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

        $this->publishes([
            realpath(__DIR__.'/../../../filedb.php') => config_path('filedb.php')
        ]);

        $this->registerFileDb();

        $this->registerRoutes();

    }

    protected function registerFileDb(){

        $this->app->singleton('filedb.model', function($app){

            $url = $this->app['config']->get('filedb.url');
            $dir = $this->app['config']->get('filedb.dir');

            if(!starts_with($url,'http')){
               $url = $app['url']->to($url);
            }

            $mapper = UrlMapper::create()->setBasePath($dir)->setBaseUrl($url);

            $model = $this->app['config']->get('filedb.model');

            $fileDb = new EloquentFileDBModel($mapper, new FileIdentifier());
            $fileDb->setFileClassName($model);

            return $fileDb;

        });

    }

    protected function registerRoutes(){

        $routePrefix = $this->app['config']->get('filedb.route.prefix');
        $controller = $this->app['config']->get('filedb.route.controller');

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