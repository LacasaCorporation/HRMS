<?php

namespace App\Support;

use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditTrail
{
    public static function record(string $event, ?Model $subject = null, array $metadata = []): void
    {
        $actor = Auth::user();

        AuditEvent::create([
            'company_id' => $actor?->company_id,
            'actor_id' => $actor?->id,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
        ]);
    }
}
