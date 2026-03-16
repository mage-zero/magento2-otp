<?php

declare(strict_types=1);

namespace MageZero\Otp\Controller\Auth;

use MageZero\Otp\Model\Challenge;
use MageZero\Otp\Model\Service\ChallengeService;
use MageZero\Otp\Model\Session\Context as SessionContext;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;

abstract class AbstractSessionChallengeAction extends Action
{
    /** @var SessionContext */
    protected $sessionContext;

    /** @var ChallengeService */
    protected $challengeService;

    public function __construct(
        Context $context,
        SessionContext $sessionContext,
        ChallengeService $challengeService
    ) {
        parent::__construct($context);
        $this->sessionContext = $sessionContext;
        $this->challengeService = $challengeService;
    }

    protected function getSessionChallenge(): ?Challenge
    {
        $challengeId = $this->sessionContext->getChallengeId();
        if ($challengeId === null) {
            return null;
        }

        $challenge = $this->challengeService->getById($challengeId);
        if ($challenge === null) {
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
}
