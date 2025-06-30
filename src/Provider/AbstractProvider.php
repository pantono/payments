<?php

namespace Pantono\Payments\Provider;

use Pantono\Payments\Model\Payment;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\PaymentGateway;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Payments\Exception\GatewayDoesNotSupportMandates;
use Pantono\Config\Config;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractProvider
{
    protected Payments $payments;
    private PaymentGateway $gateway;
    private Config $config;

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

    public function setPayments(Payments $payments): void
    {
        $this->payments = $payments;
    }

    public function setGateway(PaymentGateway $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function setConfig(Config $config): void
    {
        $this->config = $config;
    }
}
