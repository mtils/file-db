<?php namespace FileDB\Model;

/**
 * Interface FileDBModelInterface
 *
 * The FileDBModel delivers a filesystem. It creates database entries for any
 * file it finds in a filesystem.
 * This is not meant as a every time syncing or two-way sync method. It should
 * be pretty dumb.
 * It assumes that you do every file operation with this class. So every method
 * except one is Database -> Filesystem and not vice versa.
 *
 * For the initial fill or if really (accidentally) someone changed the file
 * system outside of this class choose syncWithFs() and it will update the
 * database by the filesystem (Filesystem -> Database)
 *
 * @package FileDB\Model
 */
interface FileDBModelInterface
{
    /**
     * Get a file by its database id. Just look into the database.
     *
     * @param $id
     * @param int $depth
     *
     * @return FileInterface
     */
    public function getById($id, $depth=0);

    /**
     * Get a file by its $path.
     *
     * @param string $path
     * @param int   $depth
     *
     * @return FileInterface
     */
    public function get($path, $depth=0);

    /**
     * Instantiate a new file. (without persistence)
     *
     * @return FileInterface
     */
    public function create();

    /**
     * Return all files in $folder
     *
     * @param FileInterface|null $folder
     *
     * @return FileInterface[]
     */
    public function listDir(FileInterface $folder=NULL);

    /**
     * Persist the changed on $file. Sync the FS and DB for this $file.
     *
     * @param FileInterface $file
     *
     * @return bool
     */
    public function save(FileInterface $file);

    /**
     * Synchronize database and filesystem for $fileOrFolder. The wording is very
     * misleading in this case. The direction is: take the fs and apply the
     * structure in the database.
     *
     * @param FileInterface|string $fileOrFolder
     * @param int $depth
     *
     * @return mixed
     */
    public function syncWithFs($fileOrFolder, $depth=0);

    /**
     * Import a local file into the FileDB
     *
     * @param string         $localPath
     * @param FileInterface  $folder (optional)
     *
     * @return FileInterface The new file
     */
    public function importFile($localPath, FileInterface $folder=null);

    /**
     * Delete a file from fs and db.
     *
     * @param FileInterface $file
     *
     * @return self
     */
    public function deleteFile(FileInterface $file);

    /**
     * Get all parents of $file in a list.
     *
     * @param FileInterface $file
     *
     * @return FileInterface[]
     */
    public function getParents(FileInterface $file);
}
