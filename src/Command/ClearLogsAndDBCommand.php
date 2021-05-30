<?php

namespace App\Command;

use App\Service\DBConnector;
use App\Service\ParserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearLogsAndDBCommand extends Command
{
    protected static $defaultName = 'run:annihilation';

    protected function configure()
    {
        $this->setDescription('Clears the database and logs.')
            ->setHelp('Clears the database and logs.')
            ->addArgument('force', InputArgument::OPTIONAL, 'Setting this to YES fires up script without prompting for confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->warning("You're about to do something destructive, probably interfering with Space-Time continuum.");
        if ($input->getArgument('force') == "YES") {
            $answer = $input->getArgument('force');
        } else {
            $answer = $io->ask("You're about to COMPLETELY destroy data in DB and in logs. Are you ABSOLUTELY sure you know what you're doing?", "YES");
        }

        if ($answer == "YES") {
            $db = new DBConnector();
            $mysqli = $db->getDbConnection();
            $sql = "TRUNCATE `tbl_hist_results`";
            $mysqli->query($sql);
            $sql = "TRUNCATE `tbl_horses`";
            $mysqli->query($sql);
            $sql = "TRUNCATE `tbl_meetings`";
            $mysqli->query($sql);
            $sql = "TRUNCATE `tbl_races`";
            $mysqli->query($sql);
            $sql = "TRUNCATE `tbl_results`";
            $mysqli->query($sql);
            $sql = "TRUNCATE `tbl_temp_hraces`";
            $mysqli->query($sql);

            $files = [
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'results_log.txt',
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'main_log.txt',
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'main_logs.txt',
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'debug_algorithm.log',
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'db_logs.txt',
                APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'race_details.txt',
            ];

            foreach ($files as $file) {
                file_put_contents($file, "");
            }
            $io->success("Logs and DB cleaned. Check for local disruptions to make sure you're still living in known universe.");

            return Command::SUCCESS;
        }

        $io->note("Can't touch this");

        return Command::SUCCESS;
    }
}