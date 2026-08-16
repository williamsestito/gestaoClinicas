<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Enums\AuditAction;
use App\Enums\WaitlistEntryStatus;
use App\Models\WaitlistEntry;
use App\Support\Auditing\AuditLogger;
use Illuminate\Validation\ValidationException;

class CancelWaitlistEntryAction
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function handle(WaitlistEntry $entry): WaitlistEntry
    {
        if ($entry->status !== WaitlistEntryStatus::Waiting) {
            throw ValidationException::withMessages([
                'waitlist_entry' => 'Esta entrada da lista de espera já não está mais aguardando.',
            ]);
        }

        $entry->update(['status' => WaitlistEntryStatus::Cancelled]);

        $this->auditLogger->log(
            AuditAction::Updated,
            auditable: $entry,
            before: ['status' => WaitlistEntryStatus::Waiting->value],
            after: ['status' => WaitlistEntryStatus::Cancelled->value],
            organization: $entry->organization,
            unit: $entry->unit,
        );

        return $entry;
    }
}
