<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'lead_number_prefix' => 'RL',
        'public_form_heading' => 'Request a callback',
        'public_form_intro' => 'Leave your details and one of our advisors will contact you shortly.',
        'public_form_success' => 'Thank you! Your details have been received. An advisor will be in touch soon.',
    ];

    public static function get(string $key): ?string
    {
        $all = Cache::rememberForever('settings.all', fn () => self::query()->pluck('value', 'key')->all());

        return $all[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function allWithDefaults(): array
    {
        return array_map(fn ($k) => self::get($k), array_combine(array_keys(self::DEFAULTS), array_keys(self::DEFAULTS)));
    }

    public static function put(string $key, ?string $value): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('settings.all');
    }
}
