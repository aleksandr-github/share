<?php

namespace App\Service\App;

use App\Service\DBConnector;

class HorseService
{
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    public function getCount(): int
    {
        return $this->dbConnector->getHorsesCount();
    }

    /**
     * @return \App\Model\App\Horse[]
     */
    public function getAll(): array
    {
        return $this->dbConnector->getHorses();
    }
}