<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/8
 * Time: 19:50
 */

namespace rabbit\wsserver;


use rabbit\core\ObjectFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class WebsocketCommand
 * @package rabbit\wsserver
 */
class WebsocketCommand extends Command
{
    /**
     *
     */
    protected function configure(): void
    {
        $this->setName('ws:server')->setDescription('start|stop|reload websocketserver')->setHelp('This command allows you to start|stop|reload websocketserver.')
            ->addArgument('cmd', InputArgument::REQUIRED, 'start|stop|reload');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        $cmd = $input->getArgument('cmd');
        ObjectFactory::get('websocketserver')->$cmd();
    }
}