<?php

namespace App\Domains\Menus\Models;

use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchMenuFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchMenu extends Model
{
    /** @use HasFactory<SwitchMenuFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'menu_id';

    protected $fillable = [
        'switch_account_id', 'switch_resource_id', 'name', 'timeout', 'interdigit_timeout',
        'max_extension_length', 'retries', 'hunt', 'allow_record_from_offnet', 'suppress_media',
        'record_pin_configured', 'hunt_allow', 'hunt_deny', 'greeting_media_id', 'invalid_media_id',
        'transfer_media_id', 'exit_media_id', 'greeting_media_reference', 'invalid_media_reference',
        'transfer_media_reference', 'exit_media_reference', 'invalid_media_enabled',
        'transfer_media_enabled', 'exit_media_enabled', 'last_synced_at', 'sync_status',
        'projection_version', 'switch_json',
    ];

    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    public function greetingMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'greeting_media_id', 'media_id');
    }

    public function invalidMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'invalid_media_id', 'media_id');
    }

    public function transferMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'transfer_media_id', 'media_id');
    }

    public function exitMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'exit_media_id', 'media_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'timeout' => 'integer', 'interdigit_timeout' => 'integer', 'max_extension_length' => 'integer',
            'retries' => 'integer', 'hunt' => 'boolean', 'allow_record_from_offnet' => 'boolean',
            'suppress_media' => 'boolean', 'invalid_media_enabled' => 'boolean',
            'record_pin_configured' => 'boolean',
            'transfer_media_enabled' => 'boolean', 'exit_media_enabled' => 'boolean',
            'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer', 'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchMenuFactory
    {
        return SwitchMenuFactory::new();
    }
}
