<?php namespace FileDB\Model;

use Ems\Contracts\Core\Named;

interface FileInterface extends Named
{
    /**
     * @return string
     */
    public function getMimeType();

    /**
     * @param string $mimeType
     *
     * @return self
     */
    public function setMimeType($mimeType);

    /**
     * @param string $name
     *
     * @return self
     */
    public function setName($name);

    /**
     * @return string
     */
    public function getPath();

    /**
     * @param string $path
     *
     * @return self
     */
    public function setPath($path);

    /**
     * @return string
     */
    public function getFullPath();

    /**
     * @return string
     */
    public function getUrl();

    /**
     * @return self|null
     */
    public function getDir();

    /**
     * @param FileInterface $dir
     *
     * @return self
     */
    public function setDir(FileInterface $dir);

    /**
     * @return bool
     */
    public function isEmpty();

    /**
     * @return bool
     */
    public function isDir();

    /**
     * @param FileInterface $child
     *
     * @return self
     */
    public function addChild(FileInterface $child);

    /**
     * @return FileInterface[]
     */
    public function children();

    /**
     * @return self
     */
    public function clearChildren();

    /**
     * Return the previously saved path.
     *
     * @return string
     */
    public function getOriginalPath();
}