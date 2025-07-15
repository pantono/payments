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
use Pantono\Contracts\Locator\LocatorInterface;
use Pantono\Payments\Model\PaymentWebhook;
use Symfony\Component\HttpFoundation\Request;
use Pantono\Payments\Event\PaymentWebhookEvent;
use Pantono\Customers\Model\Customer;
use Pantono\Payments\Filter\PaymentFilter;

class Payments
{
    private PaymentsRepository $repository;
    private Hydrator $hydrator;
    private EventDispatcher $dispatcher;
    public const STATUS_PENDING = 1;
    public const STATUS_COMPLETED = 2;
    public const STATUS_FAILED = 3;
    public const STATUS_CHARGEBACK = 4;
    public const STATUS_REFUNDED = 5;
    public const MANDATE_STATUS_PENDING = 1;
    public const MANDATE_STATUS_ACTIVE = 2;
    public const MANDATE_STATUS_CANCELLED = 3;
    public const MANDATE_STATUS_EXPIRED = 4;
    public const MANDATE_STATUS_ERROR = 5;
    private Config $config;
    private LocatorInterface $locator;

    public function __construct(PaymentsRepository $repository, Hydrator $hydrator, EventDispatcher $dispatcher, Config $config, LocatorInterface $locator)
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

    public function getPaymentByProviderId(string $id): ?Payment
    {
        return $this->hydrator->hydrate(Payment::class, $this->repository->getPaymentByProviderId($id));
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

    public function createPayment(PaymentGateway $gateway, int $amountInPence, array $requestData = [], string $currency = 'gbp'): Payment
    {
        $pendingStatus = $this->getPaymentStatusById(self::STATUS_PENDING);
        if ($pendingStatus === null) {
            throw new \RuntimeException('Pending payment status does not exist');
        }
        $payment = new Payment();
        $payment->setDateCreated(new \DateTimeImmutable);
        $payment->setDateUpdated(new \DateTimeImmutable);
        $payment->setCurrency($currency);
        $payment->setGateway($gateway);
        $payment->setAmount($amountInPence);
        $payment->setRequestData($requestData);
        $payment->setStatus($pendingStatus);
        $this->savePayment($payment);
        $this->getProviderController($gateway)->initiate($payment);
        $this->savePayment($payment);
        return $payment;
    }

    public function createMandate(PaymentGateway $gateway, Customer $customer, string $currency = 'GBP', array $requestData = []): PaymentMandate
    {
        $pendingStatus = $this->getMandateStatusById(self::MANDATE_STATUS_PENDING);
        if ($pendingStatus === null) {
            throw new \RuntimeException('Pending mandate status does not exist');
        }
        $mandate = new PaymentMandate();
        $mandate->setCustomer($customer);
        $mandate->setPaymentGateway($gateway);
        $mandate->setSetupData($requestData);
        $mandate->setStatus($pendingStatus);
        $mandate->setCurrency($currency);

        $this->saveMandate($mandate);
        $this->getProviderController($gateway)->initiateMandate($mandate);
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

    public function saveWebhook(PaymentWebhook $webhook): void
    {
        $this->repository->saveWebhook($webhook);
    }

    public function ingestWebhook(PaymentGateway $paymentGateway, Request $request): PaymentWebhook
    {
        $webhook = new PaymentWebhook();
        $webhook->setData($request->request->all());
        $webhook->setDate(new \DateTimeImmutable());
        $webhook->setHeaders($request->headers->all());
        $webhook->setGateway($paymentGateway);
        $webhook->setProcessed(false);
        $webhook->setRequest($request);
        $this->saveWebhook($webhook);
        $event = new PaymentWebhookEvent();
        $event->setWebhook($webhook);
        $this->dispatcher->dispatch($event);
        $this->saveWebhook($webhook);
        return $webhook;
    }

    public function addHistoryToPayment(Payment $payment, string $entry, array $data = [], ?\DateTimeInterface $date = null): void
    {
        $this->repository->addHistoryToPayment($payment, $entry, $data, $date);
    }

    public function addHistoryToMandate(PaymentMandate $mandate, string $entry, array $data = [], ?\DateTimeInterface $date = null): void
    {
        $this->repository->addHistoryToMandate($mandate, $entry, $data, $date);
    }

    /**
     * @return Payment[]
     */
    public function getPaymentsByFilter(PaymentFilter $filter): array
    {
        return $this->hydrator->hydrateSet(Payment::class, $this->repository->getPaymentsByFilter($filter));
    }

    /**
     * @return PaymentMandate[]
     */
    public function getMandatesForCustomer(Customer $customer): array
    {
        return $this->hydrator->hydrateSet(PaymentMandate::class, $this->repository->getMandatesForCustomer($customer));
    }
}
