<?php

namespace Knik\Gameap;

use Knik\Binn\BinnList;
use RuntimeException;

abstract class Gdaemon
{
    const DAEMON_SERVER_MODE_NOAUTH = 0;
    const DAEMON_SERVER_MODE_AUTH   = 1;
    const DAEMON_SERVER_MODE_CMD    = 2;
    const DAEMON_SERVER_MODE_FILES  = 3;

    const DAEMON_SERVER_STATUS_OK   = 100;

    /**
     * @var string
     */
    const SOCKET_MSG_ENDL = "\xFF\xFF\xFF\xFF";

    /**
     * @var resource
     */
    private $_connection;

    /**
     * @var resource
     */
    protected $_socket;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port = 31717;

    /**
     * @var int
     */
    protected $timeout = 10;

    /**
     * @var array
     */
    protected $configurable = [
        'host',
        'port',
        // 'username',
        // 'password',
        // 'privateKey',
        // 'privateKeyPass',
        'timeout',
    ];

    /**
     * @var int
     */
    protected $maxBufsize = 10240;

    /**
     * @var bool
     */
    private $_auth = false;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->setConfig($config);

        $this->connect();
        $this->login($config['username'], $config['password'], $config['privateKey'], $config['privateKeyPass']);
    }

    /**
     * Disconnect on destruction.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Set the config.
     *
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config)
    {
        foreach ($this->configurable as $setting) {
            if ( ! isset($config[$setting])) {
                continue;
            }

            if (property_exists($this, $setting)) {
                $this->$setting = $config[$setting];
            }
        }

        return $this;
    }

    /**
     * Connect to the server.
     */
    public function connect()
    {
        // $this->_connection = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeout);
        $this->_connection = stream_socket_client("tcp://{$this->host}:{$this->port}", $errno, $errstr, 30);

        if ( ! $this->_connection) {
            throw new RuntimeException('Could not connect to host: '
                . $this->host
                . ', port:' . $this->port
                . "(Error $errno: $errstr)");
        }

         $this->getSocket();
    }

    /**
     * @return mixed
     */
    protected function getConnection()
    {
        if (! is_resource($this->_connection)) {
            $this->disconnect();
            $this->connect();
        }

        return $this->_connection;
    }

    /**
     * @param $username
     * @param $password
     * @param $privateKey
     * @param $privateKeyPass
     */
    protected function login($username, $password, $privateKey, $privateKeyPass)
    {
        if ($this->_auth) {
            return;
        }

        $writeBinn= new BinnList;

        $writeBinn->addInt16(self::DAEMON_SERVER_MODE_AUTH);
        $writeBinn->addStr($username);
        $writeBinn->addStr($password);
        $writeBinn->addInt16(3); // Set mode DAEMON_SERVER_MODE_FILES

        $fp = fopen($privateKey, "r");
        $privateKey = fread($fp, 8192);
        fclose($fp);

        $res = openssl_get_privatekey($privateKey, $privateKeyPass);
        openssl_private_encrypt($writeBinn->serialize() . "\00", $encoded, $res);

        $read = $this->writeAndReadSocket($encoded);

        $decrypted = "";
        if (!openssl_private_decrypt($read, $decrypted, $res)) {
            throw new RuntimeException('OpenSSL private decrypt error');
        }

        if ($decrypted == '') {
            throw new RuntimeException('Empty decrypted results');
        }

        $readBinn = new BinnList;
        $readBinn->binnOpen($decrypted);
        $results = $readBinn->unserialize();

        if ($results[0] != self::DAEMON_SERVER_STATUS_OK) {
            $this->_auth = true;
        } else {
            throw new RuntimeException('Could not login with connection: ' . $this->host . '::' . $this->port
                . ', username: ' . $username);
        }
    }

    /**
     * Disconnect
     */
    public function disconnect()
    {
        if (is_resource($this->_socket)) {
            socket_close($this->_socket);
            $this->_socket = null;
        }

        if (is_resource($this->_connection)) {
            fclose($this->_connection);
            $this->_connection = null;
        }

        $this->_auth = false;
    }

    /**
     * @return bool|null|resource
     */
    protected function getSocket()
    {
        if (is_resource($this->_socket)) {
            return $this->_socket;
        }

        set_error_handler(function () {});
        $this->_socket = socket_import_stream($this->getConnection());
        restore_error_handler();

        if (! $this->_socket) {
            $this->disconnect();
            throw new RuntimeException('Could not import socket');
        }

        stream_set_timeout($this->getConnection(), $this->timeout);
        socket_set_option($this->_socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $this->timeout, 'usec' => 0));
        socket_set_option($this->_socket, SOL_SOCKET, SO_SNDTIMEO, array('sec'=> $this->timeout, 'usec' => 0));

        return $this->_socket;
    }

    /**
     * @param integer
     * @param bool
     * @return bool|string
     */
    protected function readSocket($len = 0, $notTrimEndSymbols = false)
    {
        if ($len == 0) {
            $len = $this->maxBufsize;
        }

        $read = socket_read($this->getSocket(), $len);

        if ($read === false) {
            throw new RuntimeException('Socket read failed: ' . socket_strerror(socket_last_error($this->getSocket())));
        }

        return $notTrimEndSymbols ? $read : substr($read, 0, -4);
    }

    /**
     * @param $buffer
     * @return int
     */
    protected function writeSocket($buffer)
    {
        $result = socket_write($this->getSocket(), $buffer);

        if ($result === false) {
            throw new RuntimeException('Socket read failed: ' . socket_strerror(socket_last_error($this->getSocket())));
        }

        return $result;
    }

    /**
     * Write data to socket and read
     *
     * @param string $buffer
     * @return bool|string
     */
    protected function writeAndReadSocket($buffer)
    {
        $this->writeSocket($buffer . self::SOCKET_MSG_ENDL);

        $read = $this->readSocket();

        if (!$read) {
            throw new RuntimeException('Read socket error');
        }

        return $read;
    }
}