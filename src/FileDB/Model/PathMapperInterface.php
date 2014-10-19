<?php namespace FileDB\Model;

interface PathMapperInterface{
    public function getBasePath();
    public function setBasePath($path);
    public function relativePath($absolutePath);
    public function absolutePath($relativePath);
}
