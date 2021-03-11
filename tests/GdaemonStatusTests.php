<?php

use Knik\Binn\Binn;
use PHPUnit\Framework\TestCase;
use Knik\Binn\BinnList;
use Knik\Gameap\GdaemonStatus;

/**
 * @covers \Knik\Gameap\GdaemonStatus<extended>
 */
class GdaemonStatusTests extends TestCase
{
    public function adapterProvider()
    {
        $mock = Mockery::mock(GdaemonStatusOverride::class)->makePartial();

        /** @var GdaemonStatus $gdaemonStatus */
        $gdaemonStatus = $mock;

        $gdaemonStatus->setBinn(new Binn());

        $gdaemonStatus->setConfig([
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
            [$gdaemonStatus, $mock],
        ];
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonStatus $gdaemonStatus
     * @param Mockery\MockInterface $mock
     */
    public function testVersion($gdaemonStatus, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonStatus::STATUS_OK,
                "2.0.0",
                "27.08.2019 17:05"
            ])
        );

        $result = $gdaemonStatus->version();
        
        self::assertEquals(['version' => '2.0.0', 'compile_date' => '27.08.2019 17:05'], $result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonStatus $gdaemonStatus
     * @param Mockery\MockInterface $mock
     */
    public function testVersionFail($gdaemonStatus, $mock)
    {
        $this->expectException(\RuntimeException::class);
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonStatus::STATUS_ERROR,
                'Error',
                "2.0.0",
                "27.08.2019 17:05"
            ])
        );

        $gdaemonStatus->version();
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonStatus $gdaemonStatus
     * @param Mockery\MockInterface $mock
     */
    public function testInfoBase($gdaemonStatus, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonStatus::STATUS_OK,
                123,
                2,
                3,
                4,
            ])
        );

        $result = $gdaemonStatus->infoBase();

        self::assertEquals([
            'uptime' => 123, 
            'working_tasks_count' => 2,
            'waiting_tasks_count' => 3,
            'online_servers_count' => 4,
        ], $result);
    }

    /**
     * @dataProvider adapterProvider
     * @param Knik\Gameap\GdaemonStatus $gdaemonStatus
     * @param Mockery\MockInterface $mock
     */
    public function testInfoDetails($gdaemonStatus, $mock)
    {
        $mock->shouldReceive('overrideReadSocket')->andReturn(
            (new BinnList())->serialize([
                GdaemonStatus::STATUS_OK,
                123,
                [1, 2],
                [3, 4],
                [5, 6],
            ])
        );

        $result = $gdaemonStatus->infoDetails();

        self::assertEquals([
            'uptime' => 123,
            'working_tasks_list' => [1, 2],
            'waiting_tasks_list' => [3, 4],
            'online_servers_list' => [5, 6],
        ], $result);
    }
}

class GdaemonStatusOverride extends GdaemonStatus
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

    protected function writeSocket($buffer): int
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
