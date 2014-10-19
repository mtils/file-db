<?php namespace FileDB\ServiceProviders;

use Illuminate\Support\ServiceProvider;
use FileDB\Model\EloquentFileDBModel;
use FileDB\Model\UrlMapper;
use FileDB\Model\FileIdentifier;

class FileDBServiceProvider extends ServiceProvider{
    public function register(){
        $this->app->singleton('fsdb', function(){
            $uploadPath = '/uploads';
            $basePath = \public_path().$uploadPath;
            $baseUrl = \URL::to($uploadPath);
            $mapper = UrlMapper::create()->setBasePath($basePath)->setBaseUrl($baseUrl);
            return new EloquentFileDBModel($mapper, new FileIdentifier());
        });
    }
}