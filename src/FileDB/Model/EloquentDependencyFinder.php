<?php namespace FileDB\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use FileDB\Model\FileInterface;
use FileDB\Model\GenericDependency;

class EloquentDependencyFinder
{

    protected $relationName;

    protected $category;

    protected $titleKey;

    public function __construct($relationName, $category, $titleKey)
    {
        $this->relationName = $relationName;
        $this->category = $category;
        $this->titleKey = $titleKey;
    }

    public function __invoke(FileInterface $file)
    {

        $modelResults = $file->{$this->relationName}()->getResults();

        $dependencies = [];

        foreach ($modelResults as $resource) {
            $dependencies[] = new GenericDependency(
                $resource->getKey(),
                $this->category,
                $resource->getAttribute($this->titleKey)
            );
        }

        return $dependencies;
    }

}