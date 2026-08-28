<?php

namespace App\Domains\Extensions\Models;

use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use App\Shared\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtensionLifecycleOperation extends Model
{
    use HasPublicUuid, HasUlids;

    protected $primaryKey = 'extension_lifecycle_operation_id';

    protected $fillable = [
        'switch_account_id',
        'switch_extension_id',
        'requested_by_user_id',
        'operation',
        'status',
        'completed_steps',
        'failed_step',
        'error_type',
        'error_message',
        'context',
        'completed_at',
    ];

    /** @return BelongsTo<SwitchAccount, $this> */
    public function switchAccount(): BelongsTo
    {
        return $this->belongsTo(SwitchAccount::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<SwitchExtension, $this> */
    public function extension(): BelongsTo
    {
        return $this->belongsTo(SwitchExtension::class, 'switch_extension_id', 'extension_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id', 'user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'context' => 'array',
            'completed_at' => 'datetime',
        ];
    }
}
