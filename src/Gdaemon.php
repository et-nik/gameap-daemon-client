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
     * @var string
     */
    private $serverCertificate;

    /**
     * @var string
     */
    private $localCertificate;

    /**
     * @var string
     */
    private $privateKey;

    /**
     * @var string
     */
    private $privateKeyPass;

    /**
     * @var array
     */
    protected $configurable = [
        'host',
        'port',
        // 'username',
        // 'password',
        'serverCertificate',
        'localCertificate',
        'privateKey',
        'privateKeyPass',
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
        $this->login($config['username'], $config['password']);
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
        $sslContext = stream_context_create([
            'ssl' => [
                'allow_self_signed' => true,
                'verify_peer'       => true,
                'verify_peer_name'  => false,
                'cafile'            => $this->serverCertificate,
                'local_cert'        => $this->localCertificate,
                'local_pk'          => $this->privateKey,
                'passphrase'        => $this->privateKeyPass,
                'crypto_method'     => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            ]
        ]);

        set_error_handler(function () {});
        $this->_connection = stream_socket_client("tls://{$this->host}:{$this->port}",
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            $sslContext
        );
        restore_error_handler();

        if ( ! $this->_connection) {
            throw new RuntimeException('Could not connect to host: '
                . $this->host
                . ', port:' . $this->port
                . "(Error $errno: $errstr)");
        }

        stream_set_blocking($this->_connection, true);
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
    protected function login($username, $password)
    {
        if ($this->_auth) {
            return;
        }

        $writeBinn= new BinnList;

        $writeBinn->addInt16(self::DAEMON_SERVER_MODE_AUTH);
        $writeBinn->addStr($username);
        $writeBinn->addStr($password);
        $writeBinn->addInt16(3); // Set mode DAEMON_SERVER_MODE_FILES

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] == self::DAEMON_SERVER_STATUS_OK) {
            $this->_auth = true;
        } else {
            throw new RuntimeException('Could not login with connection: ' . $this->host . ':' . $this->port
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
        return $this->getConnection();
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

        $read = fread($this->getConnection(), $len);

        if ($read === false) {
            throw new RuntimeException('Socket read failed: ' );
        }

        return $notTrimEndSymbols ? $read : substr($read, 0, -4);
    }

    /**
     * @param $buffer
     * @return int
     */
    protected function writeSocket($buffer)
    {
        $result = fwrite($this->getConnection(), $buffer);

        if ($result === false) {
            throw new RuntimeException('Socket read failed');
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

        return $read;
    }
}