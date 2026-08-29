<?php

namespace App\Domains\PhoneNumbers\Models;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchPhoneNumberFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchPhoneNumber extends Model
{
    /** @use HasFactory<SwitchPhoneNumberFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;

    protected $primaryKey = 'phone_number_id';

    protected $fillable = [
        'switch_account_id',
        'assigned_callflow_id',
        'number',
        'state',
        'used_by',
        'assigned_to_switch_account_id',
        'carrier_name',
        'features',
        'cnam_display_name',
        'cnam_inbound_lookup',
        'e911_status',
        'source_created_timestamp',
        'source_updated_timestamp',
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

    /** @return BelongsTo<SwitchCallflow, $this> */
    public function assignedCallflow(): BelongsTo
    {
        return $this->belongsTo(SwitchCallflow::class, 'assigned_callflow_id', 'callflow_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'features' => 'array',
            'cnam_inbound_lookup' => 'boolean',
            'source_created_timestamp' => 'integer',
            'source_updated_timestamp' => 'integer',
            'last_synced_at' => 'datetime',
            'sync_status' => ProjectionStatus::class,
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchPhoneNumberFactory
    {
        return SwitchPhoneNumberFactory::new();
    }

    public function isE911Enabled(): bool
    {
        $features = collect($this->features ?? [])
            ->filter(static fn (mixed $feature): bool => is_string($feature))
            ->map(static fn (string $feature): string => strtolower($feature));

        return $features->contains('e911')
            || in_array(strtolower((string) $this->e911_status), [
                'active',
                'enabled',
                'provisioned',
            ], true);
    }
}
