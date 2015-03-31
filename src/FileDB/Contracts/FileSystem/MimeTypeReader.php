<?php namespace FileDB\Contracts\FileSystem;


interface MimeTypeReader
{

    /**
     * Returns the mimetype of file with path $path
     *
     * @param string $path
     * @return string
     **/
    public function getMimeType($path);

}