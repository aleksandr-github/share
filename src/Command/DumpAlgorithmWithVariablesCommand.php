<?php

namespace App\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DumpAlgorithmWithVariablesCommand extends Command
{
    protected static $defaultName = 'run:dump-info';
    protected $algorithmInfo;

    public function __construct(ContainerInterface $container, $name = null)
    {
        $this->algorithmInfo = $container->get('algorithmContext')->getAlgorithm()->getAlgorithmInfo();
        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Dumps info about current settings of algorithm.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $timerHandicapMultiplier = $_ENV['timerHandicapMultiplier'];
        $positionPercentage = $_ENV['positionPercentage'];
        $handicapModifier = $_ENV['handicapModifier'];

        $io->section("Algorithm used:");
        $io->table(['Description','Value'], [
            ['Class name', $this->algorithmInfo->getClassName()],
            ['Algorithm name', $this->algorithmInfo->getName()],
            ['Algorithm version', $this->algorithmInfo->getVersion()],
            ['Description', $this->algorithmInfo->getDescription()],
        ]);

        $io->section("Algorithm parameters configuration:");
        $io->table(['Description', 'Value'], [
            ["timerHandicapMultiplier value used in calculations", $timerHandicapMultiplier],
            ["positionPercentage value used in calculations", $positionPercentage],
            ["handicapModifier value used in calculations", $handicapModifier]
        ]);

        return Command::SUCCESS;
    }
}