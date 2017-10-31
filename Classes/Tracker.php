<?php
namespace Classes;

interface Tracker
{
    public function __construct($socket, $databaseController);
    public function getName();
    public static function isTracker($hexArray);
    public function process($hexArray);
}