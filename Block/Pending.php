<?php

declare(strict_types=1);

namespace MageZero\Otp\Block;

use MageZero\Otp\Model\Config;
use Magento\Framework\View\Element\Template;

class Pending extends Template
{
    /** @var Config */
    private $config;

    public function __construct(
        Template\Context $context,
        Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->config = $config;
    }

    public function getStatusUrl(): string
    {
        return $this->getUrl('otp/auth/status');
    }

    public function getPollIntervalMs(): int
    {
        return $this->config->getPollIntervalMs((int) $this->_storeManager->getStore()->getId());
    }
}
