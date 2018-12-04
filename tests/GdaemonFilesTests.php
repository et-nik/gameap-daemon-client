<?php

use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\GdaemonFiles;

/**
 * @covers \Knik\Gameap\GdaemonFiles<extended>
 */
class GdaemonFilesTests extends TestCase
{
    private $rootDir = __DIR__ . '/fixtures';

    public function adapterProvider()
    {
        $mock = Mockery::mock(GdaemonFilesOverride::class)->makePartial();
        $gdaemonFiles = $mock;

        return [
            [$gdaemonFiles, $mock],
        ];

    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testDirectoryContents($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    // Filename, size, mtime, type (1-dir, 2-file), privileges
                    ['.', 0, 0, 1, 0755],
                    ['..', 0, 0, 1, 0755],
                    ['filename', 5, 1234566, 2, 0755],
                    ['filename2', 15654, 34567890, 2, 0755],
                ]
            ])
        );

        $result = $gdaemonFiles->directoryContents($this->rootDir);
        $this->assertCount(2, $result);

        $this->assertArraySubset(['name' => 'filename', 'size' => 5, 'type' => 'file'], $result[0]);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testDirectoryContentsEmpty($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                ]
            ])
        );

        $result = $gdaemonFiles->directoryContents($this->rootDir);
        $this->assertCount(0, $result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testDirectoryContentsError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->directoryContents($this->rootDir);
    }


    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testListFiles($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    ['.'],
                    ['..'],
                    ['contents.txt']
                ]
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                ]
            ])
        );

        $listFiles = $gdaemonFiles->listFiles($this->rootDir);
        $this->assertEquals(['contents.txt'], $listFiles);

        $listFiles = $gdaemonFiles->listFiles($this->rootDir);
        $this->assertEquals([], $listFiles);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testListFilesServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->listFiles($this->rootDir);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     */
    public function testMkdir($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->mkdir($this->rootDir . '/directory');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     * @expectedExceptionMessageRegExp /File `[\/\_\-\w]+` exist/
     */
    public function testMkdirExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'File `/directory` exist'
            ])
        );

        $gdaemonFiles->mkdir($this->rootDir . '/directory');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testRmdir($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->delete($this->rootDir . '/directory');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testRmdirServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->delete($this->rootDir . '/directory');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testRenameFile($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->rename($this->rootDir . '/contents.txt', $this->rootDir . '/contents2.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testRenameDirectory($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->rename($this->rootDir . '/directory', $this->rootDir . '/directory_new');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testRenameDirectoryServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->rename($this->rootDir . '/directory', $this->rootDir . '/directory_new');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testCopy($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->copy($this->rootDir . '/contents.txt', $this->rootDir . '/contents2.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testCopyServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->copy($this->rootDir . '/contents.txt', $this->rootDir . '/contents2.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     */
    public function testChmod($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])
        );

        $result = $gdaemonFiles->chmod(0755, $this->rootDir . '/contents.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     *
     */
    public function testChmodServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->chmod(0755, $this->rootDir . '/contents.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    ['contents.txt']
                ]
            ])
        );

        $result = $gdaemonFiles->exist($this->rootDir . '/contents.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testNotExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    ['contents.txt']
                ]
            ])
        );

        $result = $gdaemonFiles->exist($this->rootDir . '/not_exist_file');
        $this->assertFalse($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testMetadata($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    'file.txt', 43, 2, 1541971363, 1541971363, 1541971363, 0644, "text/plain"
                ]
            ])
        );

        $result = $gdaemonFiles->metadata('file.txt');
        $this->assertArraySubset(['name' => 'file.txt', 'size' => 43, 'type' => 'file', 'mimetype' => 'text/plain'], $result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testMetadataServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])
        );

        $gdaemonFiles->metadata('file.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testGet($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                8,
            ]),
            'CONTENTS'
        );

        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
        $this->assertTrue($result);
        $this->assertFileExists($this->rootDir . '/contents_get.txt');
        $this->assertStringEqualsFile($this->rootDir . '/contents_get.txt', 'CONTENTS');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testGetBetterMaxBufsize($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                20,
            ]),
            'AAAAAAAAAAAAAAAAAAAA'
        );

        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
        $this->assertTrue($result);
        $this->assertFileExists($this->rootDir . '/contents_get.txt');
        $this->assertStringEqualsFile($this->rootDir . '/contents_get.txt', 'AAAAAAAAAAAAAAAAAAAA');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testGetFileResource($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                8,
            ]),
            'CONTENTS'
        );

        $fileHandle = fopen($this->rootDir . '/contents_resouce.txt', 'w+b');

        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $fileHandle);
        $this->assertTrue(is_resource($result));
        $this->assertFileExists($this->rootDir . '/contents_resouce.txt');
        fclose($fileHandle);

        $this->assertStringEqualsFile($this->rootDir . '/contents_resouce.txt', 'CONTENTS');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testGetInvalidFile($gdaemonFiles, $mock)
    {
        $gdaemonFiles->get($this->rootDir . '/contents_put.txt', '/root/file.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testGetInvalidServerResponse($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'File sending started',
                4,
            ])
        );

        $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException InvalidArgumentException
     */
    public function testGetInvalidArgument($gdaemonFiles, $mock)
    {
        $gdaemonFiles->get($this->rootDir . '/contents.txt', true);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testGetServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error',
                4,
            ])
        );

        $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testPut($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File receive started',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])

        );

        $result = $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testPutFileResource($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File receive started',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])

        );

        $fileHandle = fopen($this->rootDir . '/contents.txt', 'r');

        $result = $gdaemonFiles->put($fileHandle, $this->rootDir . '/contents_put.txt');
        $this->assertTrue(is_resource($result));
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testPutInvalidServerResponse($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'File receive started',
            ])

        );

        $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testPutInvalidFile($gdaemonFiles, $mock)
    {
        $gdaemonFiles->put('/root/file.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testPutServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error',
            ])

        );

        $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testPutServerPutError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File receive started',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Error'
            ])

        );

        $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonFiles $gdaemonFiles
     *
     * @expectedException InvalidArgumentException
     */
    public function testPutInvalidArgumentException($gdaemonFiles)
    {
        $gdaemonFiles->put(0, $this->rootDir . '/contents_put.txt');
    }
}

class GdaemonFilesOverride extends GdaemonFiles
{
    protected $fakeConnection;

    protected $maxBufsize = 10;

    public function connect()
    {
        $this->getSocket();
    }

    protected function getSocket()
    {
        set_error_handler(function () {});
        $this->_socket = socket_import_stream($this->getConnection());
        restore_error_handler();

        stream_set_timeout($this->getConnection(), $this->timeout);
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        socket_set_option($this->_socket, SOL_SOCKET, SO_SNDTIMEO, array('sec'=> $this->timeout, 'usec' => 0));

        return $this->_socket;
    }

    protected function getConnection()
    {
        return $this->fakeConnection;
    }

    public function overrideReadSocket()
    {
        return '';
    }

    public function overrideWriteSocket($buffer)
    {
        return 1;
    }

    protected function readSocket($len = 0, $notTrimEndSymbols = false)
    {
        return $this->overrideReadSocket();
    }

    protected function writeSocket($buffer)
    {
        return $this->overrideWriteSocket($buffer);
    }

    public function login($username, $password)
    {
        return true;
    }

    public function writeAndReadSocket($buffer)
    {
        return $this->readSocket();
    }
}