<?php

namespace AppBundle\Domain\Order\Reactor;

use AppBundle\Domain\Order\Event\OrderCreated;
use AppBundle\Entity\Restaurant;
use AppBundle\Service\RemotePushNotificationManager;
use Symfony\Component\Serializer\SerializerInterface;

class SendRemotePushNotification
{
    private $remotePushNotificationManager;
    private $serializer;

    public function __construct(
        RemotePushNotificationManager $remotePushNotificationManager,
        SerializerInterface $serializer)
    {
        $this->remotePushNotificationManager = $remotePushNotificationManager;
        $this->serializer = $serializer;
    }

    public function __invoke($event)
    {
        $order = $event->getOrder();

        if ($event instanceof OrderCreated && $order->isFoodtech()) {

            $owners = $order->getRestaurant()->getOwners()->toArray();

            if (count($owners) > 0) {

                $restaurantNormalized = $this->normalizeRestaurant($order->getRestaurant());

                $data = [
                    'event' => [
                        'name' => 'order:created',
                        'data' => [
                            'restaurant' => $restaurantNormalized,
                            'date' => $order->getShippedAt()->format('Y-m-d')
                        ]
                    ]
                ];

                $this->remotePushNotificationManager
                    ->send('New order to accept', $owners, $data);
            }
        }
    }

    private function normalizeRestaurant(Restaurant $restaurant)
    {
        $restaurantNormalized = $this->serializer->normalize($restaurant, 'jsonld', [
            'resource_class' => Restaurant::class,
            'operation_type' => 'item',
            'item_operation_name' => 'get'
        ]);

        return [
            '@id' => $restaurantNormalized['@id'],
            'name' => $restaurantNormalized['name']
        ];
    }
}
