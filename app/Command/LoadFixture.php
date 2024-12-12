<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\Fixtures\EpisodeDataLoader;
use App\DataFixtures\Fixtures\ShowDataLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class LoadFixture extends Command
{

    public function __construct(
        private readonly EntityManager $entityManager,
        private string $name = 'fixtures:load',
    ) {
        parent::__construct($name);
        $this->setDescription('Load data fixtures.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $loader = new Loader();
        $loader->addFixture(new ShowDataLoader);
        $loader->addFixture(new EpisodeDataLoader);

        $executor = new ORMExecutor($this->entityManager, new ORMPurger());
        $executor->execute($loader->getFixtures());


        $output->write('Fixtures loaded into DB.', true);
        return Command::SUCCESS;
    }
}
