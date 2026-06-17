<?php

namespace App\Services\Accounting;

use App\Models\FinancialAuditLog;

class FinancialAuditService
{
    public function log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): FinancialAuditLog
    {
        return FinancialAuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details ?: null,
        ]);
    }

    public function listForPeriod(?string $start, ?string $end, int $limit = 200): array
    {
        $query = FinancialAuditLog::query()->orderByDesc('created_at')->limit($limit);

        if ($start && $end) {
            $query->whereBetween('created_at', [$start . ' 00:00:00', $end . ' 23:59:59']);
        }

        return $query->get()->map(fn ($row) => [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'action' => $row->action,
            'entity_type' => $row->entity_type,
            'entity_id' => $row->entity_id,
            'details' => $row->details,
            'created_at' => $row->created_at?->toDateTimeString(),
        ])->all();
    }
}
