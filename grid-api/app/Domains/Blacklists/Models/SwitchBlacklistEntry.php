<?php

namespace App\Domains\Blacklists\Models;

use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchBlacklistEntry extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'blacklist_entry_id';

    protected $fillable = ['switch_blacklist_id', 'number', 'metadata'];

    public function blacklist(): BelongsTo
    {
        return $this->belongsTo(SwitchBlacklist::class, 'switch_blacklist_id', 'blacklist_id');
    }

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
