<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\Email;

use MageZero\Otp\Model\Config;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Area;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Store\Model\StoreManagerInterface;

class OtpEmailSender
{
    /** @var TransportBuilder */
    private $transportBuilder;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var Config */
    private $config;

    public function __construct(
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        Config $config
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->config = $config;
    }

    /**
     * @throws LocalizedException
     */
    public function send(CustomerInterface $customer, string $approvalUrl): void
    {
        $store = $this->storeManager->getStore((int) $customer->getStoreId());
        $storeId = (int) $store->getId();

        $transport = $this->transportBuilder
            ->setTemplateIdentifier($this->config->getEmailTemplate($storeId))
            ->setTemplateOptions([
                'area' => Area::AREA_FRONTEND,
                'store' => $storeId,
            ])
            ->setTemplateVars([
                'customer_name' => trim((string) ($customer->getFirstname() . ' ' . $customer->getLastname())),
                'approval_url' => $approvalUrl,
                'expires_in_minutes' => $this->config->getChallengeTtlMinutes($storeId),
                'store_name' => $store->getFrontendName(),
            ])
            ->setFromByScope($this->config->getEmailIdentity($storeId), $storeId)
            ->addTo((string) $customer->getEmail(), trim((string) ($customer->getFirstname() . ' ' . $customer->getLastname())))
            ->getTransport();

        $transport->sendMessage();
    }
}
