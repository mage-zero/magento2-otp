<?php

declare(strict_types=1);

namespace MageZero\Otp\Controller\Auth;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Model\Account\Redirect as AccountRedirect;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\Cookie\PhpCookieManager;

class Submit extends AbstractSessionChallengeAction implements HttpPostActionInterface
{
    /** @var FormKeyValidator */
    private $formKeyValidator;

    /** @var CustomerRepositoryInterface */
    private $customerRepository;

    /** @var CustomerSession */
    private $customerSession;

    /** @var AccountRedirect */
    private $accountRedirect;

    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var PhpCookieManager */
    private $cookieManager;

    /** @var CookieMetadataFactory */
    private $cookieMetadataFactory;

    /**
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        Context $context,
        SessionContext $sessionContext,
        ChallengeService $challengeService,
        FormKeyValidator $formKeyValidator,
        CustomerRepositoryInterface $customerRepository,
        CustomerSession $customerSession,
        AccountRedirect $accountRedirect,
        ScopeConfigInterface $scopeConfig,
        PhpCookieManager $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
    ) {
        parent::__construct($context, $sessionContext, $challengeService);
        $this->formKeyValidator = $formKeyValidator;
        $this->customerRepository = $customerRepository;
        $this->customerSession = $customerSession;
        $this->accountRedirect = $accountRedirect;
        $this->scopeConfig = $scopeConfig;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost() || !$this->formKeyValidator->validate($this->getRequest())) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }

        $challenge = $this->getSessionChallenge();
        if ($challenge === null) {
            $this->messageManager->addErrorMessage(__('Your login request has expired. Please sign in again.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account/login');
            return $resultRedirect;
        }

        if ($challenge->getStatus() !== Challenge::STATUS_APPROVED) {
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('otp/auth/pending');
            return $resultRedirect;
        }

        $submittedCode = trim((string) $this->getRequest()->getParam('otp_code', ''));
        if ($submittedCode === '') {
            $this->messageManager->addErrorMessage(__('The login code is required.'));
            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('otp/auth/verify');
            return $resultRedirect;
        }

        if ($this->challengeService->validateOtpCode($challenge, $submittedCode)) {
            $customer = $this->customerRepository->getById($challenge->getCustomerId());
            $this->customerSession->setCustomerDataAsLoggedIn($customer);
            $this->challengeService->complete($challenge);
            $this->sessionContext->clearChallengeId();
            $this->clearCacheSessionCookie();

            $redirectUrl = $this->accountRedirect->getRedirectCookie();
            if (!$this->scopeConfig->isSetFlag('customer/startup/redirect_dashboard') && $redirectUrl) {
                $this->accountRedirect->clearRedirectCookie();
                $resultRedirect = $this->resultRedirectFactory->create();
                $resultRedirect->setUrl($this->_redirect->success($redirectUrl));
                return $resultRedirect;
            }

            $resultRedirect = $this->resultRedirectFactory->create();
            $resultRedirect->setPath('customer/account');
            return $resultRedirect;
        }

        $attempts = $this->challengeService->incrementAttempts($challenge);
        $remainingAttempts = $challenge->getMaxAttempts() - $attempts;

        if ($remainingAttempts <= 0) {
            $this->challengeService->fail($challenge);
            $this->sessionContext->clearChallengeId();
            $this->messageManager->addErrorMessage(
                __('The login code was incorrect. Maximum attempts reached. Please sign in again.')
            );
        } else {
            $this->messageManager->addErrorMessage(
                __('The login code was incorrect. Please sign in again. %1 attempt(s) remaining.', $remainingAttempts)
            );
        }

        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('customer/account/login');
        return $resultRedirect;
    }

    private function clearCacheSessionCookie(): void
    {
        if ($this->cookieManager->getCookie('mage-cache-sessid')) {
            $metadata = $this->cookieMetadataFactory->createCookieMetadata();
            $metadata->setPath('/');
            $this->cookieManager->deleteCookie('mage-cache-sessid', $metadata);
        }
    }
}
