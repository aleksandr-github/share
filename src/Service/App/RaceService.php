<?php

namespace App\Service\App;

use App\Model\App\Race;
use App\Service\DBConnector;

class RaceService
{
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    public function getCount(): int
    {
        return $this->dbConnector->getRacesCount();
    }

    /**
     * @return Race[]
     */
    public function getAll(): array
    {
        return $this->dbConnector->getRaces();
    }

    /**
     * @return Race[]|object
     */
    public function getRacesWithPartialWhere(string $partialSQL): array
    {
        return $this->dbConnector->getRacesWithWhereStatement($partialSQL);
    }

    public function getWithEmptyRankStatus(): array
    {
        return $this->dbConnector->getRacesWithWhereStatement("WHERE `rank_status`='0'", false);
    }

    public function getWithoutSectionalAVG(): array
    {
        return $this->dbConnector->getRacesWithWhereStatement("WHERE `sec_status`='0' OR `sec_status`='' ORDER by `race_id`", false);
    }
}