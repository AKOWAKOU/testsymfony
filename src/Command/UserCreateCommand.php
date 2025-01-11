<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create users',
)]
class BillsUserCreateCommand extends Command
{
    protected EntityManagerInterface $em;
    protected UserPasswordHasherInterface $hasher;

    public function __construct(EntityManagerInterface $em, UserPasswordHasherInterface $hasher, string $name = null)
    {
        parent::__construct($name);

        $this->em = $em;
        $this->hasher = $hasher;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addArgument('name', InputArgument::REQUIRED, 'User name')
            ->addArgument('password', InputArgument::REQUIRED, 'User password')
            ->addOption('super', 's', InputOption::VALUE_NONE, 'Make super user');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $email = $input->getArgument('email');
        $name = $input->getArgument('name');
        $password = $input->getArgument('password');

        if (null !== $email && null !== $name && null !== $password) {
            return;
        }

        $io = new SymfonyStyle($input, $output);

        $io->title('Interactive wizard for new security user creation');
        $io->text([
            'If you prefer not to use this interactive wizard,',
            'just provide the required arguments in the given order:',
            '<question>$ bin/console app:user:create <email> <name> <password></question>',
            'You always will be asked for the missing arguments.',
        ]);
        $io->newLine();

        // ask for the <email> if missing
        if (null !== $email) {
            $io->text('<info>User email</info>: ' . $email);
        } else {
            $email = $io->ask('User email');

            $input->setArgument('email', $email);
        }

        // ask for the <name> if missing
        if (null !== $name) {
            $io->text('<info>User full name</info>: ' . $name);
        } else {
            $name = $io->ask('User full name');
            $input->setArgument('name', $name);
        }

        // ask for the <password> if missing
        if (null !== $password) {
            $io->text('<info>User password</info>: ' . $password);
        } else {
            $password = $io->askHidden('User password');
            $input->setArgument('password', $password);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $name = $input->getArgument('name');
        $email = $input->getArgument('email');
        $password = $input->getArgument('password');
        $super = $input->getOption('super');

        if ($email) {
            $io->note(sprintf('You passed an argument: %s', $email));
        }
        if ($name) {
            $io->note(sprintf('You passed an argument: %s', $name));
        }
        if ($password) {
            $io->note(sprintf('You passed an argument: %s', $password));
        }

        $confirm = $io->confirm('Proceed?');
        if (!$confirm) {
            return 0;
        }

        // check if the user already exists
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user) {
            $io->error(sprintf('There\'s an user with email %s', $email));
            return 0;
        }

        $user = new User();
        $user
            ->setEmail($email)
            ->setName($name)
            ->setPassword($this->hasher->hashPassword($user, $password));

        if ($super) {
            $user->addRole('ROLE_SUPER');
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success(sprintf("The user %s <%s> has been successfully created!", $user->getName(), $user->getEmail()));

        return Command::SUCCESS;
    }
}
