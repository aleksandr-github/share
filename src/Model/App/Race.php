<?php

namespace App\Model\App;

use JsonSerializable;

/**
 * Class Race
 * @package App\Model\App
 *
 * Represent data from DB, table `tbl_races`
 * TODO strict types
 */
class Race implements JsonSerializable
{
    protected $raceId;
    protected $oldRaceId;
    protected $meetingId;
    protected $raceOrder;
    protected $raceScheduleTime;
    protected $raceTitle;
    protected $raceSlug;
    protected $raceDistance;
    protected $roundDistance;
    protected $raceUrl;
    protected $rankStatus;
    protected $secStatus;

    public function __construct($raceData)
    {
        $this->raceId = $raceData->race_id;
        $this->oldRaceId = $raceData->old_race_id;
        $this->meetingId = $raceData->meeting_id;
        $this->raceOrder = $raceData->race_order;
        $this->raceScheduleTime = $raceData->race_schedule_time;
        $this->raceTitle = $raceData->race_title;
        $this->raceSlug = $raceData->race_slug;
        $this->raceDistance = $raceData->race_distance;
        $this->roundDistance = $raceData->round_distance;
        $this->raceUrl = $raceData->race_url;
        $this->rankStatus = $raceData->rank_status;
        $this->secStatus = $raceData->sec_status;
    }

    /**
     * @return mixed
     */
    public function getRaceId()
    {
        return $this->raceId;
    }

    /**
     * @return mixed
     */
    public function getOldRaceId()
    {
        return $this->oldRaceId;
    }

    /**
     * @param mixed $oldRaceId
     * @return Race
     */
    public function setOldRaceId($oldRaceId)
    {
        $this->oldRaceId = $oldRaceId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMeetingId()
    {
        return $this->meetingId;
    }

    /**
     * @param mixed $meetingId
     * @return Race
     */
    public function setMeetingId($meetingId)
    {
        $this->meetingId = $meetingId;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceOrder()
    {
        return $this->raceOrder;
    }

    /**
     * @param mixed $raceOrder
     * @return Race
     */
    public function setRaceOrder($raceOrder)
    {
        $this->raceOrder = $raceOrder;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceScheduleTime()
    {
        return $this->raceScheduleTime;
    }

    /**
     * @param mixed $raceScheduleTime
     * @return Race
     */
    public function setRaceScheduleTime($raceScheduleTime)
    {
        $this->raceScheduleTime = $raceScheduleTime;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceTitle()
    {
        return $this->raceTitle;
    }

    /**
     * @param mixed $raceTitle
     * @return Race
     */
    public function setRaceTitle($raceTitle)
    {
        $this->raceTitle = $raceTitle;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceSlug()
    {
        return $this->raceSlug;
    }

    /**
     * @param mixed $raceSlug
     * @return Race
     */
    public function setRaceSlug($raceSlug)
    {
        $this->raceSlug = $raceSlug;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceDistance()
    {
        return $this->raceDistance;
    }

    /**
     * @param mixed $raceDistance
     * @return Race
     */
    public function setRaceDistance($raceDistance)
    {
        $this->raceDistance = $raceDistance;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRoundDistance()
    {
        return $this->roundDistance;
    }

    /**
     * @param mixed $roundDistance
     * @return Race
     */
    public function setRoundDistance($roundDistance)
    {
        $this->roundDistance = $roundDistance;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRaceUrl()
    {
        return $this->raceUrl;
    }

    /**
     * @param mixed $raceUrl
     * @return Race
     */
    public function setRaceUrl($raceUrl)
    {
        $this->raceUrl = $raceUrl;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getRankStatus()
    {
        return $this->rankStatus;
    }

    /**
     * @param mixed $rankStatus
     * @return Race
     */
    public function setRankStatus($rankStatus)
    {
        $this->rankStatus = $rankStatus;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getSecStatus()
    {
        return $this->secStatus;
    }

    /**
     * @param mixed $secStatus
     * @return Race
     */
    public function setSecStatus($secStatus)
    {
        $this->secStatus = $secStatus;
        return $this;
    }

    public function jsonSerialize(): array
    {
        $jsonSerializedArray = [];
        $reflection = new \ReflectionClass($this);
        foreach ($reflection->getProperties() as $property) {
            $jsonSerializedArray[$property->name] = $this->{$property->name};
        }

        return $jsonSerializedArray;
    }
}