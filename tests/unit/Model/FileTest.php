<?php 

use Mockery as m;
use FileDB\Model\File;
use FileDB\Model\Directory;
use FileDB\Model\DummyRepository;

class FileTest extends PHPUnit_Framework_TestCase{

    public function testImplementsInterface()
    {
        $this->assertInstanceOf('FileDB\Contracts\Model\File', new File);
    }

    public function testNameAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $name = 'test.txt';

        $file->setName($name);

        $this->assertEquals('', $file2->getName());
        $this->assertEquals($name, $file->getName());

    }

    public function testPathAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $path = '/tmp/foo/bar.txt';

        $file->setPath($path);

        $this->assertEquals('', $file2->getPath());
        $this->assertEquals($path, $file->getPath());
        $this->assertEquals(basename($path), $file->getName());

    }

    public function testHashAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $hash = md5('/tmp/foo/bar.txt');

        $file->setHash($hash);

        $this->assertEquals('', $file2->getHash());
        $this->assertEquals($hash, $file->getHash());
    }

    public function testDirAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $dir = $this->newDirectory();

        $file->setDir($dir);

        $this->assertSame($dir, $file->getDir());
    }

    public function testRepositoryAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $repo = $this->newRepository();

        $file->_setRepository($repo);

        $this->assertSame($repo, $file->getRepository());
    }

    public function testUnmodifiedPathAccessor()
    {

        $file = $this->newFile();
        $file2 = $this->newFile();
        $path = '/text.txt';

        $file->_setUnmodifiedPath($path);

        $this->assertEquals('', $file2->getUnmodifiedPath());
        $this->assertEquals($path, $file->getUnmodifiedPath());
    }

    public function testSetPathRemovesDirectory(){
        $this->fail('Implement '.__METHOD__);
    }

    public function testSetDirectoryManipulatesPath(){
        $this->fail('Implement '.__METHOD__);
    }

    protected function newFile()
    {
        return new File;
    }

    protected function newDirectory()
    {
        return new Directory();
    }

    protected function newRepository()
    {
        return new DummyRepository($this->newFile(), $this->newDirectory());
    }

}