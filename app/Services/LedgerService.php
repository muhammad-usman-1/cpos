<?php

namespace App\Services;

use App\Models\Contracts\LedgerParty;
use App\Models\LedgerEntry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Party ledgers with running balances (SUP02, PAY01). Each party decides how debits and credits turn into its balance:
 * a supplier balance is payable (credit - debit), a customer balance is receivable (debit - credit).
 * Callers must run inside a database transaction.
 */
class LedgerService
{
    public function post(Model&LedgerParty $party, string $type, string $description, float $debit, float $credit, ?Model $reference = null, Carbon|string|null $date = null): LedgerEntry
    {
        $entry = LedgerEntry::create([
            'party_type' => $party->getMorphClass(),
            'party_id' => $party->getKey(),
            'entry_date' => $date ?? now()->toDateString(),
            'type' => $type,
            'description' => $description,
            'debit' => round($debit, 2),
            'credit' => round($credit, 2),
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'user_id' => auth()->id(),
        ]);

        $this->refreshBalance($party);

        return $entry;
    }

    /**
     * Post opposite entries for every live entry of a document. Originals stay visible and are marked reversed.
     */
    public function reverseFor(Model $reference, string $description): void
    {
        $entries = LedgerEntry::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->getKey())
            ->whereNull('reversed_at')
            ->where('type', '!=', LedgerEntry::REVERSAL)
            ->get();

        foreach ($entries as $entry) {
            /** @var Model&LedgerParty $party */
            $party = $entry->party;

            $this->post($party, LedgerEntry::REVERSAL, $description, (float) $entry->credit, (float) $entry->debit, $reference);
            $entry->forceFill(['reversed_at' => now()])->save();
        }
    }

    public function refreshBalance(Model&LedgerParty $party): void
    {
        $balance = $party->ledgerBalance(
            (float) $party->ledgerEntries()->sum('debit'),
            (float) $party->ledgerEntries()->sum('credit'),
        );

        $party->newQueryWithoutScopes()->whereKey($party->getKey())->update(['balance' => round($balance, 2)]);
    }

    /**
     * Ledger lines for a period with an opening balance and a running balance on every line (SUP04).
     *
     * @return array{opening: float, entries: Collection<int, LedgerEntry>, closing: float, debit: float, credit: float}
     */
    public function statement(Model&LedgerParty $party, ?string $from, ?string $to): array
    {
        $opening = 0.0;

        if ($from) {
            $before = $party->ledgerEntries()->whereDate('entry_date', '<', $from);
            $opening = $party->ledgerBalance((float) (clone $before)->sum('debit'), (float) (clone $before)->sum('credit'));
        }

        $entries = $party->ledgerEntries()
            ->with('user', 'reference')
            ->when($from, fn ($q) => $q->whereDate('entry_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('entry_date', '<=', $to))
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get();

        $running = $opening;

        foreach ($entries as $entry) {
            $running = round($running + $party->ledgerBalance((float) $entry->debit, (float) $entry->credit), 2);
            $entry->setAttribute('running_balance', $running);
        }

        return [
            'opening' => round($opening, 2),
            'entries' => $entries,
            'closing' => round($running, 2),
            'debit' => round((float) $entries->sum('debit'), 2),
            'credit' => round((float) $entries->sum('credit'), 2),
        ];
    }
}
