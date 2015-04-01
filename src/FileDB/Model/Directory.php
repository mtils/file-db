<?php namespace FileDB\Model;


use FileDB\Contracts\Model\Directory as DirectoryInterface;

class Directory implements DirectoryInterface{

    use FileTrait;
    use DirectoryTrait;

}