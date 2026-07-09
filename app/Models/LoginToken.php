<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LoginToken extends Model
{
    public const TTL_MINUTES = 15;

    protected $fillable = [
        'user_id',
        'token_hash',
        'expires_at',
        'used_at',
        'requested_ip',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Выпустить токен для пользователя. Возвращает [модель, plaintext-токен] —
     * plaintext живёт только в письме, в БД лежит sha256.
     */
    public static function issue(User $user, ?string $ip = null): array
    {
        $plain = Str::random(64);

        $token = self::create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'requested_ip' => $ip,
        ]);

        return [$token, $plain];
    }

    public static function findUsable(string $plain): ?self
    {
        return self::query()
            ->where('token_hash', hash('sha256', $plain))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }
}
