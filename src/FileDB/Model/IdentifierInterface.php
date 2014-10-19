<?php namespace FileDB\Model;

interface IdentifierInterface{
    public function fileId($path);
    public function dirId($path);
}