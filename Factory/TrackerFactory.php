<?php

namespace Factory;


use Classes\Tracker;
use Classes\Trackers\GT06N;
use Controller\DatabaseController;
use ReflectionClass;

class TrackerFactory
{

    /**
     * Try to create a new tracker class for a given hex array (packet)
     *
     * @param array              $hexArray
     * @param socket             $socket
     * @param DatabaseController $databaseController
     *
     * @return null|Tracker
     */
    public static function create($hexArray, $socket, $databaseController)
    {
        //Array of all available trackers
        $trackerArray = array(GT06N::class);

        foreach ($trackerArray as $tracker) {
            $class = new ReflectionClass($tracker);
            if ($class->implementsInterface(Tracker::class)) {
                //Check if packet is meant for this tracker
                if ($tracker::isTracker($hexArray)) {
                    //Return new instance of tracker class
                    return new $tracker($socket, $databaseController);
                }
            }
        }

        return null;
    }
}