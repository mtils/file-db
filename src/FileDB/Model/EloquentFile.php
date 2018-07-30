<?php namespace FileDB\Model;

use App;
use Illuminate\Database\Eloquent\Model;

class EloquentFile extends Model implements FileInterface{

    protected static $adapterFacade = 'filedb.model';

    protected static $fsAdapter;

    protected $dir;

    protected $_children = array();

    protected $appends = array('url','children');

    protected $table = 'files';

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

    public function is($file){
        if ($file instanceof FileInterface) {
            return static::getFsAdapter()->isEqual($this, $file);
        }
        return parent::is($file);
    }

    public function getTitle()
    {
        if ($this->title) {
            return $this->title;
        }

        return $this->name;
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

    protected function getUrlPath()
    {
        $path = $this->getPath();

        $parts = explode('/', $path);

        $cleanedParts = [];

        foreach($parts as $part) {
            $cleanedParts[] = rawurlencode($part);
        }

        return implode('/', $cleanedParts);
    }

    public function getFullPath(){
        return static::getFsAdapter()->getPathMapper()->absolutePath($this->getPath());
    }

    public function getUrl(){
        return static::getFsAdapter()->getPathMapper()->pathToUrl($this->getUrlPath());
    }

    public function getUrlAttribute(){
        return $this->getUrl();
    }

    public function getId()
    {
        return $this->id;
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