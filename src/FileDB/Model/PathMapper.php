<?php namespace FileDB\Model;

use ReflectionClass;

class PathMapper implements PathMapperInterface{

    protected $basePath = '';

    public static function create(){
        $refl = new ReflectionClass(get_called_class());
        return $refl->newInstance();
    }

    public function getBasePath(){
        return $this->basePath;
    }

    public function setBasePath($path){
        $this->basePath = rtrim($path,'/');
        return $this;
    }

    public function relativePath($absolutePath){
        return trim(str_replace($this->basePath, '', $absolutePath),'/');
    }

    public function absolutePath($relativePath){
        if($relativePath == '/'){
            return $this->basePath;
        }
        return $this->basePath . '/' . trim($relativePath,'/');
    }
}