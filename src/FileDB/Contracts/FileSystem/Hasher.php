<?php namespace FileDB\Contracts\FileSystem;


interface Hasher
{

    /**
     * Returns a unique id for a file
     *
     * @param string $path
     * @return string
     **/
    public function fileHash($path);

    /**
     * Returns a unique id for a directory
     *
     * @param string $path
     * @return string
     **/
    public function directoryHash($path);

}