<?php

namespace App\Domains\Dashboard\Models;

use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallGeographyAggregate extends Model
{
    use HasPublicUuid;

    protected $table = 'switch_call_geography_aggregates';

    protected $primaryKey = 'call_geography_aggregate_id';

    protected $fillable = [
        'switch_account_id',
        'bucket_started_at',
        'location_key',
        'locality',
        'region_code',
        'country_code',
        'latitude',
        'longitude',
        'precision',
        'inbound_count',
        'outbound_count',
        'source',
        'source_updated_at',
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
            'bucket_started_at' => 'datetime',
            'latitude' => 'decimal:6',
            'longitude' => 'decimal:6',
            'inbound_count' => 'integer',
            'outbound_count' => 'integer',
            'source_updated_at' => 'datetime',
        ];
    }
}
