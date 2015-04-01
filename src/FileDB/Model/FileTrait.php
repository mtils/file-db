<?php namespace FileDB\Model;


use FileDB\Contracts\Model\Repository;
use FileDB\Contracts\Model\Directory;

trait FileTrait
{

    /**
     * @var string
     **/
    protected $path = '';

    /**
     * @var string
     **/
    protected $hash = '';

    /**
     * @var \FileDB\Contracts\Model\Directory
     **/
    protected $dir;

    /**
     * @var \FileDB\Contracts\Model\Repository
     **/
    protected $repository;

    /**
     * @var string
     **/
    protected $unModifiedPath = '';

    /**
     * {@inheritdoc}
     **/
    public function getName()
    {
        return basename($this->path);
    }

    /**
     * {@inheritdoc}
     **/
    public function setName($name)
    {
        $dirName = dirname($this->path);
        $this->path = '/'.trim(implode('/',[$dirName,$name]),'/');
        return $this;
    }

    /**
     * {@inheritdoc}
     **/
    public function getPath()
    {
        return $this->path;
    }

    /**
     * {@inheritdoc}
     **/
    public function setPath($path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * {@inheritdoc}
     **/
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * {@inheritdoc}
     **/
    public function setHash($hash)
    {
        $this->hash = $hash;
        return $this;
    }

    /**
     * {@inheritdoc}
     **/
    public function getDir()
    {
        return $this->dir;
    }

    /**
     * {@inheritdoc}
     **/
    public function setDir(Directory $dir)
    {
        $this->dir = $dir;
        $dir->addChild($this);
        return $this;
    }

    /**
     * {@inheritdoc}
     **/
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * {@inheritdoc}
     **/
    public function _setRepository(Repository $repository)
    {
        $this->repository = $repository;
        return $this;
    }

    /**
     * {@inheritdoc}
     **/
    public function getUnmodifiedPath()
    {
        return $this->unModifiedPath;
    }

    /**
     * {@inheritdoc}
     **/
    public function _setUnmodifiedPath($path)
    {
        $this->unModifiedPath = $path;
        return $this;
    }

}