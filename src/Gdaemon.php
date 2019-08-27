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
    const DAEMON_SERVER_MODE_STATUS = 4;

    const STATUS_ERROR                = 1;
    const STATUS_CRITICAL_ERROR       = 2;
    const STATUS_UNKNOWN_COMMAND      = 3;
    const STATUS_OK                   = 100;

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
     * @var string
     */
    protected $username = '';

    /**
     * @var string
     */
    protected $password = '';

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
        'username',
        'password',
        'serverCertificate',
        'localCertificate',
        'privateKey',
        'privateKeyPass',
        'timeout',
    ];

    /**
     * @var int
     */
    protected $maxBufsize = 20480;

    /**
     * @var int
     */
    protected $mode = self::DAEMON_SERVER_MODE_NOAUTH;

    /**
     * @var bool
     */
    private $_auth = false;

    /**
     * Constructor.
     *
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        $this->setConfig($config);
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
        if (empty($config)) {
            return $this;
        }

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

        set_error_handler(function ($err_severity, $err_msg) {
            throw new RuntimeException($err_msg);
        });

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

        $this->login();
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

        if (!$notTrimEndSymbols) {
            $read = '';
            while (!feof($this->_connection))
            {
                $part = fread($this->_connection, $len);

                $read .= $part;

                $offset = (strlen($read) > strlen(self::SOCKET_MSG_ENDL))
                    ? strlen($read) - strlen(self::SOCKET_MSG_ENDL)
                    : 0;

                if (strpos($read, self::SOCKET_MSG_ENDL, $offset) !== false) {
                    break;
                }
            }
        } else {
            $read = stream_get_contents($this->_connection, $len);
        }

        return $read;
    }

    /**
     * @param $buffer
     * @return int
     */
    protected function writeSocket($buffer)
    {
        if (empty($buffer)) {
            throw new RuntimeException('Empty write string');
        }
        
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

    /**
     * @return bool
     */
    private function login()
    {
        if ($this->_auth) {
            return $this->_auth;
        }

        $writeBinn= new BinnList;

        $writeBinn->addInt16(self::DAEMON_SERVER_MODE_AUTH);
        $writeBinn->addStr($this->username);
        $writeBinn->addStr($this->password);
        $writeBinn->addInt16($this->mode);

        $read = $this->writeAndReadSocket($writeBinn->serialize());

        $readBinn = new BinnList;
        $readBinn->binnOpen($read);
        $results = $readBinn->unserialize();

        if ($results[0] == self::STATUS_OK) {
            $this->_auth = true;
        } else {
            throw new RuntimeException('Could not login with connection: ' . $this->host . ':' . $this->port
                . ', username: ' . $this->username);
        }

        return $this->_auth;
    }
}