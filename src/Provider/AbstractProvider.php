<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\PaymentGateway;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Payments\Exception\GatewayDoesNotSupportMandates;
use Pantono\Config\Config;

abstract class AbstractProvider
{
    private Payments $payments;
    private PaymentGateway $gateway;
    private Config $config;

    public function __construct(PaymentGateway $gateway, Payments $payments, Config $config)
    {
        $this->gateway = $gateway;
        $this->payments = $payments;
        $this->config = $config;
    }

    abstract public function supportsRecurring(): bool;

    abstract public function initiate(Payment $payment): void;

    abstract public function handleResponse(array $data): void;

    public function processMandate(PaymentMandate $mandate): void
    {
        throw new GatewayDoesNotSupportMandates('Gateway ' . $this->getGateway()->getProvider()->getName() . ' does not support mandates');
    }

    public function getGateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function getPayments(): Payments
    {
        return $this->payments;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }
}
