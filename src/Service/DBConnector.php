<?php

namespace App\Service;

use App\Model\App\HistoricResult;
use App\Model\App\Horse;
use App\Model\App\Meeting;
use App\Model\App\Race;
use App\Model\DBCredentials;
use mysqli;

class DBConnector
{
    /** @var mysqli */
    private $dbConnection;

    public function __construct()
    {
        $credentials = $this->initDBCredentials();

        if (!$this->dbConnection) {
            $this->dbConnection = new mysqli(
                $credentials->getServername(),
                $credentials->getUsername(),
                $credentials->getPassword(),
                $credentials->getDatabase()
            );
        }
    }

    /**
     * @return DBCredentials
     */
    public function initDBCredentials(): DBCredentials
    {
        $dbCredentials = new DBCredentials();
        $dbCredentials->setServername($_ENV['dbservername']);
        $dbCredentials->setDatabase($_ENV['dbdatabase']);
        $dbCredentials->setUsername($_ENV['dbusername']);
        $dbCredentials->setPassword($_ENV['dbpassword']);

        return $dbCredentials;
    }

    /**
     * @return mysqli
     */
    public function getDbConnection(): mysqli
    {
        return $this->dbConnection;
    }

    public function getHorses(): array
    {
        $horses = [];
        $horsesQuery = $this->dbConnection->query("SELECT * FROM `tbl_horses` ORDER BY horse_id");
        while ($horse = $horsesQuery->fetch_object()) {
            $horses[] = new Horse($horse);
        }

        return $horses;
    }

    public function getMeetings(): array
    {
        $meetings = [];
        $meetingsQuery = $this->dbConnection->query("SELECT * FROM `tbl_meetings`");
        while ($meeting = $meetingsQuery->fetch_object()) {
            $meetings[] = new Meeting($meeting);
        }

        return $meetings;
    }

    /**
     * @param int $meetingId
     * @return \App\Model\App\Meeting
     */
    public function getMeetingDetails(int $meetingId): Meeting
    {
        $meetingQuery = $this->dbConnection->query("SELECT * FROM `tbl_meetings` WHERE `meeting_id`='$meetingId'");
        $meeting = $meetingQuery->fetch_object();

        return new Meeting($meeting);
    }

    public function getRaces(): array
    {
        $races = [];
        $racesQuery = $this->dbConnection->query("SELECT * FROM `tbl_races`");
        while ($race = $racesQuery->fetch_object()) {
            $races[] = new Race($race);
        }

        return $races;
    }

    public function getRacesWithWhereStatement(string $whereQuery, bool $mapToModel = true): array
    {
        $races = [];
        $query = "SELECT * FROM `tbl_races` " . $whereQuery;
        $racesQuery = $this->dbConnection->query($query);
        while ($race = $racesQuery->fetch_object()) {
            if ($mapToModel) {
                $races[] = new Race($race);
            } else {
                $races[] = $race;
            }
        }

        return $races;
    }

    public function getTempHorseRacesWithWhereStatement(string $whereQuery): array
    {
        $tempHorseRaces = [];
        $query = "SELECT * FROM `tbl_temp_hraces` " . $whereQuery;
        $tempHorseRacesQuery = $this->dbConnection->query($query);
        while ($tempHorseRace = $tempHorseRacesQuery->fetch_object()) {
            $tempHorseRaces[] = $tempHorseRace;
        }

        return $tempHorseRaces;
    }

    public function getMeetingsWithWhereStatement(string $whereQuery, bool $mapToModel = true): array
    {
        $meetings = [];
        $query = "SELECT * FROM `tbl_meetings` " . $whereQuery;
        $meetingsQuery = $this->dbConnection->query($query);
        while ($meeting = $meetingsQuery->fetch_object()) {
            if ($mapToModel) {
                $meetings[] = new Meeting($meeting);
            } else {
                $meetings[] = $meeting;
            }
        }

        return $meetings;
    }

    /**
     * @param int $raceId
     * @return Race
     */
    public function getRaceDetails(int $raceId): Race
    {
        $raceQuery = $this->dbConnection->query("SELECT * FROM `tbl_races` WHERE `race_id`='$raceId'");
        $race = $raceQuery->fetch_object();

        return new Race($race);
    }

    /**
     * @param int $horseId
     * @return \App\Model\App\Horse
     */
    public function getHorseDetails(int $horseId): Horse
    {
        $horseQuery = $this->dbConnection->query("SELECT * FROM `tbl_horses` WHERE `horse_id`='$horseId'");
        $horse = $horseQuery->fetch_object();

        return new Horse($horse);
    }

    public function getHistoricResult(int $histId): HistoricResult
    {
        $histResults = $this->dbConnection->query("SELECT * FROM `tbl_hist_results` WHERE `hist_id`='$histId'");
        $historicResult = $histResults->fetch_object();

        return new HistoricResult($historicResult);
    }

