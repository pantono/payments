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
    #[FieldName('gateway_id'), Locator(methodName: 'getGatewayById', className: Payments::class)]
    private PaymentGateway $paymentGateway;
    #[FieldName('status_id'), Locator(methodName: 'getMandateStatusById', className: Payments::class)]
    private PaymentMandateStatus $status;
    private ?string $reference = null;
    private ?\DateTimeImmutable $startDate = null;
    private ?\DateTimeImmutable $endDate = null;
    #[Filter('json_decode')]
    private array $data = [];

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

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getStatus(): PaymentMandateStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentMandateStatus $status): void
    {
        $this->status = $status;
    }
}
