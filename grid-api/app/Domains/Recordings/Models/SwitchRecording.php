<?php

namespace App\Domains\Recordings\Models;

use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchRecordingFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchRecording extends Model
{
    /** @use HasFactory<SwitchRecordingFactory> */
    use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;
    protected $primaryKey = 'recording_id';
    protected $fillable = ['switch_account_id', 'switch_extension_id', 'switch_call_detail_record_id', 'switch_resource_id', 'owner_switch_resource_id', 'call_id', 'cdr_id', 'interaction_id', 'direction', 'caller_id_name', 'caller_id_number', 'callee_id_name', 'callee_id_number', 'from_uri', 'to_uri', 'request_uri', 'started_at', 'duration_seconds', 'duration_milliseconds', 'name', 'description', 'content_type', 'content_length', 'media_source', 'media_type', 'source_type', 'origin', 'has_audio', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];
    public function switchAccount(): BelongsTo { return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id'); }
    public function extension(): BelongsTo { return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id'); }
    public function callDetailRecord(): BelongsTo { return $this->belongsTo(SwitchCallDetailRecord::class, 'switch_call_detail_record_id', 'call_detail_record_id'); }
    protected function casts(): array { return ['started_at' => 'datetime', 'duration_seconds' => 'integer', 'duration_milliseconds' => 'integer', 'content_length' => 'integer', 'has_audio' => 'boolean', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array']; }
    protected static function newFactory(): SwitchRecordingFactory { return SwitchRecordingFactory::new(); }
}
