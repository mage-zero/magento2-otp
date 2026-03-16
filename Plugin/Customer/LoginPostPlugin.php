<?php

declare(strict_types=1);

namespace MageZero\Otp\Plugin\Customer;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Config;
use MageZero\Otp\Model\Email\OtpEmailSender;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Controller\Account\LoginPost;
use Magento\Customer\Model\Session;
use Magento\Customer\Model\Url as CustomerUrl;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\EmailNotConfirmedException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class LoginPostPlugin
{
    /** @var Session */
    private $customerSession;

    /** @var FormKeyValidator */
    private $formKeyValidator;

    /** @var AccountManagementInterface */
    private $accountManagement;

    /** @var RedirectFactory */
    private $resultRedirectFactory;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var CustomerUrl */
    private $customerUrl;

    /** @var ChallengeService */
    private $challengeService;

    /** @var SessionContext */
    private $sessionContext;

    /** @var OtpEmailSender */
    private $emailSender;

    /** @var UrlInterface */
    private $urlBuilder;

    /** @var Config */
    private $config;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Session $customerSession,
        FormKeyValidator $formKeyValidator,
        AccountManagementInterface $accountManagement,
        RedirectFactory $resultRedirectFactory,
        ManagerInterface $messageManager,
        CustomerUrl $customerUrl,
        ChallengeService $challengeService,
        SessionContext $sessionContext,
        OtpEmailSender $emailSender,
        UrlInterface $urlBuilder,
        Config $config
    ) {
        $this->customerSession = $customerSession;
        $this->formKeyValidator = $formKeyValidator;
        $this->accountManagement = $accountManagement;
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->customerUrl = $customerUrl;
        $this->challengeService = $challengeService;
        $this->sessionContext = $sessionContext;
        $this->emailSender = $emailSender;
        $this->urlBuilder = $urlBuilder;
        $this->config = $config;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function aroundExecute(LoginPost $subject, callable $proceed)
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        $request = $subject->getRequest();
        if ($this->customerSession->isLoggedIn() || !$this->formKeyValidator->validate($request) || !$request->isPost()) {
            return $proceed();
        }

        $login = $request->getPost('login');
        if (!is_array($login) || empty($login['username']) || empty($login['password'])) {
            return $proceed();
        }

        try {
            $customer = $this->accountManagement->authenticate((string) $login['username'], (string) $login['password']);

            $challenge = $this->getUsableSessionChallenge((int) $customer->getId());
            if ($challenge !== null) {
                return $this->redirectForExistingChallenge($challenge);
            }

            $storeId = $customer->getStoreId() !== null ? (int) $customer->getStoreId() : null;
            $startedChallenge = $this->challengeService->createChallenge(
                (int) $customer->getId(),
                (string) $customer->getEmail(),
                (string) $this->customerSession->getSessionId(),
                $storeId
            );

            $this->sessionContext->setChallengeId($startedChallenge->getChallengeId());

            $approvalUrl = $this->urlBuilder->getUrl('otp/auth/approve', [
                '_nosid' => true,
                'token' => $startedChallenge->getToken(),
            ]);
            $this->emailSender->send($customer, $approvalUrl);

            $this->customerSession->setUsername((string) $login['username']);

            return $this->createRedirect('otp/auth/pending');
        } catch (EmailNotConfirmedException $e) {
            $this->messageManager->addComplexErrorMessage(
                'confirmAccountErrorMessage',
                ['url' => $this->customerUrl->getEmailConfirmationUrl((string) $login['username'])]
            );
            $this->customerSession->setUsername((string) $login['username']);
        } catch (AuthenticationException $e) {
            $this->messageManager->addErrorMessage(
                __('The account sign-in was incorrect or your account is disabled temporarily. Please wait and try again later.')
            );
            $this->customerSession->setUsername((string) $login['username']);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->customerSession->setUsername((string) $login['username']);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(
                __('An unspecified error occurred. Please contact us for assistance.')
            );
            $this->customerSession->setUsername((string) $login['username']);
        }

        return $this->createRedirect('*/*/');
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

    private function redirectForExistingChallenge(Challenge $challenge): Redirect
    {
        if ($challenge->getStatus() === Challenge::STATUS_APPROVED) {
            return $this->createRedirect('otp/auth/verify');
        }

        return $this->createRedirect('otp/auth/pending');
    }

    private function createRedirect(string $path): Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath($path);

        return $resultRedirect;
    }
}
