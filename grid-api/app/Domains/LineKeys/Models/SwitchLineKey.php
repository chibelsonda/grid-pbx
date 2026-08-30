<?php

namespace App\Domains\LineKeys\Models;

use App\Domains\Devices\Models\SwitchDevice;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchLineKey extends Model
{
    use HasPublicUuid, SoftDeletes;

    protected $primaryKey = 'line_key_id';

    protected $fillable = [
        'switch_device_id',
        'category',
        'position',
        'type',
        'label',
        'value',
        'switch_json',
    ];

    /** @return BelongsTo<SwitchDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SwitchDevice::class, 'switch_device_id', 'device_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'switch_json' => 'array',
        ];
    }
}
