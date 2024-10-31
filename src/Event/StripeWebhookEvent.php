<?php

namespace Pantono\Payments\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Payments\Model\StripeWebhook;

class StripeWebhookEvent extends Event
{
    private StripeWebhook $webhook;

    public function getWebhook(): StripeWebhook
    {
        return $this->webhook;
    }

    public function setWebhook(StripeWebhook $webhook): void
    {
        $this->webhook = $webhook;
    }
}
