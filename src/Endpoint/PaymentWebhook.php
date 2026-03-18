<?php

namespace Pantono\Payments\Endpoint;

use Pantono\Core\Router\Endpoint\AbstractEndpoint;
use Pantono\Payments\Payments;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ParameterBag;
use League\Fractal\Resource\ResourceAbstract;
use League\Fractal\Resource\Item;
use Pantono\Core\Decorator\GenericArrayDecorator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentWebhook extends AbstractEndpoint
{
    private Payments $payments;

    public function __construct(Payments $payments)
    {
        $this->payments = $payments;
    }

    public function processRequest(ParameterBag $parameters): array|ResourceAbstract|Response
    {
        $gatewayId = $this->getRequest()->query->get('id');
        if (!$gatewayId) {
            throw new NotFoundHttpException('Gateway does not exist');
        }
        $gateway = $this->payments->getPaymentGatewayById((int)$gatewayId);
        if ($gateway === null) {
            throw new NotFoundHttpException('Gateway does not exist');
        }
        $webhook = $this->payments->ingestWebhook($gateway, $this->getRequest());
        return new Item(['success' => true, 'id' => $webhook->getId()], new GenericArrayDecorator());
    }
}
