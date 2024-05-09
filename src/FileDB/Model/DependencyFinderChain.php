<?php


namespace FileDB\Model;


use Collection\CallableSet;
use FileDB\Contracts\FileSystem\Dependency;
use FileDB\Model\FileInterface;
use FileDB\Contracts\FileSystem\DependencyFinder;

/**
 * {@inheritdoc}
 * This class allows to add callables which will provide the dependencies
 * for a file. The callable will be called with the file-object (or directory)
 * and must return a traversable containing all Dependency Objects.
 *
 * Add your providers via CallableSet interface (->add(), ->remove())
 *
 * @see \FileDB\Contracts\FileSystem\Dependency
 **/
class DependencyFinderChain extends CallableSet implements DependencyFinder
{

    /**
     * Lists all dependencies for file $file and filters, sorts and limit the
     * result
     *
     * @param \FileDB\Model\FileInterface $file
     * @param array $filters (optional)
     * @param string $sort id|title|category (default:title)
     * @return Dependency[]
     **/
    public function find(FileInterface $file, array $filters=[], $sort='title')
    {

        if (!$result = $this->all($file)) {
            return [];
        }

        return $result;
    }

    public function all(FileInterface $file)
    {
        $result = [];

        foreach ($this as $callable) {
            $result = array_merge($result, $this->getFromCallable($callable, $file));
        }

        return $result;
    }

    protected function getFromCallable(callable $callable, FileInterface $file)
    {
        if (!$result = call_user_func($callable, $file)) {
            return [];
        }
        return $result;
    }

}