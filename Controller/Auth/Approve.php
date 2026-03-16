<?php

declare(strict_types=1);

namespace MageZero\Otp\Controller\Auth;

use MageZero\Otp\Block\Approve as ApproveBlock;
use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\View\Result\PageFactory;

class Approve extends Action implements HttpGetActionInterface
{
    /** @var ChallengeService */
    private $challengeService;

    /** @var PageFactory */
    private $resultPageFactory;

    public function __construct(
        Context $context,
        ChallengeService $challengeService,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->challengeService = $challengeService;
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();

        $layout = $resultPage->getLayout();
        $block = $layout->getBlock('mz.otp.approve');
        if (!$block instanceof ApproveBlock) {
            return $resultPage;
        }

        $token = trim((string) $this->getRequest()->getParam('token', ''));
        if ($token === '') {
            $block->setSuccessfulApproval(false)
                ->setFailureMessage((string) __('The login link is invalid or incomplete.'));
            return $resultPage;
        }

        $challenge = $this->challengeService->getByToken($token);
        if ($challenge === null) {
            $block->setSuccessfulApproval(false)
                ->setFailureMessage((string) __('The login link is invalid or has already expired.'));
            return $resultPage;
        }

        if ($this->challengeService->hasExpired($challenge) || !$this->challengeService->hasAttemptsRemaining($challenge)) {
            $this->challengeService->fail($challenge);
            $block->setSuccessfulApproval(false)
                ->setFailureMessage((string) __('This login link has expired. Please restart login.'));
            return $resultPage;
        }

        if (in_array($challenge->getStatus(), [Challenge::STATUS_COMPLETED, Challenge::STATUS_FAILED], true)) {
            $block->setSuccessfulApproval(false)
                ->setFailureMessage((string) __('This login request is no longer active.'));
            return $resultPage;
        }

        if ($challenge->getStatus() === Challenge::STATUS_PENDING) {
            $this->challengeService->approve($challenge);
        }

        try {
            $block->setSuccessfulApproval(true)
                ->setOtpCode($this->challengeService->getDisplayCode($challenge));
        } catch (\Throwable $e) {
            $this->challengeService->fail($challenge);
            $block->setSuccessfulApproval(false)
                ->setFailureMessage((string) __('This login request is no longer active. Please restart login.'));
        }

        return $resultPage;
    }
}
