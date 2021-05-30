<?php

namespace App\Command;

use App\Helper\DateRangeBuilder;
use App\Helper\HorseSlugHelper;
use App\Helper\StringOperationsHelper;
use App\Model\SimpleHTMLDOM;
use App\Service\DBConnector;
use App\Service\LocalContentCacheService;
use App\Service\ParserService;
use App\Service\PrettyLogger;
use App\Service\SimpleHTMLDomService;
use DateTime;
use Exception;
use PDO;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Stopwatch\Stopwatch;

class RunResultsCommand extends Command
{
    protected static $defaultName = 'run:results';

    protected $cacheService;
    protected $parserService;
    protected $logger;
    protected $domParserService;

    public function __construct(ParserService $parserService, SimpleHTMLDomService $domParserService, LocalContentCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
        $this->parserService = $parserService;
        $this->domParserService = $domParserService;
        $this->logger = new PrettyLogger(__FILE__, 'main_log.txt');

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Runs parser in CLI mode to obtain data.')
            ->setHelp('Runs parser in CLI mode to obtain data.')
            ->addArgument('startDate', InputArgument::REQUIRED, 'Starting date (in Y-m-d format)')
            ->addArgument('endDate', InputArgument::OPTIONAL, 'Ending date (in Y-m-d format)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (empty($input->getArgument('endDate'))) {
            $input->setArgument('endDate', $input->getArgument('startDate'));
        }
        $globalInsertsCounter = 0;
        $globalParserDates = [];

        $io->text("Results generation in progress, please wait. You can check logs/main_log.txt to see progress in real time.");

        // Database connection
        $dbh = $this->createDBConnection();
        $dateRange = DateRangeBuilder::create($input->getArgument('startDate'), $input->getArgument('endDate'));
        $now = microtime(true);
        foreach ($dateRange->getAll() as $strDate) {
            $start = microtime(true);
            $formattedDate = $strDate;

            $this->logger->log('Results generation for date ' . $formattedDate . ' is starting');

            $base_url = 'https://www.racingzone.com.au';
            $part_url = '/results/' . $formattedDate . '/';
            $parse_url = $base_url . $part_url;

            // Getting data from remote server
            $cachedMain = false;
            if ($this->cacheService->cacheExists($parse_url)) {
                if ($this->cacheService->isCacheValid($parse_url)) {
                    $html = $this->cacheService->fetch($parse_url);
                    $cachedMain = true;
                } else {
                    throw new Exception("Something wrong with content");
                }
            } else {
                $html = $this->domParserService->file_get_html($parse_url);
            }
            $this->cacheService->add($parse_url, $html);
            if ($cachedMain) {
                $dom = new SimpleHTMLDOM(null, $lowercase = true, true, 'UTF-8', $stripRN = true, "\r\n", ' ');
                $html = $dom->load($html, $lowercase, $stripRN);
            }

            $insert_counter = 0;
            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Main parse url is $parse_url");
            $tables = $html->find('table.meeting');
            $this->logger->log("Fetching meeting tables of count: " . count($tables));
            foreach ($tables as $key => $table) {
                $this->logger->log("Parsing meeting table (" . $key . ") out of (" . count($tables) . ")");
                $rows = $table->find('tr');
                foreach ($rows as $rowKey => $row) {
                    $this->logger->log("Parsing row (" . $rowKey . ") out of (" . count($rows) . ")");

                    // select meeting_id
                    $meeting_date = $formattedDate;
                    $meeting_name = $row->find('td', 0)->find('a', 0)->plaintext;
                    $stmt = $dbh->prepare("SELECT meeting_id FROM tbl_meetings WHERE meeting_date = ? AND meeting_name = ?");
                    if ($stmt->execute(array($meeting_date, $meeting_name))) {
                        $meeting_id = $stmt->fetchColumn();
                    } else {
                        $this->logger->log("[" . date("Y-m-d H:i:s") . "] Find meeting_id for ".$meeting_date."@".$meeting_name." error");
                    }

                    $this->logger->log("Fetching meeting name: " . $meeting_name);
                    // .select meeting_id
                    $race_number = 1;
                    $tds = $row->find('td.popup-race');
                    foreach ($tds as $raceRow => $td) {
                        $this->logger->log("Parsing race row (" . $raceRow . ") out of (" . count($tds) . ")");
                        if (empty($td->title)) {
                            continue;
                        }
                        $link = $td->find('a', 0)->href;

                        // get HTML of single race link
                        $race_link = $base_url . $link;
                        $cachedRace = false;
                        if ($this->cacheService->cacheExists($race_link)) {
                            if ($this->cacheService->isCacheValid($race_link)) {
                                $race_html = $this->cacheService->fetch($race_link);
                                $cachedRace = true;
                            } else {
                                throw new Exception("Something wrong with content");
                            }
                        } else {
                            $race_html = $this->domParserService->file_get_html($race_link);
                        }
                        $this->cacheService->add($race_link, $race_html);
                        if ($cachedRace) {
                            $dom = new SimpleHTMLDOM(null, $lowercase = true, true, 'UTF-8', $stripRN = true, "\r\n", ' ');
                            $race_html = $dom->load($race_html, $lowercase, $stripRN);
                        }

                        // get race_distance
                        $race_distance_spans = $race_html->find('div#container > div > h1 > span');
                        foreach ($race_distance_spans as $key => $race_distance_span_el) {
                            $race_distance_span_el_class = $race_distance_span_el->class;
                            if (strpos($race_distance_span_el_class, 'popup') !== false) {
                                unset($race_distance_spans[$key]);
                            }
                        }
                        $race_distance_span = end($race_distance_spans);
                        $race_distance = $race_distance_span->plaintext;
                        $race_distance = str_ireplace('m', '', $race_distance);
                        $race_distance = (int)trim($race_distance);
                        if ($race_distance % 10 < 5) {
                            $race_distance -= $race_distance % 10;
                        } else {
                            $race_distance += (10 - ($race_distance % 10));
                        }
                        // .get race_distance
                        // select race_id
                        $stmt = $dbh->prepare("SELECT race_id FROM tbl_races WHERE meeting_id = ? AND race_order = ? AND race_distance = ?");
                        if ($stmt->execute(array($meeting_id, $race_number, $race_distance))) {
                            $race_id = $stmt->fetchColumn();
                        } else {
                            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Find race_id error: " . $stmt->error);
                        }
                        $race_number++;
                        // .select race_id
                        $race_table = $race_html->find('table.formguide', 0);
                        $race_table_rows = $race_table->find('tr');
                        $i = 1;
                        if ($race_id) {
                            $this->logger->log("Race ID found: " . $race_id);
                            foreach ($race_table_rows as $raceTableRowKey => $race_table_row) {
                                $this->logger->log("Parsing race table rows (" . $raceTableRowKey . ") out of (" . count($race_table_rows) . ")");
                                if (strpos($race_table_row->class, 'scratch') == false) {
                                    $horse_position = $i;
                                    $horse_name = $race_table_row->find('td.horse a', 0)->plaintext;
                                    $horseslug = HorseSlugHelper::generate($horse_name);

                                    $stmt = $dbh->prepare("SELECT horse_id FROM tbl_horses WHERE horse_slug = ?");
                                    if ($stmt->execute(array($horseslug))) {
                                        $horse_id = $stmt->fetchColumn();
                                    }

                                    $i++;
                                    $stmt = $dbh->prepare('INSERT IGNORE INTO tbl_results (race_id, horse_id, position) VALUE(:race_id, :horse_id, :position)');
                                    $data = [
                                        ':race_id' => $race_id,
                                        ':horse_id' => $horse_id,
                                        ':position' => $horse_position,
                                    ];

                                    $check = $dbh->prepare('SELECT COUNT(result_id) FROM tbl_results  WHERE race_id = :race_id AND horse_id = :horse_id AND position = :position');
                                    $check->execute($data);
                                    $numberOfRows = $check->fetchColumn();
                                    if ($numberOfRows == 0) {
                                        $this->logger->log("Inserting race result for horse: " . $horse_id . " on position: " . $horse_position . " on race: " . $race_id);
                                        if (!$stmt->execute($data)) {
                                            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Insert failed: " . $stmt->error);
                                        } else {
                                            $insert_counter++;
                                        }
                                    } else {
                                        $this->logger->log("Omitting race result for horse: " . $horse_id . " on position: " . $horse_position . " on race: " . $race_id . ' - exists', __FILE__);
                                    }
                                }
                            }
                        } else {
                            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Insert failed - race_id is empty or not found at other table for url $race_link");
                        }
                    }
                }
            }
            $globalInsertsCounter += $insert_counter;
            $globalParserDates[] = $formattedDate;
            // summary for log
            $this->logger->newLine("Results parser");
            $this->logger->log("Successfully found " . $insert_counter . " new items.");
            $time_elapsed_secs = microtime(true) - $start;
            $this->logger->log('Results generation for date ' . $formattedDate . ' took ' . $time_elapsed_secs . ' seconds');
            $this->logger->newLine("Results parser");
        }

        $this->showSummary($now, $globalInsertsCounter, implode(" => ", $globalParserDates), $io);
        $dbh = null;
        $io->success("Results finished. Check main_log.txt for details.");

        return Command::SUCCESS;
    }

