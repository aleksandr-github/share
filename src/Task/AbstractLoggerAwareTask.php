<?php

namespace App\Task;

use App\Service\PrettyLogger;

abstract class AbstractLoggerAwareTask
{
    protected $logger;

    public function __construct()
    {
        $this->logger = new PrettyLogger(__FILE__, 'main_log.txt');
    }
}