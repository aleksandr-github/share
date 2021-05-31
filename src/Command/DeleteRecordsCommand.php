<?php

namespace App\Command;

use App\Model\App\Meeting;
use App\Service\App\MeetingService;
use App\Service\App\RaceService;
use App\Service\DBConnector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class DeleteRecordsCommand extends Command
{
    protected static $defaultName = 'run:purge';
    protected $raceService;
    protected $meetingService;
    protected $dbConnector;

    public function __construct(RaceService $raceService, MeetingService $meetingService, DBConnector $dbConnector, string $name = null)
    {
        $this->raceService = $raceService;
        $this->meetingService = $meetingService;
        $this->dbConnector = $dbConnector;

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

        $io->error("This function is not implemented yet and should not be used.");
        throw new \Exception('Not implemented.');

        if (!$input->getArgument('purgeDateQuery')) {
            $this->generateRacesTables($io);
            $io->text("Supported operands: = > >= < <= -");
            $question = new Question("Delete query: (examples: <3 (deletes races with ID lower than 3), >40 (deletes races with ID higher than 40), 30 (deletes race with ID 30), 10-40 (deletes races with ID's in range of 10 to 40))");
            $answer = $io->askQuestion($question);
        } else {
            $answer = $input->getArgument('purgeDateQuery');
        }
        $partialSQL = $this->getPartialRaceSQLForAnswer($answer);
        $races = $this->raceService->getRacesWithPartialWhere($partialSQL);

        $io->warning("You're about to delete " . count($races) . " races along with records and historic data from DB");
        $proceed = $io->confirm("Are you sure you want to proceed?");
        if ($proceed) {
            $io->text("Deleting, please wait.");
            $io->progressStart(count($races));
            foreach ($races as $race) {
                // logic
                $sqls = [
                    "DELETE FROM `tbl_temp_hraces` WHERE `race_id` = " . $race->getRaceId(),
                    "DELETE FROM `tbl_results` WHERE `race_id` = " . $race->getRaceId(),
                    "DELETE FROM `tbl_hist_results` WHERE `race_id` = " . $race->getRaceId(),
                    "DELETE FROM `tbl_meetings` WHERE `meeting_id` = " . $race->getMeetingId(),
                    "DELETE FROM `tbl_races` WHERE `race_id` = " . $race->getRaceId(),
                ];
                foreach ($sqls as $sql) {
                    $this->dbConnector->getDbConnection()->query($sql);
                }
                $io->progressAdvance();
            }
            $io->progressFinish();
            $io->success("Races with it's corresponding data has been deleted.");
        }

        return Command::SUCCESS;
    }

    /**
     * @param array|Meeting[] $arrayOfObjects
     * @param int $meetingId
     * @return Meeting
     * @throws \Exception
     */
    protected function searchForMeetingIdInArray(array $arrayOfObjects, int $meetingId): Meeting
    {
        $searchArray = array_filter(
            $arrayOfObjects,
            function ($e) use (&$meetingId) {
                return $e->getMeetingId() == $meetingId;
            }
        );

        dump($searchArray);

        if ($searchArray[array_key_first($searchArray)] instanceof Meeting) {
            return $searchArray[array_key_first($searchArray)];
        }

        throw new \Exception('Meeting of ID:' . $meetingId . ' not found.');
    }

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @return array
     * @throws \Exception
     */
    protected function generateRacesTables(SymfonyStyle $io): array
    {
        $tableRows = [];
        $races = $this->raceService->getAll();
        $meetings = $this->meetingService->getAll();
        foreach ($races as $race) {
            $meeting = $this->searchForMeetingIdInArray($meetings, $race->getMeetingId());
            $tableRows[] = [$race->getRaceId(), $race->getRaceTitle(), $race->getRaceScheduleTime(), $race->getRoundDistance(), $meeting->getMeetingName(), $meeting->getMeetingDate()];
        }
        $io->table(['ID', 'Race title', 'Race scheduled time', 'Race distance', 'Meeting', 'Meeting date'], $tableRows);

        return $races;
    }

    /**
     * @throws \Exception
     */
    private function getPartialRaceSQLForAnswer(string $answer): string
    {
        if (strpos($answer, '-') > 0) {
            $idsArray = explode("-", $answer);
            if ($idsArray[0] >= $idsArray[1]) {
                throw new \LogicException("Range must valid.");
            }
            return "WHERE `race_id` BETWEEN ".$idsArray[0]." AND ".$idsArray[1];
        } else {
            $sign = $answer[0];
            switch ($sign) {
                case is_numeric($sign):
                    return "WHERE `race_id`=" . $sign;
                case "=":
                    return "WHERE `race_id`=" . str_replace("=", "", $answer);
                case "<":
                    return "WHERE `race_id`<" . str_replace("<", "", $answer);
                case "<=":
                    return "WHERE `race_id`<=" . str_replace("<=", "", $answer);
                case ">":
                    return "WHERE `race_id`>" . str_replace(">", "", $answer);
                case ">=":
                    return "WHERE `race_id`>=" . str_replace(">=", "", $answer);
                default:
                    throw new \Exception('Unknown operand used.');
            }
        }
    }
}