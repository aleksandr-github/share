<?php

namespace App\Service\App;

use App\Model\App\Result\HorseResultDataModel;
use App\Service\DBConnector;

class ResultService
{
    protected $dbConnector;

    public function __construct(DBConnector $dbConnector)
    {
        $this->dbConnector = $dbConnector;
    }

    /**
     * @return HorseResultDataModel[]
     */
    public function getHorseResultDataModels(): array
    {
        $results = [];
        $sql = "select race_id, horse_id, position from `tbl_results`";
        $resultQuery = $this->dbConnector->getDbConnection()->query($sql);
        while ($result = $resultQuery->fetch_object()) {
            $results[] = new HorseResultDataModel($result->race_id, $result->horse_id, $result->position);
        }

        return $results;
    }
}