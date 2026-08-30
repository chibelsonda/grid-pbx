<?php

namespace App\Domains\Queues\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchQueueAgent extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'queue_agent_id';

    protected $fillable = ['switch_queue_id', 'switch_extension_id', 'switch_user_resource_id'];

    /** @return BelongsTo<SwitchQueue, $this> */
    public function queue(): BelongsTo
    {
        return $this->belongsTo(SwitchQueue::class, 'switch_queue_id', 'queue_id');
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id');
    }
}
