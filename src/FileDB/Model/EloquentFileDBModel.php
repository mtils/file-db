<?php namespace FileDB\Model;

use App;
use File;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesser;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use ReflectionClass;
use RuntimeException;

class EloquentFileDBModel implements FileDBModelInterface{

    protected $fileClassName = '\FileDB\Model\EloquentFile';

    protected $baseUrl;

    protected $basePath;

    protected $mapper;

    public function __construct(PathMapperInterface $mapper, IdentifierInterface $hasher){
        $this->mapper = $mapper;
        $this->hasher = $hasher;
    }

    public function setFileClassName($className){
        $this->fileClassName = $className;
        return $this;
    }

    public function create(){
        $refl = new ReflectionClass($this->fileClassName);
        return $refl->newInstance();
    }

    public function getPathMapper(){
        return $this->mapper;
    }

    public function createFromPath($path){
        $file = $this->create();
        $this->fillByPath($file, $path);
        return $file;
    }

    protected function fillByPath(FileInterface $file, $path){
        $absolutePath = $this->mapper->absolutePath($path);
        $file->setPath($path);
        $file->setName(basename($absolutePath));

        if(File::isDirectory($absolutePath)){
            $mimeType = 'inode/directory';
            $hash = $this->hasher->dirId($path);
        }
        else{
            $mimeType = MimeTypeGuesser::getInstance()->guess($absolutePath);
            $hash = $this->hasher->fileId($absolutePath);
        }
        $file->setMimeType($mimeType);
        $file->hash = $hash;
    }

    public function getById($id, $depth=0){
        $method = array($this->fileClassName, 'where');
        $where = call_user_func($method,'id','=',$id);
        if($depth == 1){
            $where = $where->orWhere('parent_id','=',$id);
        }
        $dir = NULL;
        $result = $where->get()->all();
        foreach($result as $file){
            if($file->id == $id){
                $dir = $file;
                break;
            }
        }
        if(!$dir){
            throw new RuntimeException("No dir found for id $id");
        }
        foreach($result as $file){
            if($file->id != $dir->id){
                $dir->addChild($file);
                $file->setDir($dir);
            }
        }
        return $dir;
    }

    public function get($path, $depth=0){
        $method = array($this->fileClassName, 'where');
        $where = call_user_func($method,'file_path','=','/');

        if(!$result = $where->get()->first()){
            throw new NotInDbException("Path '$path' is not in DB");
        }
        if($result->isDir() && $depth > 0 && !$result->isEmpty()){
            $where = call_user_func($method,'parent_id','=',$result->id);
            foreach($where->get() as $file){
                $result->addChild($file);
            }
        }
        return $result;
    }

    public function listDir(FileInterface $folder=NULL){
        $method = array($this->fileClassName, 'where');
        if(!$folder){
            return $this->get('/');
        }
        return call_user_func($method, 'parent_id','=',$folder->id);
    }

    public function save(FileInterface $file){
        if(!$file->exists){
            if(!$parentDir = $file->getDir()){
                if(!$file->parent_id){
                    throw new RuntimeException('No parentId, dont know where to save the folder');
                }
                $parentDir = $this->getById($file->parent_id);
            }
            if(!$parentDir){
                throw new RuntimeException('Parent Dir not found');
            }
            $path = trim($parentDir->getPath(),'/').'/'.trim($file->getName(),'/');
            $absPath = $this->mapper->absolutePath($path);

            if($file->isDir()){
                if(!File::makeDirectory($absPath)){
                    throw new RuntimeException('Couldnt create directory in filesystem. (Access Rights?)');
                }
                $title = $file->title;
                $this->fillByPath($file, $path);
                if($title){
                    $file->title = $title;
                }
                $file->parent_id = $parentDir->id;
                $file->save();
            }
            else{
                $title = $file->title;
                $this->fillByPath($file, $path);
                if($title){
                    $file->title = $title;
                }
                $file->parent_id = $parentDir->id;
                $file->save();
            }
        }
    }

