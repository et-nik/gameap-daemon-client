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

    public function testGdaemon()
    {
        $gdaemon = new GdaemonStatus([
            'host' => 'localhost',
            'port' => 31717,
            'serverCertificate' => '/path/to/server.crt',
            'localCertificate' => '/path/to/client.crt',
            'privateKey' => '/path/to/client.key.pem',
            'privateKeyPass' => '1234',
            'timeout' => 10,
            'workDir' => '/home/user',
        ]);
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
//    public function testLogin($gdaemon, $mock)
//    {
//        $mock->shouldReceive('overrideReadSocket')->andReturn(
//            (new BinnList())->serialize([
//                Gdaemon::STATUS_OK,
//                'Auth success'
//            ])
//        );
//
//        // $result = $gdaemon->login();
//        $this->assertTrue($result);
//
//        // Repeat
//        $result = $gdaemon->login();
//        $this->assertTrue($result);
//    }

    /**
     * @dataProvider adapterProvider
     * @param Gdaemon $gdaemon
     * @param MockInterface $mock
     *
     * @expectedException RuntimeException
     */
//    public function testLoginFail($gdaemon, $mock)
//    {
//        $mock->shouldReceive('overrideReadSocket')->andReturn(
//            (new BinnList())->serialize([
//                Gdaemon::STATUS_ERROR,
//                'Auth failed'
//            ])
//        );
//
//        $gdaemon->login();
//    }

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
        $binn = (new BinnList($result))->unserialize();
        $this->assertEquals([Gdaemon::STATUS_OK, 'TEST'], $binn);
    }
}

namespace Knik\Gameap;

use Knik\Binn\BinnList;

function stream_socket_client ($remote_socket, &$errno = null, &$errstr = null, $timeout = null, $flags = null, $context = null)
{
    return fopen("/dev/null", 'r+');
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