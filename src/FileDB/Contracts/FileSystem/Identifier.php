<?php namespace FileDB\Contracts\FileSystem;


interface Identifier
{

    /**
     * Returns a unique id for a file
     *
     * @param string $path
     * @return string
     **/
    public function identifyFile($path);

    /**
     * Returns a unique id for a directory
     *
     * @param string $path
     * @return string
     **/
    public function identifyDirectory($path);

}