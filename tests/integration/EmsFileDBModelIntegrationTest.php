<?php
/**
 *  * Created by mtils on 14.10.18 at 16:52.
 **/

namespace FileDB\Test;

use Ems\Contracts\Core\Filesystem;
use Ems\Core\Laravel\IlluminateFilesystem;
use Ems\Core\LocalFilesystem;
use Ems\Testing\Eloquent\MigratedDatabase;
use Ems\Testing\FilesystemMethods;
use Ems\Tree\Eloquent\NodeRepository;
use FileDB\Model\EloquentFile;
use FileDB\Model\EmsFileDBModel;
use FileDB\Model\FileDBModelInterface;
use FileDB\Model\UrlMapper;
use Illuminate\Filesystem\FilesystemAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use function realpath;
use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\Local\LocalFilesystemAdapter as LocalFSAdapter;
use Ems\Contracts\Core\Errors\NotFound;

class EmsFileDBModelIntegrationTest extends TestCase
{
    use MigratedDatabase;
    use FilesystemMethods;

    protected $shouldPurgeTempFiles = true;


    #[Test] public function implements_interface()
    {
        $this->assertInstanceOf(FileDBModelInterface::class,
            $this->newFileDb());
    }

    #[Test] public function syncWithFs_and_get_nodes()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => ''
                ]
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $rootNode = $fileDb->get('/', 1);

        $this->assertEquals('/', $rootNode->getPath());

        $childByName = [];

        foreach ($rootNode->children() as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure as $basename=>$unused) {
            $index = "/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }

        $this->assertEquals('inode/directory', $childByName['/directory']->getMimeType());
        $this->assertEquals('text/plain', $childByName['/foo.txt']->getMimeType());
        $this->assertEquals('text/plain', $childByName['/bar.txt']->getMimeType());


        $dir = $fileDb->get('/directory', 1);

        $this->assertEquals('/directory', $dir->getPath());

        $childByName = [];

        foreach ($dir->children() as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure['directory'] as $basename=>$unused) {
            $index = "/directory/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }

        $dir = $fileDb->get('/directory/2016', 1);

        $this->assertEquals('/directory/2016', $dir->getPath());

        $childByName = [];

        foreach ($dir->children() as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure['directory']['2016'] as $basename=>$unused) {
            $index = "/directory/2016/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }

        $dirById = $fileDb->getById($dir->getId(), 1);

        $this->assertEquals($dirById->getId(), $dir->getId());
        $this->assertCount(2, $dirById->children());

        $this->truncate('files');

    }

    #[Test] public function syncWithFs_and_listDir()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => ''
                ]
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $childByName = [];

        foreach ($fileDb->listDir() as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure as $basename=>$unused) {
            $index = "/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }

        $subDir = $childByName['/directory'];

        $childByName = [];


        foreach ($fileDb->listDir($subDir) as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure['directory'] as $basename=>$unused) {
            $index = "/directory/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }


        $subSubDir = $childByName['/directory/2016'];

        $childByName = [];

        foreach ($fileDb->listDir($subSubDir) as $child) {
            $childByName[$child->getPath()] = $child;
        }

        foreach ($structure['directory']['2016'] as $basename=>$unused) {
            $index = "/directory/2016/$basename";
            $this->assertEquals($basename, $childByName[$index]->getPathSegment());
            $this->assertEquals($index, $childByName[$index]->getPath());
        }

        $this->truncate('files');

    }

    #[Test] public function syncWithFs_excludes_excluded_files()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            '.hidden-file' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => '',
                    'web.config' => '',
                    'thumbs.db' => '',
                ],
                '_excluded' => ''
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $childByName = [];

        foreach ($fileDb->listDir() as $child) {
            $childByName[$child->getPath()] = $child;
        }

        $this->assertTrue(isset($childByName['/foo.txt']));
        $this->assertFalse(isset($childByName['/.hidden-file']));

        $path = '/directory/users.json';
        $this->assertEquals($path, $fileDb->get($path)->getPath());

        try {
            $fileDb->get('/directory/_excluded');
            $this->fail("excluded file '_excluded' should not be in database");
        } catch (NotFound $e) {
            $this->assertTrue(true);
        }

        $path = '/directory/2016/ho.odt';
        $this->assertEquals($path, $fileDb->get($path)->getPath());

        try {
            $fileDb->get('/directory/2016/thumbs.db');
            $this->fail("excluded file 'thumbs.db' should not be in database");
        } catch (NotFound $e) {
            $this->assertTrue(true);
        }

        $this->truncate('files');

    }

    #[Test] public function syncWithFs_twice_does_not_change_database()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => ''
                ]
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $allNodes = $this->allNodesById($fileDb);

        $this->assertCount(8, $allNodes);

        $fileDb->syncWithFs('/', 10);

        $allNodesAfter = $this->allNodesById($fileDb);

        $this->assertCount(8, $allNodesAfter);

        $this->truncate('files');

    }

    #[Test] public function importFile_from_outside_into_empty_db()
    {

        list($tempDir, $dirs) = $this->createNestedDirectories([]);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $this->assertCount(0, $fileDb->listDir());

        $dirName = $this->tempDir();
        $fileName = "$dirName/test.txt";

        $fs = $this->newFilesystem();

        $fs->write($fileName, 'I am a test file');

        $importedFile = $fileDb->importFile($fileName);

        $this->assertInstanceOf(EloquentFile::class, $importedFile);
        $this->assertGreaterThan(1, $importedFile->getId());
        $this->assertEquals('/test.txt', $importedFile->getPath());
        $this->assertTrue($filesystem->exists('/test.txt'));

        $this->truncate('files');

    }

    #[Test] public function importFile_with_outside_file_throws_exception()
    {
        $this->expectException(
            \Ems\Core\Exceptions\ResourceNotFoundException::class
        );

        list($tempDir, $dirs) = $this->createNestedDirectories([]);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $this->assertCount(0, $fileDb->listDir());

        $fileDb->importFile('/foo/bar.txt');

    }

    #[Test] public function importFile_from_inside_empty_db()
    {

        $this->truncate('files');

        list($tempDir, $dirs) = $this->createNestedDirectories([]);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $this->assertCount(0, $fileDb->listDir());

        $fileName = "test.txt";
        $contents = 'I am a test file';

        $filesystem->write($fileName, $contents);

        $importedFile = $fileDb->importFile("/$fileName");

        $this->assertInstanceOf(EloquentFile::class, $importedFile);
        $this->assertGreaterThan(1, $importedFile->getId());
        $this->assertEquals("/$fileName", $importedFile->getPath());

        $this->assertEquals($contents, $filesystem->read($fileName));

        $this->truncate('files');

    }

    #[Test] public function syncWithFs_and_deleteFile()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            '.hidden-file' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => '',
                    'web.config' => '',
                    'thumbs.db' => '',
                ],
                '_excluded' => ''
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);

        $fileDb = $this->newFileDb($filesystem);

        $fileDb->syncWithFs('/', 10);

        $deleteFile = '/directory/baz.xml';
        $this->assertTrue($filesystem->exists($deleteFile));

        $deleteNode = $fileDb->get($deleteFile);
        $this->assertEquals($deleteFile, $deleteNode->getPath());

        $this->assertSame($fileDb, $fileDb->deleteFile($deleteNode));

        $this->assertFalse($filesystem->exists($deleteFile));

        $this->truncate('files');

    }

    #[Test] public function syncWithFs_with_file_urls()
    {
        $structure = [
            'foo.txt' => '',
            'bar.txt' => '',
            '.hidden-file' => '',
            'directory' => [
                'baz.xml' => '',
                'users.json' => '',
                '2016' => [
                    'gong.doc' => '',
                    'ho.odt' => '',
                    'web.config' => '',
                    'thumbs.db' => '',
                ],
                '_excluded' => ''
            ]
        ];

        list($tempDir, $dirs) = $this->createNestedDirectories($structure);
        unset($dirs);

        $filesystem = $this->newTestFilesystem(['root' => $tempDir]);


        $fileDb = $this->newFileDb($filesystem);
        $baseUrl = 'https://ems.org';

        $mapper = UrlMapper::create()->setBasePath('/')->setBaseUrl($baseUrl);

        $fileDb->setUrlMapper($mapper);

        $fileDb->syncWithFs('/', 10);

        $filePath= '/directory/baz.xml';

        $node = $fileDb->get($filePath);

        $this->assertEquals("$baseUrl$filePath", $fileDb->get($filePath)->getUrl());


    }

    protected function allNodesById(EmsFileDBModel $fileDb, $node = null, array &$flat=[])
    {
        foreach ($fileDb->listDir($node) as $child) {
            $flat[$child->getId()] = $child;
            if ($child->isDir()) {
                $this->allNodesById($fileDb, $child, $flat);
            }
        }
        return $flat;
    }

    protected function newFileDb(
        Filesystem $filesystem = null,
        NodeRepository $nodeRepository = null
    ) {
        $fs = $filesystem ?: $this->newFilesystem();
        $nodeRepository = $nodeRepository ?: $this->newNodeRepository();

        return new EmsFileDBModel($fs, $nodeRepository);

    }

    protected function newTestFilesystem(array $args=[])
    {
        return new IlluminateFilesystem($this->createLaravelAdapter($args));
    }

    protected function newNodeRepository()
    {
        $nodeRepo =  new NodeRepository(new EloquentFile());
        $nodeRepo->setSegmentKey('name')
                 ->setPathKey('file_path');
        return $nodeRepo;
    }

    /**
     * Return the (ems internal) package migration path
     *
     * @return string
     **/
    protected function packageMigrationPath()
    {
        return realpath(__DIR__ . '/../../migrations');
    }


    /**
     * @param array $args
     *
     * @return FilesystemAdapter
     */
    protected function createLaravelAdapter(array $args=[])
    {
        $adapter = $this->createFlysystemAdapter($args);
        return new FilesystemAdapter($this->createFlysystem($adapter, $args), $adapter);
    }

    /**
     * @param LocalFSAdapter $adapter
     * @param array $args
     * @return Flysystem
     */
    protected function createFlysystem(LocalFSAdapter $adapter, array $args = [])
    {
        return new Flysystem($adapter, ['url' => $args['url'] ?? '/']);
    }

    /**
     * @param array $args
     *
     * @return LocalFSAdapter
     */
    protected function createFlysystemAdapter(array $args = [])
    {
        return new LocalFSAdapter($args['root'] ?? '/');
    }

}