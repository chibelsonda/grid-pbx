<?php

namespace App\Domains\Faxes\Services;

use App\Domains\Faxes\Models\SwitchFaxBox;
use App\Domains\Organizations\Models\SwitchAccount;
use DateTimeZone;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FaxBoxService
{
    public function paginate(SwitchAccount $account, array $filters, int $perPage): LengthAwarePaginator
    {
        return $account->faxBoxes()->with('owner')->withCount('faxes')->when($filters['search'] ?? null, fn ($query, string $search) => $query->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('smtp_email_address', 'like', "%{$search}%")->orWhere('custom_smtp_email_address', 'like', "%{$search}%")))->orderBy('name')->paginate($perPage)->withQueryString();
    }

    public function find(SwitchAccount $account, string $id): SwitchFaxBox
    {
        return $account->faxBoxes()->where('id', $id)->with('owner')->withCount('faxes')->firstOrFail();
    }

    public function options(SwitchAccount $account): array
    {
        return [
            'account_defaults' => ['timezone' => $account->timezone],
            'timezones' => DateTimeZone::listIdentifiers(),
            'caller_id_numbers' => $account->phoneNumbers()->orderBy('number')->pluck('number')->all(),
            'owners' => $account->extensions()->orderBy('display_name')->get()->map(fn ($item): array => [
                'id' => $item->id,
                'label' => $item->display_name ?? $item->extension ?? 'Unnamed user',
                'detail' => $item->extension,
            ])->values()->all(),
        ];
    }
}
