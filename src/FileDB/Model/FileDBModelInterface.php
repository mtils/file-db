<?php namespace FileDB\Model;

use Symfony\Component\HttpFoundation\File\UploadedFile;

interface FileDBModelInterface{
    public function get($path, $depth=0);
    public function create();
    public function createFromPath($path);
    public function listDir(FileInterface $folder=NULL);
    public function save(FileInterface $folder);
    public function syncWithFs(FileInterface $fileOrFolder, $depth=0);
    public function deleteFile(FileInterface $file);
    public function getAttributes(FileInterface $file);
    public function moveIntoFolder(UploadedFile $uploadedFile, FileInterface $folder);
    public function isSame(FileInterface $leftFile, FileInterface $rightFile);
    public function getPathMapper();
    public function getParents(FileInterface $file);
}