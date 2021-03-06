<?php

namespace ZanPHP\ConnectionPool\Factory;

use swoole_client as SwooleClient;
use ZanPHP\ConnectionPool\Driver\Tcp as TcpConnection;
use ZanPHP\Contracts\ConnectionPool\ConnectionFactory;
use ZanPHP\Timer\Timer;

class Tcp implements ConnectionFactory
{
    private $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function create()
    {
        $isUnixSock = isset($this->config['path']);

        $clientFlags = $isUnixSock ? SWOOLE_SOCK_UNIX_STREAM : SWOOLE_SOCK_TCP;
        $socket = new SwooleClient($clientFlags, SWOOLE_SOCK_ASYNC);
        $socket->set($this->config['config']);

        $connection = new TcpConnection();
        $connection->setSocket($socket);
        $connection->setConfig($this->config);
        $connection->init();

        //call connect
        if ($isUnixSock) {
            $connected = $socket->connect($this->config['path']);
            $dst = $this->config['path'];
        } else {
            $connected = $socket->connect($this->config['host'], $this->config['port']);
            $dst = $this->config['host'].":".$this->config['port'];
        }

        if (false === $connected) {
            sys_error("Tcp connect $dst failed");
            return null;
        }

        Timer::after($this->config['connect_timeout'], $this->getConnectTimeoutCallback($connection), $connection->getConnectTimeoutJobId());

        return $connection;
    }

    public function getConnectTimeoutCallback(TcpConnection $connection)
    {
        return function() use ($connection) {
            $connection->close();
            $connection->onConnectTimeout();
        };
    }

    public function close()
    {

    }

    public function getFactoryType()
    {
        return "Tcp";
    }
}
