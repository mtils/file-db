<?php namespace FileDB\Model;

use FileDB\Contracts\FileSystem\Dependency;

class GenericDependency implements Dependency
{

    public $id;

    public $category;

    public $title;

    public function __construct($id, $category, $title=null)
    {
        $this->id = $id;
        $this->category = $category;
        $this->title = $title ?: "$category#$id";
    }

    /**
     * A unique id of the dependent object
     *
     * @return mixed
     **/
    public function id()
    {
        return $this->id;
    }

    /**
     * A readable category for the type of dependencies (if a news entry would
     * depend on this file-object it could be "News")
     *
     * @return string
     **/
    public function category()
    {
        return $this->category;
    }

    /**
     * A readable title for the dependent object. (If a news entry is dependent,
     * it could be the title of the news)
     *
     * @return string
     **/
    public function title()
    {
        return $this->title;
    }

}