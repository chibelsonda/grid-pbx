<?php

namespace App\Domains\Extensions\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use Database\Factories\SwitchVoicemailBoxFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchVoicemailBox extends Model
{
    /** @use HasFactory<SwitchVoicemailBoxFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'switch_resource_id',
        'owner_switch_resource_id',
        'name',
        'mailbox',
        'is_setup',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'source_payload',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class);
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_setup' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'source_payload' => 'array',
        ];
    }

    protected static function newFactory(): SwitchVoicemailBoxFactory
    {
        return SwitchVoicemailBoxFactory::new();
    }
}
