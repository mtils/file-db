<?php namespace FileDB\ServiceProviders;

use Ems\Contracts\Core\Exceptions\TypeException;
use Ems\Contracts\Core\Type;
use Ems\Core\Laravel\IlluminateFilesystem;
use Ems\Tree\Eloquent\NodeRepository;
use FileDB\Model\DependencyFinderChain;
use FileDB\Model\EloquentFile;
use FileDB\Model\EmsFileDBModel;
use FileDB\Model\PathMapperInterface;
use FileDB\Model\UrlMapper;
use FileDB\Model\UrlMapperInterface;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalFSAdapter;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\FilesystemAdapter as FlysystemAdapter;


class FileDBServiceProvider extends ServiceProvider
{

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

        $this->registerUrlMapper();

        $this->registerFileDb();

        $this->registerDependencyFinder();

        $this->registerRoutes();

    }

    protected function registerUrlMapper()
    {
        $this->app->alias(UrlMapperInterface::class, PathMapperInterface::class);

        $this->app->singleton(UrlMapperInterface::class, function ($app) {

            /** @var Repository $config */
            $config = $this->app['config'];

            /** @var UrlGenerator $urls */
            $urls = $this->app['url'];

            $url = $config->get('filedb.url');
            $dir = $config->get('filedb.dir');

            if (!str_starts_with($url,'http')) {
                try {
                    $url = $urls->to($url);
                } catch (\OutOfBoundsException $e) {
                    $url = $config->get('app.url') . "$url";
                }
            }

            return UrlMapper::create()->setBasePath($dir)->setBaseUrl($url);

        });
    }

    protected function registerFileDb()
    {



        $this->app->alias('filedb.model', 'FileDB\Model\FileDBModelInterface');

        // $this->app->singleton('filedb.model', EmsFileDBModel::class);

        $this->app->singleton('filedb.model', function($app) {

            /** @var Repository $config */
            $config = $this->app['config'];

            $dir = $config->get('filedb.dir');

            $class = $config->get('filedb.model') ?: EloquentFile::class;


            /** @var NodeRepository $nodeRepository */
            $nodeRepository = $this->app->make(NodeRepository::class, [
                'model' => new $class
            ]);

            $nodeRepository->setPathKey('file_path')
                           ->setSegmentKey('name');

            $laravelFsAdapter = $this->createLaravelAdapter(['root' => $dir]);

            $fileDb = $this->app->make(EmsFileDBModel::class, [
                'filesystem'        => new IlluminateFilesystem($laravelFsAdapter),
                'nodeRepository'    => $nodeRepository
            ]);

            $this->app->call([$fileDb, 'setUrlMapper']);

            return $fileDb;

        });

    }

    protected function createLaravelFilesystem()
    {

    }

    /**
     * @param array $args
     *
     * @return FilesystemAdapter
     */
    protected function createLaravelAdapter(array $args=[])
    {
        if(!$this->app->bound('filedb.filesystem')) {
            $adapter = $this->createFlysystemAdapter($args);
            return new FilesystemAdapter($this->createFlysystem($adapter, $args), $adapter);
        }

        $adapter = $this->app->make('filedb.filesystem');

        if ($adapter instanceof FilesystemAdapter) {
            return $adapter;
        }

        throw new TypeException("The binding behind filedb.filesystem implement Filesystem interface not " . Type::of($adapter));

    }

    protected function registerDependencyFinder()
    {

        $this->app->alias('filedb.dependencies', 'FileDB\Contracts\FileSystem\DependencyFinder');

        $this->app->singleton('filedb.dependencies', function($app){
            return new DependencyFinderChain;
        });

    }

    /**
     * @param FlysystemAdapter $adapter
     * @param array $args
     *
     * @return Flysystem
     */
    protected function createFlysystem(FlysystemAdapter $adapter, array $args = [])
    {
        return new Flysystem($adapter, ['url' => $args['url'] ?? '/']);
    }

    /**
     * @param array $args
     *
     * @return LocalFSAdapter
     */
    protected function createFlysystemAdapter(array $args = [])
    {
        if ($this->app->bound('file-db.flysystem')) {
            return $this->app->make('file-db.flysystem');
        }
        return new LocalFSAdapter($args['root'] ?? '/');
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
