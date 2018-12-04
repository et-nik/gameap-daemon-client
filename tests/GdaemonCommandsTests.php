<?php

use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\GdaemonCommands;

/**
 * @covers \Knik\Gameap\GdaemonCommands<extended>
 */
class GdaemonCommandsTests extends TestCase
{
    public function adapterProvider()
    {
        $mock = Mockery::mock(GdaemonCommandsOverride::class)->makePartial();
        $gdaemonCommands = $mock;

        return [
            [$gdaemonCommands, $mock],
        ];
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonCommands $gdaemonCommands
     * @param Mockery\MockInterface $mock
     */
    public function testExec($gdaemonCommands, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonCommands::STATUS_OK,
                0,
                "CMD_RESULT"
            ])
        );

        $result = $gdaemonCommands->exec('rn -rf /', $exitCode);

        self::assertEquals('CMD_RESULT', $result);
        self::assertEquals(0, $exitCode);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonCommands $gdaemonCommands
     * @param Mockery\MockInterface $mock
     *
     * @expectedException RuntimeException
     */
    public function testExecFailure($gdaemonCommands, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonCommands::STATUS_ERROR,
                0,
                "CMD_RESULT"
            ])
        );

        $gdaemonCommands->exec('fornat c:', $exitCode);
    }
}

class GdaemonCommandsOverride extends GdaemonCommands
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

    public function login()
    {
        return true;
    }

    public function writeAndReadSocket($buffer)
    {
        return $this->readSocket();
    }
}