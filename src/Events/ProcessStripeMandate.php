<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\PaymentWebhook;
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
            PaymentWebhookEvent::class => ['handleStripeWebhook', 255]
        ];
    }

    public function handleStripeWebhook(PaymentWebhookEvent $event): void
    {
        if ($event->getWebhook()->getGateway()->getProvider()->getController() === Stripe::class) {
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
            if ($event->getWebhook()->getType() === 'checkout.session.completed') {
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
    }

    /**
     * @param PaymentWebhook $webhook
     * @return Stripe
     */
    private function getControllerFromWebhook(PaymentWebhook $webhook): AbstractProvider
    {
        return $this->payments->getProviderController($webhook->getGateway());
    }
}
