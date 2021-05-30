<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ClearHttpCache extends Command
{
    protected static $defaultName = 'run:clear-cache';

    protected function configure()
    {
        $this->setDescription('Clears the cache files used by http scraper.')
            ->setHelp('Clears the cache files used by http scraper.')
            ->addArgument('force', InputArgument::OPTIONAL, 'Setting this to YES fires up script without prompting for confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->warning("You're about to do something destructive, probably interfering with Space-Time continuum.");
        if ($input->getArgument('force') == "YES") {
            $answer = $input->getArgument('force');
        } else {
            $answer = $io->choice("You're about to COMPLETELY destroy cache data of Http client. Are you ABSOLUTELY sure you know what you're doing? This process can take up to 10 minutes.", ["YES", "NO"]);
        }

        if ($answer == "YES") {
            $folder_path = APP_ROOT . "/cache";
            $files = glob($folder_path.'/*');
            foreach ($files as $file) {
                if (is_file($file))
                    unlink($file);
            }

            $io->caution("Http cache files has been emptied.");
            $io->success("Logs and DB cleaned. Check for local disruptions to make sure you're still living in known universe.");

            return Command::SUCCESS;
        }

        $io->note("Can't touch this");

        return Command::SUCCESS;
    }
}