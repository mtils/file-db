<?php namespace FileDB\Contracts\Model;


/**
 * The file interface is mostly an interface to ensure the proper
 * functionality of a repository. All changes in a file object are in
 * in memory and will be persisted when you call Repository::save.
 * Therefore a file has to be assignable to a repository and has to
 * tell it its original path (getUnmodifiedPath())
 */
interface File
{

    /**
     * Get the (base)name of the file
     *
     * @return string
     **/
    public function getName();

    /**
     * Set the (base)name of this file. Setting the name will result
     * in changing the path too.
     *
     * @param string $name
     * @return self
     **/
    public function setName($name);

    /**
     * Return the path of this file. This path is always relative to
     * the base path of the repository of this file
     *
     * @return string
     **/
    public function getPath();

    /**
     * Setting the path of this file. Setting the path will also change
     * its (base)name
     *
     * @param string $path
     * @return self
     **/
    public function setPath($path);

    /**
     * Return a hash of this file
     *
     * @return string
     **/
    public function getHash();

    /**
     * Set a hash for this file
     *
     * @param string $hash
     * @return self
     **/
    public function setHash($hash);

    /**
     * Get the directory of a file.
     *
     * @return \FileDB\Contracts\Model\Directory
     **/
    public function getDir();

    /**
     * Set the directory of this file. Setting the directory will also
     * set the path.
     *
     * @param \FileDB\Contracts\Model\Directory $dir
     * @return self
     **/
    public function setDir(Directory $dir);

    /**
     * Return the repository which created the file
     *
     * @return \FileDB\Contracts\Model\Repository
     **/
    public function getRepository();

    /**
     * Set the repository. Only the repository itself is allowed to
     * set itself
     *
     * @param \FileDB\Contracts\Model\Repository $repository
     * @return self
     **/
    public function _setRepository(Repository $repository);

    /**
     * Return the unmodified path. This should everytime be the path
     * where the file is currently saved. If you set a new path via
     * setPath() the unmodified path will no change until you call
     * Repository::save()
     *
     * @return string
     **/
    public function getUnmodifiedPath();

    /**
     * Set the unmodified path. Only the repository should call this
     * method, therefore its pseudo-protected
     *
     * @param string $path
     * @return self
     **/
    public function _setUnmodifiedPath($path);

}