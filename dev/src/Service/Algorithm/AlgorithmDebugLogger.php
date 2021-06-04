<?php

namespace App\Service\Algorithm;

use App\Model\DateRange;
use App\Model\ScraperSummary;
use DateTime;
use Exception;

/**
 * Class PrettyLogger
 *
 * Simple class which is implements basic logging functions.
 * Mostly you will use log() and setLevel() methods.
 *
 * @example
 * $logger = new logger('', 'ERROR');
 *
 * $logger->log('DB query error', 'error');
 * $logger->log($mysqli->error, 'debug'); // will not be logging
 *
 * $logger->setLevel('debug');
 * $logger->log($mysqli->error, 'debug'); // will be logging
 * $logger->setLevel(); // reset the level
 *
 * @author idzhalalov@gmail.com
 */

class AlgorithmDebugLogger
{
    protected $logLevel;
    protected $filePath;
    protected $emitter;

    /**
     * Create a logger object
     *
     * @param $emitter
     * @param string $fileName - a name of log file
     * @param string $level
     * @throws Exception
     */
    public function __construct()
    {
        $this->filePath = APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'debug_algorithm.log';
        $this->emitter = "ALGORITHM_DEBUG";
        $this->setLevel('DEBUG');
    }

    /**
     * @return string
     */
    protected function logPath()
    {
        return $this->filePath;
    }

    /**
     * @return string
     */
    protected function defaultLevel()
    {
        return (defined('LOG_LEVEL')) ? LOG_LEVEL : 'error';
    }

    /**
     * Add log message
     *
     * @param        $message
     * @param string $level - default: "INFO"
     * @param bool   $backtrace - add backtrace (default: false)
     *
     * @return bool|int
     */
    public function log($message, $level="info", $emitterOverride = null, $backtrace = false)
    {
        if ($level == null) {
            $level = 'info';
        }

        if ($emitterOverride) {
            $this->emitter = $emitterOverride;
        }

        if ( ! $this->loggerFilter($level)) {
            return false;
        }

        $dt = date('m/d/Y H:i:s', time()) . substr((string)microtime(), 1, 4);
        $message = '[' . str_replace(".PHP", "", strtoupper($this->emitter)) . '][' . strtoupper($level) . '][' . $dt . '] ' . (string) $message;

        if ($backtrace) {
            $message .= PHP_EOL . print_r(debug_backtrace(), true);
        }
        $message .= PHP_EOL;

        return file_put_contents($this->logPath(), $message, FILE_APPEND | LOCK_EX);
    }

    /**
     * Thank's to this function we can track
     * how multiple tasks inside runners are doing
     * their job.
     *
     * Used in Tasks for algorithm debugging
     *
     * @param array $vars
     */
    public function varLog(array $vars)
    {
        $message = "";
        foreach ($vars as $field => $value) {
            $message .= $field."=".$value.';';
        }

        $this->log($message);
    }

    /**
     * Inserts new line in logs
     */
    public function newLine(string $message = null)
    {
        if ($message) {
            $this->log("°º¤ø,¸¸,ø¤º°`°º¤ø,¸,ø¤°{{ " . $message . " }}°`°º¤ø,¸,ø¤°º¤ø,¸¸,ø¤º°`°º¤ø,¸");
        } else {
            $this->log("°º¤ø,¸¸,ø¤º°`°º¤ø,¸,ø¤°º¤ø,¸¸,ø¤º°`°º¤ø,¸¸,ø¤º°`°º¤ø,¸,ø¤°º¤ø,¸¸,ø¤º°`°º¤ø,¸");
        }
    }

    /**
     * Log filter.
     *
     * Check whether a level could be logged or not
     *
     * @param $loggerLevel
     *
     * @return bool
     */
    public function loggerFilter($loggerLevel)
    {
        $logLevels = $this->logLevels();
        $loggerLevel = strtoupper($loggerLevel);

        $defaultLevel = $this->level();
        $defaultLevel = strtoupper($defaultLevel);
        $defaultLevelNum = $logLevels[$defaultLevel];

        if (isset($logLevels[$loggerLevel])) {
            $loggerLevelNum = $logLevels[$loggerLevel];
        } else {
            $loggerLevelNum = $logLevels[$defaultLevel];
        }

        return (bool) ($defaultLevelNum <= $loggerLevelNum);
    }

    /**
     * Available log levels
     *
     * @return array
     */
    public function logLevels()
    {
        return [
            'DEBUG' => 0,
            'INFO' => 1,
            'WARN' => 2,
            'ERROR' => 3
        ];
    }

    /**
     * Set log level for a current logger instance
     *
     * @param string $level
     */
    public function setLevel($level = null)
    {
        if ($level == null) {
            $level = $this->defaultLevel();
        }

        $level = strtoupper($level);
        $logLevels = $this->logLevels();
        if (isset($logLevels[$level])) {
            $this->logLevel = $level;
        }
    }

    /**
     * Current log level
     *
     * @return string
     */
    public function level()
    {
        if ($this->logLevel !== null) {
            $result = $this->logLevel;
        } else {
            $result = $this->defaultLevel();
        }

        return $result;
    }
}