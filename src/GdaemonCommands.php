<?php

namespace Knik\Gameap;

use Knik\Binn\BinnList;
use RuntimeException;
use InvalidArgumentException;

class GdaemonCommands extends Gdaemon
{
    const COMMAND_EXEC = 1;

    /**
     * @var array
     */
    protected $configurable = [
        'host',
        'port',
        'username',
        'password',
        'serverCertificate',
        'localCertificate',
        'privateKey',
        'privateKeyPass',
        'timeout',
        'workDir',
    ];

    /**
     * @var string
     */
    protected $workDir = '';

    /**
     * @var int
     */
    protected $mode = self::DAEMON_SERVER_MODE_CMD;

    /**
     * @param string $command
     * @param string $exitCode Command exit code
     * @return string Command execute results
     */
    public function exec($command, &$exitCode = null)
    {
        if (empty($this->workDir)) {
            throw new RuntimeException('Empty working directory');
        }

        $writeBinn = new BinnList;

        $writeBinn->addUint8(self::COMMAND_EXEC);
        $writeBinn->addStr($command);
        $writeBinn->addStr($this->workDir);
        //$writeBinn->addUint8($timeout); // Options

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] != self::STATUS_OK) {
            throw new RuntimeException('Execute command error: ' . isset($results[1]) ? $results[1] : 'Unknown');
        }

        $exitCode = $results[1];

        // Return command execute results
        return $results[2];
    }
}