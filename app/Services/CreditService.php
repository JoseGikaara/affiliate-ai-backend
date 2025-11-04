<?php

namespace App\Services;

use App\Models\AffiliateNetwork;
use App\Models\CreditTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function hasEnoughCredits(User $user, int $amount): bool
    {
        return (int) ($user->credits ?? 0) >= $amount;
    }

    public function deductCredits(User $user, int $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description) {
            $user->refresh();
            $newBalance = max(0, (int) $user->credits - $amount);
            $user->update(['credits' => $newBalance]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => -abs($amount),
                'type' => 'debit',
                'description' => $description ?? 'Credit deduction',
            ]);
        });
    }

    /**
     * Training-specific checks: allow using free credits first, then paid credits.
     */
    public function hasEnoughTrainingCredits(User $user, int $amount): bool
    {
        $free = (int) ($user->free_credits ?? 0);
        $paid = (int) ($user->credits ?? 0);
        return ($free + $paid) >= $amount;
    }

    /**
     * Deduct credits for training modules: consume free credits first, then paid.
     */
    public function deductTrainingCredits(User $user, int $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description) {
            $user->refresh();
            $free = (int) ($user->free_credits ?? 0);
            $paid = (int) ($user->credits ?? 0);

            $useFree = min($free, $amount);
            $remaining = $amount - $useFree;

            if ($useFree > 0) {
                $user->update(['free_credits' => $free - $useFree]);

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'amount' => -abs($useFree),
                    'type' => 'debit',
                    'origin' => 'free',
                    'locked_for' => 'training',
                    'description' => $description ?? 'Training deduction (free)',
                ]);
            }

            if ($remaining > 0) {
                // Use paid credits for the rest
                $newBalance = max(0, $paid - $remaining);
                $user->update(['credits' => $newBalance]);

                CreditTransaction::create([
                    'user_id' => $user->id,
                    'amount' => -abs($remaining),
                    'type' => 'debit',
                    'origin' => 'paid',
                    'locked_for' => 'training',
                    'description' => $description ?? 'Training deduction (paid)',
                ]);
            }
        });
    }

    /**
     * Add free credits (locked for training only)
     */
    public function addFreeCredits(User $user, int $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description) {
            $user->refresh();
            $newBalance = (int) $user->free_credits + $amount;
            $user->update(['free_credits' => $newBalance]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => abs($amount),
                'type' => 'credit',
                'origin' => 'free',
                'locked_for' => 'training',
                'description' => $description ?? 'Free training credits granted',
            ]);
        });
    }

    public function addCredits(User $user, int $amount, ?string $description = null): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($user, $amount, $description) {
            $user->refresh();
            $newBalance = (int) $user->credits + $amount;
            $user->update(['credits' => $newBalance]);

            CreditTransaction::create([
                'user_id' => $user->id,
                'amount' => abs($amount),
                'type' => 'credit',
                'description' => $description ?? 'Credit addition',
            ]);
        });
    }

    /**
     * Calculate credit cost for landing page generation based on network category
     */
    public function calculateLandingPageCost(?AffiliateNetwork $network, bool $withEmailAutomation = false): int
    {
        if (!$network) {
            return config('credits.cost_per_task', 5);
        }

        $baseCost = $network->getBaseCreditCost();

        // Apply email automation multiplier if enabled
        if ($withEmailAutomation) {
            $multiplier = config('credits.email_automation_multiplier', 1.2);
            $baseCost = (int) ceil($baseCost * $multiplier);
        }

        return $baseCost;
    }

    /**
     * Calculate renewal cost (same as base cost)
     */
    public function calculateRenewalCost(?AffiliateNetwork $network): int
    {
        if (!$network) {
            return config('credits.cost_per_task', 5);
        }

        return $network->getBaseCreditCost();
    }
}


