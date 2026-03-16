<?php

declare(strict_types=1);

namespace MageZero\Otp\Model\Service;

class StartedChallenge
{
    /** @var int */
    private $challengeId;

    /** @var string */
    private $token;

    public function __construct(int $challengeId, string $token)
    {
        $this->challengeId = $challengeId;
        $this->token = $token;
    }

    public function getChallengeId(): int
    {
        return $this->challengeId;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
