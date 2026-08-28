<?php

namespace App\Domains\Auditing\Models;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasPublicUuid, HasUlids;

    protected $primaryKey = 'audit_log_id';

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'organization_id',
        'switch_account_id',
        'request_id',
        'action',
        'resource_type',
        'resource_id',
        'outcome',
        'metadata',
        'ip_address',
        'created_at',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
