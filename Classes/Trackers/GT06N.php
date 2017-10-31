<?php

namespace Classes\Trackers;

use Classes\Tracker;
use Controller\DatabaseController;
use Helper\CRCHelper;

class GT06N implements Tracker
{
    private $socket;
    private $databaseController;
    private $terminalId;
    private $imei = '';


    /**
     * GT06N constructor.
     *
     * @param string             $socket
     * @param DatabaseController $databaseController
     */
    public function __construct($socket, $databaseController)
    {
        $this->socket = $socket;
        $this->databaseController = $databaseController;
    }


    /**
     * Get class name
     *
     * @return string
     */
    public function getName()
    {
        return "GT06N";
    }


    /**
     * Check is packet is meant for this tracker class
     *
     * @param array $hexArray
     *
     * @return bool
     */
    public static function isTracker($hexArray)
    {
        if (count($hexArray) > 0) {
            if ($hexArray[0] . $hexArray[1] == "7878") {
                return true;
            }
        }

        return false;
    }


    /**
     * Process hex array
     * Supports Login, GPS and Heartbeat packets
     *
     * @param array $hexArray
     */
    public function process($hexArray)
    {
        if (self::isTracker($hexArray)) {
            //Get protocol number from hex array
            $protocolNumber = strtoupper(trim($hexArray[3]));
            if ($protocolNumber == LOGIN) {
                $this->handleLogin($hexArray);
            } elseif ($protocolNumber == GPS) {
                $this->handleGPS($hexArray);
            } elseif ($protocolNumber == HEARTBEAT) {
                $this->handleHeartbeat($hexArray);
            } else {
                return;
            }
        }
    }


    /**
     * Process Login packet
     * Responds with message in the TCP socket.
     *
     * @param array $hexArray
     */
    private function handleLogin($hexArray)
    {
        //Get Terminal IMEI
        for ($i = 4; $i < 12; $i++) {
            $this->imei = $this->imei . $hexArray[$i];
        }
        $this->imei = substr($this->imei, 1, 15);

        //Query database for terminal info
        $result = $this->databaseController->runQuery("SELECT * FROM terminals WHERE terminal='" . $this->imei . "'");
        if ($result->num_rows == 0) {
            $this->terminalId = $this->databaseController->insertQuery("INSERT INTO terminals (terminal) VALUES ('$this->imei')");
        } else {
            $row = $result->fetch_assoc();
            $this->terminalId = $row['id'];
        }

        //Create packet to send back
        $serial = strtoupper($hexArray[count($hexArray) - 6] . " " . $hexArray[count($hexArray) - 5]);
        $responsePacket = "78 78 05 01 " . $serial . " " . CRCHelper::getCRC("05 01 " . $serial) . " 0D 0A";

        //Send back to tracker
        $sendCommands = explode(" ", $responsePacket);
        $responsePacket = '';
        for ($i = 0; $i < count($sendCommands); $i++) {
            $responsePacket .= chr(hexdec(trim($sendCommands[$i])));
        }

        socket_send($this->socket, $responsePacket, strlen($responsePacket), 0);
    }


    /**
     * Process GPS packet
     * Inserts stripped GPS packet into database
     *
     * @param array $hexArray
     */
    private function handleGPS($hexArray)
    {
        //Strip package for info
        $datetime = "20" . hexdec($hexArray[4]) . "-" . str_pad(hexdec($hexArray[5]), 2, '0',
                STR_PAD_LEFT) . "-" . str_pad(hexdec($hexArray[6]), 2, '0',
                STR_PAD_LEFT) . " " . str_pad(hexdec($hexArray[7]), 2, '0',
                STR_PAD_LEFT) . ":" . str_pad(hexdec($hexArray[8]), 2, '0',
                STR_PAD_LEFT) . ":" . str_pad(hexdec($hexArray[9]), 2, '0', STR_PAD_LEFT);
        $satellites = hexdec(substr($hexArray[10], 0, 1));
        $lat = hexdec($hexArray[11] . $hexArray[12] . $hexArray[13] . $hexArray[14]);
        $lng = hexdec($hexArray[15] . $hexArray[16] . $hexArray[17] . $hexArray[18]);
        $lati = ($lat / 30000) / 60;
        $lngi = ($lng / 30000) / 60;
        $speed = hexdec($hexArray[19]);
        $courseInfo = decbin(hexdec($hexArray[20] . $hexArray[21]));
        while (strlen($courseInfo) < 16) {
            $courseInfo = "0" . $courseInfo;
        }
        $course = bindec(substr($courseInfo, 6, 10));
        $realtime = bindec(substr($courseInfo, 2, 1));
        $positioned = bindec(substr($courseInfo, 3, 1));


        $MMC = hexdec($hexArray[22] . $hexArray[23]);
        $MNC = hexdec($hexArray[24]);
        $LAC = hexdec($hexArray[25] . $hexArray[26]);
        $cell = hexdec($hexArray[27] . $hexArray[28] . $hexArray[29]);


        //Insert info into database
        $sql = "INSERT INTO gps (terminal, time, satellites, lat, lng, speed, heading, realtime, positioned, MMC, MNC, LAC, cell) VALUES ('$this->terminalId', '$datetime', '$satellites', '$lati', '$lngi', '$speed', '$course', '$realtime', '$positioned', '$MMC', '$MNC', '$LAC', '$cell')";
        $this->databaseController->insertQuery($sql);
    }


