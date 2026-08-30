<?php

namespace App\Domains\Directories\Models;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchDirectoryMember extends Model
{
    use HasPublicUuid;

    protected $primaryKey = 'directory_member_id';

    protected $fillable = [
        'switch_directory_id', 'switch_extension_id', 'switch_callflow_id',
        'switch_user_resource_id', 'switch_callflow_resource_id',
    ];

    /** @return BelongsTo<SwitchDirectory, $this> */
    public function directory(): BelongsTo
    {
        return $this->belongsTo(SwitchDirectory::class, 'switch_directory_id', 'directory_id');
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id');
    }

    /** @return BelongsTo<SwitchCallflow, $this> */
    public function callflow(): BelongsTo
    {
        return $this->belongsTo(SwitchCallflow::class, 'switch_callflow_id', 'callflow_id');
    }
}
