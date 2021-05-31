<?php

namespace App\Service\App;

use App\Model\App\HistoricResult;
use App\Service\DBConnector;

class HistoricResultService
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
     * @return HistoricResult[]
     */
    public function getAll(): array
    {
        return $this->dbConnector->getHistoricResults();
    }

    /**
     * @return HistoricResult[]|object
     */
    public function getHistoricResultsWithPartialWhere(string $partialSQL): array
    {
        return $this->dbConnector->getHistoricResultsWithWhereStatement($partialSQL);
    }

    /**
     * @return HistoricResult[]|object
     */
    public function getWithEmptyHandicap(): array
    {
        return $this->dbConnector->getHistoricResultsWithWhereStatement("WHERE `handicap`='0.00'", false);
    }
}