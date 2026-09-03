<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use App\Domains\Organizations\Models\SwitchAccount;
use Illuminate\Support\Collection;

class CallflowEntryPointDiscoveryService
{
    private const SUGGESTION_START = 1000;

    private const SUGGESTION_END = 9999;

    /**
     * Return occupied internal extensions without exposing Switch identifiers.
     *
     * @return list<array{number: string, source: string, label: string, callflow: array{id: string, name: string|null}|null, current: bool}>
     */
    public function directory(
        SwitchAccount $account,
        ?SwitchCallflow $currentCallflow = null,
        ?string $search = null,
        int $limit = 50,
    ): array {
        $phoneNumbers = $account->phoneNumbers()
            ->pluck('number')
            ->filter(fn (mixed $number): bool => is_string($number))
            ->all();
        $query = $this->occupiedExtensions($account, $currentCallflow, $phoneNumbers);
        $normalizedSearch = trim((string) $search);

        if ($normalizedSearch !== '') {
            $query = $query->filter(fn (array $entry): bool => str_contains($entry['number'], $normalizedSearch)
                || str_contains(mb_strtolower($entry['label']), mb_strtolower($normalizedSearch)));
        }

        return $query
            ->sortBy(fn (array $entry): string => str_pad($entry['number'], 20, '0', STR_PAD_LEFT))
            ->take($limit)
            ->values()
            ->all();
    }

    /** @return array{number: string, available: bool, reason: string|null, conflict: array{source: string, label: string, callflow: array{id: string, name: string|null}|null}|null, suggested_extension: string|null} */
    public function availability(
        SwitchAccount $account,
        string $number,
        ?SwitchCallflow $currentCallflow = null,
    ): array {
        $entries = collect($this->directory($account, $currentCallflow, null, 10000));
        $conflict = $entries->first(
            fn (array $entry): bool => $entry['number'] === $number && ! $entry['current'],
        );

        return [
            'number' => $number,
            'available' => $conflict === null,
            'reason' => $conflict === null
                ? null
                : sprintf('Extension %s is already used by %s.', $number, $conflict['label']),
            'conflict' => $conflict === null ? null : [
                'source' => $conflict['source'],
                'label' => $conflict['label'],
                'callflow' => $conflict['callflow'],
            ],
            'suggested_extension' => $this->suggestedExtension($entries, $number),
        ];
    }

    /**
     * @param  list<string>  $phoneNumbers
     * @return Collection<int, array{number: string, source: string, label: string, callflow: array{id: string, name: string|null}|null, current: bool}>
     */
    private function occupiedExtensions(
        SwitchAccount $account,
        ?SwitchCallflow $currentCallflow,
        array $phoneNumbers,
    ): Collection {
        $managed = $account->extensions()
            ->whereNotNull('extension')
            ->get(['extension_id', 'id', 'display_name', 'extension'])
            ->filter(fn ($extension): bool => is_string($extension->extension)
                && preg_match('/^[0-9]{2,15}$/', $extension->extension) === 1)
            ->map(fn ($extension): array => [
                'number' => $extension->extension,
                'source' => 'managed_extension',
                'label' => $extension->display_name ?? "Extension {$extension->extension}",
                'callflow' => null,
                'current' => $currentCallflow?->switch_extension_id === $extension->getKey(),
            ]);

        $callflows = $account->callflows()
            ->get(['callflow_id', 'id', 'name', 'numbers'])
            ->flatMap(function (SwitchCallflow $callflow) use ($currentCallflow, $phoneNumbers): array {
                return collect($callflow->numbers ?? [])
                    ->filter(fn (mixed $number): bool => is_string($number)
                        && preg_match('/^[0-9]{2,15}$/', $number) === 1
                        && ! in_array($number, $phoneNumbers, true))
                    ->map(fn (string $number): array => [
                        'number' => $number,
                        'source' => 'callflow',
                        'label' => $callflow->name ?? "Callflow {$number}",
                        'callflow' => ['id' => $callflow->id, 'name' => $callflow->name],
                        'current' => $currentCallflow?->is($callflow) ?? false,
                    ])
                    ->all();
            });

        return $managed
            ->concat($callflows)
            ->unique('number')
            ->values();
    }

    /** @param Collection<int, array{number: string, current: bool}> $entries */
    private function suggestedExtension(Collection $entries, string $requested): ?string
    {
        $occupied = $entries
            ->filter(fn (array $entry): bool => ! $entry['current'])
            ->pluck('number')
            ->flip();
        $start = ctype_digit($requested) && (int) $requested >= self::SUGGESTION_START
            ? max(self::SUGGESTION_START, (int) $requested + 1)
            : self::SUGGESTION_START;

        for ($candidate = $start; $candidate <= self::SUGGESTION_END; $candidate++) {
            if (! $occupied->has((string) $candidate)) {
                return (string) $candidate;
            }
        }

        return null;
    }
}
