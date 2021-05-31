<?php

namespace App\Service\App;

use App\Model\App\Meeting;
use App\Service\DBConnector;

class MeetingService
{
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    public function getCount(): int
    {
        return $this->dbConnector->getMeetingsCount();
    }

    /**
     * @return Meeting[]
     */
    public function getAll(): array
    {
        return $this->dbConnector->getMeetings();
    }
}