<?php

namespace Knik\Gameap;

use Knik\Gameap\Exception\GdaemonClientException;

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
    public function exec($command, &$exitCode = null): string
    {
        if (empty($this->workDir)) {
            throw new GdaemonClientException('Empty working directory');
        }

        $message = $this->binn->serialize([
            self::COMMAND_EXEC,
            $command,
            $this->workDir,
        ]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::STATUS_OK) {
            throw new GdaemonClientException('Execute command error: ' . isset($results[1]) ? $results[1] : 'Unknown');
        }

        $exitCode = $results[1] ?? -1;

        // Return command execute results
        return $results[2] ?? '';
    }
}
