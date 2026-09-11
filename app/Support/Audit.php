<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class Audit
{
    public static function log(Request $request, string $action, Model $model, ?array $before, ?array $after): void
    {
        try {
            AuditLog::query()->create([
                'actor_user_id' => $request->user()?->id,
                'action' => $action,
                'auditable_type' => $model::class,
                'auditable_id' => (int) $model->getKey(),
                'before' => $before,
                'after' => $after,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 2000),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public static function snapshot(Model $model, array $only): array
    {
        $out = [];
        foreach ($only as $k) {
            $out[$k] = $model->getAttribute($k);
        }

        return $out;
    }
}
