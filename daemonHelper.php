<?php

/**
 * Fork process and return PID
 *
 * @return int
 */
function becomeDaemon()
{
    $pid = pcntl_fork();
    if ($pid == -1) {
        exit();
    } elseif ($pid) {
        exit();
    } else {
        posix_setsid();
        chdir('/');
        umask(0);

        return posix_getpid();
    }
}


/**
 * Set signal Handlers
 */
function setSigHandlers() {
    pcntl_signal(SIGTERM, 'sigHandler');
    pcntl_signal(SIGINT, 'sigHandler');
    pcntl_signal(SIGCHLD,  'sigHandler');
}

/**
 * Basic signal handler
 *
 * @param $sig
 */
function sigHandler($sig)
{
    switch ($sig) {
        case SIGTERM:
        case SIGINT:
            break;
        case SIGCHLD:
            pcntl_waitpid(-1, $status);
            break;
    }
}