    public function syncWithFs(FileInterface $fileOrFolder, $depth=0){

        if(!$fileOrFolder->getMimeType()){
            throw new RuntimeException('Assign a mimeType before syncing');
        }
//         echo "\nsyncWithFs: $fileOrFolder->file_path $fileOrFolder->mime_type";
        $isEmpty = 0;
        $children = array();

        if($fileOrFolder->isDir()){
            $files = File::files($fileOrFolder->getFullPath());
            $dirs = File::directories($fileOrFolder->getFullPath());
            $children = array_merge($dirs, $files);
            if(!count($children)){
                $isEmpty = 1;
            }
        }
        else{
            if($parentDir = $this->getOrCreateParent($fileOrFolder)){
                $fileOrFolder->parent_id = $parentDir->id;
                $fileOrFolder->save();
                return;
            }
        }

        $savedChildren = array();

        if(!$fileOrFolder->exists){
            if($fileOrFolder->getPath() == '/'){
                $fileOrFolder->parent_id = NULL;
                $fileOrFolder->is_empty = $isEmpty;
                $fileOrFolder->save();
            }
            else{
                if($parentDir = $this->getOrCreateParent($fileOrFolder)){
                    $fileOrFolder->parent_id = $parentDir->id;
                    $fileOrFolder->is_empty = $isEmpty;
                    $fileOrFolder->save();
                }
            }
        }
        elseif($fileOrFolder->isDir()){
            $savedResult = array();
            if(!$savedResult = $fileOrFolder->children()){
                $savedResult = $this->listDir($fileOrFolder);
            }
            foreach($savedResult as $file){
                $savedChildren[$file->getPath()] = $file;
            }
        }

        if( $depth > 0 && $children){
            foreach($children as $filePath){

                $relPath = $this->mapper->relativePath($filePath);
                $file = $this->createFromPath($relPath);

                if(isset($savedChildren[$relPath])){
                    if($this->isSame($savedChildren[$relPath], $file)){
                        continue;
                    }
                }
                $fileOrFolder->addChild($file);
                $file->setDir($fileOrFolder);
                $file->parent_id = $fileOrFolder->id;

                $this->syncWithFs($file, $depth-1);
            }
        }
    }

    public function getOrCreateParent(FileInterface $fileOrFolder){
        $parent = $fileOrFolder->getDir();
        if($parent && $parent->exists){
            return $parent;
        }
        if($fileOrFolder->getPath() == '/'){
            return;
        }
        $paths = $this->getParentPaths($fileOrFolder->getPath());
        var_dump($paths);
    }

    protected function getParentPaths($path){
        $segments = explode('/', $path);
        $paths = array('/');
        $stack = array();
        foreach($segments as $segment){
            $stack[] = $segment;
            $paths[] = implode('/', $stack);
        }
        return $paths;
    }

    public function getParents(FileInterface $file){
        if($file->getPath() != '/'){
            $paths = $this->getParentPaths($file->getPath());
            if(count($paths)){
                $method = array($this->fileClassName, 'whereIn');
                $result = call_user_func($method,'file_path',$paths)->get();
                $parentsById = array();
                foreach($result as $parent){
                    if($parent->id == $file->id){
                        $parentsById[$file->id] = $file;
                    }
                    else{
                        $parentsById[$parent->id] = $parent;
                    }
                }
                foreach($parentsById as $id=>$parent){
                    if(isset($parentsById[$parent->parent_id])){
                        $parentsById[$parent->parent_id]->addChild($parent);
                        $parent->setDir($parentsById[$parent->parent_id]);
                    }
                }
                $parents = array();
                $child = $file;
                while($parent = $child->getDir()){
                    $parents[] = $parent;
                    if($parent->id == $child->id){
                        break;
                    }
                    $child = $parent;
                }
                return array_reverse($parents);
            }
        }
        return array();
    }

    public function deleteFile(FileInterface $file){
    
    }

    public function getAttributes(FileInterface $file){

    }

    public function mapToUrl(FileInterface $file){
    
    }

    public function mapToFile($url){

    }

    public function moveIntoFolder(UploadedFile $uploadedFile, FileInterface $folder){
        if(!$folder->isDir()){
            throw new RuntimeException('Files can only be moved into directories');
        }
        $fileName = $uploadedFile->getClientOriginalName();
        $targetPath = $folder->getPath();
        $absPath = $this->mapper->absolutePath($targetPath);

        $uploadedFile->move($absPath, $fileName);
        $file = $this->createFromPath("$targetPath/$fileName");
        $file->setDir($folder);
        $folder->addChild($file);
        $this->save($file);
        return $file;
    }

    public function isSame(FileInterface $leftFile, FileInterface $rightFile){
        return ($leftFile->hash == $rightFile->hash);
    }

    public function getBaseUrl(){
        return $this->baseUrl;
    }

    public function getBasePath(){
        return $this->basePath;
    }
}