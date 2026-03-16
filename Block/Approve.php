<?php

declare(strict_types=1);

namespace MageZero\Otp\Block;

use Magento\Framework\View\Element\Template;

class Approve extends Template
{
    /** @var bool */
    private $successfulApproval = false;

    /** @var string */
    private $otpCode = '';

    /** @var string */
    private $failureMessage = '';

    public function setSuccessfulApproval(bool $successfulApproval): self
    {
        $this->successfulApproval = $successfulApproval;
        return $this;
    }

    public function isSuccessfulApproval(): bool
    {
        return $this->successfulApproval;
    }

    public function setOtpCode(string $otpCode): self
    {
        $this->otpCode = $otpCode;
        return $this;
    }

    public function getOtpCode(): string
    {
        return $this->otpCode;
    }

    public function setFailureMessage(string $failureMessage): self
    {
        $this->failureMessage = $failureMessage;
        return $this;
    }

    public function getFailureMessage(): string
    {
        return $this->failureMessage;
    }
}
