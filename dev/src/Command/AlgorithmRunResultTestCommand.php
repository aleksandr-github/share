<?php

namespace App\Command;

use Amp\Http\Client\HttpClientBuilder;
use App\Entity\AlgorithmRunResult;
use App\Repository\AlgorithmRunResultRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpClient\HttpClient;

class AlgorithmRunResultTestCommand extends Command
{
    protected static $defaultName = 'run:parameters-test';
    protected $objectManager;
    protected $algorithmRunResultRepository;

    public function __construct(EntityManagerInterface $objectManager, AlgorithmRunResultRepository $algorithmRunResultRepository, string $name = null)
    {
        $this->objectManager = $objectManager;
        $this->algorithmRunResultRepository = $algorithmRunResultRepository;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('For testing purposes of different parameters.')
            ->setHelp('Only use to check best parameters configuration.')
            ->addArgument('show', InputArgument::OPTIONAL, 'Set to any value to show current results');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->getArgument('show') != null) {
            $io = new SymfonyStyle($input, $output);
            $results = $this->algorithmRunResultRepository->findAll();
            $formattedValues = [];
            foreach ($results as $result) {
                $formattedValues[] = [$result->getId(), $result->getTimerHandicapMultiplier(), $result->getPositionPercentage(), $result->getHandicapModifier(), $result->getAvgRankTotalProfit(), $result->getRatingTotalProfit()];
            }
            $io->table(
                ['ID',  'Handicap Timer modifier', 'Position percentage', 'Handicap modifier', 'AVG Rank Total Profit', 'Rating Profit'],
                $formattedValues
            );

            return Command::SUCCESS;
        }
        $paramsToTest = $this->getParametersValues();

        $runs = [];
        foreach ($paramsToTest['timerHandicapMultiplier'] as $timerHandicapMultiplier) {
            foreach ($paramsToTest['positionPercentage'] as $positionPercentage) {
                foreach ($paramsToTest['handicapModifier'] as $handicapModifier) {
                    $runs[] = (new AlgorithmRunResult())->setTimerHandicapMultiplier($timerHandicapMultiplier)->setPositionPercentage($positionPercentage)->setHandicapModifier($handicapModifier);
                }
            }
        }

        $client = HttpClient::createForBaseUri($_ENV['appUrl']);
        $algoCommand = $this->getApplication()->find('run:algorithm');
        /** @var AlgorithmRunResult $run */
        foreach ($runs as $run) {
            $arguments = [
                'ACTION' => 'ALL',
                'timerHandicapMultiplier' => $run->getTimerHandicapMultiplier(),
                'positionPercentage' => $run->getPositionPercentage(),
                'handicapModifier' => $run->getHandicapModifier()
            ];
            $algoInput = new ArrayInput($arguments);
            $algoCommand->run($algoInput, $output);

            $request = $client->request('GET', '/api/avg_rank');
            $jsonContent = $request->getContent();
            $avgTotalProfit = json_decode($jsonContent)->absoluteTotal;
            $run->setAvgRankTotalProfit($avgTotalProfit);

            $request = $client->request('GET', '/api/rating');
            $jsonContent = $request->getContent();
            $ratingTotalProfit = json_decode($jsonContent)->absoluteTotal;
            $run->setRatingTotalProfit($ratingTotalProfit);
            $this->objectManager->persist($run);
            $this->objectManager->flush();
        }

        return Command::SUCCESS;
    }

    protected function getParametersValues()
    {
        return [
            'timerHandicapMultiplier' => $this->getArrayOfValuesForParameterName( 0.0017, 2, 10),
            'positionPercentage' => $this->getArrayOfValuesForParameterName( 20, 80, 10),
            'handicapModifier' => $this->getArrayOfValuesForParameterName( 0.010, 1.0, 10),
        ];
    }

    protected function getArrayOfValuesForParameterName(float $startValue, float $endValue, int $chunks): array
    {
        $arrayOfValues = [];

        $diff = $endValue - $startValue;
        $step = $diff / $chunks;
        for (; $startValue <= $endValue; $startValue = $startValue + $step) {
            $arrayOfValues[] = $startValue;
        }

        return $arrayOfValues;
    }
}