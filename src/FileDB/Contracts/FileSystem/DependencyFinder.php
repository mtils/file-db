<?php namespace FileDB\Contracts\FileSystem;

use FileDB\Model\FileInterface;

/**
 * The dependency search searches all dependencies for a file/directory.
 **/
interface DependencyFinder
{

    /**
     * Lists all dependencies for file $file and filters, sorts and limit the
     * result
     *
     * @param \FileDB\Model\FileInterface $file
     * @param array $filters (optional)
     * @param string $sort id|title|category (default:title)
     * @return \Traversable of \FileDB\Contracts\FileSystem\Dependency
     * @see \FileDB\Contracts\FileSystem\Dependency
     **/
    public function find(FileInterface $file, array $filters=[], $sort='title');

}