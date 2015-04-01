<?php namespace FileDB\Model;

use FileDB\Contracts\Model\Repository;
use FileDB\Contracts\Model\File as FileInterface;
use FileDB\Contracts\Model\Directory as DirInterface;

class DummyRepository implements Repository
{

    /**
     * @var string
     **/
    protected $basePath = '/';

    /**
     * @var array
     **/
    protected $fs = [];

    /**
     * @var array
     **/
    protected $fsById = [];

    /**
     * @var \FileDB\Contracts\Model\File
     **/
    protected $filePrototype;

    /**
     * @var \FileDB\Contracts\Model\Directory
     **/
    protected $dirPrototype;

    public function __construct(FileInterface $filePrototype=null,
                                DirInterface $dirPrototype=null)
    {
        $this->filePrototype = $filePrototype ?: new File;
        $this->dirPrototype = $dirPrototype ?: new Directory;
    }

    /**
     * Return the base path (chroot) of this repository
     * 
     * @return string
     **/
    public function getBasePath()
    {
        return $this->basePath;
    }

    /**
     * Set the base path (chroot) of this repository
     *
     * @param string $basePath
     * @return self
     **/
    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
        return $this;
    }

    /**
     * Checks if a path exists
     *
     * @return bool
     **/
    public function exists($path)
    {
        return false;
    }

    /**
     * Get a file object by the given path
     *
     * @param string $path
     * @return \FileDB\Contracts\Model\File
     **/
    public function getFromPath($path, $depth=0, $withParents=false)
    {

         if(isset($this->fs[$path])){
            return $this->fs[$path];
         }

    }

    /** 
     * Get a file by its unique id. In normal filesystems its unique
     * id is the path. In Database driven filesystems its easier to
     * work with primary keys etc.
     *
     * @param mixed $id
     * @param int $depth
     * @return \FileDB\Contracts\Model\File
     **/
    public function getById($id, $depth=0, $withParents=false)
    {

        foreach($this->fs as $file){
            if($file->getId() == $id){
                return $file;
            }
        }

    }

    /**
     * Get a file by its hash
     *
     * @param string $hash
     * @return \FileDB\Contracts\Model\File
     **/
    public function getByHash($hash)
    {

        foreach($this->fs as $file){
            if($file->getHash() == $hash){
                return $file;
            }
        }

    }

    /**
     * Create a directory
     *
     * @param string $path
     * @return \FileDB\Contracts\Model\Directory
     **/
    public function makeDirectory($path)
    {
        $this->fs[$path] = $this->newDir();
        $this->fs[$path]->setPath($path);
        return $this->fs[$path];
    }

    /**
     * Create a file
     *
     * @param string $path
     * @param string $contents
     * @return \FileDB\Contracts\Model\File
     **/
    public function create($path, $contents='')
    {
        $this->fs[$path] = $this->newFile();
        $this->fs[$path]->setPath($path);
        return $this->fs[$path];
    }

    /**
     * Save the file and persist all changes you did. This could
     * trigger just updating the name or recursively apply changes
     * to all childs of the file
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return self
     **/
    public function save(FileInterface $file)
    {
        return $this;
    }

    /**
     * Delete the file. If it is a directory recursively delete it
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return self
     **/
    public function delete(FileInterface $file)
    {

        if(isset($this->fs[$file->getPath()])){
            unset($this->fs[$file->getPath()]);
        }
        if($dir = $file->getDir()){
            $dir->removeChild($file);
        }
        return $this;

    }

    /**
     * Return all child files of directory $directory
     *
     * @param \FileDB\Contracts\Model\Directory $directory
     * @param int $depth
     * @return \Traversable
     **/
    public function listDirectory(DirInterface $directory, $depth=0)
    {
        return $directory->children();
    }

    /**
     * Copy a file to dir $dir
     *
     * @param \FileDB\Contracts\Model\File $file
     * @param \FileDB\Contracts\Model\Directory $directory
     * @return \FileDB\Contracts\Model\File The newly created one
     **/
    public function copy(FileInterface $file, DirInterface $directory)
    {
        $newFile = $this->newFile();
        $newFile->setName($file->getName());
        $newFile->setDir($directory);
        $this->fs[$newFile->getPath()] = $newFile;
        return $newFile;
    }

    /**
     * Move a file to dir $dir
     *
     * @param \FileDB\Contracts\Model\File $file
     * @param \FileDB\Contracts\Model\Directory $directory
     * @return \FileDB\Contracts\Model\File The passed one
     **/
    public function move(FileInterface $file, DirInterface $directory)
    {
        $file->setDir($directory);
        return $file;
    }

    /**
     * Returns the size in bytes
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return int
     **/
    public function size(FileInterface $file){
        return rand();
    }

    /**
     * Returns the last modification date as a DateTime object
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return \DateTime
     **/
    public function lastModified(FileInterface $file){
        return new \DateTime();
    }

    protected function newFile(){
        $class = get_class($this->filePrototype);
        $file = new $class();
        $file->_setRepository($this);
        return $file;
    }

    protected function newDir(){
        $class = get_class($this->dirPrototype);
        $dir = new $class();
        $dir->_setRepository($this);
        return $dir;
    }

}