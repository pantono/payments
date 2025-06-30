<?php

namespace Pantono\Payments\Endpoint;

use Pantono\Core\Router\Endpoint\AbstractEndpoint;
use Pantono\Payments\Payments;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Fractal\Resource\ResourceAbstract;
use Pantono\Payments\Provider\Stripe;
use League\Fractal\Resource\Item;
use Pantono\Core\Decorator\GenericArrayDecorator;

class PaymentWebhook extends AbstractEndpoint
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public function processRequest(ParameterBag $parameters): array|ResourceAbstract|Response
    {
        $gatewayId = $this->getRequest()->get('id');
        $gateway = $this->payments->getPaymentGatewayById($gatewayId);
        if ($gateway === null) {
            throw new \RuntimeException('Gateway does not exist');
        }
        /**
         * @var Stripe $provider
         */
        $provider = $this->payments->getProviderController($gateway);
        $provider->ingestWebhook($parameters->get('type'), $parameters->all());
        return new Item(['success' => true], new GenericArrayDecorator());
    }
}
