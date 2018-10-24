<?php namespace FileDB\Model;

use App;
use Ems\Core\LocalFilesystem;
use Ems\Tree\Eloquent\EloquentNode;

class EloquentFile extends EloquentNode implements FileInterface
{

    protected static $adapterFacade = 'filedb.model';

    protected static $fsAdapter;

    protected $dir;

    protected $_children = array();

    protected $appends = array('url','children');

    protected $table = 'files';

    /**
     * @var FileDBModelInterface
     */
    protected $fileDb;

    /**
     * @var UrlMapperInterface
     */
    protected $urlMapper;

    /**
     * @deprecated
     *
     * @return string
     */
    public static function getAdapterFacade()
    {
        return static::$adapterFacade;
    }

    /**
     * @deprecated
     *
     * @param string $facade
     */
    public static function setAdapterFacade($facade)
    {
        static::$adapterFacade = $facade;
    }

    /**
     * @deprecated
     * @return FileDBModelInterface
     */
    public static function getFsAdapter()
    {
        if(!static::$fsAdapter){
            static::$fsAdapter = App::make(strtolower(static::$adapterFacade));
        }
        return static::$fsAdapter;
    }

    /**
     * @deprecated
     * @param FileDBModelInterface $adapter
     */
    public static function setFsAdapter(FileDBModelInterface $adapter)
    {
        static::$fsAdapter = $adapter;
    }

    /**
     * @return string
     */
    public function getMimeType()
    {
        return $this->getAttribute('mime_type');
    }

    /**
     * @param string $mimeType
     *
     * @return self
     */
    public function setMimeType($mimeType)
    {
        $this->setAttribute('mime_type', $mimeType);
        return $this;
    }

    /**
     * @deprecated
     *
     * @param FileInterface $file
     *
     * @return bool
     */
    public function is($file)
    {
        return $file->getPath() == $this->getPath();
    }

    /**
     * @return bool
     */
    public function hasTitle()
    {
        return (bool)$this->getAttribute('title');
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        if ($this->hasTitle()) {
            return $this->getAttribute('title');
        }

        return $this->getAttribute('name');
    }

    /**
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        $this->setAttribute('title', $title);
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->getAttribute('name');
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->setAttribute('name', $name);
        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->getAttribute('file_path');
    }

    /**
     * @param string $path
     *
     * @return $this|FileInterface
     */
    public function setPath($path){
        $this->setAttribute('file_path', $path);
        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getPathSegment()
    {
        return (string)$this->getName();
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getOriginalPath()
    {
        return $this->getOriginal('file_path');
    }

    public function getFileDb()
    {
        if ($this->fileDb) {

        }
        return $this->fileDb;
    }

    /**
     * @return UrlMapperInterface
     */
    public function getUrlMapper()
    {
        if (!$this->urlMapper) {
            $this->urlMapper = static::getFsAdapter()->getPathMapper();
        }
        return $this->urlMapper;
    }

    /**
     * @param UrlMapperInterface $urlMapper
     *
     * @return self
     */
    public function setUrlMapper(UrlMapperInterface $urlMapper)
    {
        $this->urlMapper = $urlMapper;
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

    /**
     * @deprecated
     * @return string
     */
    public function getFullPath()
    {
        return $this->getUrlMapper()->absolutePath($this->getPath());
    }

    public function getUrl()
    {
        return $this->getUrlMapper()->pathToUrl($this->getUrlPath());
    }

    public function getUrlAttribute()
    {
        return $this->getUrl();
    }

    public function getId()
    {
        return $this->getKey();
    }

    public function getDir()
    {
        return $this->getParent();
    }

    public function setDir(FileInterface $dir)
    {
        $this->setParent($dir);
        return $this;
    }

    public function isDir()
    {
        return ($this->getMimeType() == LocalFilesystem::$directoryMimetype);
    }

    public function isEmpty()
    {
        return $this->getAttribute('is_empty');
    }

    public function children()
    {
        return $this->getChildren();
    }

    public function addChild(FileInterface $child)
    {
        $this->getChildren()->append($child);
        return $this;
    }

    public function getChildrenAttribute()
    {
        return $this->getChildren();
    }

    public function clearChildren()
    {
        $this->getChildren()->clear();
        return $this;
    }

}