    /**
     * Process heartbeat packet
     * Responds with message in the TCP socket
     * Creates trips and updates tracker data in database.
     *
     * @param array $hexArray
     */
    private function handleHeartbeat($hexArray)
    {
        //Get new information from the terminal
        $terminalInformation = decbin(hexdec($hexArray[4]));
        while (strlen($terminalInformation) < 8) {
            $terminalInformation = '0' . $terminalInformation;
        }

        $tracking = substr($terminalInformation, 1, 1);
        $charging = substr($terminalInformation, 5, 1);
        $acc = substr($terminalInformation, 6, 1);
        $defense = substr($terminalInformation, 7, 1);
        $voltage = hexdec($hexArray[5]);
        $gsm = hexdec($hexArray[6]);

        //Update GPS info
        $sql = "UPDATE terminals SET tracking='$tracking', charging='$charging', acc='$acc', defense='$defense', voltage='$voltage', gsm='$gsm' WHERE id=$this->terminalId";
        $this->databaseController->runQuery($sql);

        //Create new trip if needed
        $sql = "SELECT * FROM trips WHERE terminal=$this->terminalId  ORDER BY id DESC LIMIT 1";
        $result = $this->databaseController->runQuery($sql);

        //Check if there's a trip in progress
        $ritBezig = true;
        $row_trip = null;
        if ($result->num_rows == 0) {
            $ritBezig = false;
        } else {
            $row_trip = $result->fetch_assoc();
            if ($row_trip['end'] != "0") {
                $ritBezig = false;
            }
        }

        //Stop the trip in progress
        if ($ritBezig && $acc == "0") {
            //Get last GPS coordinate
            $sql = "SELECT * FROM gps WHERE terminal=$this->terminalId  ORDER BY id DESC LIMIT 1";
            $result = $this->databaseController->runQuery($sql);
            $row = $result->fetch_assoc();

            $id = $row_trip['id'];
            $end = $row['id'];
            $sql = "UPDATE trips SET end='$end' WHERE id=$id";
            $this->databaseController->runQuery($sql);
        }

        //Start new trip
        if (!$ritBezig && $acc == "1") {
            //Get last GPS coordinate
            $sql = "SELECT * FROM gps WHERE terminal=$this->terminalId  ORDER BY id DESC LIMIT 1";
            $result = $this->databaseController->runQuery($sql);
            $row = $result->fetch_assoc();

            $id = $row['id'];
            $sql = "INSERT INTO trips (terminal, begin, end) VALUES ('$this->terminalId', '$id', '0')";
            $this->databaseController->insertQuery($sql);
        }

        //Create packet to send back
        $serial = strtoupper($hexArray[9]) . " " . strtoupper($hexArray[10]);
        $send_cmd = "78 78 05 13 " . $serial . " " . CRCHelper::getCRC("05 13 " . $serial) . " 0D 0A";

        //Send back to tracker
        $sendCommands = explode(" ", $send_cmd);
        $send_cmd = '';
        for ($i = 0; $i < count($sendCommands); $i++) {
            $send_cmd .= chr(hexdec(trim($sendCommands[$i])));
        }

        socket_send($this->socket, $send_cmd, strlen($send_cmd), 0);
    }
}