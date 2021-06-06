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
use Symfony\Component\Console\Input\InputOption;
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
        $this
            ->setName('purgeDateQuery')
            ->setDescription('Greet purgeDateQuery')
            ->addArgument('startDate', InputArgument::OPTIONAL, 'What is Start Date?')
            ->addArgument('endDate', InputArgument::OPTIONAL, 'What is End Date?')
            ->addOption('dateOption', null, InputOption::VALUE_NONE, 'If set, the task will yell in uppercase letters')
        ;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startDate = $input->getArgument('startDate');
        $endDate = $input->getArgument('endDate');

        if ((!$startDate)||(!$this->check_date($startDate))) {
            throw new \LogicException("Range must valid.");
        }
        else{
            $startTimeStamp = strtotime($startDate);
        }

        if (!$endDate) {
            $endDate = $startDate;
        }
        if (!$this->check_date($endDate)) {
            throw new \LogicException("Range must valid.");
        }
        $endTimeStamp = strtotime($endDate);

        if($startTimeStamp > $endTimeStamp) {
            throw new \LogicException("Range must valid.");
        }

//        if ($input->getOption('dateOption')) {
//            $text = strtoupper($text)."ok";
//        }

        $output->writeln($startDate.' '.$endDate);

        return Command::SUCCESS;
    }

    protected function check_date($x): bool
    {
        return (date('Y-m-d', strtotime($x)) == $x);
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