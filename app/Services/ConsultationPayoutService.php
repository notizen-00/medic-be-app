<?php

namespace App\Services;

use App\Models\Consultation;
use App\Models\User;

class ConsultationPayoutService
{
    public function __construct(
        private readonly BalanceService $balanceService
    ) {
    }

    public function creditPartnerIfNeeded(Consultation $consultation, User $partner, array $meta = []): Consultation
    {
        if ((float) $consultation->consultation_fee <= 0 || $consultation->partner_balance_transaction_id !== null) {
            return $consultation;
        }

        $balance = $this->balanceService->getOrCreateBalance($partner);
        $transaction = $this->balanceService->credit($balance, (float) $consultation->consultation_fee, [
            'reference_type' => 'consultation',
            'reference_id' => $consultation->id,
            'consultation_code' => $consultation->consultation_code,
            'idempotency_key' => 'consultation:'.$consultation->id.':partner_payout',
            'description' => 'Pendapatan konsultasi '.$consultation->consultation_code,
            ...$meta,
        ]);

        $consultation->update([
            'partner_paid_at' => now(),
            'partner_balance_transaction_id' => $transaction->id,
        ]);

        return $consultation;
    }
}
