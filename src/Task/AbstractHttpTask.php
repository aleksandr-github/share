<?php

namespace App\Task;

use App\Service\LocalContentCacheService;

abstract class AbstractHttpTask extends AbstractLoggerAwareTask
{
    protected $base_url = "https://www.racingzone.com.au";
    protected $cacheService;
    protected $httpClientTimeoutSeconds = 60;

    public function __construct()
    {
        parent::__construct();

        $this->cacheService = new LocalContentCacheService();
    }
}