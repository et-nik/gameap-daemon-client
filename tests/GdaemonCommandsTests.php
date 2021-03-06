<?php

use Knik\Binn\Binn;
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

        /** @var GdaemonCommandsOverride $gdaemonCommands */
        $gdaemonCommands = $mock;

        $gdaemonCommands->setBinn(new Binn());

        $gdaemonCommands->setConfig([
            'host' => 'localhost',
            'port' => 31717,
            'username' => 'sEcreT-L0gin',
            'password' => 'seCrEt-PaSSW0rD',
            'serverCertificate' => '/path/to/server.crt',
            'localCertificate' => '/path/to/client.crt',
            'privateKey' => '/path/to/client.key',
            'privateKeyPass' => null,
            'timeout' => 10,
            'workDir' => '/home/user',
        ]);

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

        $this->expectException(\RuntimeException::class);
        $gdaemonCommands->exec('fornat c:', $exitCode);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonCommands $gdaemonCommands
     * @param Mockery\MockInterface $mock
     */
    public function testExecEmptyDir($gdaemonCommands, $mock)
    {
        $gdaemonCommands->setConfig([
            'workDir' => '',
        ]);

        $this->expectException(\RuntimeException::class);
        $gdaemonCommands->exec('fornat c:', $exitCode);
    }
}

class GdaemonCommandsOverride extends GdaemonCommands
{
    protected $fakeConnection;

    protected $maxBufsize = 10;

    public function setBinn($binn): void
    {
        $this->binn = $binn;
    }

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

    protected function writeSocket(string $buffer): int
    {
        return $this->overrideWriteSocket($buffer);
    }

    public function login()
    {
        return true;
    }

    public function writeAndReadSocket(string $buffer)
    {
        return $this->readSocket();
    }
}
