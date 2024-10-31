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
use Pantono\Payments\Event\PreMandateSaveSaveEvent;
use Pantono\Payments\Event\PostMandateSaveSaveEvent;
use Pantono\Payments\Model\PaymentMandateStatus;
use Pantono\Config\Config;
use Pantono\Payments\Event\PreGatewaySaveEvent;
use Pantono\Payments\Event\PostGatewaySaveEvent;
use Pantono\Container\Service\Locator;

class Payments
{
    private PaymentsRepository $repository;
    private Hydrator $hydrator;
    private EventDispatcher $dispatcher;
    public const STATUS_PENDING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_FAILED = 3;
    public const MANDATE_STATUS_PENDING = 1;
    public const MANDATE_STATUS_ACTIVE = 2;
    public const MANDATE_STATUS_CANCELLED = 3;
    public const MANDATE_STATUS_EXPIRED = 4;
    private Config $config;
    private Locator $locator;

    public function __construct(PaymentsRepository $repository, Hydrator $hydrator, EventDispatcher $dispatcher, Config $config, Locator $locator)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->dispatcher = $dispatcher;
        $this->config = $config;
        $this->locator = $locator;
    }

    public function getPaymentById(int $id): ?Payment
    {
        return $this->hydrator->hydrate(Payment::class, $this->repository->getPaymentById($id));
    }

    public function getPaymentByReference(string $reference): ?Payment
    {
        return $this->hydrator->hydrate(Payment::class, $this->repository->getPaymentByReference($reference));
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

    public function getMandateStatusById(int $id): ?PaymentMandateStatus
    {
        return $this->hydrator->hydrate(PaymentMandateStatus::class, $this->repository->getMandateStatusById($id));
    }

    public function getMandateByReference(string $reference): ?PaymentMandate
    {
        return $this->hydrator->hydrate(PaymentMandate::class, $this->repository->getPaymentMandateByReference($reference));
    }

    /**
     * @return PaymentGateway[]
     */
    public function getGatewaysByProviderId(int $id): array
    {
        return $this->hydrator->hydrateSet(PaymentGateway::class, $this->repository->getGatewaysByProvider($id));
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

    public function createMandate(PaymentGateway $gateway, string $currency = 'GBP', array $requestData = []): PaymentMandate
    {
        $pendingStatus = $this->getMandateStatusById(self::MANDATE_STATUS_PENDING);
        if ($pendingStatus === null) {
            throw new \RuntimeException('Pending mandate status does not exist');
        }
        $mandate = new PaymentMandate();
        $mandate->setPaymentGateway($gateway);
        $mandate->setData($requestData);
        $mandate->setStatus($pendingStatus);
        $mandate->setCurrency($currency);

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
        $previous = $mandate->getId() ? $this->getMandateById($mandate->getId()) : null;
        $event = new PreMandateSaveSaveEvent();
        $event->setCurrent($mandate);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);

        $this->repository->saveMandate($mandate);

        $event = new PostMandateSaveSaveEvent();
        $event->setCurrent($mandate);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);
    }

    public function saveGateway(PaymentGateway $gateway): void
    {
        $previous = $gateway->getId() ? $this->getPaymentGatewayById($gateway->getId()) : null;
        $event = new PreGatewaySaveEvent();
        $event->setCurrent($gateway);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);

        $this->repository->saveGateway($gateway);

        $event = new PostGatewaySaveEvent();
        $event->setCurrent($gateway);
        $event->setPrevious($previous);
        $this->dispatcher->dispatch($event);
    }

    public function getProviderController(PaymentGateway $gateway): AbstractProvider
    {
        if (!class_exists($gateway->getProvider()->getController())) {
            throw new \RuntimeException('Controller for provider does not exist');
        }
        /**
         * @var AbstractProvider $controller
         */
        $controller = $this->locator->getClassAutoWire($gateway->getProvider()->getController());
        $controller->setPayments($this);
        $controller->setConfig($this->config);
        $controller->setGateway($gateway);
        return $controller;
    }
}
