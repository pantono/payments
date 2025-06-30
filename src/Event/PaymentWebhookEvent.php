<?php

namespace Pantono\Payments\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Payments\Model\PaymentWebhook;

class PaymentWebhookEvent extends Event
{
    private PaymentWebhook $webhook;

    public function getWebhook(): PaymentWebhook
    {
        return $this->webhook;
    }

    public function setWebhook(PaymentWebhook $webhook): void
    {
        $this->webhook = $webhook;
    }
}
