<?php

namespace App\Domains\Blacklists\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchBlacklistFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchBlacklist extends Model
{
    /** @use HasFactory<SwitchBlacklistFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'blacklist_id';
    protected $fillable = ['switch_account_id', 'switch_resource_id', 'name', 'should_block_anonymous', 'is_active', 'flags', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];

    public function switchAccount(): BelongsTo { return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id'); }
    public function entries(): HasMany { return $this->hasMany(SwitchBlacklistEntry::class, 'switch_blacklist_id', 'blacklist_id')->orderBy('number'); }

    protected function casts(): array
    {
        return ['should_block_anonymous' => 'boolean', 'is_active' => 'boolean', 'flags' => 'array', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array'];
    }

    protected static function newFactory(): SwitchBlacklistFactory { return SwitchBlacklistFactory::new(); }
}
