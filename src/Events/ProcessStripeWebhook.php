<?php

namespace Pantono\Payments\Events;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Payments\Payments;
use Pantono\Payments\Provider\Stripe;
use Symfony\Component\HttpFoundation\ParameterBag;
use Pantono\Payments\Model\PaymentStatus;

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
            PaymentWebhookEvent::class => ['handleStripeWebhook', 255]
        ];
    }


    public function handleStripeWebhook(PaymentWebhookEvent $event): void
    {
        $gateway = $event->getWebhook()->getGateway();
        if ($gateway->getProvider()->getController() === Stripe::class) {
            $data = $this->getObjectFromData($event->getWebhook()->getData());
            if (!$data) {
                return;
            }
            if ($event->getWebhook()->getData()['type'] === 'payment_intent.created') {
                $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment created webhook received', $event->getWebhook()->getData());
            }
            if ($event->getWebhook()->getData()['type'] === 'payment_intent.succeeded') {
                $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
                $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment succeeded webhook received', $event->getWebhook()->getData(), $status);
            }
            if ($event->getWebhook()->getData()['type'] === 'payment_intent.payment_failed') {
                $status = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
                $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment failed webhook received', $event->getWebhook()->getData(), $status);
            }

            if ($event->getWebhook()->getData()['type'] === 'charge.succeeded') {
                $this->logHistoryForAttemptId($data->get('payment_intent'), 'Stripe payment charge succeeded', $event->getWebhook()->getData());
            }
            if ($event->getWebhook()->getData()['type'] === 'charge.failed') {
                $this->logHistoryForAttemptId($data->get('payment_intent'), 'Stripe payment charge failed', $event->getWebhook()->getData());
            }
            if ($event->getWebhook()->getData()['type'] === 'charge.updated') {
                $this->logHistoryForAttemptId($data->get('payment_intent'), 'Stripe payment charge updated', $event->getWebhook()->getData());
            }
            if ($event->getWebhook()->getData()['type'] === 'charge.dispute.created') {
                $this->logHistoryForAttemptId($data->get('payment_intent'), 'Stripe dispute received', $event->getWebhook()->getData());
            }
            if ($event->getWebhook()->getData()['type'] === 'charge.dispute.funds_withdrawn') {
                $status = $this->payments->getPaymentStatusById(Payments::STATUS_CHARGEBACK);
                $this->logHistoryForAttemptId($data->get('payment_intent'), 'Stripe funds withdrawn', $event->getWebhook()->getData(), $status);
            }
        }
    }

    private function logHistoryForAttemptId(string $providerPaymentId, string $entry, array $data, ?PaymentStatus $status = null): void
    {
        $payment = $this->payments->getPaymentByProviderId($providerPaymentId);
        if ($payment) {
            $this->payments->addHistoryToPayment($payment, $entry, $data);
            if ($status !== null) {
                $payment->setStatus($status);
                $this->payments->savePayment($payment);
            }
        }
    }

    private function getObjectFromData(array $data): ?ParameterBag
    {
        if (!isset($data['data']['object'])) {
            return null;
        }
        return new ParameterBag($data['data']['object']);
    }
}
