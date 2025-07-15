<?php

namespace Pantono\Payments\Filter;

use Pantono\Contracts\Filter\PageableInterface;
use Pantono\Database\Traits\Pageable;
use Pantono\Payments\Model\PaymentMandate;

class PaymentFilter implements PageableInterface
{
    use Pageable;

    private ?int $minAmountInPence = null;
    private ?int $maxAmountInPence = null;
    private ?string $authCode = null;
    private ?PaymentMandate $mandate = null;
    private ?string $providerId = null;

    public function getMinAmountInPence(): ?int
    {
        return $this->minAmountInPence;
    }

    public function setMinAmountInPence(?int $minAmountInPence): void
    {
        $this->minAmountInPence = $minAmountInPence;
    }

    public function getMaxAmountInPence(): ?int
    {
        return $this->maxAmountInPence;
    }

    public function setMaxAmountInPence(?int $maxAmountInPence): void
    {
        $this->maxAmountInPence = $maxAmountInPence;
    }

    public function getAuthCode(): ?string
    {
        return $this->authCode;
    }

    public function setAuthCode(?string $authCode): void
    {
        $this->authCode = $authCode;
    }

    public function getMandate(): ?PaymentMandate
    {
        return $this->mandate;
    }

    public function setMandate(?PaymentMandate $mandate): void
    {
        $this->mandate = $mandate;
    }

    public function getProviderId(): ?string
    {
        return $this->providerId;
    }

    public function setProviderId(?string $providerId): void
    {
        $this->providerId = $providerId;
    }
}
