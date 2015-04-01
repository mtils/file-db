<?php namespace FileDB\Contracts\Model;


interface Repository
{

    /**
     * Return the base path (chroot) of this repository
     * 
     * @return string
     **/
    public function getBasePath();

    /**
     * Set the base path (chroot) of this repository
     *
     * @param string $basePath
     * @return self
     **/
    public function setBasePath($basePath);

    /**
     * Checks if a path exists
     *
     * @return bool
     **/
    public function exists($path);

    /**
     * Get a file object by the given path
     *
     * @param string $path
     * @return \FileDB\Contracts\Model\File
     **/
    public function getFromPath($path, $depth=0, $withParents=false);

    /** 
     * Get a file by its unique id. In normal filesystems its unique
     * id is the path. In Database driven filesystems its easier to
     * work with primary keys etc.
     *
     * @param mixed $id
     * @param int $depth
     * @return \FileDB\Contracts\Model\File
     **/
    public function getById($id, $depth=0, $withParents=false);

    /**
     * Get a file by its hash
     *
     * @param string $hash
     * @return \FileDB\Contracts\Model\File
     **/
    public function getByHash($hash);

    /**
     * Create a directory
     *
     * @param string $path
     * @return \FileDB\Contracts\Model\Directory
     **/
    public function makeDirectory($path);

    /**
     * Create a file
     *
     * @param string $path
     * @param string $contents
     * @return \FileDB\Contracts\Model\File
     **/
    public function create($path, $contents='');

    /**
     * Save the file and persist all changes you did. This could
     * trigger just updating the name or recursively apply changes
     * to all childs of the file
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return self
     **/
    public function save(File $file);

    /**
     * Delete the file. If it is a directory recursively delete it
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return self
     **/
    public function delete(File $file);

    /**
     * Return all child files of directory $directory
     *
     * @param \FileDB\Contracts\Model\Directory $directory
     * @param int $depth
     * @return \Traversable
     **/
    public function listDirectory(Directory $directory, $depth=0);

    /**
     * Copy a file to dir $dir
     *
     * @param \FileDB\Contracts\Model\File $file
     * @param \FileDB\Contracts\Model\Directory $directory
     * @return \FileDB\Contracts\Model\File The newly created one
     **/
    public function copy(File $file, Directory $directory);

    /**
     * Move a file to dir $dir
     *
     * @param \FileDB\Contracts\Model\File $file
     * @param \FileDB\Contracts\Model\Directory $directory
     * @return \FileDB\Contracts\Model\File The passed one
     **/
    public function move(File $file, Directory $directory);

    /**
     * Returns the size in bytes
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return int
     **/
    public function size(File $file);

    /**
     * Returns the last modification date as a DateTime object
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return \DateTime
     **/
    public function lastModified(File $file);

}