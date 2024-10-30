<?php

namespace Pantono\Payments\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Payments\Model\Payment;

abstract class AbstractPaymentSaveEvent extends Event
{
    private Payment $current;
    private ?Payment $previous = null;

    public function getCurrent(): Payment
    {
        return $this->current;
    }

    public function setCurrent(Payment $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?Payment
    {
        return $this->previous;
    }

    public function setPrevious(?Payment $previous): void
    {
        $this->previous = $previous;
    }
}
