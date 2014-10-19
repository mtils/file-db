<?php namespace FileDB\Model;

class UrlMapper extends PathMapper implements UrlMapperInterface{

    protected $baseUrl;

    public function getBaseUrl(){
        return $this->baseUrl;
    }

    public function setBaseUrl($url){
        $this->baseUrl = rtrim($url,'/');
        return $this;
    }

    public function pathToUrl($path){
        return $this->baseUrl . '/' . trim($path, '/');
    }

    public function urlToPath($url){
        return trim(str_replace($this->baseUrl,'', $url),'/');
    }
}