<?php namespace FileDB\Contracts\Uri;

interface PathMapper{

    /**
     * Return the relative path ob $absolutePath
     *
     * @param string $absolutePath
     * @return string
     **/
    public function relativePath($absolutePath, $basePath);

    /**
     * Return the absolute path of $relativePath
     *
     * @param string $relativePath
     * @return string
     **/
    public function absolutePath($relativePath, $basePath);

}