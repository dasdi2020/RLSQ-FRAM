<?php

declare(strict_types=1);

namespace RLSQ\Security\Authorization;

use RLSQ\Security\Authentication\TokenInterface;
use RLSQ\Security\Authorization\Voter\VoterInterface;

class AccessDecisionManager
{
    public const STRATEGY_AFFIRMATIVE = 'affirmative';
    public const STRATEGY_UNANIMOUS = 'unanimous';
    public const STRATEGY_CONSENSUS = 'consensus';

    /** @var VoterInterface[] */
    private array $voters;
    private string $strategy;

    /**
     * @param VoterInterface[] $voters
     */
    public function __construct(array $voters = [], string $strategy = self::STRATEGY_AFFIRMATIVE)
    {
        $this->voters = $voters;
        $this->strategy = $strategy;
    }

    public function addVoter(VoterInterface $voter): void
    {
        $this->voters[] = $voter;
    }

    /**
     * @param string[] $attributes
     */
    public function decide(TokenInterface $token, array $attributes, mixed $subject = null): bool
    {
        return match ($this->strategy) {
            self::STRATEGY_AFFIRMATIVE => $this->decideAffirmative($token, $attributes, $subject),
            self::STRATEGY_UNANIMOUS => $this->decideUnanimous($token, $attributes, $subject),
            self::STRATEGY_CONSENSUS => $this->decideConsensus($token, $attributes, $subject),
            default => false,
        };
    }

    /**
     * Un seul GRANTED suffit.
     */
    private function decideAffirmative(TokenInterface $token, array $attributes, mixed $subject): bool
    {
        $deny = 0;

        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $subject, $attributes);

            if ($result === VoterInterface::ACCESS_GRANTED) {
                return true;
            }

            if ($result === VoterInterface::ACCESS_DENIED) {
                $deny++;
            }
        }

        return $deny === 0;
    }

    /**
     * Tous doivent être GRANTED (aucun DENIED).
     */
    private function decideUnanimous(TokenInterface $token, array $attributes, mixed $subject): bool
    {
        $grant = 0;

        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $subject, $attributes);

            if ($result === VoterInterface::ACCESS_DENIED) {
                return false;
            }

            if ($result === VoterInterface::ACCESS_GRANTED) {
                $grant++;
            }
        }

        return $grant > 0;
    }

    /**
     * La majorité l'emporte.
     */
    private function decideConsensus(TokenInterface $token, array $attributes, mixed $subject): bool
    {
        $grant = 0;
        $deny = 0;

        foreach ($this->voters as $voter) {
            $result = $voter->vote($token, $subject, $attributes);

            if ($result === VoterInterface::ACCESS_GRANTED) {
                $grant++;
            } elseif ($result === VoterInterface::ACCESS_DENIED) {
                $deny++;
            }
        }

        return $grant > $deny;
    }
}
