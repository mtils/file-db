<?php namespace FileDB\Model;

class FileIdentifier implements IdentifierInterface{

    public function fileId($path){
        return sha1_file($path);
    }

    public function dirId($path){
        return sha1($path);
    }

}