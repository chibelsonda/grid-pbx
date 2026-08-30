<?php

namespace App\Domains\Media\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchMediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchMedia extends Model
{
    /** @use HasFactory<SwitchMediaFactory> */
    use HasFactory, HasPublicUuid, SoftDeletes;

    protected $table = 'switch_media';

    protected $primaryKey = 'media_id';

    protected $fillable = [
        'switch_account_id',
        'switch_resource_id',
        'name',
        'description',
        'language',
        'media_source',
        'content_type',
        'content_length',
        'prompt_id',
        'source_type',
        'source_resource_id',
        'streamable',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'switch_json',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'content_length' => 'integer',
            'streamable' => 'boolean',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchMediaFactory
    {
        return SwitchMediaFactory::new();
    }
}
