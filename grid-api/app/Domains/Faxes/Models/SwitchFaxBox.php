<?php

namespace App\Domains\Faxes\Models;

use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Domains\SwitchSynchronization\Enums\ProjectionStatus;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchFaxBoxFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SwitchFaxBox extends Model
{
    /** @use HasFactory<SwitchFaxBoxFactory> */ use HasFactory, HasPublicUuid, HasUlids, SoftDeletes;
    protected $primaryKey = 'fax_box_id';
    protected $fillable = ['switch_account_id', 'owner_extension_id', 'switch_resource_id', 'owner_switch_resource_id', 'name', 'caller_id', 'caller_name', 'fax_header', 'fax_identity', 'fax_timezone', 'retries', 't38_enabled', 'smtp_email_address', 'custom_smtp_email_address', 'smtp_permission_list', 'inbound_notification_emails', 'outbound_notification_emails', 'last_synced_at', 'sync_status', 'projection_version', 'switch_json'];
    public function switchAccount(): BelongsTo { return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(SwitchExtension::class, 'owner_extension_id', 'extension_id'); }
    public function faxes(): HasMany { return $this->hasMany(SwitchFax::class, 'switch_fax_box_id', 'fax_box_id'); }
    protected function casts(): array { return ['retries' => 'integer', 't38_enabled' => 'boolean', 'smtp_permission_list' => 'array', 'inbound_notification_emails' => 'array', 'outbound_notification_emails' => 'array', 'last_synced_at' => 'datetime', 'sync_status' => ProjectionStatus::class, 'projection_version' => 'integer', 'switch_json' => 'array']; }
    protected static function newFactory(): SwitchFaxBoxFactory { return SwitchFaxBoxFactory::new(); }
}
