<?php

namespace Controller;

class LogController
{
    private $databaseController;
    private static $instance;


    /**
     * Create or get LogController instance
     *
     * @return LogController
     */
    public static function Instance()
    {
        if (!is_object(self::$instance)) {
            self::$instance = new LogController();
        }

        return self::$instance;
    }


    /**
     * LogController constructor.
     */
    private function __construct()
    {
        $this->databaseController = DatabaseController::Instance();
    }


    /**
     * Write log message to the database
     *
     * @param string $message
     */
    public function writeLog($message)
    {
        $this->databaseController->open();
        $this->databaseController->runQuery("INSERT INTO logs (packet) VALUES ('$message')");
        $this->databaseController->close();
    }
}