    /**
     * @return int|\PDO
     */
    private function createDBConnection()
    {
        $db = new DBConnector();
        $credentials = $db->initDBCredentials();
        date_default_timezone_set('Europe/London');
        try {
            $dbh = new PDO('mysql:host=' . $credentials->getServername() . ';dbname=' . $credentials->getDatabase(),
                $credentials->getUsername(),
                $credentials->getPassword()
            );
        } catch (PDOException $e) {
            $this->logger->log("Error: " . $e->getMessage());

            return Command::FAILURE;
        }

        return $dbh;
    }

    private function showSummary($startDate, $insert_counter, $today_date, SymfonyStyle $io)
    {
        $micro = sprintf("%06d",($startDate - floor($startDate)) * 1000000);
        $d = new DateTime( date('Y-m-d H:i:s.'.$micro, $startDate) );
        $now = new DateTime();
        $diff = $now->diff($d);

        $io->writeln("");
        $io->title("RESULTS SUMMARY");
        $io->table(["Description", "Result"], [
            ["(❍ᴥ❍ʋ) Results parser started at", $d->format("Y-m-d H:i:s.u")],
            ["Obtaining race results for date(s)", $today_date],
            ["New items found", $insert_counter],
        ]);

        $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
        $io->title('୧༼◕ ᴥ ◕༽୨ Parsing results took ' . $diff->format("%i minutes and %s seconds") . " to complete");
    }
}