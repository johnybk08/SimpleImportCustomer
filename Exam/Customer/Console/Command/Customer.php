<?php

namespace Exam\Customer\Console\Command;

use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Customer extends Command
{
    protected $state;

    /**
     * Customer constructor.
     * @param State $state
     * @param string $name
     */
    public function __construct(
        State $state,
        $name = null
    ) {
        parent::__construct($name);
        $this->state = $state;
    }

    /**
     * Configure console
     *
     * @throws LocalizedException
     */
    protected function configure()
    {
        $this->setName('exam:import:customer');
        $this->setDescription('Importing Customer');

        parent::configure();
    }

    /**
     * Execute console
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws LocalizedException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln("start");
    }
}
