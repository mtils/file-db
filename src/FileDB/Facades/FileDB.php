<?php namespace FileDB\Facades;

use Illuminate\Support\Facades\Facade;

class FileDB extends Facade{

    protected static function getFacadeAccessor(){
        return 'filedb.model';
    }
}