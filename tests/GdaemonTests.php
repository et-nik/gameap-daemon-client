<?php

use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\Gdaemon;

/**
 * @covers \Knik\Gameap\Gdaemon<extended>
 */
class GdaemonTests extends TestCase
{
    public function adapterProvider()
    {
        $mock = Mockery::mock(GdaemonOverride::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $gdaemon = $mock;

        return [
            [$gdaemon, $mock],
        ];
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\Gdaemon $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testLogin($gdaemon, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                Gdaemon::STATUS_OK,
                'Auth success'
            ])
        );

        $result = $gdaemon->login();
        $this->assertNull($result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\Gdaemon $gdaemonFiles
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testLoginFail($gdaemon, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                Gdaemon::STATUS_ERROR,
                'Auth failed'
            ])
        );

        $gdaemon->login();
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\Gdaemon $gdaemonFiles
     * @param Mockery\MockInterface $mock
     */
    public function testWriteAndReadSocket($gdaemon, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
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

class GdaemonOverride extends Gdaemon
{
    protected $fakeConnection;

    protected $maxBufsize = 10;

    public function connect()
    {
        $this->getSocket();
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
}