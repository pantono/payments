<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Payments\Payments;
use Pantono\Payments\Provider\Stripe;

class ProcessStripeWebhook implements EventSubscriberInterface
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PaymentWebhookEvent::class => ['handleStripeWebhook', 254]
        ];
    }


    public function handleStripeWebhook(PaymentWebhookEvent $event): void
    {
        $gateway = $event->getWebhook()->getGateway();
        if ($gateway->getProvider()->getController() === Stripe::class) {
            $this->payments->getProviderController($gateway)->ingestWebhook($event->getWebhook());
        }
    }
}
