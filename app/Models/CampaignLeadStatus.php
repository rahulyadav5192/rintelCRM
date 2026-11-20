<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignLeadStatus extends Model
{
    protected $fillable = ['name', 'color', 'sort_order', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'lead_status_id');
    }
}
