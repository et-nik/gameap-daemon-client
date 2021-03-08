<?php

namespace Knik\Gameap\Tests;

use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\Gdaemon;
use Knik\Gameap\GdaemonStatus;
use Mockery\MockInterface;
use Mockery;

/**
 * @covers \Knik\Gameap\Gdaemon<extended>
 */
class GdaemonTests extends TestCase
{
    static public $overriding = true;
    
    public function adapterProvider()
    {
        $mock = Mockery::mock(Gdaemon::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $gdaemon = $mock;

        return [
            [$gdaemon, $mock],
        ];
    }

    /**
     * @dataProvider adapterProvider
     * @param Gdaemon $gdaemon
     * @param MockInterface $mock
     */
    public function testSetConfig($gdaemon, $mock)
    {
        $this->assertInstanceOf(Gdaemon::class, $gdaemon->setConfig([]));

        $gdaemon->setConfig([
            'host' => 'changedHost',
        ]);
    }

    /**
     * @dataProvider adapterProvider
     * @param Gdaemon $gdaemon
     * @param MockInterface $mock
     */
    public function testWriteAndReadSocket($gdaemon, $mock)
    {
        $mock->shouldReceive('readSocket')->andReturn(
            (new BinnList())->serialize([
                Gdaemon::STATUS_OK,
                'TEST'
            ])
        );

        $result = $gdaemon->writeAndReadSocket('test');
        $binn = (new BinnList())->unserialize($result);
        $this->assertEquals([Gdaemon::STATUS_OK, 'TEST'], $binn);
    }
}

namespace Knik\Gameap;

use Knik\Binn\BinnList;

function stream_socket_client ($remote_socket, &$errno = null, &$errstr = null, $timeout = null, $flags = null, $context = null)
{
    return fopen("/dev/null", 'rb+');
}

function fwrite ($handle, $string, $length = null) 
{
    if (\Knik\Gameap\Tests\GdaemonTests::$overriding) {
        return true;
    } else {
        return \fwrite($handle, $string, $length);
    }
    
}

function stream_get_contents ($handle, $maxlength = null, $offset = null) 
{
    return (new BinnList())->serialize([
        Gdaemon::STATUS_OK,
        'OK',
    ]);
}

function feof ($handle) 
{
    return true;
}

function fread ($handle, $length) 
{
    return (new BinnList())->serialize([
        Gdaemon::STATUS_OK,
        'OK',
    ]) . Gdaemon::SOCKET_MSG_ENDL;
}

function stream_set_blocking ($stream, $mode) 
{
    return true;
}
