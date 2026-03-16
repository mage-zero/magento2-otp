<?php

declare(strict_types=1);

namespace MageZero\Otp\Model;

use Magento\Framework\Model\AbstractModel;

class Challenge extends AbstractModel
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Challenge::class);
    }

    public function getChallengeId(): int
    {
        return (int) $this->getData('entity_id');
    }

    public function getCustomerId(): int
    {
        return (int) $this->getData('customer_id');
    }

    public function getStatus(): string
    {
        return (string) $this->getData('status');
    }

    public function getAttempts(): int
    {
        return (int) $this->getData('attempts');
    }

    public function getMaxAttempts(): int
    {
        return (int) $this->getData('max_attempts');
    }

    public function getExpiresAt(): string
    {
        return (string) $this->getData('expires_at');
    }

    public function setStatus(string $status): self
    {
        return $this->setData('status', $status);
    }
}
