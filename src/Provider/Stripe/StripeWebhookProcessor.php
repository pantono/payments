<?php

namespace Pantono\Payments\Provider\Stripe;

use Pantono\Payments\Payments;
use Stripe\Event;
use Pantono\Payments\Model\PaymentStatus;
use Symfony\Component\HttpFoundation\ParameterBag;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Customers\Customers;
use Pantono\Utilities\DateTimeParser;
use Pantono\Payments\Provider\Stripe;

class StripeWebhookProcessor
{
    private Payments $payments;
    private ParameterBag $parameters;
    private Event $event;
    private Customers $customers;

    public function __construct(Payments $payments, Event $event, Customers $customers)
    {
        $this->payments = $payments;
        $data = $event->toArray();
        if (!isset($data['data']['object'])) {
            throw new \RuntimeException('Invalid Stripe event data');
        }
        $this->parameters = new ParameterBag($data['data']['object']);
        $this->event = $event;
        $this->customers = $customers;
    }

    public function process(): void
    {
        if ($this->event->type === Event::PAYMENT_INTENT_CREATED) {
            $this->logHistoryForAttemptId($this->parameters->get('id'), 'Stripe payment created webhook received', $this->parameters->all());
            return;
        }

        if ($this->event->type === Event::PAYMENT_INTENT_SUCCEEDED) {
            //Expire it as the setup data is no longer required, we store the payment method
            $status = $this->payments->getPaymentStatusById(Payments::MANDATE_STATUS_EXPIRED);
            $payment = $this->payments->getPaymentByProviderId($this->parameters->get('id'));
            $this->logHistoryForAttemptId($this->parameters->get('id'), 'Stripe payment succeeded webhook received', $this->parameters->all(), $status);
            if ($payment) {
                $charge = $this->getChargeData();
                $cardData = $this->getCardData($charge);

                $payment->setResponseData($this->parameters->all());
                if ($cardData !== []) {
                    $payment->setCardData($cardData);
                    $payment->setAuthCode($this->getAuthCode($cardData));
                }
                $methodName = StripePaymentMethodDescriber::describe($this->getPaymentMethodDetails($charge));
                if ($methodName !== null) {
                    $payment->setPaymentMethodName($methodName);
                }
                if ($status) {
                    $payment->setStatus($status);
                }
                $this->payments->savePayment($payment);
            }
        }
        if ($this->event->type === Event::PAYMENT_METHOD_DETACHED) {
            $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_CANCELLED);
            if (!$status) {
                throw new \RuntimeException('Payment status not found');
            }
            $id = $this->parameters->get('id');
            if ($this->parameters->get('object') === 'payment_method') {
                $mandate = $this->payments->getMandateByReference($id);
                if ($mandate) {
                    $mandate->setStatus($status);
                    $this->payments->saveMandate($mandate);
                }
            }
        }

        if ($this->event->type === Event::PAYMENT_METHOD_UPDATED) {
            $id = $this->parameters->get('id');
            if ($this->parameters->get('object') === 'payment_method') {
                $mandate = $this->payments->getMandateByReference($id);
                if ($mandate) {
                    $card = new ParameterBag($this->parameters->get('card', []));
                    if ($card->has('exp_month') && $card->has('exp_year')) {
                        $date = DateTimeParser::parseDateImmutable($card->get('exp_year') . '-' . $card->get('exp_month') . '-01');
                        if ($date) {
                            $mandate->setEndDate($date);
                            $this->payments->saveMandate($mandate);
                        }
                    }
                }
            }
        }

        if ($this->event->type === Event::PAYMENT_METHOD_ATTACHED) {
            $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ACTIVE);
            if (!$status) {
                throw new \RuntimeException('Payment status not found');
            }
            $id = $this->parameters->get('id');
            if ($this->parameters->get('object') === 'payment_method') {
                $customer = $this->customers->getCustomerByExternalIdentifier('stripe', $this->parameters->get('customer'));
                if ($customer) {
                    $gateways = $this->payments->getGatewaysByProviderId(Stripe::PROVIDER_ID);
                    if (empty($gateways)) {
                        throw new \RuntimeException('No stripe providers available');
                    }
                    $mandate = new PaymentMandate();
                    $mandate->setStartDate(new \DateTimeImmutable());
                    $mandate->setReference($id);
                    $mandate->setSetupData([]);
                    $mandate->setPaymentGateway($gateways[0]);
                    $mandate->setCurrency('');
                    $card = new ParameterBag($this->parameters->get('card', []));
                    $cardParts = [];
                    if ($card->has('brand')) {
                        $cardParts[] = $card->get('brand');
                    }
                    if ($card->has('last4')) {
                        $cardParts[] = $card->get('last4');
                    }
                    if ($card->has('exp_month') && $card->has('exp_year')) {
                        $date = DateTimeParser::parseDateImmutable($card->get('exp_year') . '-' . $card->get('exp_month') . '-01');
                        if ($date) {
                            $mandate->setEndDate($date);
                        }
                    }
                    $mandate->setDescription(implode(' ', $cardParts));
                    $mandate->setResponseData($this->parameters->all());
                    $mandate->setCustomer($customer);
                    $mandate->setStatus($status);
                    $this->payments->saveMandate($mandate);
                }
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

    private function getPaymentMethodDetails(array $charge): array
    {
        $details = $charge['payment_method_details'] ?? [];
        if (!is_array($details)) {
            return [];
        }

        return $details;
    }

    private function getCardData(array $charge): array
    {
        $cardData = $charge['payment_method_details']['card'] ?? [];
        if (!is_array($cardData)) {
            return [];
        }

        return $cardData;
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
