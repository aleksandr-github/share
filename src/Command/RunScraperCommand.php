<?php

namespace App\Command;

use Amp\MultiReasonException;
use Amp\Parallel\Worker\TaskFailureError;
use App\Helper\DateRangeBuilder;
use App\Model\DateRange;
use App\Model\ScraperSummary;
use App\Service\ParserService;
use DateTime;
use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

class RunScraperCommand extends Command
{
    protected static $defaultName = 'run:scraper';

    protected $parserService;
    protected $httpWorkersNumber;
    protected $mysqliWorkersNumber;
    protected $algorithmInfo;

    public function __construct(ParserService $parserService, ContainerInterface $container, $httpWorkersNumber, $mysqliWorkersNumber)
    {
        $this->parserService = $parserService;
        $this->httpWorkersNumber = $httpWorkersNumber;
        $this->mysqliWorkersNumber = $mysqliWorkersNumber;
        $this->algorithmInfo = $container->get('algorithmContext')->getAlgorithm()->getAlgorithmInfo();

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Runs parser in CLI mode for pthreads implementation.')
            ->setHelp('Runs parser in CLI mode for pthreads implementation.')
            ->addArgument('startDate', InputArgument::REQUIRED, 'Starting date (in Y-m-d format)')
            ->addArgument('endDate', InputArgument::OPTIONAL, 'Ending date (in Y-m-d format)')
            ->addArgument('timerHandicapMultiplier', InputArgument::OPTIONAL, 'Custom timer handicap multiplier value')
            ->addArgument('positionPercentage', InputArgument::OPTIONAL, 'Position Percentage modifier')
            ->addArgument('handicapModifier', InputArgument::OPTIONAL, 'Handicap Modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timerHandicapMultiplier = $input->getArgument('timerHandicapMultiplier') ?? $_ENV['timerHandicapMultiplier'];
        $positionPercentage = $input->getArgument('positionPercentage') ?? $_ENV['positionPercentage'];
        $handicapModifier = $input->getArgument('handicapModifier') ?? $_ENV['handicapModifier'];

        $io->section("Algorithm used:");
        $io->table(['Description','Value'], [
            ['Class name', $this->algorithmInfo->getClassName()],
            ['Algorithm name', $this->algorithmInfo->getName()],
            ['Algorithm version', $this->algorithmInfo->getVersion()],
            ['Description', $this->algorithmInfo->getDescription()],
        ]);

        $io->section("Workers configuration:");
        $io->table(['Description', 'Value'], [
            ["HTTP parser workers", $this->httpWorkersNumber],
            ["MySQL client workers", $this->mysqliWorkersNumber]
        ]);

        $io->section("Algorithm parameters configuration:");
        $io->table(['Description', 'Value'], [
            ["timerHandicapMultiplier value used in calculations", $timerHandicapMultiplier],
            ["positionPercentage value used in calculations", $positionPercentage],
            ["handicapModifier value used in calculations", $handicapModifier]
        ]);

        if (empty($input->getArgument('endDate'))) {
            $input->setArgument('endDate', $input->getArgument('startDate'));
        }

        $dateRange = DateRangeBuilder::create($input->getArgument('startDate'), $input->getArgument('endDate'));

        if ($this->isRecordsInDBForDateRange($dateRange)) {
            $io->error("Data already exists for selected dates.");

            return Command::FAILURE;
        }

        $io->text("Started parsing, please wait. You can check logs/main_log.txt to see progress in real time.");

        try {
            $results = $this->parserService->startParser($dateRange, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
        } catch (\Throwable $e) {
            if ($e instanceof MultiReasonException) {
                foreach ($e->getReasons() as $reason) {
                    $io->error($reason->getMessage() . ' at file ' . $reason->getFile());
                    $io->note($reason->getTraceAsString());
                    if ($reason instanceof TaskFailureError) {
                        $io->newLine();
                        $io->error($reason->getOriginalMessage());
                        $io->note($reason->getOriginalTraceAsString());
                        $io->newLine();
                    }
                }
            }
            $io->error($e->getMessage());
            $io->note($e->getTraceAsString());
            while ($e = $e->getPrevious()) {
                $io->note($e->getTraceAsString());
                $io->error($e->getMessage());
            }

            return Command::FAILURE;
        }

        $this->showResults($results, $io);
        $io->success("Parser finished. Check main_log.txt for details.");

        return Command::SUCCESS;
    }

    protected function isRecordsInDBForDateRange(DateRange $dateRange)
    {
        $mysqli = $this->initMultiSessionDatabase();
        $query = "SELECT COUNT(meeting_id) FROM `tbl_meetings` WHERE meeting_date IN (".$dateRange->toSQLQuery().")";
        $res = $mysqli->query($query);
        $num_rows = $res->fetch_array();
        $count = $num_rows[0];
        if ($count > 0) {
            return true;
        }

        return false;
    }

    /**
     * @return mysqli
     */
    protected function initMultiSessionDatabase(): mysqli
    {
        (new Dotenv())->bootEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env');

        $mysqli = new mysqli(
            $_ENV["dbservername"],
            $_ENV["dbusername"],
            $_ENV["dbpassword"],
            $_ENV["dbdatabase"]
        );

        return $mysqli;
    }

    private function showResults(ScraperSummary $scraperSummary, SymfonyStyle $io)
    {
        $micro = sprintf("%06d",($scraperSummary->getAlgStart() - floor($scraperSummary->getAlgStart())) * 1000000);
        $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $scraperSummary->getAlgStart()) );
        $now = new DateTime();
        $diff = $now->diff($d);
        $diffSeconds = microtime(true) - $scraperSummary->getAlgStart();

        $io->writeln("");
        $io->title("SCRAPER SUMMARY");
        $io->table(["Description", "Time"], [
            ["(❍ᴥ❍ʋ) Scraper started at", $d->format("Y-m-d H:i:s.u")],
            ["Parsing race results in dates", $scraperSummary->getDateRange()->__toString()],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getMeetingsTimeEnd(), $diffSeconds) . "%] Meetings workers parsing time", number_format($scraperSummary->getMeetingsTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getRacesTimeEnd(), $diffSeconds) . "%] Races workers parsing time", number_format($scraperSummary->getRacesTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getHorsesTimeEnd(), $diffSeconds) . "%] Horses workers parsing time", number_format($scraperSummary->getHorsesTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getHorsesRecordsTimeEnd(), $diffSeconds) . "%] Records workers parsing time", number_format($scraperSummary->getHorsesRecordsTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getResultsTimeEnd(), $diffSeconds) . "%] Records workers saving time", number_format($scraperSummary->getResultsTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getHistoricResultsTimeEnd(), $diffSeconds) . "%] Historic Results workers parsing time", number_format($scraperSummary->getHistoricResultsTimeEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getSectionalRecalculationsEndTime(), $diffSeconds) . "%] Sectional recalculation workers time", number_format($scraperSummary->getSectionalRecalculationsEndTime(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getRankRecalculationsStartEnd(), $diffSeconds) . "%] Rank recalculation workers time", number_format($scraperSummary->getRankRecalculationsStartEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getSectionalAVGRecalculationsStartEnd(), $diffSeconds) . "%] Sectional AVG recalculation workers time", number_format($scraperSummary->getSectionalAVGRecalculationsStartEnd(), 2) . " seconds"],
            ["[" . $this->getPercentageOfValueInTotal($scraperSummary->getRatingRecalculationsStartEnd(), $diffSeconds) . "%] Rating recalculation workers time", number_format($scraperSummary->getRatingRecalculationsStartEnd(), 2) . " seconds"],
        ]);

        $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
        $io->title('୧༼◕ ᴥ ◕༽୨ Scraper took ' . $diff->format("%i minutes and %s seconds") . " overall to complete");
    }

    protected function getPercentageOfValueInTotal(float $value, int $total): string
    {
        $percentage = number_format((float)($value / $total * 100), 2);
        if (strpos($percentage, ".") === 1) {
            $percentage = "0" . $percentage; // add leading zero just to make sure table in console is aligned as pretty as it can be ;-)
        }

        return $percentage;
    }
}