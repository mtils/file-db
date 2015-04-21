<?php

return [

    /*
    |--------------------------------------------------------------------------
    | The directory containing the files
    |--------------------------------------------------------------------------
    |
    | This directory is used as the (ch)root directory of filedb
    |
    */

    'dir' => public_path().'/uploads',

    /*
    |--------------------------------------------------------------------------
    | The url pointing to that directory
    |--------------------------------------------------------------------------
    |
    | If this string starts with http it will be treated as an absolute url,
    | otherwise it will go trough URL::to()
    |
    */
    'url' => '/uploads',

    /*
    |--------------------------------------------------------------------------
    | The File Model
    |--------------------------------------------------------------------------
    |
    | This is the eloquent model for file objects
    |
    */
    'model'    => 'FileDB\Model\EloquentFile',

    'route' => [
        'prefix'     => 'files',
        'controller' => 'FileDB\Controller\FileController'
    ]

];