<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Auditing\Services\AuditService;
use App\Domains\Faxes\Contracts\SwitchFaxBoxGateway;
use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\IdentityAccess\Models\User;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class FaxBoxMutationService
{
    public function __construct(private readonly SwitchFaxBoxGateway $gateway, private readonly FaxBoxProjectionService $projection, private readonly AuditService $audit) {}
    public function create(SwitchAccount $account, User $actor, array $data, ?string $ip = null): SwitchFaxBox
    {
        $resourceId = null;
        try { $snapshot = $this->gateway->create($account, $this->resolve($account, $data)); $resourceId = is_string($snapshot['id'] ?? null) ? $snapshot['id'] : null; if ($resourceId === null) throw new \UnexpectedValueException('Switch fax box create response is missing its identifier.'); return DB::transaction(function () use ($account, $actor, $ip, $snapshot): SwitchFaxBox { $box = $this->projection->project($account, $snapshot); $this->audit->record($actor, $account, 'fax_box.created', 'succeeded', $box->switch_resource_id, [], $ip, 'fax_box'); return $box; }); }
        catch (Throwable $exception) { if ($resourceId !== null) try { $this->gateway->delete($account, $resourceId); } catch (Throwable) {} throw $exception; }
    }
    public function update(SwitchAccount $account, SwitchFaxBox $box, User $actor, array $data, ?string $ip = null): SwitchFaxBox
    {
        $snapshot = $this->gateway->update($account, $box->switch_resource_id, $this->resolve($account, $data));
        return DB::transaction(function () use ($account, $actor, $ip, $snapshot): SwitchFaxBox { $updated = $this->projection->project($account, $snapshot); $this->audit->record($actor, $account, 'fax_box.updated', 'succeeded', $updated->switch_resource_id, [], $ip, 'fax_box'); return $updated; });
    }
    public function delete(SwitchAccount $account, SwitchFaxBox $box, User $actor, ?string $ip = null): void
    {
        foreach ($account->callflows()->get() as $callflow) if ($this->containsFaxBox($callflow->switch_json['flow'] ?? null, $box->switch_resource_id)) throw ValidationException::withMessages(['fax_box' => ['Remove this fax box from call routing before deleting it.']]);
        $this->gateway->delete($account, $box->switch_resource_id); DB::transaction(function () use ($account, $box, $actor, $ip): void { $box->delete(); $this->audit->record($actor, $account, 'fax_box.deleted', 'succeeded', $box->switch_resource_id, ['retained_fax_count' => $box->faxes()->count()], $ip, 'fax_box'); });
    }
    private function resolve(SwitchAccount $account, array $data): array { $ownerId = $data['owner_id'] ?? null; $owner = empty($ownerId) ? null : $account->extensions()->where('id', $ownerId)->first(); if (! empty($ownerId) && $owner === null) throw ValidationException::withMessages(['owner_id' => ['The selected fax-box owner is unavailable for this account.']]); return [...$data, 'switch_owner_reference' => $owner?->switch_resource_id]; }
    private function containsFaxBox(mixed $node, string $id): bool { if (! is_array($node)) return false; $data = is_array($node['data'] ?? null) ? $node['data'] : []; if (($node['module'] ?? null) === 'faxbox' && (($data['id'] ?? null) === $id || ($data['faxbox_id'] ?? null) === $id)) return true; foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $child) if ($this->containsFaxBox($child, $id)) return true; return false; }
}
