<?php

namespace Pantono\Payments\Model;

use Pantono\Contracts\Attributes\FieldName;
use Pantono\Contracts\Attributes\Filter;
use Pantono\Database\Traits\SavableModel;
use Pantono\Contracts\Attributes\Locator;
use Pantono\Payments\Payments;
use Pantono\Contracts\Attributes\Lazy;
use Pantono\Payments\Utility\CurrencyHelper;

class Payment
{
    use SavableModel;

    private ?int $id = null;
    #[FieldName('gateway_id'), Locator(methodName: 'getPaymentGatewayById', className: Payments::class)]
    private PaymentGateway $gateway;
    private ?string $reference = null;
    private ?string $providerId = null;
    #[FieldName('mandate_id'), Locator(methodName: 'getMandateById', className: Payments::class)]
    private ?PaymentMandate $mandate = null;
    private ?string $currency = null;
    private ?string $paymentMethodName = null;
    private ?string $authCode = null;
    #[Filter('json_decode')]
    private array $cardData = [];
    #[Filter('json_decode')]
    private array $requestData = [];
    #[Filter('json_decode')]
    private array $responseData = [];
    private int $amount;
    #[FieldName('status_id'), Locator(methodName: 'getPaymentStatusById', className: Payments::class)]
    private PaymentStatus $status;
    private \DateTimeImmutable $dateCreated;
    private \DateTimeImmutable $dateUpdated;
    #[Filter('json_decode')]
    private array $data = [];
    private ?string $redirectUrl = null;
    /**
     * @var PaymentHistory[]
     */
    #[Locator(methodName: 'getHistoryForPayment', className: Payments::class), FieldName('$this'), Lazy]
    private array $history = [];

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

    public function getMandate(): ?PaymentMandate
    {
        return $this->mandate;
    }

    public function setMandate(?PaymentMandate $mandate): void
    {
        $this->mandate = $mandate;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): void
    {
        $this->reference = $reference;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): void
    {
        $this->providerId = $providerId;
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

    public function getPaymentMethodName(): ?string
    {
        return $this->paymentMethodName;
    }

    public function setPaymentMethodName(?string $paymentMethodName): void
    {
        $this->paymentMethodName = $paymentMethodName;
    }

    public function getCardData(): array
    {
        return $this->cardData;
    }

    public function setCardData(array $cardData): void
    {
        $this->cardData = $cardData;
    }

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function setAuthCode(?string $authCode): void
    {
        $this->authCode = $authCode;
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

    public function setDataValue(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    public function getDataField(string $string): mixed
    {
        return $this->data[$string] ?? null;
    }

    public function getHistory(): array
    {
        return $this->history;
    }

    public function setHistory(array $history): void
    {
        $this->history = $history;
    }

    public function getDisplayAmount(): string
    {
        $number = number_format($this->amount / 100, 2, '.', '');
        $currency = $this->getCurrency();
        if ($currency) {
            $symbol = CurrencyHelper::getSymbol($currency);
            if ($symbol) {
                return sprintf('%s%s', $symbol, $number);
            }
        }
        return $number;
    }
}
