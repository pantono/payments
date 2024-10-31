<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Payments\Event\StripeWebhookEvent;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\StripeWebhook;
use Pantono\Payments\Provider\Stripe;
use Pantono\Payments\Provider\AbstractProvider;

class ProcessStripeMandate implements EventSubscriberInterface
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StripeWebhookEvent::class => ['handleStripeWebhook', 255]
        ];
    }

    public function handleStripeWebhook(StripeWebhookEvent $event): void
    {
        if ($event->getWebhook()->getType() === 'setup_intent.succeeded') {
            $controller = $this->getControllerFromWebhook($event->getWebhook());

            $intentId = $event->getWebhook()->getDataValue('id');
            $mandate = $this->payments->getMandateByReference($intentId);
            if (!$mandate) {
                throw new \RuntimeException('Cannot find mandate ' . $intentId);
            }
            $data = $event->getWebhook()->getObjectData();
            if (!$data) {
                throw new \RuntimeException('Cannot find object data in response');
            }
            $controller->completeMandate($mandate, $data);
        }
    }

    private function getControllerFromWebhook(StripeWebhook $webhook): Stripe
    {
        $controller = $this->payments->getProviderController($webhook->getGateway());
        if ($controller instanceof Stripe) {
            return $controller;
        }
        throw new \RuntimeException('Payment controller is invalid type');
    }
}
