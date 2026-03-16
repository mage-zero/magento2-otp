<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\Service;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\ChallengeFactory;
use MageZero\Otp\Model\Config;
use MageZero\Otp\Model\ResourceModel\Challenge as ChallengeResource;
use MageZero\Otp\Model\ResourceModel\Challenge\CollectionFactory;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;

class ChallengeService
{
    /** @var ChallengeFactory */
    private $challengeFactory;

    /** @var ChallengeResource */
    private $challengeResource;

    /** @var CollectionFactory */
    private $collectionFactory;

    /** @var EncryptorInterface */
    private $encryptor;

    /** @var DateTime */
    private $dateTime;

    /** @var Config */
    private $config;

    public function __construct(
        ChallengeFactory $challengeFactory,
        ChallengeResource $challengeResource,
        CollectionFactory $collectionFactory,
        EncryptorInterface $encryptor,
        DateTime $dateTime,
        Config $config
    ) {
        $this->challengeFactory = $challengeFactory;
        $this->challengeResource = $challengeResource;
        $this->collectionFactory = $collectionFactory;
        $this->encryptor = $encryptor;
        $this->dateTime = $dateTime;
        $this->config = $config;
    }

    public function createChallenge(int $customerId, string $customerEmail, string $sessionId, ?int $storeId = null): StartedChallenge
    {
        $token = $this->generateToken();
        $code = $this->generateCode();

        $now = $this->dateTime->gmtTimestamp();
        $expiresAt = gmdate('Y-m-d H:i:s', $now + ($this->config->getChallengeTtlMinutes($storeId) * 60));

        /** @var Challenge $challenge */
        $challenge = $this->challengeFactory->create();
        $challenge->setData('customer_id', $customerId);
        $challenge->setData('customer_email', $customerEmail);
        $challenge->setData('session_id', $sessionId);
        $challenge->setData('status', Challenge::STATUS_PENDING);
        $challenge->setData('otp_code_hash', $this->encryptor->getHash($code, true));
        $challenge->setData('otp_code_encrypted', $this->encryptor->encrypt($code));
        $challenge->setData('email_token_hash', $this->hashToken($token));
        $challenge->setData('attempts', 0);
        $challenge->setData('max_attempts', $this->config->getMaxAttempts($storeId));
        $challenge->setData('expires_at', $expiresAt);

        $this->challengeResource->save($challenge);

        return new StartedChallenge((int) $challenge->getId(), $token);
    }

    public function getById(int $challengeId): ?Challenge
    {
        /** @var Challenge $challenge */
        $challenge = $this->challengeFactory->create();
        $this->challengeResource->load($challenge, $challengeId);

        if (!$challenge->getId()) {
            return null;
        }

        return $challenge;
    }

    public function getByToken(string $token): ?Challenge
    {
        $tokenHash = $this->hashToken($token);
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('email_token_hash', $tokenHash);
        $collection->setPageSize(1);

        /** @var Challenge|null $challenge */
        $challenge = $collection->getFirstItem();
        if (!$challenge || !$challenge->getId()) {
            return null;
        }

        return $challenge;
    }

    public function hasExpired(Challenge $challenge): bool
    {
        $expiresAt = strtotime($challenge->getExpiresAt());
        if ($expiresAt === false) {
            return true;
        }

        return $expiresAt < $this->dateTime->gmtTimestamp();
    }

    public function hasAttemptsRemaining(Challenge $challenge): bool
    {
        return $challenge->getAttempts() < $challenge->getMaxAttempts();
    }

    public function approve(Challenge $challenge): void
    {
        $challenge->setStatus(Challenge::STATUS_APPROVED);
        $challenge->setData('approved_at', $this->dateTime->gmtDate());
        $this->challengeResource->save($challenge);
    }

    public function complete(Challenge $challenge): void
    {
        $challenge->setStatus(Challenge::STATUS_COMPLETED);
        $this->challengeResource->save($challenge);
    }

    public function fail(Challenge $challenge): void
    {
        $challenge->setStatus(Challenge::STATUS_FAILED);
        $this->challengeResource->save($challenge);
    }

    public function incrementAttempts(Challenge $challenge): int
    {
        $attempts = $challenge->getAttempts() + 1;
        $challenge->setData('attempts', $attempts);
        $this->challengeResource->save($challenge);

        return $attempts;
    }

    public function validateOtpCode(Challenge $challenge, string $submittedCode): bool
    {
        return $this->encryptor->validateHash(trim($submittedCode), (string) $challenge->getData('otp_code_hash'));
    }

    /**
     * Returns a human-readable OTP code for display in approval browser.
     *
     * @throws LocalizedException
     */
    public function getDisplayCode(Challenge $challenge): string
    {
        $encryptedCode = (string) $challenge->getData('otp_code_encrypted');
        if ($encryptedCode === '') {
            throw new LocalizedException(new Phrase('Missing challenge code.'));
        }

        return (string) $this->encryptor->decrypt($encryptedCode);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
