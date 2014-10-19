<?php namespace FileDB\Model;

interface UrlMapperInterface extends PathMapperInterface{
    public function getBaseUrl();
    public function setBaseUrl($url);
    public function pathToUrl($path);
    public function urlToPath($url);
}