    public function getHistoricResults(): array
    {
        $histResults = [];
        $histResultsQuery = $this->dbConnection->query("SELECT * FROM `tbl_hist_results`");
        while ($histResult = $histResultsQuery->fetch_object()) {
            $histResults[] = new HistoricResult($histResult);
        }

        return $histResults;
    }

    // todo model
    public function getTempHorseRaces(): array
    {
        $tempHorseRaces = [];
        $tempHorseRacesQuery = $this->dbConnection->query("SELECT * FROM `tbl_temp_hraces`");
        while ($tempHorseRace = $tempHorseRacesQuery->fetch_object()) {
            $tempHorseRaces[] = $tempHorseRace;
        }

        return $tempHorseRaces;
    }

    public function getHistoricResultsWithWhereStatement(string $whereQuery, bool $mapToModel = true): array
    {
        $histResults = [];
        $query = "SELECT * FROM `tbl_hist_results` " . $whereQuery;
        $histResultsQuery = $this->dbConnection->query($query);
        while ($race = $histResultsQuery->fetch_object()) {
            if ($mapToModel) {
                $histResults[] = new HistoricResult($race);
            } else {
                $histResults[] = $race;
            }
        }

        return $histResults;
    }

    public function getDistinctRacesIdsWithEmptyRating(): array
    {
        $raceIds = [];
        $query = "SELECT DISTINCT(race_id) FROM `tbl_hist_results` WHERE rating='0'";
        $histResultsQuery = $this->dbConnection->query($query);
        while ($histResultDistRaceId = $histResultsQuery->fetch_object()) {
            $raceIds[] = $histResultDistRaceId->race_id;
        }

        return $raceIds;
    }

    public function getHorseIdSlugNameMappingsArray(): array
    {
        $results = [];
        $query = "SELECT horse_id, horse_slug FROM tbl_horses";
        $res = $this->dbConnection->query($query);
        while ($result = $res->fetch_object()) {
            $results[$result->horse_slug] = $result->horse_id;
        }

        return $results;
    }

    public function getMeetingsForIDs($meetingsIds): array
    {
        $strMeetings = implode(",", $meetingsIds);
        $sql = "SELECT * FROM tbl_meetings WHERE meeting_id IN (".$strMeetings.")";
        $stmt = $this->dbConnection->query($sql);

        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    public function getRacesForIDs($racesIds): array
    {
        $strRaces = implode(",", $racesIds);
        $sql = "SELECT * FROM tbl_races WHERE race_id IN (".$strRaces.")";
        $stmt = $this->dbConnection->query($sql);

        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    public function getHorsesForIDs($horsesIDs): array
    {
        $strHorses = implode(",", $horsesIDs);
        $sql = "SELECT * FROM tbl_horses WHERE horse_id IN (".$strHorses.")";
        $stmt = $this->dbConnection->query($sql);

        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    public function getResultsForHistoricIDs($historicRecordsIDs)
    {
        $strResults = implode(",", $historicRecordsIDs);
        $sql = "SELECT * FROM tbl_hist_results WHERE tbl_hist_results.hist_id IN (".$strResults.")";
        $stmt = $this->dbConnection->query($sql);

        return $stmt->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * @param string $implode
     * @return array
     */
    public function multiQueryInsertIDs(string $implode): array
    {
        $this->dbConnection->multi_query($implode);

        $ids = array();
        do
        {
            $ids[] = $this->dbConnection->insert_id;
            $this->dbConnection->next_result();
        } while($this->dbConnection->more_results());

        return $ids;
    }

    public function getHorsesCount(): int
    {
        $query = $this->dbConnection->query("SELECT COUNT(horse_id) AS countHorses FROM `tbl_horses`");

        return $query->fetch_object()->countHorses;
    }

    public function getMeetingsCount(): int
    {
        $query = $this->dbConnection->query("SELECT COUNT(meeting_id) AS countMeetings FROM `tbl_meetings`");

        return $query->fetch_object()->countMeetings;
    }

    public function getRacesCount(): int
    {
        $query = $this->dbConnection->query("SELECT COUNT(race_id) AS countRaces FROM `tbl_races`");

        return $query->fetch_object()->countRaces;
    }

    public function getHistoricResultsCount(): int
    {
        $query = $this->dbConnection->query("SELECT COUNT(hist_id) AS countHistoricResults FROM `tbl_hist_results`");

        return $query->fetch_object()->countHistoricResults;
    }

    public function getResultsCount(): int
    {
        $query = $this->dbConnection->query("SELECT COUNT(result_id) AS countResults FROM `tbl_results`");

        return $query->fetch_object()->countResults;
    }
}