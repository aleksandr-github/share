<?php

namespace App\Exception;

use Throwable;

class ModelNotFoundException extends \Exception
{
    public function __construct($message = "Model not found", $code = 802, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}