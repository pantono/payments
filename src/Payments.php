<?php

namespace Pantono\Payments;

use Pantono\Payments\Repository\PaymentsRepository;
use Pantono\Hydrator\Hydrator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Pantono\Payments\Model\PaymentGateway;
use Pantono\Payments\Model\PaymentProvider;
use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentStatus;
use Pantono\Payments\Event\PrePaymentSaveEvent;
use Pantono\Payments\Event\PostPaymentSaveEvent;
use Pantono\Payments\Provider\AbstractProvider;
use Pantono\Payments\Model\PaymentMandate;

class Payments
{
    private PaymentsRepository $repository;
    private Hydrator $hydrator;
    private EventDispatcher $dispatcher;
    public const STATUS_PENDING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_FAILED = 3;

    public function __construct(PaymentsRepository $repository, Hydrator $hydrator, EventDispatcher $dispatcher)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->dispatcher = $dispatcher;
    }

    public function getPaymentById(int $id): ?Payment
    {
        return $this->hydrator->hydrate(Payment::class, $this->repository->getPaymentById($id));
    }

    public function getPaymentGatewayById(int $id): ?PaymentGateway
    {
        return $this->hydrator->hydrate(PaymentGateway::class, $this->repository->getPaymentGatewayById($id));
    }

    public function getProviderById(int $id): ?PaymentProvider
    {
        return $this->hydrator->hydrate(PaymentProvider::class, $this->repository->getProviderById($id));
    }

    public function getPaymentStatusById(int $id): ?PaymentStatus
    {
        return $this->hydrator->hydrate(PaymentStatus::class, $this->repository->getPaymentStatusById($id));
    }

    public function getMandateById(int $id): ?PaymentMandate
    {
        return $this->hydrator->hydrate(PaymentMandate::class, $this->repository->getPaymentMandateById($id));
    }

    public function createPayment(PaymentGateway $gateway, int $amountInPence, array $requestData = []): Payment
    {
        $pendingStatus = $this->getPaymentStatusById(self::STATUS_PENDING);
        if ($pendingStatus === null) {
            throw new \RuntimeException('Pending payment status does not exist');
        }
        $payment = new Payment();
        $payment->setDateCreated(new \DateTimeImmutable);
        $payment->setGateway($gateway);
        $payment->setAmount($amountInPence);
        $payment->setRequestData($requestData);
        $payment->setStatus($pendingStatus);
        $this->savePayment($payment);
        $this->getProviderController($gateway)->initiate($payment);
        return $payment;
    }

    public function createMandate(PaymentGateway $gateway, array $requestData = []): PaymentMandate
    {
        $mandate = new PaymentMandate();
        $mandate->setPaymentGateway($gateway);
        $mandate->setData($requestData);

        $this->saveMandate($mandate);
        $this->getProviderController($gateway)->processMandate($mandate);
        return $mandate;
    }

    public function savePayment(Payment $payment): void
    {
        $previous = $payment->getId() ? $this->getPaymentById($payment->getId()) : null;
        $event = new PrePaymentSaveEvent();
        $event->setCurrent($payment);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);

        $this->repository->savePayment($payment);

        $event = new PostPaymentSaveEvent();
        $event->setCurrent($payment);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);
    }

    public function saveMandate(PaymentMandate $mandate): void
    {

    }

    private function getProviderController(PaymentGateway $gateway): AbstractProvider
    {
        if (!class_exists($gateway->getProvider()->getController())) {
            throw new \RuntimeException('Controller for provider does not exist');
        }
        $controllerName = $gateway->getProvider()->getController();
        /**
         * @var AbstractProvider $controller
         */
        $controller = new $controllerName($gateway);
        $controller->setPayments($this);
        return $controller;
    }
}
