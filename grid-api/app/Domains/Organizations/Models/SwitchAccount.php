<?php

namespace App\Domains\Organizations\Models;

use App\Domains\Billing\Models\SwitchBillingSummary;
use App\Domains\Billing\Models\SwitchBillingTransaction;
use App\Domains\Billing\Models\SwitchLedgerSummary;
use App\Domains\Blacklists\Models\SwitchBlacklist;
use App\Domains\CallDetailRecords\Models\SwitchCallDetailRecord;
use App\Domains\CallerIdLists\Models\SwitchCallerIdList;
use App\Domains\CallRouting\Models\CallflowIntegrationProfile;
use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Conferences\Models\SwitchConference;
use App\Domains\Dashboard\Models\CallGeographyAggregate;
use App\Domains\Devices\Models\SwitchDevice;
use App\Domains\Directories\Models\SwitchDirectory;
use App\Domains\Extensions\Models\SwitchExtension;
use App\Domains\Faxes\Models\SwitchFax;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Groups\Models\SwitchGroup;
use App\Domains\Media\Models\SwitchMedia;
use App\Domains\Menus\Models\SwitchMenu;
use App\Domains\Payments\Models\PaymentAttempt;
use App\Domains\PhoneNumbers\Models\SwitchPhoneNumber;
use App\Domains\Queues\Models\SwitchQueue;
use App\Domains\Recordings\Models\SwitchRecording;
use App\Domains\Services\Models\SwitchServiceLimit;
use App\Domains\Services\Models\SwitchServicePlan;
use App\Domains\Services\Models\SwitchServiceQuantity;
use App\Domains\Services\Models\SwitchServiceSummary;
use App\Domains\SwitchSynchronization\Models\SyncCheckpoint;
use App\Domains\SwitchSynchronization\Models\SyncRun;
use App\Domains\TemporalRouting\Models\SwitchTemporalRule;
use App\Domains\TemporalRouting\Models\SwitchTemporalRuleSet;
use App\Domains\Voicemail\Models\SwitchVoicemailBox;
use App\Domains\Voicemail\Models\SwitchVoicemailGreeting;
use App\Domains\Voicemail\Models\SwitchVoicemailMessage;
use App\Shared\Models\Concerns\HasPublicUuid;
use Database\Factories\SwitchAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SwitchAccount extends Model
{
    /** @use HasFactory<SwitchAccountFactory> */
    use HasFactory, HasPublicUuid;

    protected $primaryKey = 'account_id';

    protected $fillable = [
        'organization_id',
        'parent_account_id',
        'switch_account_id',
        'parent_switch_account_id',
        'name',
        'org_name',
        'realm',
        'timezone',
        'language',
        'music_on_hold_media_id',
        'is_enabled',
        'is_reseller',
        'is_superduper_admin',
        'billing_mode',
        'descendants_count',
        'hierarchy_synced_at',
        'call_waiting_enabled',
        'do_not_disturb_enabled',
        'outbound_privacy',
        'ringtone_internal',
        'ringtone_external',
        'last_synced_at',
        'sync_status',
        'projection_version',
        'switch_json',
    ];

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'organization_id', 'organization_id');
    }

    /** @return BelongsTo<SwitchAccount, $this> */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id', 'account_id');
    }

    /** @return HasMany<SwitchAccount, $this> */
    public function childAccounts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id', 'account_id');
    }

    /** @return HasMany<SwitchExtension, $this> */
    public function extensions(): HasMany
    {
        return $this->hasMany(SwitchExtension::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchFaxBox, $this> */
    public function faxBoxes(): HasMany
    {
        return $this->hasMany(SwitchFaxBox::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchFax, $this> */
    public function faxes(): HasMany
    {
        return $this->hasMany(SwitchFax::class, 'switch_account_id', 'account_id');
    }

    /** @return HasOne<SwitchServiceSummary, $this> */
    public function serviceSummary(): HasOne
    {
        return $this->hasOne(SwitchServiceSummary::class, 'switch_account_id', 'account_id');
    }

    /** @return HasOne<SwitchServiceLimit, $this> */
    public function serviceLimit(): HasOne
    {
        return $this->hasOne(SwitchServiceLimit::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchServicePlan, $this> */
    public function servicePlans(): HasMany
    {
        return $this->hasMany(SwitchServicePlan::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchServiceQuantity, $this> */
    public function serviceQuantities(): HasMany
    {
        return $this->hasMany(SwitchServiceQuantity::class, 'switch_account_id', 'account_id');
    }

    /** @return HasOne<SwitchBillingSummary, $this> */
    public function billingSummary(): HasOne
    {
        return $this->hasOne(SwitchBillingSummary::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchLedgerSummary, $this> */
    public function ledgerSummaries(): HasMany
    {
        return $this->hasMany(SwitchLedgerSummary::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchBillingTransaction, $this> */
    public function billingTransactions(): HasMany
    {
        return $this->hasMany(SwitchBillingTransaction::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<PaymentAttempt, $this> */
    public function paymentAttempts(): HasMany
    {
        return $this->hasMany(PaymentAttempt::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchDevice, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(SwitchDevice::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<CallGeographyAggregate, $this> */
    public function callGeographyAggregates(): HasMany
    {
        return $this->hasMany(CallGeographyAggregate::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchVoicemailBox, $this> */
    public function voicemailBoxes(): HasMany
    {
        return $this->hasMany(SwitchVoicemailBox::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(SwitchMedia::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchMenu, $this> */
    public function menus(): HasMany
    {
        return $this->hasMany(SwitchMenu::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchDirectory, $this> */
    public function directories(): HasMany
    {
        return $this->hasMany(SwitchDirectory::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchGroup, $this> */
    public function groups(): HasMany
    {
        return $this->hasMany(SwitchGroup::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchQueue, $this> */
    public function queues(): HasMany
    {
        return $this->hasMany(SwitchQueue::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchTemporalRule, $this> */
    public function temporalRules(): HasMany
    {
        return $this->hasMany(SwitchTemporalRule::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchTemporalRuleSet, $this> */
    public function temporalRuleSets(): HasMany
    {
        return $this->hasMany(SwitchTemporalRuleSet::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchBlacklist, $this> */
    public function blacklists(): HasMany
    {
        return $this->hasMany(SwitchBlacklist::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchCallerIdList, $this> */
    public function callerIdLists(): HasMany
    {
        return $this->hasMany(SwitchCallerIdList::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchRecording, $this> */
    public function recordings(): HasMany
    {
        return $this->hasMany(SwitchRecording::class, 'switch_account_id', 'account_id');
    }

    /** @return BelongsTo<SwitchMedia, $this> */
    public function musicOnHoldMedia(): BelongsTo
    {
        return $this->belongsTo(SwitchMedia::class, 'music_on_hold_media_id', 'media_id');
    }

    /** @return HasMany<SwitchVoicemailMessage, $this> */
    public function voicemailMessages(): HasMany
    {
        return $this->hasMany(SwitchVoicemailMessage::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchVoicemailGreeting, $this> */
    public function voicemailGreetings(): HasMany
    {
        return $this->hasMany(SwitchVoicemailGreeting::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchCallflow, $this> */
    public function callflows(): HasMany
    {
        return $this->hasMany(SwitchCallflow::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<CallflowIntegrationProfile, $this> */
    public function callflowIntegrationProfiles(): HasMany
    {
        return $this->hasMany(CallflowIntegrationProfile::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchConference, $this> */
    public function conferences(): HasMany
    {
        return $this->hasMany(SwitchConference::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchPhoneNumber, $this> */
    public function phoneNumbers(): HasMany
    {
        return $this->hasMany(SwitchPhoneNumber::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SwitchCallDetailRecord, $this> */
    public function callDetailRecords(): HasMany
    {
        return $this->hasMany(SwitchCallDetailRecord::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SyncRun, $this> */
    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class, 'switch_account_id', 'account_id');
    }

    /** @return HasMany<SyncCheckpoint, $this> */
    public function syncCheckpoints(): HasMany
    {
        return $this->hasMany(SyncCheckpoint::class, 'switch_account_id', 'account_id');
    }

    /** @return HasOne<SyncCheckpoint, $this> */
    public function serviceSyncCheckpoint(): HasOne
    {
        return $this->hasOne(SyncCheckpoint::class, 'switch_account_id', 'account_id')
            ->where('resource_type', 'services');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'parent_account_id' => 'integer',
            'is_reseller' => 'boolean',
            'is_superduper_admin' => 'boolean',
            'descendants_count' => 'integer',
            'hierarchy_synced_at' => 'datetime',
            'call_waiting_enabled' => 'boolean',
            'do_not_disturb_enabled' => 'boolean',
            'last_synced_at' => 'datetime',
            'projection_version' => 'integer',
            'switch_json' => 'array',
        ];
    }

    protected static function newFactory(): SwitchAccountFactory
    {
        return SwitchAccountFactory::new();
    }
}
