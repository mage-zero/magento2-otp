<?php

declare(strict_types=1);

namespace MageZero\Otp\Controller\Auth;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Verify extends AbstractSessionChallengeAction implements HttpGetActionInterface
{
    /** @var PageFactory */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        SessionContext $sessionContext,
        ChallengeService $challengeService,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context, $sessionContext, $challengeService);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
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

        return $this->resultPageFactory->create();
    }
}
