<?php

declare(strict_types=1);

namespace MageZero\Otp\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH_ENABLED = 'mz_otp/general/enabled';
    private const XML_PATH_CHALLENGE_TTL_MINUTES = 'mz_otp/general/challenge_ttl_minutes';
    private const XML_PATH_MAX_ATTEMPTS = 'mz_otp/general/max_attempts';
    private const XML_PATH_POLL_INTERVAL_MS = 'mz_otp/general/poll_interval_ms';
    private const XML_PATH_EMAIL_IDENTITY = 'mz_otp/email/identity';
    private const XML_PATH_EMAIL_TEMPLATE = 'mz_otp/email/template';

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getChallengeTtlMinutes(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_CHALLENGE_TTL_MINUTES,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value < 1) {
            return 10;
        }

        return $value;
    }

    public function getMaxAttempts(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_MAX_ATTEMPTS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value < 1) {
            return 3;
        }

        return $value;
    }

    public function getPollIntervalMs(?int $storeId = null): int
    {
        $value = (int) $this->scopeConfig->getValue(
            self::XML_PATH_POLL_INTERVAL_MS,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        if ($value < 500) {
            return 2000;
        }

        return $value;
    }

    public function getEmailIdentity(?int $storeId = null): string
    {
        $identity = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $identity !== '' ? $identity : 'general';
    }

    public function getEmailTemplate(?int $storeId = null): string
    {
        $template = (string) $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        return $template !== '' ? $template : 'mz_customer_login_otp_request';
    }
}
