<?php namespace FileDB\Model;

interface FileInterface{
    public function getId();
    public function getMimeType();
    public function setMimeType($mimeType);
    public function is(FileInterface $file);
    public function getName();
    public function setName($name);
    public function getPath();
    public function setPath($path);
    public function getFullPath();
    public function getUrl();
    public function getDir();
    public function setDir(FileInterface $dir);
    public function isEmpty();
    public function isDir();
    public function addChild(FileInterface $child);
    public function children();
}