<?php

namespace App\Model;

class ScraperSummary
{
    private $dateRange;
    private $algStart;
    private $meetingsTimeEnd;
    private $racesTimeEnd;
    private $horsesTimeEnd;
    private $horsesRecordsTimeEnd;
    private $resultsTimeEnd;
    private $historicResultsTimeEnd;
    private $distanceUpdateTimeEnd;
    private $handicapRecalculationsStartEnd;

    /**
     * ScraperSummary constructor.
     */
    public function __construct(
        $dateRange,
        $algStart,
        $meetingsTimeEnd,
        $racesTimeEnd,
        $horsesTimeEnd,
        $horsesRecordsTimeEnd,
        $resultsTimeEnd,
        $historicResultsTimeEnd,
        $distanceUpdateTimeEnd,
        $handicapRecalculationsStartEnd
    ){
        $this->dateRange = $dateRange;
        $this->algStart = $algStart;
        $this->meetingsTimeEnd = $meetingsTimeEnd;
        $this->racesTimeEnd = $racesTimeEnd;
        $this->horsesTimeEnd = $horsesTimeEnd;
        $this->horsesRecordsTimeEnd = $horsesRecordsTimeEnd;
        $this->resultsTimeEnd = $resultsTimeEnd;
        $this->historicResultsTimeEnd = $historicResultsTimeEnd;
        $this->distanceUpdateTimeEnd = $distanceUpdateTimeEnd;
        $this->handicapRecalculationsStartEnd = $handicapRecalculationsStartEnd;
    }

    /**
     * @return mixed
     */
    public function getDateRange()
    {
        return $this->dateRange;
    }

    /**
     * @return mixed
     */
    public function getAlgStart()
    {
        return $this->algStart;
    }

    /**
     * @return mixed
     */
    public function getMeetingsTimeEnd()
    {
        return $this->meetingsTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getRacesTimeEnd()
    {
        return $this->racesTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getHorsesTimeEnd()
    {
        return $this->horsesTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getHorsesRecordsTimeEnd()
    {
        return $this->horsesRecordsTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getResultsTimeEnd()
    {
        return $this->resultsTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getHistoricResultsTimeEnd()
    {
        return $this->historicResultsTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getDistanceUpdateTimeEnd()
    {
        return $this->distanceUpdateTimeEnd;
    }

    /**
     * @return mixed
     */
    public function getHandicapRecalculationsStartEnd()
    {
        return $this->handicapRecalculationsStartEnd;
    }
}