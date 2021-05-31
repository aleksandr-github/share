<?php

namespace App\Task;

use mysqli;
use Symfony\Component\Dotenv\Dotenv;

abstract class AbstractMySQLTask extends AbstractLoggerAwareTask
{
    /**
     * @return mysqli
     */
    protected function initMultiSessionDatabase(): mysqli
    {
        (new Dotenv())->bootEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env');

        $mysqli = new mysqli(
            $_ENV["dbservername"],
            $_ENV["dbusername"],
            $_ENV["dbpassword"],
            $_ENV["dbdatabase"]
        );
        $mysqli->ping();

        return $mysqli;
    }
}