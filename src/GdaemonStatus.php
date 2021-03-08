<?php
namespace Knik\Gameap;

use Knik\Binn\BinnList;
use RuntimeException;
use InvalidArgumentException;

class GdaemonStatus extends Gdaemon
{
    const COMMAND_VERSION = 1;
    const COMMAND_STATUS_BASE = 2;
    const COMMAND_STATUS_DETAILS = 3;
    
    /**
     * @var int
     */
    protected $mode = self::DAEMON_SERVER_MODE_STATUS;

    /**
     * @return array
     */
    public function version()
    {
        $results = $this->request(self::COMMAND_VERSION);
        
        return [
            'version' => $results[0] ?? '-',
            'compile_date' => $results[1] ?? '-',
        ];
    }

    /**
     * @return array
     */
    public function infoBase()
    {
        $results = $this->request(self::COMMAND_STATUS_BASE);

        return [
            'uptime' => $results[0],
            'working_tasks_count' => $results[1],
            'waiting_tasks_count' => $results[2],
            'online_servers_count' => $results[3],
        ];
    }

    /**
     * @return array
     */
    public function infoDetails()
    {
        $results = $this->request(self::COMMAND_STATUS_DETAILS);

        return [
            'uptime' => $results[0],
            'working_tasks_list' => $results[1],
            'waiting_tasks_list' => $results[2],
            'online_servers_list' => $results[3],
        ];
    }

    /**
     * @param $command
     *
     * @return array
     */
    private function request($command)
    {
        $writeBinn = new BinnList;
        $writeBinn->addUint8($command);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList();
        $results = $readBinn->unserialize($read);

        if ($results[0] != self::STATUS_OK) {
            throw new RuntimeException('Error: ' . isset($results[1]) ? $results[1] : 'Unknown');
        }
        
        return array_slice($results, 2);
    }
}
