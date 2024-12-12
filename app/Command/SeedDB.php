<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\Seeds\ShowSeedDataLoader;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManager;
use JsonMachine\Items;
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
        $output->write('Memory usage start: ' . memory_get_usage(), true);
        $output->write('Unit of work before: ' . $this->entityManager->getUnitOfWork()->size(), true);


        $loader = new Loader();
        $loader->addFixture(new ShowSeedDataLoader);

        $executor = new ORMExecutor($this->entityManager, new ORMPurger());
        $executor->execute($loader->getFixtures());


        $timeEnd = microtime(true);
        $executionTime = ($timeEnd - $timeStart) / 60;
        $output->write('DB seeded.', true);
        $output->write('Memory usage end: ' . memory_get_usage(), true);
        $output->write('Unit of work after: ' . $this->entityManager->getUnitOfWork()->size(), true);
        $output->write('Total Execution Time: ' . round($executionTime, 1) . ' min.', true);
        return Command::SUCCESS;
    }
}
