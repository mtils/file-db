<?php namespace FileDB\Contracts\FileSystem;


interface FileSystem
{

    TYPE_FILE = 'file';

    TYPE_DIR = 'dir';

    /**
     * Checks if a path exists
     *
     * @return bool
     **/
    public function exists($path);

    /**
     * Returns if path $path is a directory
     *
     * @param string $path
     * @return bool
     **/
    public function isDirectory($path);

    /**
     * Return all files and directories from path $path. You can
     * restrict the result by $onlyType=FileSystem::TYPE_FILE
     *
     * @see self::TYPE_FILE
     * @param string $path
     * @param string $onlyType
     * @return \Traversable
     **/
    public function readDirectory($path, $onlyType=null);

    /**
     * Returns the contents of the from the given path
     *
     * @param string $path
     * @return string (bytes)
     **/
    public function getContent($path);

    /**
     * Set the file content of file with path $path to the contents $contents.
     * If the file does not exist it will be created
     *
     * @param string $path
     * @param string $contents (bytes)
     * @return self
     **/
    public function setContent($path, $contents);

    /**
     * Create a directory. Must support recursivly creating it
     *
     * @param string $path
     * @return self
     **/
    public function makeDirectory($path);

    /**
     * Delete the file or directory with path $path. Directories will everytime
     * deleted recursivly
     *
     * @param string $path
     * @return self
     **/
    public function delete($path);

    /**
     * Moves file or directory $source to $target. Like on unix filesystems a
     * move in the same directory will be a rename operation.
     *
     * @param string $source
     * @param string $target
     * @return self
     **/
    public function move($source, $target);

    /**
     * Copies path $source to $target
     *
     * @param string $source
     * @param string $target
     * @return self
     **/
    public function copy($source, $target);

    /**
     * Returns the size in bytes
     *
     * @param string $path
     * @return int
     **/
    public function size($path);

    /**
     * Returns the last modification date as a DateTime object
     *
     * @param string $path
     * @return \DateTime
     **/
    public function lastModified($path);

}