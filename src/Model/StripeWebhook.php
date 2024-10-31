<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Payments\Payments;
use Pantono\Contracts\Attributes\FieldName;

class StripeWebhook
{
    use SavableModel;

    private ?int $id = null;
    #[Locator(methodName: 'getPaymentGatewayById', className: Payments::class), FieldName('gateway_id')]
    private PaymentGateway $gateway;
    private \DateTimeImmutable $date;
    private string $type;
    #[Filter('json_decode')]
    private array $data;

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

    public function getDate(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): void
    {
        $this->date = $date;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getDataValue(string $name): mixed
    {
        $webhookData = $this->getData();
        $objectData = $webhookData['data']['object'] ?? null;
        return $objectData[$name] ?? null;
    }

    public function getObjectData(): ?array
    {
        $webhookData = $this->getData();
        return $webhookData['data']['object'] ?? null;
    }
}
