<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\FieldName;
use Pantono\Contracts\Attributes\Filter;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Payments\Payments;
use Pantono\Database\Traits\SavableModel;

class PaymentMandate
{
    use SavableModel;

    private ?int $id = null;
    #[FieldName('gateway_id'), Locator(methodName: 'getPaymentGatewayById', className: Payments::class)]
    private PaymentGateway $paymentGateway;
    #[FieldName('status_id'), Locator(methodName: 'getMandateStatusById', className: Payments::class)]
    private PaymentMandateStatus $status;
    private ?string $reference = null;
    private ?\DateTimeImmutable $startDate = null;
    private ?\DateTimeImmutable $endDate = null;
    private string $currency;
    #[Filter('json_decode')]
    private array $setupData = [];
    #[Filter('json_decode')]
    private array $responseData = [];

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getPaymentGateway(): PaymentGateway
    {
        return $this->paymentGateway;
    }

    public function setPaymentGateway(PaymentGateway $paymentGateway): void
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): void
    {
        $this->startDate = $startDate;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): void
    {
        $this->endDate = $endDate;
    }

    public function getSetupData(): array
    {
        return $this->setupData;
    }

    public function setSetupData(array $setupData): void
    {
        $this->setupData = $setupData;
    }

    public function getDataValue(string $name): mixed
    {
        $data = $this->getSetupData();
        return $data[$name] ?? null;
    }

    public function setDataValue(string $name, mixed $dataValue): void
    {
        $data = $this->getSetupData();
        if (!$data) {
            $data = [];
        }
        $data[$name] = $dataValue;
        $this->setupData = $data;
    }

    public function getStatus(): PaymentMandateStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentMandateStatus $status): void
    {
        $this->status = $status;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }

    public function setResponseData(array $responseData): void
    {
        $this->responseData = $responseData;
    }
}
