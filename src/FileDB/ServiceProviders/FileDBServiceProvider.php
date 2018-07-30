<?php namespace FileDB\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use FileDB\Model\EloquentFileDBModel;
use FileDB\Model\UrlMapper;
use FileDB\Model\FileIdentifier;
use URL;

class FileDBServiceProvider extends ServiceProvider{

    protected $packagePath;

    protected $defer = false;

    public function register(){}

    public function boot()
    {

        $this->publishes([
            $this->packagePath('config/filedb.php') => config_path('filedb.php')
        ], 'config');

        $this->publishes([
            $this->packagePath('migrations/') => database_path('/migrations')
        ], 'migrations');

        $this->loadViewsFrom(
            $this->packagePath('resources/views'),
            'file-db'
        );

        $this->loadTranslationsFrom(
            $this->packagePath('resources/lang'),
            'file-db'
        );

        $this->registerFileDb();

        $this->registerDependencyFinder();

        $this->registerRoutes();

    }

    protected function registerFileDb()
    {

        $this->app->alias('filedb.model', 'FileDB\Model\FileDBModelInterface');

        $this->app->singleton('filedb.model', function($app) {

            $url = $this->app['config']->get('filedb.url');
            $dir = $this->app['config']->get('filedb.dir');

            if(!starts_with($url,'http')){
                try {
                    $url = $app['url']->to($url);
                } catch (\OutOfBoundsException $e) {
                    $url = $app['config']['app.url'] . "$url";
                }
            }

            $mapper = UrlMapper::create()->setBasePath($dir)->setBaseUrl($url);

            $model = $this->app['config']->get('filedb.model');

            $fileDb = $app->make('FileDB\Model\EloquentFileDBModel',[
                'mapper' => $mapper,
                'hasher' => new FileIdentifier
            ]);

            $fileDb->setFileClassName($model);

            return $fileDb;

        });

    }

    protected function registerDependencyFinder()
    {

        $this->app->alias('filedb.dependencies', 'FileDB\Contracts\FileSystem\DependencyFinder');

        $this->app->singleton('filedb.dependencies', function($app){
            return new \FileDB\Model\DependencyFinderChain;
        });

    }

    protected function registerRoutes()
    {

        $routePrefix = $this->app['config']->get('filedb.route.prefix');
        $controller = $this->app['config']->get('filedb.route.controller');

        $this->app['router']->get("$routePrefix/js-config", [
            'as'=> "$routePrefix.js-config",
            'uses' => "$controller@jsConfig"
        ]);

        $this->app['router']->get("$routePrefix/{dir?}", [
            'as'=> "$routePrefix.index",
            'uses' => "$controller@index"
        ]);

        $this->app['router']->post("$routePrefix/{dir}", [
            'as'=> "$routePrefix.store",
            'uses' => "$controller@store"
        ]);

        $this->app['router']->post("$routePrefix/{dir}/upload", [
            'as'=> "$routePrefix.upload",
            'uses' => "$controller@upload"
        ]);

        $this->app['router']->get("$routePrefix/{dir}/sync", [
            'as'=> "$routePrefix.sync",
            'uses' => "$controller@sync"
        ]);

        $this->app['router']->get("$routePrefix/{dir}/destroy-confirm", [
            'as'=> "$routePrefix.destroy-confirm",
            'uses' => "$controller@destroyConfirm"
        ]);

        $this->app['router']->delete("$routePrefix/{dir}", [
            'as'=> "$routePrefix.destroy",
            'uses' => "$controller@destroy"
        ]);


//         $this->app['router']->get("$routePrefix/index/{id?}", [
//             'as'=> "$routePrefix-index",
//             'uses' => "$controller@index"
//         ]);

    }

    protected function packagePath($dir='')
    {

        if (!$this->packagePath) {
            $this->packagePath = realpath(__DIR__.'/../../../');
        }

        if ($dir) {
            return $this->packagePath . "/$dir";
        }

        return $this->packagePath;
    }

    public function provides()
    {
        return ['filedb.model'];
    }

}
