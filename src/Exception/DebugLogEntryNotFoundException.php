<?php

namespace App\Exception;

use Throwable;

class DebugLogEntryNotFoundException extends \Exception
{
    public function __construct($message = "Debug log not found for given entry ID. Try '0' as entry ID instead.", $code = 802, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}