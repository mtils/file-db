<?php namespace FileDB\Contracts\Model;

interface Synchronizer{

    /**
     * Syncs file $file with target $targetRepository. If $file
     * is a directory it will be recursivly synced
     *
     * @param \FileDB\Contracts\Model\File $file
     * @param \FileDB\Contracts\Model\Repository $targetRepository
     * @return self
     **/
    public function synchronize(File $file, Repository $targetRepository);

}