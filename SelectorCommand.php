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
use Symfony\Component\Dotenv\Dotenv;

class SelectorCommand extends Command
{
    protected static $defaultName = 'run:selectorQuery';
    protected $raceService;
    protected $meetingService;
    protected $tempHorseRacesService;
    protected $dbConnector;
    protected $logger;

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
            ->setName('selector')
            ->setDescription('Greet selector')
            ->addArgument('selector', InputArgument::OPTIONAL, 'What is Selector?')
            ->addArgument('timerHandicapMultiplier', InputArgument::OPTIONAL, 'Custom timer handicap multiplier value')
            ->addArgument('positionPercentage', InputArgument::OPTIONAL, 'Position Percentage modifier')
            ->addArgument('handicapModifier', InputArgument::OPTIONAL, 'Handicap Modifier');
        ;
    }

    /**
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $selector = $input->getArgument('selector') ?? 1;
        $timerHandicapMultiplier = $input->getArgument('timerHandicapMultiplier') ?? $_ENV['timerHandicapMultiplier'];
        $positionPercentage = $input->getArgument('positionPercentage') ?? $_ENV['positionPercentage'];
        $handicapModifier = $input->getArgument('handicapModifier') ?? $_ENV['handicapModifier'];

        if (!$selector) {
            throw new \LogicException("Range must valid.");
        }

        $output->writeln($selector);

        $client = HttpClient::createForBaseUri($_ENV['appUrl']);
        $request = $client->request('GET', '/api/avg_rank', ['odds' => 'true', 'limit' => '4']);
        $jsonContent = $request->getContent();
        $avgTotalProfit = json_decode($jsonContent)->absoluteTotal;

        $this->logger = new PrettyLogger(__FILE__, 'selector.txt');
        $this->logger->log("Fetching selector: " . $selector);


        return Command::SUCCESS;
    }

}