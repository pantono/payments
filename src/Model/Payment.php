<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\FieldName;
use Pantono\Contracts\Attributes\Filter;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Payments\Payments;

class Payment
{
    use SavableModel;

    private ?int $id = null;
    #[FieldName('gateway_id'), Locator(methodName: 'getPaymentGatewayById', className: Payments::class)]
    private PaymentGateway $gateway;
    private ?string $reference = null;
    private ?string $currency = null;
    #[Filter('json_decode')]
    private array $requestData = [];
    #[Filter('json_decode')]
    private array $responseData = [];
    private int $amount;
    #[FieldName('status_id'), Locator(methodName: 'getStatusById', className: Payments::class)]
    private PaymentStatus $status;
    private \DateTimeImmutable $dateCreated;
    private \DateTimeImmutable $dateUpdated;
    #[Filter('json_decode')]
    private array $data = [];
    private ?string $redirectUrl = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getGateway(): PaymentGateway
    {
        return $this->gateway;
    }

    public function setGateway(PaymentGateway $gateway): void
    {
        $this->gateway = $gateway;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getRequestData(): array
    {
        return $this->requestData;
    }

    public function setRequestData(array $requestData): void
    {
        $this->requestData = $requestData;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }

    public function setResponseData(array $responseData): void
    {
        $this->responseData = $responseData;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): void
    {
        $this->amount = $amount;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): void
    {
        $this->status = $status;
    }

    public function getDateCreated(): \DateTimeImmutable
    {
        return $this->dateCreated;
    }

    public function setDateCreated(\DateTimeImmutable $dateCreated): void
    {
        $this->dateCreated = $dateCreated;
    }

    public function getDateUpdated(): \DateTimeImmutable
    {
        return $this->dateUpdated;
    }

    public function setDateUpdated(\DateTimeImmutable $dateUpdated): void
    {
        $this->dateUpdated = $dateUpdated;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getRedirectUrl(): ?string
    {
        return $this->redirectUrl;
    }

    public function setRedirectUrl(?string $redirectUrl): void
    {
        $this->redirectUrl = $redirectUrl;
    }
}
