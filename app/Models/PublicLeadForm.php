<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class PublicLeadForm extends Model
{
    protected $guarded = ['id', 'token_hash', 'token_encrypted'];

    protected $hidden = ['token_hash', 'token_encrypted'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_reset_at' => 'datetime'];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Generates a fresh 256-bit random token, replacing (and thereby invalidating) the old one. */
    public function rotateToken(): string
    {
        $token = Str::random(48);
        $this->token_hash = self::hashToken($token);
        $this->token_encrypted = Crypt::encryptString($token);

        return $token;
    }

    public function plainToken(): string
    {
        return Crypt::decryptString($this->token_encrypted);
    }

    public function url(): string
    {
        return route('public-leads.show', $this->plainToken());
    }

    /** Resolve an active form from a raw URL token; null when unknown, reset or inactive. */
    public static function findActiveByToken(string $token): ?self
    {
        if (strlen($token) < 32 || strlen($token) > 128) {
            return null;
        }

        return self::query()
            ->where('token_hash', self::hashToken($token))
            ->where('is_active', true)
            ->with('branch')
            ->first();
    }
}
