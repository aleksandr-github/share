<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

class CreateAppUserCommand extends Command
{
    protected static $defaultName = 'app:create-user';
    protected $passwordEncoder;
    protected $userRepository;
    protected $objectManager;

    public function __construct(UserPasswordEncoderInterface $passwordEncoder, UserRepository $userRepository, EntityManagerInterface $objectManager, string $name = null)
    {
        $this->passwordEncoder = $passwordEncoder;
        $this->userRepository = $userRepository;
        $this->objectManager = $objectManager;

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setDescription('Creates user for app usage.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title("User creation wizard.");

        $io->section("Current users in DB");
        $currentUsers = $this->userRepository->findAll();
        $usersTable = [];
        foreach ($currentUsers as $currentUser) {
            $usersTable[] = [$currentUser->getId(), $currentUser->getUsername()];
        }
        $io->table(
            ['ID', 'Username'],
            $usersTable
        );
        $io->newLine();

        $username = $io->ask("Enter desired username for login purposes");
        $password = $io->askHidden("Enter password for username " . $username);

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->passwordEncoder->encodePassword(
            $user,
            $password
        ));
        try {
            $this->objectManager->persist($user);
            $this->objectManager->flush();
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success("User " . $username . " created. You can login to app with your credentials.");

        return Command::SUCCESS;
    }
}