<?php

namespace Pantono\Payments\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Payments\Model\PaymentGateway;

class AbstractGatewaySaveEvent extends Event
{
    private PaymentGateway $current;
    private ?PaymentGateway $previous = null;

    public function getCurrent(): PaymentGateway
    {
        return $this->current;
    }

    public function setCurrent(PaymentGateway $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?PaymentGateway
    {
        return $this->previous;
    }

    public function setPrevious(?PaymentGateway $previous): void
    {
        $this->previous = $previous;
    }
}
