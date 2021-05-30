<?php

namespace App\Command;

use App\Helper\DateRangeBuilder;
use App\Service\MeetingsDownloadService;
use App\Service\ParserService;
use App\Service\ResultsDownloadService;
use Carbon\Carbon;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadAndCacheRacingZoneDataCommand extends Command
{
    protected static $defaultName = 'run:download-cache';

    protected $meetingsDownloadService;
    protected $resultsDownloadService;
    protected $parserService;

    public function __construct(MeetingsDownloadService $meetingsDownloadService, ResultsDownloadService $resultsDownloadService, ParserService $parserService, $name = null)
    {
        $this->meetingsDownloadService = $meetingsDownloadService;
        $this->resultsDownloadService = $resultsDownloadService;
        $this->parserService = $parserService;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Downloads pre-cached Racing Zone data.')
            ->setHelp('Use for cron jobs.')
            ->addArgument('startDate', InputArgument::REQUIRED, 'Starting date (in Y-m-d format)')
            ->addArgument('endDate', InputArgument::OPTIONAL, 'Ending date (in Y-m-d format)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $startDate = new Carbon($input->getArgument('startDate'));
        $endDate = new Carbon($input->getArgument('endDate'));
        if ($startDate->isFuture()) {
            throw new \Exception('Start date cannot be future date');
        }
        if ($endDate->isFuture()) {
            throw new \Exception('End date cannot be future date');
        }


        if (empty($input->getArgument('endDate'))) {
            $input->setArgument('endDate', $input->getArgument('startDate'));
        }

        $dateRange = DateRangeBuilder::create($input->getArgument('startDate'), $input->getArgument('endDate'));

        foreach ($dateRange->getAll() as $strDate) {
            $this->meetingsDownloadService->downloadMeetingsForDate($strDate);
            $this->resultsDownloadService->downloadResultsForDate($strDate);
        }

        return Command::SUCCESS;
    }
}