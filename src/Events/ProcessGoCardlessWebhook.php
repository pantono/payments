<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Payments\Payments;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Payments\Provider\Stripe;
use Pantono\Payments\Provider\GoCardless;

class ProcessGoCardlessWebhook implements EventSubscriberInterface
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentWebhookEvent::class => ['handleGoCardlessWebhook', 253]
        ];
    }

    public function handleGoCardlessWebhook(PaymentWebhookEvent $event)
    {
        $gateway = $event->getWebhook()->getGateway();
        if ($gateway->getProvider()->getController() === GoCardless::class) {
            
        }
    }
}
