<?php

namespace App\Filament\Support;

use App\Models\AuditLog;

/** For CreateRecord / EditRecord pages: the save goes to the audit log as [old, new]. */
trait AuditsPageWrites
{
    /** @var array<string, mixed> */
    protected array $auditBefore = [];

    protected function beforeSave(): void
    {
        $this->auditBefore = $this->getRecord()->getOriginal();
    }

    protected function afterSave(): void
    {
        $changes = AuditHooks::diff($this->auditBefore, $this->getRecord());

        if ($changes !== []) {
            AuditLog::record(AuditHooks::action($this->getRecord(), 'update'), $this->getRecord(), $changes);
        }
    }

    protected function afterCreate(): void
    {
        AuditLog::record(AuditHooks::action($this->getRecord(), 'create'), $this->getRecord(), AuditHooks::attributes($this->getRecord()));
    }
}
