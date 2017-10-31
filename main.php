<?php
use Classes\Server;

include "daemonHelper.php";

/**
 * Setup PHP vars
 */
error_reporting(E_ALL);
set_time_limit(0);
ob_implicit_flush();
declare(ticks=1);


/**
 * Define constants
 */
define('LOGIN', '01');
define('GPS', '12');
define('HEARTBEAT', '13');


/**
 * Setup autoloader
 */
function autoload($class_name)
{
    $class_name = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    include $class_name . '.php';
}

spl_autoload_extensions(".php");
spl_autoload_register('autoload');


/**
 * Start Daemon & Setup Sig Handler
 */
becomeDaemon();
setSigHandlers();


//Start Server
$server = new Server("0.0.0.0", 55334);
$server->startServer();