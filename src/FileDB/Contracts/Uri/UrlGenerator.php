<?php namespace FileDB\Contracts\Uri;

use FileDB\Contracts\Model\File;

interface UrlGenerator{

    /**
     * Generates a url to file $file with the given params $urlParams
     *
     * @param \FileDB\Contracts\Model\File
     * @param array $urlParams
     * @return string
     **/
    public function to(File $file, $urlParams=[]);

}