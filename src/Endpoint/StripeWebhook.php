<?php

namespace Pantono\Payments\Endpoint;

use Pantono\Core\Router\Endpoint\AbstractEndpoint;
use Pantono\Payments\Payments;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Fractal\Resource\ResourceAbstract;

class StripeWebhook extends AbstractEndpoint
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public function processRequest(ParameterBag $parameters): array|ResourceAbstract|Response
    {
        dd([$parameters, $this->payments]);
    }
}
