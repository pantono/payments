<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\Filter;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\FieldName;
use Symfony\Component\HttpFoundation\Request;
use Pantono\Contracts\Attributes\NoSave;
use Pantono\Contracts\Attributes\DatabaseTable;
use Pantono\Contracts\Attributes\Database\OneToOne;

#[DatabaseTable('payment_webhook')]
class PaymentWebhook
{
    use SavableModel;

    private ?int $id = null;
    #[OneToOne(targetModel: PaymentGateway::class), FieldName('gateway_id')]
    private PaymentGateway $gateway;
    private \DateTimeImmutable $date;
    private ?string $type = null;
    #[Filter('json_decode')]
    private array $data;
    #[Filter('json_decode')]
    private array $headers;
    private bool $processed = false;
    private bool $verified = false;
    #[NoSave]
    private ?Request $request = null;
    /**
     * @var array<string,mixed>
     */
    #[Filter('json_decode')]
    private ?array $decodedData = null;
    private ?string $error = null;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers): void
    {
        $this->headers = $headers;
    }

    public function getHeader(string $name): mixed
    {
        return $this->headers[$name] ?? null;
    }

    public function getSingleHeader(string $name): mixed
    {
        $header = $this->getHeader($name);
        if (is_array($header) && isset($header[0])) {
            return $header[0];
        }
        return $header;
    }

    public function isProcessed(): bool
    {
        return $this->processed;
    }

    public function setProcessed(bool $processed): void
    {
        $this->processed = $processed;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): void
    {
        $this->request = $request;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): void
    {
        $this->verified = $verified;
    }

    /**
     * @return mixed[]|null
     */
    public function getDecodedData(): ?array
    {
        return $this->decodedData;
    }

    public function setDecodedData(?array $decodedData): void
    {
        $this->decodedData = $decodedData;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }
}
