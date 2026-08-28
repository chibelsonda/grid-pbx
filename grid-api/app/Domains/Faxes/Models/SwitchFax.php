<?php

namespace App\Domains\Faxes\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchFaxFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchFax extends Model
{
    /** @use HasFactory<SwitchFaxFactory> */ use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;
    protected $primaryKey = 'fax_id';
    protected $fillable = ['switch_account_id', 'switch_fax_box_id', 'switch_extension_id', 'switch_resource_id', 'fax_box_switch_resource_id', 'owner_switch_resource_id', 'folder', 'status', 'from_name', 'from_number', 'to_name', 'to_number', 'subject', 'attempts', 'retries', 'successful', 'error_message', 'pages', 'fax_speed', 'elapsed_seconds', 'switch_created_at', 'has_document', 'document_content_type', 'document_size', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];
    public function switchAccount(): BelongsTo { return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id'); }
    public function faxBox(): BelongsTo { return $this->belongsTo(SwitchFaxBox::class, 'switch_fax_box_id', 'fax_box_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id'); }
    protected function casts(): array { return ['attempts' => 'integer', 'retries' => 'integer', 'successful' => 'boolean', 'pages' => 'integer', 'fax_speed' => 'integer', 'elapsed_seconds' => 'integer', 'switch_created_at' => 'datetime', 'has_document' => 'boolean', 'document_size' => 'integer', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array']; }
    protected static function newFactory(): SwitchFaxFactory { return SwitchFaxFactory::new(); }
}
