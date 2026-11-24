<?php

namespace App\Models;

use App\Enums\Role;
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

    /** The Admin this form belongs to; every lead submitted through it is owned by them. */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
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

    /** Creates the public form for an Admin if they do not have one yet. */
    public static function ensureFor(User $admin, ?int $createdBy = null): self
    {
        $form = self::query()->firstWhere('user_id', $admin->id);
        if (! $form) {
            $form = new self(['is_active' => true]);
            $form->user_id = $admin->id;
            $form->created_by = $createdBy;
            $form->rotateToken();
            $form->save();
        }

        return $form;
    }

    public function plainToken(): string
    {
        return Crypt::decryptString($this->token_encrypted);
    }

    public function url(): string
    {
        return route('public-leads.show', $this->plainToken());
    }

    /** Resolve an active form from a raw URL token; null when unknown, reset, inactive or the admin is inactive. */
    public static function findActiveByToken(string $token): ?self
    {
        if (strlen($token) < 32 || strlen($token) > 128) {
            return null;
        }

        return self::query()
            ->where('token_hash', self::hashToken($token))
            ->where('is_active', true)
            ->whereHas('admin', fn ($q) => $q->where('is_active', true)->where('role', Role::Admin->value))
            ->with('admin')
            ->first();
    }
}
