<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    public function log(string $action, ?Model $entity = null, ?array $before = null, ?array $after = null): AuditLog
    {
        // Never put passwords, tokens or payment secrets in before/after.
        return AuditLog::create([
            'user_id' => $this->request->user()?->id,
            'action' => $action,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => $this->request->ip(),
        ]);
    }
}
