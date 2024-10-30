<?php

namespace Pantono\Payments\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Payments\Model\PaymentMandate;

class AbstractMandateSaveEvent extends Event
{
    private PaymentMandate $current;
    private ?PaymentMandate $previous = null;

    public function getCurrent(): PaymentMandate
    {
        return $this->current;
    }

    public function setCurrent(PaymentMandate $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?PaymentMandate
    {
        return $this->previous;
    }

    public function setPrevious(?PaymentMandate $previous): void
    {
        $this->previous = $previous;
    }
}
