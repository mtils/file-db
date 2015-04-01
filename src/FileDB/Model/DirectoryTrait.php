<?php namespace FileDB\Model;


use OverflowException;
use OutOfBoundsException;
use FileDB\Contracts\Model\File as FileInterface;


trait DirectoryTrait
{

    /**
     * @var array
     **/
    protected $_children = [];

    /**
     * {@inheritdoc}
     **/
    public function children()
    {
        return $this->_children;
    }

    /**
     * {@inheritdoc}
     **/
    public function addChild(FileInterface $child)
    {

        if($this->hasChild($child)){
            $path = $child->getPath();
            return $this;
        }

        $this->_children[] = $child;
        $child->setDir($this);

        return $this;

    }

    /**
     * {@inheritdoc}
     **/
    public function removeChild(FileInterface $child){

        unset($this->_children[$this->indexOf($child)]);
        $this->_children = array_values($this->_children);
    }

    /**
     * {@inheritdoc}
     **/
    public function hasChild(FileInterface $child)
    {

        try{
            return is_int($this->indexOf($child));
        }
        catch(OutOfBoundsException $e){
            return false;
        }

    }

    /**
     * Finds the index of $child
     *
     * @param \FileDB\Contracts\Model\File $file
     * @return int
     **/
    public function indexOf(FileInterface $child)
    {

        foreach($this->_children as $index=>$added){
            if($added == $child){
                return $index;
            }
        }

        $path = $child->getPath();
        throw new OutOfBoundsException("Child with path $path not found");
    }

}