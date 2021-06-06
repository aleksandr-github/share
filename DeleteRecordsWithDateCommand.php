<?php

namespace App\Command;

use App\Model\App\Meeting;
use App\Service\App\MeetingService;
use App\Service\App\RaceService;
use App\Service\App\TempHorseRacesService;
use App\Service\DBConnector;
use App\Model\DateRange;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteRecordsWithDateCommand extends Command
{
    protected static $defaultName = 'run:purgeDateQuery';
    protected $raceService;
    protected $meetingService;
    protected $tempHorseRacesService;
    protected $dbConnector;

    public function __construct(RaceService $raceService, MeetingService $meetingService, TempHorseRacesService $tempHorseRacesService, DBConnector $dbConnector, string $name = null)
    {
        $this->raceService = $raceService;
        $this->meetingService = $meetingService;
        $this->dbConnector = $dbConnector;
        $this->tempHorseRacesService = $tempHorseRacesService;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Purge is a function that is sometimes necessary to use to update a DB to clean up records.')
            ->setHelp('Use at your own will.')
            ->addArgument('purgeDateQuery', InputArgument::OPTIONAL, 'Query for purge dates');
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($input->getArgument('endDate'))) {
            $input->setArgument('endDate', $input->getArgument('startDate'));
        }

        $dateRange = DateRangeBuilder::create($input->getArgument('startDate'), $input->getArgument('endDate'));

        $partialSQL = $this->getPartialMeetingSQLForAnswer($dateRange);
        $partialMeetings = $this->meetingService->getWithPartialWhere($partialSQL, true);

        $io->warning("You're about to delete " . count($partialMeetings) . " meeting(s), along with races, records and historic data from DB.");
        $proceed = $io->confirm("Are you sure you want to proceed?");
        if ($proceed) {
            foreach ($partialMeetings as $meeting) {
                $io->text("Deleting meeting " . $meeting->getMeetingName() . ", please wait.");
                $races = $this->raceService->getRacesWithPartialWhere("WHERE meeting_id = " . $meeting->getMeetingId(), true);

                // delete races
                $progress = new ProgressBar($io);
                ProgressBar::setFormatDefinition('races_format', 'Deleting race of ID: %message%');
                $progress->setFormat('races_format');
                $progress->setMaxSteps(count($races));
                foreach ($races as $race) {
                    // historic results for race
                    $progress->setMessage($race->getRaceId());
                    $this->dbConnector->getDbConnection()->query("DELETE FROM `tbl_hist_results` WHERE `race_id` = " . $race->getRaceId());
                    $this->dbConnector->getDbConnection()->query("DELETE FROM `tbl_temp_hraces` WHERE `race_id` = " . $race->getRaceId());
                    $this->dbConnector->getDbConnection()->query("DELETE FROM `tbl_results` WHERE `race_id` = " . $race->getRaceId());
                    $this->dbConnector->getDbConnection()->query("DELETE FROM `tbl_races` WHERE `race_id` = " . $race->getRaceId());

                    $progress->advance();
                }
                $this->dbConnector->getDbConnection()->query("DELETE FROM `tbl_meetings` WHERE `meeting_id` = " . $meeting->getMeetingId());
                $progress->finish();
            }
            $io->success("Meetings with it's corresponding data has been deleted.");
        }

        return Command::SUCCESS;
    }

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @return array
     * @throws \Exception
     */
    protected function generateMeetingsTable(SymfonyStyle $io): array
    {
        $tableRows = [];
        $meetings = $this->meetingService->getAll();
        foreach ($meetings as $meeting) {
            $races = $this->raceService->getRacesWithPartialWhere("WHERE meeting_id = " . $meeting->getMeetingId());
            $tableRows[] = [$meeting->getMeetingId(), $meeting->getMeetingName(), $meeting->getMeetingDate(), $meeting->getMeetingUrl(), count($races)];
        }
        $io->table(['ID', 'Meeting title', 'Meeting date', 'Meeting URL', 'Races count'], $tableRows);

        return $meetings;
    }

    /**
     * @throws \Exception
     */
    private function getPartialMeetingSQLForAnswer(DateRange $dateRange): string
    {
        $query = "WHERE meeting_date IN (".$dateRange->toSQLQuery().")";
        return $query;
    }
}