<?php
namespace Knik\Gameap;

use Knik\Gameap\Exception\GdaemonClientException;

class GdaemonStatus extends Gdaemon
{
    const COMMAND_VERSION = 1;
    const COMMAND_STATUS_BASE = 2;
    const COMMAND_STATUS_DETAILS = 3;

    /** @var int */
    protected $mode = self::DAEMON_SERVER_MODE_STATUS;

    public function version(): array
    {
        $results = $this->request(self::COMMAND_VERSION);
        
        return [
            'version' => $results[0] ?? '-',
            'compile_date' => $results[1] ?? '-',
        ];
    }

    public function infoBase(): array
    {
        $results = $this->request(self::COMMAND_STATUS_BASE);

        return [
            'uptime' => $results[0],
            'working_tasks_count' => $results[1],
            'waiting_tasks_count' => $results[2],
            'online_servers_count' => $results[3],
        ];
    }

    public function infoDetails(): array
    {
        $results = $this->request(self::COMMAND_STATUS_DETAILS);

        return [
            'uptime' => $results[0],
            'working_tasks_list' => $results[1],
            'waiting_tasks_list' => $results[2],
            'online_servers_list' => $results[3],
        ];
    }

    private function request(int $command): array
    {
        $message = $this->binn->serialize([$command]);

        $read = $this->writeAndReadSocket($message);

        $results = $this->binn->unserialize($read);

        if ($results[0] != self::STATUS_OK) {
            throw new GdaemonClientException('Error: ' . isset($results[1]) ? $results[1] : 'Unknown');
        }
        
        return array_slice($results, 1);
    }
}
