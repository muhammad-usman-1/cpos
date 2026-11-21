<?php

namespace App\Models\Contracts;

use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A model with its own ledger and cached `balance` column (suppliers, customers).
 */
interface LedgerParty
{
    /**
     * @return MorphMany<LedgerEntry, $this>
     */
    public function ledgerEntries(): MorphMany;

    /**
     * Turn debit and credit amounts into this party's balance: payable for suppliers, receivable for customers.
     */
    public function ledgerBalance(float $debit, float $credit): float;
}
