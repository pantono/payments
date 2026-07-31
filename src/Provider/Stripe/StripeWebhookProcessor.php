<?php

namespace Pantono\Payments\Provider\Stripe;

use Pantono\Payments\Payments;
use Stripe\Event;
use Pantono\Payments\Model\PaymentStatus;
use Symfony\Component\HttpFoundation\ParameterBag;

class StripeWebhookProcessor
{
    private Payments $payments;
    private ParameterBag $parameters;
    private ParameterBag $allParameters;
    private Event $event;

    public function __construct(Payments $payments, Event $event)
    {
        $this->payments = $payments;
        $data = $event->toArray();
        if (!isset($data['data']['object'])) {
            throw new \RuntimeException('Invalid Stripe event data');
        }
        $this->parameters = new ParameterBag($data['data']['object']);
        $this->allParameters = new ParameterBag($data);
        $this->event = $event;
    }

    public function process(): void
    {
        if ($this->event->type === Event::PAYMENT_INTENT_CREATED) {
            $this->logHistoryForAttemptId($this->parameters->get('id'), 'Stripe payment created webhook received', $this->parameters->all());
            return;
        }

        if ($this->event->type === Event::PAYMENT_INTENT_SUCCEEDED) {
            $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
            $payment = $this->payments->getPaymentByProviderId($this->parameters->get('id'));
            $this->logHistoryForAttemptId($this->parameters->get('id'), 'Stripe payment succeeded webhook received', $this->parameters->all(), $status);
            if ($payment) {
                $charge = $this->getChargeData();
                $cardData = $this->getCardData($charge);

                $payment->setResponseData($this->parameters->all());
                if ($cardData !== []) {
                    $payment->setCardData($cardData);
                    $payment->setPaymentMethodName($this->getPaymentMethodName($cardData));
                    $payment->setAuthCode($this->getAuthCode($cardData));
                }
                if ($status) {
                    $payment->setStatus($status);
                }
                $this->payments->savePayment($payment);
            }
        }

        if ($this->event->type === Event::PAYMENT_INTENT_PAYMENT_FAILED) {
            $status = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
            $payment = $this->payments->getPaymentByProviderId($this->parameters->get('id'));
            $this->logHistoryForAttemptId($this->parameters->get('id'), 'Stripe payment failed webhook received', $this->parameters->all(), $status);
            if ($payment) {
                if ($status) {
                    $payment->setStatus($status);
                }
                $this->payments->savePayment($payment);
            }
        }

        if ($this->event->type === Event::SETUP_INTENT_SUCCEEDED) {
            $id = $this->parameters->get('id');
            $mandate = $this->payments->getMandateByReference($id);
            if ($mandate) {
                $mandate->setResponseData($this->parameters->all());
                if ($this->parameters->get('status') === 'succeeded') {
                    $mandate->setStartDate(new \DateTimeImmutable());
                    $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ACTIVE);
                    if ($status) {
                        $mandate->setStatus($status);
                        $this->payments->addHistoryToMandate($mandate, 'Completed mandate setup', $this->parameters->all());
                    }
                }
                $this->payments->saveMandate($mandate);
            }
        }

        if ($this->event->type === Event::REFUND_CREATED) {
            $id = $this->parameters->get('payment_intent');
            $payment = $this->payments->getPaymentByProviderId($id);
            if ($payment) {
                $this->logHistoryForAttemptId($this->parameters->get('payment_intent'), 'Refund created', $this->parameters->all());
            }
        }
    }

    private function getChargeData(): array
    {
        $charges = $this->parameters->get('charges');
        if (is_array($charges) && isset($charges['data'][0]) && is_array($charges['data'][0])) {
            return $charges['data'][0];
        }

        $latestCharge = $this->parameters->get('latest_charge');
        if (is_array($latestCharge)) {
            return $latestCharge;
        }

        return [];
    }

    private function getCardData(array $charge): array
    {
        return $charge['payment_method_details']['card'] ?? [];
    }

    private function getPaymentMethodName(array $cardData): ?string
    {
        $brand = $cardData['display_brand'] ?? $cardData['brand'] ?? null;
        $last4 = $cardData['last4'] ?? null;
        if ($brand && $last4) {
            return $brand . ' ending ' . $last4;
        }

        return $brand;
    }

    private function getAuthCode(array $cardData): ?string
    {
        return $cardData['authorization_code'] ?? $cardData['network_transaction_id'] ?? null;
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
}
