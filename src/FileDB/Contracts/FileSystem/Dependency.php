<?php namespace FileDB\Contracts\FileSystem;

/**
 * A Dependency is something related to a file. If you have a database object,
 * which has a relation to a file-db object (e.g. news->main_image) you can
 * add your news entry to the dependency list of a file.
 * In file-dbs frontend these dependencies are shown in two places: as a
 * separate info window and before deleting files/directories
 **/
interface Dependency
{

    /**
     * A unique id of the dependent object
     *
     * @return mixed
     **/
    public function id();

    /**
     * A readable category for the type of dependencies (if a news entry would
     * depend on this file-object it could be "News")
     *
     * @return string
     **/
    public function category();

    /**
     * A readable title for the dependent object. (If a news entry is dependent,
     * it could be the title of the news)
     *
     * @return string
     **/
    public function title();

}