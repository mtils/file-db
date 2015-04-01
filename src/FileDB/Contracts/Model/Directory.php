<?php namespace FileDB\Contracts\Model;


/**
 * This, like File is a memory-only interface. No method call will
 * trigger a filesystem operation until you call Repo::save()
 * 
 */
interface Directory extends File
{

    /**
     * Return all children of this file
     *
     * @return array
     **/
    public function children();

    /**
     * Add a child file to this directory
     *
     * @param \FileDB\Contracts\Model\File $child
     * @return self
     **/
    public function addChild(File $child);

    /**
     * Removes the child $child from this directory
     *
     * @param \FileDB\Contracts\Model\File $child
     * @return self
     **/
    public function removeChild(File $child);

}