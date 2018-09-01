<?php

namespace AppBundle\Api\EventSubscriber;

use ApiPlatform\Core\EventListener\EventPriorities;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

final class RestaurantSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['updateState', EventPriorities::POST_VALIDATE],
        ];
    }

    public function updateState(GetResponseForControllerResultEvent $event)
    {
        $request = $event->getRequest();

        if ('api_restaurants_put_item' !== $request->attributes->get('_route')) {
            return;
        }
    }
}
