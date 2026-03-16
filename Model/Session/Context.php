<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\Session;

use Magento\Customer\Model\Session;

class Context
{
    private const KEY_CHALLENGE_ID = 'mz_otp_challenge_id';

    /** @var Session */
    private $customerSession;

    public function __construct(Session $customerSession)
    {
        $this->customerSession = $customerSession;
    }

    public function getChallengeId(): ?int
    {
        $value = $this->customerSession->getData(self::KEY_CHALLENGE_ID);
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    public function setChallengeId(int $challengeId): void
    {
        $this->customerSession->setData(self::KEY_CHALLENGE_ID, $challengeId);
    }

    public function clearChallengeId(): void
    {
        $this->customerSession->unsetData(self::KEY_CHALLENGE_ID);
    }
}
