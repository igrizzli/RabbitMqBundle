<?php

namespace OldSound\RabbitMqBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use OldSound\RabbitMqBundle\RabbitMq\BaseConsumer as Consumer;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand as Command;

abstract class BaseConsumerCommand extends BaseRabbitMqCommand
{
    protected $consumer;

    protected $amount;

    abstract protected function getConsumerService();

    public function stopConsumer()
    {
        if($this->consumer instanceof Consumer) {
            $this->consumer->forceStopConsumer();
        }else{
            exit();
        }
    }

    public function restartConsumer()
    {

    }

    protected function configure()
    {
        parent::configure();

        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Consumer Name')
            ->addOption('messages', 'm', InputOption::VALUE_OPTIONAL, 'Messages to consume', 0)
            ->addOption('route', 'r', InputOption::VALUE_OPTIONAL, 'Routing Key', '')
            ->addOption('debug', 'd', InputOption::VALUE_OPTIONAL, 'Enable Debugging', false)
            ->addOption('with-signals', 'w', InputOption::VALUE_OPTIONAL, 'Enable catching of system signals', false)
        ;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input  An InputInterface instance
     * @param OutputInterface $output An OutputInterface instance
     *
     * @return integer 0 if everything went fine, or an error code
     *
     * @throws \InvalidArgumentException When the number of messages to consume is less than 0
     * @throws \InvalidArgumentException When the pcntl is not installed and option -s is true
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $signals = $input->getOption('with-signals');
        if($signals){
            if(extension_loaded('pcntl')){
                pcntl_signal(SIGTERM, array(&$this, 'stopConsumer'));
                pcntl_signal(SIGINT, array(&$this, 'stopConsumer'));
                pcntl_signal(SIGHUP, array(&$this, 'restartConsumer'));
            }else{
                throw new \InvalidArgumentException("The -w option can be used, if pcntl extension installed");
            }
        }

        define('AMQP_DEBUG', (bool) $input->getOption('debug'));

        $this->amount = $input->getOption('messages');

        if (0 > $this->amount) {
            throw new \InvalidArgumentException("The -m option should be null or greater than 0");
        }

        $this->consumer = $this->getContainer()
            ->get(sprintf($this->getConsumerService(), $input->getArgument('name')));

        $this->consumer->setRoutingKey($input->getOption('route'));
        $this->consumer->consume($this->amount);
    }
}