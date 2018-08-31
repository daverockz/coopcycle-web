<?php

namespace AppBundle\Entity;

use AppBundle\Sylius\Order\OrderInterface;

class RestaurantReminder
{
    private $id;
    private $restaurant;
    private $order;
    private $scheduledAt;
    private $state;
    private $createdAt;
    private $updatedAt;

    public function __construct(Restaurant $restaurant, OrderInterface $order)
    {
        $this->restaurant = $restaurant;
        $this->order = $order;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRestaurant()
    {
        return $this->restaurant;
    }

    public function getOrder()
    {
        return $this->order;
    }

    public function getState()
    {
        return $this->state;
    }

    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    public function getScheduledAt()
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt($scheduledAt)
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }
}
