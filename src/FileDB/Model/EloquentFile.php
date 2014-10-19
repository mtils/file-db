<?php namespace FileDB\Model;

use App;

class EloquentFile extends \Eloquent implements FileInterface{

    protected static $adapterFacade = 'fsdb';

    protected static $fsAdapter;

    protected $dir;

    protected $_children = array();

    protected $appends = array('url','children');

    public static function getAdapterFacade(){
        return static::$adapterFacade;
    }

    public static function setAdapterFacade($facade){
        static::$adapterFacade = $facade;
    }

    public static function getFsAdapter(){
        if(!static::$fsAdapter){
            static::$fsAdapter = App::make(strtolower(static::$adapterFacade));
        }
        return static::$fsAdapter;
    }

    public static function setFsAdapter(FileDBModelInterface $adapter){
        static::$fsAdapter = $adapter;
    }

    public function getMimeType(){
        return $this->mime_type;
    }

    public function setMimeType($mimeType){
        $this->mime_type = $mimeType;
        return $this;
    }

    public function is(FileInterface $file){
        return static::getFsAdapter()->isEqual($this, $file);
    }

    public function getName(){
        return $this->name;
    }

    public function setName($name){
        $this->name = $name;
        return $this;
    }

    public function getPath(){
        return $this->file_path;
    }

    public function setPath($path){
        $this->file_path = $path;
        return $this;
    }

    public function getFullPath(){
        return static::getFsAdapter()->getPathMapper()->absolutePath($this->getPath());
    }

    public function getUrl(){
        return static::getFsAdapter()->getPathMapper()->pathToUrl($this->getPath());
    }

    public function getUrlAttribute(){
        return $this->getUrl();
    }

    public function getDir(){
        return $this->dir;
    }

    public function setDir(FileInterface $dir){
        $this->dir = $dir;
        return $this;
    }

    public function isDir(){
        return ($this->mime_type == 'inode/directory');
    }

    public function isEmpty(){
        return $this->is_empty;
    }

    public function children(){
        return $this->_children;
    }

    public function addChild(FileInterface $child){
        $this->_children[] = $child;
        return $this;
    }

    public function getChildrenAttribute(){
        return $this->children();
    }

    public function clearChildren(){
        $this->_children = array();
        return $this;
    }

}