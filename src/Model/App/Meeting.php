<?php

namespace App\Model\App;

use JsonSerializable;

/**
 * Class Meeting
 * @package App\Model\App
 *
 * Represent data from DB, table `tbl_meetings`
 * TODO strict types
 */
class Meeting implements JsonSerializable
{
    protected $meetingId;
    protected $meetingDate;
    protected $meetingName;
    protected $meetingUrl;
    protected $addedOn;

    public function __construct($meetingData)
    {
        $this->meetingId = $meetingData->meeting_id;
        $this->meetingDate = $meetingData->meeting_date;
        $this->meetingName = $meetingData->meeting_name;
        $this->meetingUrl = $meetingData->meeting_url;
        $this->addedOn = $meetingData->added_on;
    }

    /**
     * @return mixed
     */
    public function getMeetingId()
    {
        return $this->meetingId;
    }

    /**
     * @return mixed
     */
    public function getMeetingDate()
    {
        return $this->meetingDate;
    }

    /**
     * @param mixed $meetingDate
     * @return Meeting
     */
    public function setMeetingDate($meetingDate)
    {
        $this->meetingDate = $meetingDate;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMeetingName()
    {
        return $this->meetingName;
    }

    /**
     * @param mixed $meetingName
     * @return Meeting
     */
    public function setMeetingName($meetingName)
    {
        $this->meetingName = $meetingName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMeetingUrl()
    {
        return $this->meetingUrl;
    }

    /**
     * @param mixed $meetingUrl
     * @return Meeting
     */
    public function setMeetingUrl($meetingUrl)
    {
        $this->meetingUrl = $meetingUrl;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getAddedOn()
    {
        return $this->addedOn;
    }

    /**
     * @param mixed $addedOn
     * @return Meeting
     */
    public function setAddedOn($addedOn)
    {
        $this->addedOn = $addedOn;
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