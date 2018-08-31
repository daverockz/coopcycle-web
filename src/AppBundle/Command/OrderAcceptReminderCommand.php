<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class OrderAcceptReminderCommand extends ContainerAwareCommand
{
    private $orderRepository;
    private $orderManager;

    protected function configure()
    {
        $this
            ->setName('coopcycle:orders:reminders')
            ->setDescription('Sends reminders for orders.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Execute the command as a dry run.'
            );
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->orderRepository = $this->getContainer()->get('sylius.repository.order');
        $this->em = $this->getContainer()->get('sylius.manager.order');
        $this->orderManager = $this->getContainer()->get('coopcycle.order_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $dryRun = $input->getOption('dry-run');

        $now = new \DateTime();

        $io->section('Retrieving orders');

        $orders = $this->orderRepository->findBy(
            ['state' => OrderInterface::STATE_NEW],
            ['createdAt' => 'ASC']
        );

        $io->text(sprintf('Found %d orders in state "%s"', count($orders), OrderInterface::STATE_NEW));

        $ordersToCancel = array_filter($orders, function (OrderInterface $order) use ($now) {
            $preparationExpectedAt = $order->getPreparationExpectedAt();
            if (null === $preparationExpectedAt) {
                return false;
            }

            return $preparationExpectedAt < $now;
        });

        $io->text(sprintf('Found %d order(s) to cancel', count($ordersToCancel)));
        if (count($ordersToCancel) > 0) {
            $io->listing(array_map(function (OrderInterface $order) {
                return sprintf('#%d (should have started %s)',
                    $order->getId(),
                    Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
                );
            }, $ordersToCancel));
        }

        $otherOrders = array_filter($orders, function (OrderInterface $order) use ($now) {
            $preparationExpectedAt = $order->getPreparationExpectedAt();
            if (null === $preparationExpectedAt) {
                return false;
            }

            return $preparationExpectedAt > $now;
        });

        $io->text(sprintf('Found %d order(s) waiting to be accepted', count($otherOrders)));
        if (count($otherOrders) > 0) {
            $io->listing(array_map(function (OrderInterface $order) {
                return sprintf('#%d (should start in %s)',
                    $order->getId(),
                    Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
                );
            }, $otherOrders));
        }

        if (count($ordersToCancel) > 0) {
            $io->section('Cancelling orders');
            $io->progressStart(count($ordersToCancel));
            foreach ($ordersToCancel as $order) {
                if (!$dryRun) {
                    $this->orderManager->cancel($order);
                    $this->em->flush();
                }
                $io->progressAdvance();
            }
            $io->progressFinish();
        }

    }
}
