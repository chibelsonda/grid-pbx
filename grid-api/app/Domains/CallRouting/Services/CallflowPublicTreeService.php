<?php

namespace App\Domains\CallRouting\Services;

class CallflowPublicTreeService
{
    /**
     * @param  array<string, mixed>  $node
     * @param  array{key: string, label: string, kind: string}|null  $branch
     * @return array<string, mixed>
     */
    public function transform(array $node, ?array $branch = null): array
    {
        $children = [];
        $preservedIndex = 0;
        $module = is_string($node['module'] ?? null) ? $node['module'] : 'unknown';

        foreach (is_array($node['children'] ?? null) ? $node['children'] : [] as $rawKey => $child) {
            if ((! is_string($rawKey) && ! is_int($rawKey)) || ! is_array($child)) {
                continue;
            }

            $key = (string) $rawKey;

            if (CallflowBranchPolicy::supports($node, $key)) {
                $publicKey = $key;
                $publicBranch = $this->knownBranch($module, $key);
            } else {
                $preservedIndex++;
                $publicKey = 'preserved_'.$preservedIndex;
                $publicBranch = [
                    'key' => $publicKey,
                    'label' => 'Preserved branch '.$preservedIndex,
                    'kind' => 'preserved',
                ];
            }

            $children[$publicKey] = $this->transform($child, $publicBranch);
        }

        return [
            'module' => $module,
            'target' => is_array($node['target'] ?? null) ? $node['target'] : null,
            'reference_status' => is_string($node['reference_status'] ?? null)
                ? $node['reference_status']
                : 'not_applicable',
            'branch' => $branch,
            'temporal_rules' => is_array($node['temporal_rules'] ?? null)
                ? array_values($node['temporal_rules'])
                : [],
            'settings' => is_array($node['settings'] ?? null) ? $node['settings'] : null,
            'children' => (object) $children,
        ];
    }

    /** @return array{key: string, label: string, kind: string} */
    private function knownBranch(string $parentModule, string $key): array
    {
        return [
            'key' => $key,
            'label' => match ($key) {
                '_' => $parentModule === 'temporal_route' ? 'No schedule match' : 'Default branch',
                'rule_set' => 'Schedule matches',
                'timeout' => 'Timeout',
                'match' => 'Caller ID matches',
                'nomatch' => 'Caller ID does not match',
                '*' => 'Star',
                default => match ($parentModule) {
                    'branch_variable' => 'Priority '.$key,
                    'branch_bnumber' => 'Captured number '.$key,
                    default => 'Key '.$key,
                },
            },
            'kind' => match ($key) {
                '_' => 'default',
                'rule_set' => 'schedule_match',
                'match', 'nomatch' => 'condition',
                default => in_array($parentModule, ['branch_variable', 'branch_bnumber'], true)
                    ? 'condition'
                    : 'key',
            },
        ];
    }
}
