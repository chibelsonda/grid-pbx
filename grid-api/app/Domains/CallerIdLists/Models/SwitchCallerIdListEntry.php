<?php

namespace App\Domains\CallerIdLists\Models;

use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchCallerIdListEntry extends Model
{
    use HasPublicUuid, HasUlids;

    protected $primaryKey = 'caller_id_list_entry_id';

    protected $fillable = [
        'switch_caller_id_list_id',
        'switch_resource_id',
        'display_name',
        'number',
        'pattern',
        'switch_json',
    ];

    /** @return BelongsTo<SwitchCallerIdList, $this> */
    public function callerIdList(): BelongsTo
    {
        return $this->belongsTo(SwitchCallerIdList::class, 'switch_caller_id_list_id', 'caller_id_list_id');
    }

    protected function casts(): array
    {
        return ['switch_json' => 'array'];
    }
}
