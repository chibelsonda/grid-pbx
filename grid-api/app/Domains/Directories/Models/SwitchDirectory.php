<?php

namespace App\Domains\Directories\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchDirectoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchDirectory extends Model
{
    /** @use HasFactory<SwitchDirectoryFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'directory_id';

    protected $fillable = [
        'switch_account_id', 'switch_resource_id', 'name', 'confirm_match', 'min_dtmf',
        'max_dtmf', 'sort_by', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchDirectoryMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(SwitchDirectoryMember::class, 'switch_directory_id', 'directory_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'confirm_match' => 'boolean', 'min_dtmf' => 'integer', 'max_dtmf' => 'integer',
            'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer', 'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchDirectoryFactory
    {
        return SwitchDirectoryFactory::new();
    }
}
