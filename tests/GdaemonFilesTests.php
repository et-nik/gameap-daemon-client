<?php

namespace Knik\Gameap\Tests;

use Knik\Binn\Binn;
use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\GdaemonFiles;
use Mockery\MockInterface;
use Mockery;
use ReflectionClass;

/**
 * @covers \Knik\Gameap\GdaemonFiles<extended>
 */
class GdaemonFilesTests extends TestCase
{
    private $rootDir = __DIR__ . '/fixtures';

    public function adapterProvider()
    {
        $mock = Mockery::mock(GdaemonFiles::class)->makePartial()->shouldAllowMockingProtectedMethods();

        $reflection = new ReflectionClass(GdaemonFiles::class);
        $reflectionProperty = $reflection->getProperty('binn');
        $reflectionProperty->setAccessible(true);

        $reflectionProperty->setValue($mock, new Binn());

        $gdaemonFiles = $mock;

        return [
            [$gdaemonFiles, $mock],
        ];

    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testDirectoryContents($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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

        self::assertCount(2, $result);
        self::assertEquals(
            ['name' => 'filename', 'size' => 5, 'mtime' => 1234566, 'type' => 'file', 'permissions' => 0755],
            $result[0]
        );
        self::assertEquals(
            ['name' => 'filename2', 'size' => 15654, 'mtime' => 34567890, 'type' => 'file', 'permissions' => 0755],
            $result[1]
        );
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testDirectoryContentsEmpty($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testDirectoryContentsError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^GDaemon List files error/');
        $gdaemonFiles->directoryContents($this->rootDir);
    }


    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testListFiles($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testListFilesServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom Error Message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Custom Error Message/');
        $gdaemonFiles->listFiles($this->rootDir);
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testMkdir($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testMkdirExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'File `/directory` exist'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/File `[\/\_\-\w]+` exist/');
        $gdaemonFiles->mkdir($this->rootDir . '/directory');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testRmdir($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testRmdirServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t delete: Custom error message$/');
        $gdaemonFiles->delete($this->rootDir . '/directory');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testRenameFile($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testRenameDirectory($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testRenameDirectoryServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t move file: Custom error message$/');
        $gdaemonFiles->rename($this->rootDir . '/directory', $this->rootDir . '/directory_new');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testCopy($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testCopyServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t copy file: Custom error message$/');
        $gdaemonFiles->copy($this->rootDir . '/contents.txt', $this->rootDir . '/contents2.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     *
     */
    public function testChmod($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testChmodServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t chmod: Custom error message$/');
        $gdaemonFiles->chmod(0755, $this->rootDir . '/contents.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testNotExist($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testMetadata($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK',
                [
                    'file.txt', 43, 2, 1541971363, 1541971363, 1541971363, 0644, "text/plain"
                ]
            ])
        );

        $result = $gdaemonFiles->metadata('file.txt');
        self::assertEquals(
            [
                'name' => 'file.txt',
                'size' => 43,
                'type' => 'file',
                'mimetype' => 'text/plain',
                'mtime' => 1541971363,
                'atime' => 1541971363,
                'ctime' => 1541971363,
                'permissions' => 0644,
            ],
            $result
        );
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testMetadataServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK'
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom error message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^GDaemon metadata error: Custom error message$/');
        $gdaemonFiles->metadata('file.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGet($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                8,
            ]),
            'CONTENTS'
        );

        GdaemonTests::$overriding = false;
        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
        GdaemonTests::$overriding = true;
        
        $this->assertTrue($result);
        $this->assertFileExists($this->rootDir . '/contents_get.txt');
        $this->assertStringEqualsFile($this->rootDir . '/contents_get.txt', 'CONTENTS');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetGreatMaxBuf($gdaemonFiles, $mock)
    {
        $str = str_repeat('A', 20481);
        
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                strlen($str),
            ]),
            $str
        );

        GdaemonTests::$overriding = false;
        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
        GdaemonTests::$overriding = true;

        $this->assertTrue($result);
        $this->assertFileExists($this->rootDir . '/contents_get.txt');
        $this->assertStringEqualsFile($this->rootDir . '/contents_get.txt', $str);
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetBetterMaxBufsize($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                20,
            ]),
            'AAAAAAAAAAAAAAAAAAAA'
        );

        GdaemonTests::$overriding = false;
        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
        GdaemonTests::$overriding = true;
        
        $this->assertTrue($result);
        $this->assertFileExists($this->rootDir . '/contents_get.txt');
        $this->assertStringEqualsFile($this->rootDir . '/contents_get.txt', 'AAAAAAAAAAAAAAAAAAAA');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetFileResource($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File sending started',
                8,
            ]),
            'CONTENTS'
        );

        $fileHandle = fopen($this->rootDir . '/contents_resouce.txt', 'w+b');

        GdaemonTests::$overriding = false;
        $result = $gdaemonFiles->get($this->rootDir . '/contents.txt', $fileHandle);
        GdaemonTests::$overriding = true;
        
        $this->assertTrue(is_resource($result));
        $this->assertFileExists($this->rootDir . '/contents_resouce.txt');
        fclose($fileHandle);

        $this->assertStringEqualsFile($this->rootDir . '/contents_resouce.txt', 'CONTENTS');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetInvalidFile($gdaemonFiles, $mock)
    {
        $this->expectException(\RuntimeException::class);
        $gdaemonFiles->get($this->rootDir . '/contents_put.txt', '/root/file.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetInvalidServerResponse($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'File sending started',
                4,
            ])
        );

        $this->expectException(\RuntimeException::class);
        $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetInvalidArgument($gdaemonFiles, $mock)
    {
        $this->expectException(\InvalidArgumentException::class);
        $gdaemonFiles->get($this->rootDir . '/contents.txt', true);
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testGetServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Some Error',
                4,
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Some Error/');
        $gdaemonFiles->get($this->rootDir . '/contents.txt', $this->rootDir . '/contents_get.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPut($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File receive started',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'OK'
            ])

        );

        $result = $gdaemonFiles->put($this->rootDir . '/contents.txt', $this->rootDir . '/contents_put.txt');
        $this->assertTrue($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPutFileResource($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
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
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPutInvalidServerResponse($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_OK,
                'File receive started',
            ])

        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Unexpected \'OK\' status/');
        $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPutInvalidFile($gdaemonFiles, $mock)
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^File open error/');
        $gdaemonFiles->put('/root/file.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPutServerError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom Error Message',
            ])

        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t upload file/');
        $gdaemonFiles->put($this->rootDir . '/contents_get.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     * @param MockInterface $mock
     */
    public function testPutServerPutError($gdaemonFiles, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonFiles::STATUS_OK,
                'OK',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_FILE_TRANSFER_READY,
                'File receive started',
            ]),
            (new BinnList())->serialize([
                GdaemonFiles::FSERV_STATUS_ERROR,
                'Custom Error Message'
            ])
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/^Couldn\'t send file: Custom Error Message/');
        $gdaemonFiles->put($this->rootDir . '/contents.txt', $this->rootDir . '/contents_put.txt');
    }

    /**
     * @dataProvider adapterProvider
     * @param GdaemonFiles $gdaemonFiles
     */
    public function testPutInvalidArgumentException($gdaemonFiles)
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/^Invalid local file/');
        $gdaemonFiles->put(0, $this->rootDir . '/contents_put.txt');
    }
}
