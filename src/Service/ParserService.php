<?php

namespace App\Service;

// DOM Service
use App\Helper\RaceDistanceArrayHelper;
use App\Helper\RacesOldIdMappingsHelper;
use App\Model\DateRange;
use App\Model\ScraperSummary;

define('HDOM_TYPE_ELEMENT', 1);
define('HDOM_TYPE_COMMENT', 2);
define('HDOM_TYPE_TEXT', 3);
define('HDOM_TYPE_ENDTAG', 4);
define('HDOM_TYPE_ROOT', 5);
define('HDOM_TYPE_UNKNOWN', 6);
define('HDOM_QUOTE_DOUBLE', 0);
define('HDOM_QUOTE_SINGLE', 1);
define('HDOM_QUOTE_NO', 3);
define('HDOM_INFO_BEGIN', 0);
define('HDOM_INFO_END', 1);
define('HDOM_INFO_QUOTE', 2);
define('HDOM_INFO_SPACE', 3);
define('HDOM_INFO_TEXT', 4);
define('HDOM_INFO_INNER', 5);
define('HDOM_INFO_OUTER', 6);
define('HDOM_INFO_ENDSPACE', 7);

defined('DEFAULT_TARGET_CHARSET') || define('DEFAULT_TARGET_CHARSET', 'UTF-8');
defined('DEFAULT_BR_TEXT') || define('DEFAULT_BR_TEXT', "\r\n");
defined('DEFAULT_SPAN_TEXT') || define('DEFAULT_SPAN_TEXT', ' ');
defined('MAX_FILE_SIZE') || define('MAX_FILE_SIZE', 600000);
define('HDOM_SMARTY_AS_TEXT', 1);

class ParserService
{
    /**
     * @var PrettyLogger
     */
    private $logger;

    /**
     * @var DBConnector
     */
    private $DBConnector;

    /**
     * @var RacingZoneScraper
     */
    private $scraper;

    /**
     * @var TasksService
     */
    protected $tasksService;

    public function __construct(TasksService $tasksService)
    {
        $this->logger = new PrettyLogger(__FILE__,"main_log.txt");
        $this->DBConnector = new DBConnector();
        $this->scraper = new RacingZoneScraper($this->DBConnector->getDbConnection());
        $this->tasksService = $tasksService;
    }

    /**
     * @param \App\Model\DateRange $dateRange
     * @return \App\Model\ScraperSummary
     * @throws \Throwable
     */
    public function startParser(DateRange $dateRange, float $positionPercentage, float $timerHandicapMultiplier, float $handicapModifier): ScraperSummary
    {
        $algStart = microtime(true);
        // First we're creating DateRange object
        // We're obtaining all meetings happening in selected dates
        $meetingsForDateRange = $this->scraper->getMeetingsForDateRange($dateRange);
        // At this point we've got ArrayCollection of meetings happening in
        // selected date range along with URL's for scraping ( $meetingsForDateRange )

        // at this point we should save all meetings to the database
        // we can use simple statement manager instead of Parser method
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . $meetingsForDateRange->count() . ' workers for meetings parsing ...');
        $meetingsTimeStart = microtime(true);
        $meetingsIds = $this->tasksService->saveMeetings($meetingsForDateRange);
        // This raises new SELECT on DB, not required per se but we need data consistency
        $meetings = $this->DBConnector->getMeetingsForIDs($meetingsIds);
        $meetingsTimeEnd = microtime(true) - $meetingsTimeStart;
        $this->logger->log("¯\_(ツ)_/¯ Meeting workers took " . number_format($meetingsTimeEnd, 2) . ' seconds to complete.');

