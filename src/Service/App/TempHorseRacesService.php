<?php

namespace App\Service\App;

use App\Service\DBConnector;

class TempHorseRacesService
{
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->dbConnector->getTempHorseRaces();
    }

    public function getWithPartialWhere(string $partialWhere): array
    {
        return $this->dbConnector->getTempHorseRacesWithWhereStatement($partialWhere);
    }
}