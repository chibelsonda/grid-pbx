<?php

namespace App\Domains\Groups\Models;

use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchGroupMember extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'group_member_id';

    protected $fillable = [
        'switch_group_id', 'switch_extension_id', 'switch_device_id', 'nested_switch_group_id',
        'member_type', 'switch_member_resource_id', 'weight',
    ];

    /** @return BelongsTo<SwitchGroup, $this> */
    public function group(): BelongsTo
    {
        return $this->belongsTo(SwitchGroup::class, 'switch_group_id', 'group_id');
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id');
    }

    /** @return BelongsTo<SwitchDevice, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SwitchDevice::class, 'switch_device_id', 'device_id');
    }

    /** @return BelongsTo<SwitchGroup, $this> */
    public function nestedGroup(): BelongsTo
    {
        return $this->belongsTo(SwitchGroup::class, 'nested_switch_group_id', 'group_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['weight' => 'integer'];
    }
}
