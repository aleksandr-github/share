<?php

namespace App\Command;


use App\Enum\Algorithm\DefaultAlgorithmMethodsEnum;
use App\Helper\DefaultAlgorithmMethods;
use App\Helper\RaceDistanceArrayHelper;
use App\Service\App\HistoricResultService;
use App\Service\App\RaceService;
use App\Service\ParserService;
use App\Service\TasksService;
use Carbon\Carbon;
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
            ->addArgument('ACTION', InputArgument::OPTIONAL, 'RANK || RATING')
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

        switch ($input->getArgument('ACTION')) {
            case "RANK":
                $files = [
                    APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'debug_algorithm.log',
                ];

                foreach ($files as $file) {
                    file_put_contents($file, "");
                }

                $start = new Carbon();
                $this->refreshRank($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
                $io->title('୧༼◕ ᴥ ◕༽୨ Refreshing rank took ' . $start->diffForHumans() . " to complete");

                return Command::SUCCESS;
            case "RATING":
                $files = [
                    APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'debug_algorithm.log',
                ];

                foreach ($files as $file) {
                    file_put_contents($file, "");
                }

                $start = new Carbon();
                $this->refreshRating($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
                $io->title('୧༼◕ ᴥ ◕༽୨ Refreshing rating took ' . $start->diffForHumans() . " to complete");

                return Command::SUCCESS;
            case "ALL":
                $files = [
                    APP_ROOT . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'debug_algorithm.log',
                ];

                foreach ($files as $file) {
                    file_put_contents($file, "");
                }

                $start = new Carbon();
                $this->refreshRank($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                $this->refreshRating($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
                $io->title('୧༼◕ ᴥ ◕༽୨ Refreshing all records took ' . $start->diffForHumans() . " to complete");

                return Command::SUCCESS;
            default:
                break;
        }

        $io->title('CLI management tool for algorithm functions.');

        do {
            $actions = [
                DefaultAlgorithmMethodsEnum::REFRESH_RANK()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::REFRESH_RANK()),
                DefaultAlgorithmMethodsEnum::REFRESH_RATING()->getValue() => DefaultAlgorithmMethods::transcribe(DefaultAlgorithmMethodsEnum::REFRESH_RATING()),
                'Exit' => "Stops the script and exits to console"
            ];
            $action = $io->choice("Choose action", $actions);

            switch ($action) {
                case "Exit":
                    return Command::SUCCESS;
                case DefaultAlgorithmMethodsEnum::REFRESH_RANK()->getValue():
                    $start = new Carbon();
                    $this->refreshRank($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                    $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
                    $io->title('୧༼◕ ᴥ ◕༽୨ Refreshing rank took ' . $start->diffForHumans() . " to complete");
                    break;
                case DefaultAlgorithmMethodsEnum::REFRESH_RATING()->getValue():
                    $start = new Carbon();
                    $this->refreshRating($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
                    $io->text("♪┏(°.°)┛┗(°.°)┓┗(°.°)┛┏(°.°)┓ ♪");
                    $io->title('୧༼◕ ᴥ ◕༽୨ Refreshing rating took ' . $start->diffForHumans() . " to complete");
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

        return $mysqli;
    }

    private function purge(SymfonyStyle $io)
    {
        $this->tasksService->resetHandicap();
        $io->progressAdvance();
        $this->tasksService->resetSectional();
        $io->progressAdvance();
    }

    private function resetRank(SymfonyStyle $io)
    {
        $this->purge($io);
        $this->tasksService->resetRank();
        $io->progressAdvance();
    }

    private function resetRating(SymfonyStyle $io)
    {
        $this->purge($io);
        $this->tasksService->resetRating();
        $io->progressAdvance();
    }

    private function updateRank(SymfonyStyle $io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier): bool
    {
        $races = $this->raceService->getWithEmptyRankStatus();
        $io->progressAdvance();
        if (count($races) === 0) {
            return true;
        }
        $io->progressAdvance();
        $historicRecords = $this->historicResultService->getWithEmptyHandicap();
        if (count($historicRecords) === 0) {
            return true;
        }
        $this->tasksService->updateSectional();
        $io->progressAdvance();
        $this->tasksService->generateHandicapForHistoricResults($historicRecords, $timerHandicapMultiplier, $handicapModifier);
        $io->progressAdvance();
        $this->tasksService->updateRankForRaces($races, $positionPercentage);
        $io->progressAdvance();
        $this->tasksService->updateSectionalAVGForRaces($races, $positionPercentage);
        $io->progressAdvance();

        return true;
    }

    private function updateRating(SymfonyStyle $io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier): bool
    {
        $racesIdsWithoutRating = $this->historicResultService->getDistinctRacesIdsWithEmptyRating();
        $raceIds = implode(", ", $racesIdsWithoutRating);
        $races = $this->raceService->getRacesWithPartialWhere('WHERE race_id IN (' . $raceIds . ')');
        $io->progressAdvance();
        if (count($races) === 0) {
            return true;
        }
        $io->progressAdvance();
        $historicRecords = $this->historicResultService->getWithEmptyHandicap();
        if (count($historicRecords) === 0) {
            return true;
        }
        $this->tasksService->updateSectional();
        $io->progressAdvance();
        $this->tasksService->generateHandicapForHistoricResults($historicRecords, $timerHandicapMultiplier, $handicapModifier);
        $io->progressAdvance();
        $this->tasksService->updateSectionalAVGForRaces($races, $positionPercentage);
        $io->progressAdvance();
        $this->tasksService->updateRatingForRaces($races, $positionPercentage);
        $io->progressAdvance();

        return true;
    }

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param $positionPercentage
     * @param $timerHandicapMultiplier
     * @param $handicapModifier
     */
    protected function refreshRank(SymfonyStyle $io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier): void
    {
        $io->progressStart(10);
        $this->resetRank($io);
        $io->progressAdvance();
        $this->updateRank($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
        $io->progressFinish();
    }

    /**
     * @param \Symfony\Component\Console\Style\SymfonyStyle $io
     * @param $positionPercentage
     * @param $timerHandicapMultiplier
     * @param $handicapModifier
     */
    protected function refreshRating(SymfonyStyle $io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier): void
    {
        $io->progressStart(10);
        $this->resetRating($io);
        $io->progressAdvance();
        $this->updateRating($io, $positionPercentage, $timerHandicapMultiplier, $handicapModifier);
        $io->progressFinish();
    }
}