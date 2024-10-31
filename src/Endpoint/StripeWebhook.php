<?php

namespace Pantono\Payments\Endpoint;

use Pantono\Core\Router\Endpoint\AbstractEndpoint;
use Pantono\Payments\Payments;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Fractal\Resource\ResourceAbstract;
use Pantono\Payments\Provider\Stripe;

class StripeWebhook extends AbstractEndpoint
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public function processRequest(ParameterBag $parameters): array|ResourceAbstract|Response
    {
        $gateways = $this->payments->getGatewaysByProviderId(Stripe::PROVIDER_ID);
        if (empty($gateways)) {
            throw new \RuntimeException('No providers setup for stripe webhooks');
        }
        $gateway = $gateways[0];
        /**
         * @var Stripe $provider
         */
        $provider = $this->payments->getProviderController($gateway);
        $provider->ingestWebhook($parameters->all());
        return ['success' => true];
    }
}
