<?php

namespace AppBundle\Command;

use AppBundle\Sylius\Order\OrderInterface;
use AppBundle\Entity\RestaurantReminder;
use Carbon\Carbon;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class RestaurantReminderCommand extends ContainerAwareCommand
{
    const MINUTES_BEFORE_PREPARATION = 15;

    private $orderRepository;
    // private $orderManager;
    private $restaurantReminderRepository;

    protected function configure()
    {
        $this
            ->setName('coopcycle:restaurant:reminders')
            ->setDescription('Produce or consume restaurant reminders')
            ->addOption(
                'produce',
                null,
                InputOption::VALUE_NONE,
                'Execute the command in producer mode.'
            )
            ->addOption(
                'consume',
                null,
                InputOption::VALUE_NONE,
                'Execute the command in consumer mode.'
            )
            ->addOption(
                'now',
                null,
                InputOption::VALUE_REQUIRED,
                'Simulate time.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Execute the command as a dry run.'
            )
            ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');

        $this->restaurantReminderRepository = $doctrine->getRepository(RestaurantReminder::class);
        $this->em = $doctrine->getManagerForClass(RestaurantReminder::class);
        $this->orderRepository = $this->getContainer()->get('sylius.repository.order');
        // $this->em = $this->getContainer()->get('sylius.manager.order');
        // $this->orderManager = $this->getContainer()->get('coopcycle.order_manager');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $now = $input->getOption('now');
        if ($now !== null) {
            $now = new \DateTime($now);
        } else {
            $now = new \DateTime();
        }

        $dryRun = $input->getOption('dry-run');
        $produce = $input->getOption('produce');
        $consume = $input->getOption('consume');

        $io->title('Running in producer mode');

        // TODO Select only orders with restaurant != NULL
        $orders = $this->orderRepository->findBy(
            ['state' => OrderInterface::STATE_NEW],
            ['createdAt' => 'ASC']
        );

        $orders = array_filter($orders, function (OrderInterface $order) use ($now) {
            $preparationExpectedAt = $order->getPreparationExpectedAt();
            if (null === $preparationExpectedAt) {
                return false;
            }

            return $preparationExpectedAt > $now;
        });

        $io->text(sprintf('Found %d order(s) waiting to be accepted', count($orders)));
        if (count($orders) > 0) {
            $io->listing(array_map(function (OrderInterface $order) {
                return sprintf('#%d (should start in %s)',
                    $order->getId(),
                    Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
                );
            }, $orders));
        }

        $orders = array_filter($orders, function (OrderInterface $order) use ($now) {
            $diffInMinutes = Carbon::instance($order->getPreparationExpectedAt())->diffInMinutes($now);

            return $diffInMinutes < self::MINUTES_BEFORE_PREPARATION;
        });

        $io->text(sprintf('Found %d order(s) needing reminders', count($orders)));

        $reminders = [];
        foreach ($orders as $order) {

            $qb = $this->restaurantReminderRepository
                ->createQueryBuilder('r')
                ->andWhere('r.order = :order')
                ->andWhere('r.restaurant = :restaurant')
                ->andWhere('r.restaurant = :restaurant')
                ->setParameter('order', $order)
                ->setParameter('restaurant', $order->getRestaurant());

            $reminder = $qb->getQuery()->getOneOrNullResult();

            if (null === $reminder) {

                $scheduledAt = clone $order->getPreparationExpectedAt();
                $scheduledAt->modify(sprintf('-%d minutes', self::MINUTES_BEFORE_PREPARATION));

                $reminder = new RestaurantReminder($order->getRestaurant(), $order);
                $reminder->setScheduledAt($scheduledAt);
                $reminder->setState('scheduled');

                $this->em->persist($reminder);
            }

            $reminders[] = $reminder;
        }

        $io->listing(array_map(function (RestaurantReminder $reminder) {
            if ($reminder->getId() === null) {
                return sprintf('#%d (scheduling new reminder at %s)',
                    $reminder->getOrder()->getId(),
                    $reminder->getScheduledAt()->format('H:i')
                );
            } else {
                return sprintf('#%d (reminder scheduled at %s)',
                    $reminder->getOrder()->getId(),
                    $reminder->getScheduledAt()->format('H:i')
                );
            }
        }, $reminders));

        $this->em->flush();

        // $ordersToCancel = array_filter($orders, function (OrderInterface $order) use ($now) {
        //     $preparationExpectedAt = $order->getPreparationExpectedAt();
        //     if (null === $preparationExpectedAt) {
        //         return false;
        //     }

        //     return $preparationExpectedAt < $now;
        // });

        // $io->text(sprintf('Found %d order(s) to cancel', count($ordersToCancel)));
        // if (count($ordersToCancel) > 0) {
        //     $io->listing(array_map(function (OrderInterface $order) {
        //         return sprintf('#%d (should have started %s)',
        //             $order->getId(),
        //             Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
        //         );
        //     }, $ordersToCancel));
        // }

        // $otherOrders = array_filter($orders, function (OrderInterface $order) use ($now) {
        //     $preparationExpectedAt = $order->getPreparationExpectedAt();
        //     if (null === $preparationExpectedAt) {
        //         return false;
        //     }

        //     return $preparationExpectedAt > $now;
        // });

        // $io->text(sprintf('Found %d order(s) waiting to be accepted', count($otherOrders)));
        // if (count($otherOrders) > 0) {
        //     $io->listing(array_map(function (OrderInterface $order) {
        //         return sprintf('#%d (should start in %s)',
        //             $order->getId(),
        //             Carbon::instance($order->getPreparationExpectedAt())->diffForHumans()
        //         );
        //     }, $otherOrders));
        // }

        // /* Processing */

        // if (count($ordersToCancel) > 0) {
        //     $io->section('Cancelling orders');
        //     $io->progressStart(count($ordersToCancel));
        //     foreach ($ordersToCancel as $order) {
        //         if (!$dryRun) {
        //             $this->orderManager->cancel($order);
        //             $this->em->flush();
        //         }
        //         $io->progressAdvance();
        //     }
        //     $io->progressFinish();
        // }

        // if (count($otherOrders) > 0) {
        //     $io->section('Checking reminders');
        //     foreach ($otherOrders as $order) {
        //         $diffInMinutes = Carbon::instance($order->getPreparationExpectedAt())->diffInMinutes($now);
        //         if ($diffInMinutes < 30) {

        //         } else {

        //         }
        //     }

        //     // $io->section('Cancelling orders');
        //     // $io->progressStart(count($ordersToCancel));
        //     // foreach ($ordersToCancel as $order) {
        //     //     if (!$dryRun) {
        //     //         $this->orderManager->cancel($order);
        //     //         $this->em->flush();
        //     //     }
        //     //     $io->progressAdvance();
        //     // }
        //     // $io->progressFinish();
        // }

    }
}
