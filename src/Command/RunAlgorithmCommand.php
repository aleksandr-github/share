<?php

namespace App\Command;


use App\Enum\Algorithm\DefaultAlgorithmMethodsEnum;
use App\Helper\DefaultAlgorithmMethods;
use App\Helper\RaceDistanceArrayHelper;
use App\Service\App\HistoricResultService;
use App\Service\App\RaceService;
use App\Service\ParserService;
use App\Service\TasksService;
use Exception;
use mysqli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Dotenv\Dotenv;

class RunAlgorithmCommand extends Command
{
    protected static $defaultName = 'run:algorithm';

    protected $parserService;
    protected $httpWorkersNumber;
    protected $mysqliWorkersNumber;
    protected $algorithmInfo;
    protected $tasksService;
    protected $raceService;
    protected $historicResultService;

    public function __construct(
        ParserService $parserService,
        TasksService $tasksService,
        RaceService $raceService,
        HistoricResultService $historicResultService,
        ContainerInterface $container,
        $httpWorkersNumber,
        $mysqliWorkersNumber
    ) {
        $this->parserService = $parserService;
        $this->httpWorkersNumber = $httpWorkersNumber;
        $this->mysqliWorkersNumber = $mysqliWorkersNumber;
        $this->tasksService = $tasksService;
        $this->raceService = $raceService;
        $this->historicResultService = $historicResultService;
        $this->algorithmInfo = $container->get('algorithmContext')->getAlgorithm()->getAlgorithmInfo();

        parent::__construct();
    }

    protected function configure()
    {
        $this->setDescription('Runs algorithm for all records in CLI mode for pthreads implementation.')
            ->setHelp('Runs algorithm for all records in CLI mode for pthreads implementation.')
            ->addArgument('timerHandicapMultiplier', InputArgument::OPTIONAL, 'Custom timer handicap multiplier value')
            ->addArgument('positionPercentage', InputArgument::OPTIONAL, 'Position Percentage modifier')
            ->addArgument('handicapModifier', InputArgument::OPTIONAL, 'Handicap Modifier');
    }

    /**
     * @throws \Exception
     * @throws \Throwable
     */
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

        $io->title('CLI management tool for algorithm functions.');

        do {
            $actions = [
                DefaultAlgorithmMethodsEnum::RESET_HANDICAP()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::RESET_HANDICAP()),
                DefaultAlgorithmMethodsEnum::RESET_SECTIONAL()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::RESET_SECTIONAL()),
                DefaultAlgorithmMethodsEnum::RESET_RANK()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::RESET_RANK()),
                DefaultAlgorithmMethodsEnum::RESET_RATING()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::RESET_RATING()),
                DefaultAlgorithmMethodsEnum::RESET_ALL()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::RESET_ALL()),
                '-----------' => '-----------',
                DefaultAlgorithmMethodsEnum::UPDATE_SECTIONAL()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::UPDATE_SECTIONAL()),
                DefaultAlgorithmMethodsEnum::UPDATE_HANDICAP()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::UPDATE_HANDICAP()),
                DefaultAlgorithmMethodsEnum::UPDATE_RANK()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::UPDATE_RANK()),
                DefaultAlgorithmMethodsEnum::UPDATE_RATING()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::UPDATE_RATING()),
                '----------' => '----------',
                'quit' => "Stops the script and exits to console"
            ];
            $action = $io->choice("Choose action", $actions);

            switch ($action) {
                case "quit":
                    $io->success('All tasks done.');
                    return Command::SUCCESS;
                case DefaultAlgorithmMethodsEnum::RESET_HANDICAP()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->resetHandicap();
                    $io->success('Handicap has been reset');
                    break;
                case DefaultAlgorithmMethodsEnum::RESET_SECTIONAL()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->resetSectional();
                    $io->success('Sectional has been reset');
                    break;
                case DefaultAlgorithmMethodsEnum::RESET_RANK()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->resetRank();
                    $io->success('Rank has been reset');
                    break;
                case DefaultAlgorithmMethodsEnum::RESET_RATING()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->resetRating();
                    $io->success('Rating has been reset');
                    break;
                case DefaultAlgorithmMethodsEnum::RESET_ALL()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->resetHandicap();
                    $this->tasksService->resetSectional();
                    $this->tasksService->resetRank();
                    $this->tasksService->resetRating();
                    $io->success('All data has been reset');
                    break;
                case DefaultAlgorithmMethodsEnum::UPDATE_SECTIONAL()->getValue():
                    $io->note("Action in progress, please wait.");
                    $this->tasksService->updateSectional();
                    $io->success('New sectional values has been created');
                    break;
                case DefaultAlgorithmMethodsEnum::UPDATE_HANDICAP()->getValue():
                    $io->note("Action in progress, please wait.");
                    $histResults = $this->historicResultService->getWithEmptyHandicap();
                    $this->tasksService->generateHandicapForHistoricResults($histResults, $timerHandicapMultiplier, $handicapModifier);
                    $io->success('New handicap values has been created');
                    break;
                case DefaultAlgorithmMethodsEnum::UPDATE_RANK()->getValue():
                    $io->note("Action in progress, please wait.");
                    $races = $this->raceService->getWithEmptyRankStatus();
                    $raceDistanceArray = RaceDistanceArrayHelper::generateRaceDistanceArray($races);
                    $this->tasksService->updateRankForRaces($races, $raceDistanceArray, $positionPercentage);
                    $io->success('New rank values has been created');
                    break;
                case DefaultAlgorithmMethodsEnum::UPDATE_RATING()->getValue():
                    $io->note("Action in progress, please wait.");
                    $races = $this->raceService->getWithoutSectionalAVG();
                    $this->tasksService->updateHandicapTimeForRaces($races, $positionPercentage);
                    $io->success('New rating values has been created');
                    break;
                default:
                    throw new Exception('Not implemented.');
            }
        } while(true);
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
        $mysqli->ping();

        return $mysqli;
    }
}