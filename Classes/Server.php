<?php

namespace Classes;

use Controller\DatabaseController;
use Controller\LogController;
use Factory\TrackerFactory;

class Server
{
    private $server, $port;
    private $serverRunning, $firstTime, $remoteIp, $remotePort;
    private $logController, $databaseController;
    private $tracker;


    /**
     * Server constructor.
     *
     * @param string $server
     * @param string $port
     */
    public function __construct($server, $port)
    {
        $this->server = $server;
        $this->port = $port;

        $this->serverRunning = true;
        $this->firstTime = false;
        $this->remoteIp = null;
        $this->remotePort = null;

        $this->logController = LogController::Instance();
        $this->databaseController = DatabaseController::Instance();
    }

    /**
     * Main function to start the GPS Tracker server
     */
    public function startServer()
    {
        if (($socket = socket_create(AF_INET, SOCK_STREAM, 0)) < 0) {
            $this->logController->writeLog("Failed to create socket: " . socket_strerror($socket));
            exit();
        }

        if (($ret = socket_bind($socket, $this->server, $this->port)) < 0) {
            $this->logController->writeLog("Failed to bind socket: " . socket_strerror($ret));
            exit();
        }

        if (($ret = socket_listen($socket, 0)) < 0) {
            $this->logController->writeLog("Failed to listen to socket: " . socket_strerror($ret));
            exit();
        }

        socket_set_nonblock($socket);
        $this->logController->writeLog("Waiting for clients to connect...");

        while ($this->serverRunning) {
            $connection = @socket_accept($socket);
            if ($connection === false) {
                usleep(100);
            } elseif ($connection > 0) {
                $this->handleConnection($socket, $connection);
            } else {
                $this->logController->writeLog("Error: " . socket_strerror($connection));
                die;
            }
        }
    }

    /**
     * Main function to stop the GPS Tracker server
     */
    public function stopServer()
    {
        $this->serverRunning = false;
    }

    /**
     * Handles each new connection
     *
     * @param string $clientSocket
     * @param string $clientConnection
     *
     * @throws \Exception
     */
    private function handleConnection($clientSocket, $clientConnection)
    {
        $pid = pcntl_fork();
        $loopCount = 0;
        $rec = "";

        if ($pid == -1) {
            die();
        } elseif ($pid == 0) {
            $this->serverRunning = false;
            socket_getpeername($clientConnection, $this->remoteIp, $this->remotePort);

            $this->firstTime = true;

            socket_close($clientSocket);
            while (@socket_recv($clientConnection, $rec, 2048, 0x40) !== 0) {
                sleep(1);

                $loopCount++;
                if ($loopCount > 1800) {
                    return;
                }

                if ($rec != "") {
                    $this->logController->writeLog(date("d-m-y h:i:sa") . " " . bin2hex($rec . ""));
                    $hexArray = str_split(bin2hex($rec . ""), 2);

                    if ($this->tracker != null) {
                        $this->tracker = TrackerFactory::create($hexArray, $clientConnection,
                            $this->databaseController);
                    }

                    if ($this->tracker = null) {
                        throw new \Exception("Tracker not found!");
                        exit();
                    }
                }
                $rec = "";

                $this->tracker->process();
            }
            $this->databaseController->close();
            socket_close($clientConnection);

            $this->logController->writeLog(date("d-m-y h:i:sa") . " Connection to $this->remoteIp:$this->remotePort closed");

        } else {
            socket_close($clientConnection);
        }
    }
}