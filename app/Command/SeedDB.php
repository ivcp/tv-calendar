<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\Seeds\EpisodeSeedDataLoader;
use App\DataFixtures\Seeds\ShowSeedDataLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SeedDB extends Command
{
    public function __construct(
        private readonly EntityManager $entityManager,
        private string $name = 'db:seed',
    ) {
        parent::__construct($name);
        $this->setDescription('Seed database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        $timeStart = microtime(true);
        $output->write('Loading data...', true);

        $loader = new Loader();
        $loader->addFixture(new ShowSeedDataLoader());
        $loader->addFixture(new EpisodeSeedDataLoader());
        $executor = new ORMExecutor($this->entityManager, new ORMPurger());
        $executor->execute($loader->getFixtures());

        $timeEnd = microtime(true);
        $executionTime = $timeEnd - $timeStart;
        $output->write('DB seeded.', true);
        $output->write('Total Execution Time: ' . round($executionTime, 1) . ' sec.', true);
        return Command::SUCCESS;
    }
}
