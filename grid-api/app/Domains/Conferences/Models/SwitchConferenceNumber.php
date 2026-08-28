<?php

namespace App\Domains\Conferences\Models;

use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SwitchConferenceNumber extends Model
{
    use HasPublicUuid, HasUlids;

    protected $primaryKey = 'conference_number_id';

    protected $fillable = ['switch_conference_id', 'role', 'number'];

    public function conference(): BelongsTo
    {
        return $this->belongsTo(SwitchConference::class, 'switch_conference_id', 'conference_id');
    }
}
