<?php namespace FileDB\Facades;

use Illuminate\Support\Facades\Facade;

class FsDb extends Facade{

    protected static function getFacadeAccessor(){
        return 'fsdb';
    }
}