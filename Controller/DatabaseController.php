<?php

namespace Controller;

use mysqli;

class DatabaseController
{
    private $sname, $username, $password, $dbname;
    private $connection = null;
    private static $instance;


    /**
     * Create or get DatabaseController instance
     *
     * @return DatabaseController
     */
    public static function Instance()
    {
        if (!is_object(self::$instance)) {
            self::$instance = new DatabaseController('localhost', 'root', '', 'waarisdekawa');
        }

        return self::$instance;
    }


    /**
     * DatabaseController constructor.
     *
     * @param string $sname
     * @param string $username
     * @param string $password
     * @param string $dbname
     */
    private function __construct($sname, $username, $password, $dbname)
    {
        $this->sname = $sname;
        $this->username = $username;
        $this->password = $password;
        $this->dbname = $dbname;
    }


    /**
     * Open mysqli connection
     *
     * @throws \Exception
     */
    public function open()
    {
        if ($this->connection != null) {
            Throw new \Exception("Connection needs to be closed first.");
        }

        $this->connection = new mysqli($this->sname, $this->username, $this->password, $this->dbname);

        if ($this->connection->connect_error) {
            Throw new \Exception("Connection failed: " . $this->connection->connect_error);
        }
    }

    /**
     * Close mysqli connection
     *
     * @throws \Exception
     */
    public function close()
    {
        if ($this->connection == null) {
            Throw new \Exception("Connection needs to be opened first.");
        }

        $this->connection->close();
        $this->connection = null;
    }


    /**
     * Run mysqli query
     *
     * @param string $sql
     *
     * @return \mysqli_result
     * @throws \Exception
     */
    public function runQuery($sql)
    {
        if ($this->connection == null) {
            Throw new \Exception("Connection needs to be opened first.");
        }

        return $this->connection->query($sql);
    }


    /**
     * Run insert query and return insert Id
     *
     * @param string $sql
     *
     * @return int
     */
    public function insertQuery($sql)
    {
        $this->connection->query($sql);

        return $last_id = $this->connection->insert_id;
    }
}