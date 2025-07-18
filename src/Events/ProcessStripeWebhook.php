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
            PaymentWebhookEvent::class => ['handleStripeWebhook', 254]
        ];
    }


    public function handleStripeWebhook(PaymentWebhookEvent $event): void
    {
        $gateway = $event->getWebhook()->getGateway();
        if ($gateway->getProvider()->getController() === Stripe::class) {
            /**
             * @var Stripe $controller
             */
            $controller = $this->payments->getProviderController($gateway);
            if ($controller->verifyWebhook($event->getWebhook())) {
                $event->getWebhook()->setType($event->getWebhook()->getData()['type']);
                $event->getWebhook()->setVerified(true);
                $this->payments->saveWebhook($event->getWebhook());
                $data = $this->getObjectFromData($event->getWebhook()->getData());
                if (!$data) {
                    return;
                }
                //Payment Intents
                if ($event->getWebhook()->getData()['type'] === 'payment_intent.created') {
                    $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment created webhook received', $event->getWebhook()->getData());
                }
                if ($event->getWebhook()->getData()['type'] === 'payment_intent.succeeded') {
                    $status = $this->payments->getPaymentStatusById(Payments::STATUS_COMPLETED);
                    $payment = $this->payments->getPaymentByProviderId($data->get('id'));
                    if ($payment) {
                        $payment->setResponseData($data->all());
                    }
                    $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment succeeded webhook received', $event->getWebhook()->getData(), $status);
                }
                if ($event->getWebhook()->getData()['type'] === 'payment_intent.payment_failed') {
                    $status = $this->payments->getPaymentStatusById(Payments::STATUS_FAILED);
                    $this->logHistoryForAttemptId($data->get('id'), 'Stripe payment failed webhook received', $event->getWebhook()->getData(), $status);
                }

                //Mandates
                if ($event->getWebhook()->getData()['type'] === 'setup_intent.succeeded') {
                    $id = $data->get('id');
                    $mandate = $this->payments->getMandateByReference($id);
                    if ($mandate) {
                        $mandate->setResponseData($data->all());
                        if ($data->get('status') === 'succeeded') {
                            $mandate->setStartDate(new \DateTimeImmutable());
                            $status = $this->payments->getMandateStatusById(Payments::MANDATE_STATUS_ACTIVE);
                            if ($status) {
                                $mandate->setStatus($status);
                                $this->payments->addHistoryToMandate($mandate, 'Completed mandate setup', $data->all());
                            }
                        }
                        $this->payments->saveMandate($mandate);
                    }
                }

                //Refunds
                if ($event->getWebhook()->getData()['type'] === 'refund.created') {
                    $id = $data->get('payment_intent');
                    $payment = $this->payments->getPaymentByProviderId($id);
                    if ($payment) {
                        $this->logHistoryForAttemptId($data->get('payment_intent'), 'Refund created', $event->getWebhook()->getData());
                    }
                }
                if ($event->getWebhook()->getData()['type'] === 'charge.refunded') {
                    $id = $data->get('payment_intent');
                    $payment = $this->payments->getPaymentByProviderId($id);
                    if ($payment) {
                        $this->logHistoryForAttemptId($data->get('payment_intent'), 'Payment refunded', $event->getWebhook()->getData());
                        $status = $this->payments->getPaymentStatusById(Payments::STATUS_REFUNDED);
                        if ($status) {
                            $payment->setStatus($status);
                            $this->payments->savePayment($payment);
                        }
                    }
                }

                //Charges
                if ($event->getWebhook()->getData()['type'] === 'charge.succeeded') {
                    $id = $data->get('payment_intent');
                    $payment = $this->payments->getPaymentByProviderId($id);
                    if ($payment) {
                        $details = $data->get('payment_method_details');
                        $card = $details['card'] ?? null;
                        if ($card) {
                            $payment->setCardData($card);
                            $payment->setAuthCode($card['authorization_code']);
                            $payment->setPaymentMethodName($card['brand'] . ' ending ' . $card['last4']);
                            $this->payments->savePayment($payment);
                        }
                    }
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
