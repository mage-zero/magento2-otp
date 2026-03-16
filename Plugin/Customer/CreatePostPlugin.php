<?php

declare(strict_types=1);

namespace MageZero\Otp\Plugin\Customer;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Config;
use MageZero\Otp\Model\Email\OtpEmailSender;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Customer\Controller\Account\CreatePost;
use Magento\Customer\Model\Session;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

class CreatePostPlugin
{
    /** @var Session */
    private $customerSession;

    /** @var Config */
    private $config;

    /** @var ChallengeService */
    private $challengeService;

    /** @var SessionContext */
    private $sessionContext;

    /** @var OtpEmailSender */
    private $emailSender;

    /** @var UrlInterface */
    private $urlBuilder;

    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /** @var ManagerInterface */
    private $messageManager;

    public function __construct(
        Session $customerSession,
        Config $config,
        ChallengeService $challengeService,
        SessionContext $sessionContext,
        OtpEmailSender $emailSender,
        UrlInterface $urlBuilder,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager
    ) {
        $this->customerSession = $customerSession;
        $this->config = $config;
        $this->challengeService = $challengeService;
        $this->sessionContext = $sessionContext;
        $this->emailSender = $emailSender;
        $this->urlBuilder = $urlBuilder;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
    }

    /**
     * Enforce OTP after successful customer registration auto-login.
     *
     * @param CreatePost $subject
     * @param mixed $result
     * @return mixed
     */
    public function afterExecute(CreatePost $subject, $result)
    {
        if (!$this->config->isEnabled()) {
            return $result;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result;
        }

        try {
            $customer = $this->customerSession->getCustomerDataObject();
            $customerId = (int) $customer->getId();
            $customerEmail = (string) $customer->getEmail();

            if ($customerId <= 0 || $customerEmail === '') {
                return $result;
            }

            $challenge = $this->getUsableSessionChallenge($customerId);
            if ($challenge === null) {
                $storeId = $customer->getStoreId() !== null ? (int) $customer->getStoreId() : null;
                $startedChallenge = $this->challengeService->createChallenge(
                    $customerId,
                    $customerEmail,
                    (string) $this->customerSession->getSessionId(),
                    $storeId
                );

                $approvalUrl = $this->urlBuilder->getUrl('otp/auth/approve', [
                    '_nosid' => true,
                    'token' => $startedChallenge->getToken(),
                ]);

                $this->emailSender->send($customer, $approvalUrl);
                $challengeId = $startedChallenge->getChallengeId();
                $redirectPath = 'otp/auth/pending';
            } else {
                $challengeId = (int) $challenge->getId();
                $redirectPath = $challenge->getStatus() === Challenge::STATUS_APPROVED
                    ? 'otp/auth/verify'
                    : 'otp/auth/pending';
            }

            $this->customerSession->logout();
            $this->sessionContext->setChallengeId($challengeId);
            $this->customerSession->setUsername($customerEmail);

            $this->messageManager->addSuccessMessage(__('Please check your email inbox for a login code.'));

            return $this->createRedirect($redirectPath);
        } catch (\Throwable $e) {
            if ($this->customerSession->isLoggedIn()) {
                $this->customerSession->logout();
            }
            $this->sessionContext->clearChallengeId();
            $this->messageManager->addErrorMessage(
                __('We could not start login verification. Please sign in again.')
            );

            return $this->createRedirect('customer/account/login');
        }
    }

    private function getUsableSessionChallenge(int $customerId): ?Challenge
    {
        $challengeId = $this->sessionContext->getChallengeId();
        if ($challengeId === null) {
            return null;
        }

        $challenge = $this->challengeService->getById($challengeId);
        if ($challenge === null || $challenge->getCustomerId() !== $customerId) {
            $this->sessionContext->clearChallengeId();
            return null;
        }

        if ($this->challengeService->hasExpired($challenge) || !$this->challengeService->hasAttemptsRemaining($challenge)) {
            $this->challengeService->fail($challenge);
            $this->sessionContext->clearChallengeId();
            return null;
        }

        if (in_array($challenge->getStatus(), [Challenge::STATUS_COMPLETED, Challenge::STATUS_FAILED], true)) {
            $this->sessionContext->clearChallengeId();
            return null;
        }

        return $challenge;
    }

    private function createRedirect(string $path): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path);

        return $resultRedirect;
    }
}
