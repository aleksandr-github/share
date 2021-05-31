<?php

namespace App\Command;

use App\Helper\DateRangeBuilder;
use App\Service\DBConnector;
use App\Service\ParserService;
use App\Service\PrettyLogger;
use App\Service\SimpleHTMLDomService;
use DateTime;
use PDO;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RunResultsCommand extends Command
{
    protected static $defaultName = 'run:results';

    protected $parserService;

    protected $logger;

    protected $domParserService;

    public function __construct(ParserService $parserService, SimpleHTMLDomService $domParserService)
    {
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

        if (extension_loaded('pthreads')) {
            $io->warning('php_pthreads extension is considered abandoned and deprecated. Use php_parallel instead.');
        } elseif (extension_loaded('parallel')) {
            $io->success('Parallel extension initialized');
        } else {
            //throw new \Exception('It seems you have pThreads disabled. Console command available only on system with pThreads extension!');
            $io->warning("pThreads // Parallel not found. Results may vary. Consider installing php_parallel extension");
        }

        if (empty($input->getArgument('endDate'))) {
            $input->setArgument('endDate', $input->getArgument('startDate'));
        }

        $io->text("Results generation in progress, please wait. You can check logs/main_log.txt to see progress in real time.");

        $db = new DBConnector();
        $credentials = $db->initDBCredentials();

        date_default_timezone_set('Europe/London');

        try {
            $dbh = new PDO('mysql:host=' . $credentials->getServername() . ';dbname=' . $credentials->getDatabase(),
                $credentials->getUsername(),
                $credentials->getPassword()
            );
        } catch (PDOException $e) {
            $io->error($e->getMessage());
            $io->note($e->getTraceAsString());
            $this->logger->log("Error: " . $e->getMessage());

            return Command::FAILURE;
        }

        $dateRange = DateRangeBuilder::create($input->getArgument('startDate'), $input->getArgument('endDate'));

        $now = new DateTime();
        foreach ($dateRange->getAll() as $strDate) {
            $start = microtime(true);
            $formattedDate = $strDate;

            $this->logger->log('Results generation for date ' . $formattedDate . ' is starting');

            $base_url = 'https://www.racingzone.com.au';
            $part_url = '/results/' . $formattedDate . '/';
            $parse_url = $base_url . $part_url;

            $html = $this->domParserService->file_get_html($parse_url);

            $insert_counter = 0;

            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Main parse url is <a href='$parse_url' class='alert-link'>$parse_url</a>");
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
                        $this->logger->log("[" . date("Y-m-d H:i:s") . "] Find meeting_id error: " . $stmt->error);
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
                        $race_link = $base_url . $link;
                        $race_html = $this->domParserService->file_get_html($race_link);
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
                                    $horseslug = preg_replace('/[^A-Za-z0-9\-]/', '', strtolower($horse_name));

                                    $stmt = $dbh->prepare("SELECT horse_id FROM tbl_horses WHERE horse_slug = ?");
                                    if ($stmt->execute(array($horseslug))) {
                                        $horse_id = $stmt->fetchColumn();
                                    }

                                    $i++;
                                    $stmt = $dbh->prepare('INSERT IGNORE INTO tbl_results (race_id, horse_id, position) VALUE(:race_id, :horse_id, :position)');
                                    $data = array(
                                        ':race_id' => $race_id,
                                        ':horse_id' => $horse_id,
                                        ':position' => $horse_position,
                                    );

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
                                        $this->logger->log("Ommiting race result for horse: " . $horse_id . " on position: " . $horse_position . " on race: " . $race_id . ' - exists', 'WARN');
                                    }
                                }
                            }
                        } else {
                            $this->logger->log("[" . date("Y-m-d H:i:s") . "] Insert failed - race_id is empty or not found at other table for url <a href='$race_link' class='alert-link'>$race_link</a>");
                        }
                    }
                }
            }

            // summary
            $this->logger->newLine("Results parser");
            $this->logger->log("Successfully found " . $insert_counter . " new items.");
            $time_elapsed_secs = microtime(true) - $start;
            $this->logger->log('Results generation for date ' . $formattedDate . ' took ' . $time_elapsed_secs . ' seconds');
            $this->logger->newLine("Results parser");

            $this->showResults($now, $insert_counter, $formattedDate, $time_elapsed_secs, $io);
        }

        $dbh = null;
        $io->success("Parser finished. Check main_log.txt for details.");

        return Command::SUCCESS;
    }

    private function showResults(DateTime $now, $insert_counter, $today_date, $time_elapsed_secs, SymfonyStyle $io)
    {
        $io->writeln("");
        $io->title("RESULTS SUMMARY");
        $io->table(["Description", "Result"], [
            ["(❍ᴥ❍ʋ) Results parser started at", $now->format("Y-m-d H:i:s.u")],
            ["Obtaining race results for date", $today_date],
            ["New items found", $insert_counter],
            ["Results generation time", number_format($time_elapsed_secs, 2) . " seconds"],
        ]);

        $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
        $io->title('୧༼◕ ᴥ ◕༽୨ Parsing results for ' . $today_date . ' took ' . number_format($time_elapsed_secs, 2) . " seconds overall to complete");
    }
}