        // We've got array of meeting ID's added to DB.
        // next step is to query them and parse them by scraper
        // to obtain all the results
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($meetings) . ' workers for races parsing ...');
        $racesTimeStart = microtime(true);
        $races = $this->tasksService->getRacesForMeetings($meetings);
        $racesIds = $this->tasksService->saveRaces($races);
        $races = $this->DBConnector->getRacesForIDs($racesIds);
        $racesOldIdMappingsArray = RacesOldIdMappingsHelper::generate($races);
        $racesDistances = RaceDistanceArrayHelper::generateRaceDistanceArray($races);
        $racesTimeEnd = microtime(true) - $racesTimeStart;
        $this->logger->log("¯\_(ツ)_/¯ Races workers took " . number_format($racesTimeEnd, 2) . ' seconds to complete.');
        // NEXT IS HORSES IN RACE

        // We've got Races array in DB
        // Now we need horses in DB
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($meetings) . ' workers for horses parsing ...');
        $horsesTimeStart = microtime(true);
        $horses = $this->tasksService->getHorsesForRaces($races);
        $this->tasksService->saveHorses($horses);
        $horseIdSlugNameMappingsArray = $this->DBConnector->getHorseIdSlugNameMappingsArray();
        $horsesTimeEnd = microtime(true) - $horsesTimeStart;
        $this->logger->log("¯\_(ツ)_/¯ Horses workers took " . number_format($horsesTimeEnd, 2) . ' seconds to complete.');

        // Records parsing
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($meetings) . ' workers for horses records parsing ...');
        $horsesRecordsTimeStart = microtime(true);
        $records = $this->tasksService->getRecordsForHorsesInRaces($horses, false);
        $horsesRecordsTimeEnd = microtime(true) - $horsesRecordsTimeStart;
        $this->logger->log("¯\_(ツ)_/¯ Horses records workers took " . number_format($horsesRecordsTimeEnd, 2) . ' seconds to complete.');

        // Save records
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($records) . ' workers for horses records saving ...');
        $resultsTimeStart = microtime(true);
        $historicRecordsIDs = $this->tasksService->saveRecords($records, $racesOldIdMappingsArray, $horseIdSlugNameMappingsArray);
        $resultsTimeEnd = microtime(true) - $resultsTimeStart;
        $this->logger->log("¯\_(ツ)_/¯ Horses records saving took " . number_format($resultsTimeEnd, 2) . ' seconds to complete.');
        $this->logger->log('／人 ◕‿‿◕ 人＼ Saved ' . count($historicRecordsIDs) . ' new record entries to DB.');

        // records processing here
        $historicResultsTimeEnd = 0;
        if (count($historicRecordsIDs) > 0) {
            $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($historicRecordsIDs) . ' workers for handicap calculations ...');
            $historicResultsTimeStart = microtime(true);
            $historicRecords = $this->DBConnector->getResultsForHistoricIDs($historicRecordsIDs);
            $this->tasksService->generateHandicapForHistoricResults($historicRecords, $timerHandicapMultiplier, $handicapModifier);
            $historicResultsTimeEnd = microtime(true) - $historicResultsTimeStart;
            $this->logger->log("¯\_(ツ)_/¯ Historic results workers took " . number_format($historicResultsTimeEnd, 2) . ' seconds to complete.');
        }

        // avgsec setup
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($races) . ' workers for sectional recalculations ...');
        $sectionalRecalculationsStartTime = microtime(true);
        $this->tasksService->updateSectional();
        $sectionalRecalculationsEndTime = microtime(true) - $sectionalRecalculationsStartTime;
        $this->logger->log("¯\_(ツ)_/¯ sectional recalculations workers took " . number_format($sectionalRecalculationsEndTime, 2) . ' seconds to complete.');

        // Rank Update
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($races) . ' workers for rank recalculations ...');
        $rankRecalculationsStartTime = microtime(true);
        $this->tasksService->updateRankForRaces($races, $positionPercentage);
        $rankRecalculationsStartEnd = microtime(true) - $rankRecalculationsStartTime;
        $this->logger->log("¯\_(ツ)_/¯ rank recalculations workers took " . number_format($rankRecalculationsStartEnd, 2) . ' seconds to complete.');

        // Sectional AVG Update
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($races) . ' workers for sectional AVG recalculations ...');
        $sectionalAVGRecalculationsStartTime = microtime(true);
        $this->tasksService->updateSectionalAVGForRaces($races, $positionPercentage);
        $sectionalAVGRecalculationsStartEnd = microtime(true) - $sectionalAVGRecalculationsStartTime;
        $this->logger->log("¯\_(ツ)_/¯ sectional AVG recalculations workers took " . number_format($sectionalAVGRecalculationsStartEnd, 2) . ' seconds to complete.');

        // rating
        $this->logger->log('ლ(́◕◞Ѿ◟◕‵ლ) Spawning ' . count($races) . ' workers for rating recalculations ...');
        $ratingRecalculationsStartTime = microtime(true);
        $this->tasksService->updateRatingForRaces($races, $positionPercentage);
        $ratingRecalculationsStartEnd = microtime(true) - $ratingRecalculationsStartTime;
        $this->logger->log("¯\_(ツ)_/¯ rating recalculations workers took " . number_format($ratingRecalculationsStartEnd, 2) . ' seconds to complete.');

        $summary = new ScraperSummary(
            $dateRange,
            $algStart,
            $meetingsTimeEnd,
            $racesTimeEnd,
            $horsesTimeEnd,
            $horsesRecordsTimeEnd,
            $resultsTimeEnd,
            $historicResultsTimeEnd,
            $sectionalRecalculationsEndTime,
            $rankRecalculationsStartEnd,
            $sectionalAVGRecalculationsStartEnd,
            $ratingRecalculationsStartEnd
        );
        $this->logger->printScraperSummary($summary);

        return $summary;
    }
}