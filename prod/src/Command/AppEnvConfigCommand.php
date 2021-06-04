<?php

namespace App\Command;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class AppEnvConfigCommand extends Command
{
    protected static $defaultName = 'run:config';
    protected $container;
    protected $filesystem;
    protected $appEnv;

    public function __construct(ContainerInterface $container, Filesystem $filesystem, string $name = null)
    {
        $this->container = $container;
        $this->filesystem = $filesystem;
        $this->appEnv = $this->container->get('kernel')->getEnvironment();

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Creates new configuration.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("App .env configuration.");
        $io->text('Using environment : <info>' . $this->appEnv . "</info>");
        $io->section("Interactive configuration");
        $currentVars = $this->getCurrentEnvVars();

        $answers = [];
        foreach ($currentVars as $varName => $value) {
            $defaultValue = $this->defaultValues()[$varName];

            $io->write("Setting up <info>" . $varName . "</info> env variable. Default value is: <info>" . $defaultValue . "</info>");
            $answers[$varName] = $io->ask("What value would you like?", $value);
        }

        $io->section("Setting up ENV's, please wait");
        $envFileContents = "";
        foreach ($answers as $field => $answer) {
            $envFileContents .= $field . '=' . $answer . PHP_EOL;
        }

        $envPath = APP_ROOT . DIRECTORY_SEPARATOR . '.env.' . $this->container->get('kernel')->getEnvironment();
        if (!$this->filesystem->exists($envPath)) {
            throw new \LogicException('Not .env file configured!');
        };
        $this->filesystem->dumpFile($envPath, $envFileContents);

        if ($this->appEnv === 'prod') {
            // execute var dump
            $out = [];
            $outCode = 0;
            $io->info("Dumping contents of environment variables as .env.local.php");
            exec('php composer.phar dump-env prod', $out, $outCode);
        }

        $command = $this->getApplication()->find('cache:clear');
        $arguments = [
            '--env' => $this->appEnv,
        ];
        $input = new ArrayInput($arguments);
        $command->run($input, $output);

        $io->success('Env variables has been set.');

        return Command::SUCCESS;
    }

    protected function getCurrentEnvVars(bool $asTable = false)
    {
        $envs = [];
        if ($asTable) {
            foreach ($_ENV as $key => $item) {
                if (!in_array($key, $this->protectedValues()))
                    $envs[] = [$key, $item];
            }
        } else {
            foreach ($_ENV as $key => $item) {
                if (!in_array($key, $this->protectedValues()))
                    $envs[$key] = $item;
            }
        }

        return $envs;
    }

    protected function protectedValues(): array
    {
        return [
            'APP_ENV', 'APP_SECRET', 'DATABASE_URL', 'appVersion', 'SYMFONY_DOTENV_VARS',
            'APP_DEBUG', 'SHELL_VERBOSITY', 'SENTRY_DSN'
        ];
    }

    protected function defaultValues(): array
    {
        return [
            "dbservername" => "localhost",
            "dbdatabase" => "database",
            "dbusername" => "username",
            "dbpassword" => "password",
            "mysqliWorkersNumber" => "6",
            "httpWorkersNumber" => "6",
            "oddsThreshold" => "3",
            "timerHandicapMultiplier" => "0.0007",
            "positionPercentage" => "40",
            "handicapModifier" => "0.025",
        ];
    }
}