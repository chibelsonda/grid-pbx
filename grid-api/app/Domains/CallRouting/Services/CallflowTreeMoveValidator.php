<?php

namespace App\Domains\CallRouting\Services;

use App\Domains\CallRouting\Models\SwitchCallflow;
use Illuminate\Validation\ValidationException;

class CallflowTreeMoveValidator
{
    /**
     * @param  list<string>  $sourcePath
     * @param  list<string>  $destinationParentPath
     */
    public function assertAllowed(
        SwitchCallflow $callflow,
        array $sourcePath,
        array $destinationParentPath,
        string $destinationBranch,
    ): void {
        if ($sourcePath === []) {
            $this->fail('source_path', 'The root callflow node cannot be moved.');
        }

        $destinationPath = [...$destinationParentPath, $destinationBranch];

        if ($destinationPath === $sourcePath) {
            $this->fail('destination_branch', 'The callflow node is already in this branch.');
        }

        if ($this->pathStartsWith($destinationParentPath, $sourcePath)) {
            $this->fail('destination_parent_path', 'A callflow node cannot be moved into its own subtree.');
        }

        $flow = $callflow->flow_structure;

        if (! is_array($flow)) {
            $this->fail('callflow', 'This route has no flow tree to edit.');
        }

        $source = $this->nodeAt($flow, $sourcePath, 'source_path');
        $destination = $this->nodeAt($flow, $destinationParentPath, 'destination_parent_path');
        $sourceModule = is_string($source['module'] ?? null) ? $source['module'] : '';

        if (! CallflowBranchPolicy::isGuidedModule($sourceModule)) {
            $this->fail(
                'source_path',
                'This callflow action is not supported by the guided tree editor and remains unchanged.',
            );
        }

        if (($source['reference_status'] ?? null) === 'unresolved') {
            $this->fail(
                'source_path',
                'This callflow action has an unresolved reference and cannot be moved safely.',
            );
        }

        if (CallflowBranchPolicy::childrenAreLocked($destination)) {
            $this->fail(
                'destination_parent_path',
                'This conditional action has preserved branches that cannot be edited.',
            );
        }

        if (! CallflowBranchPolicy::supports($destination, $destinationBranch)) {
            $this->fail(
                'destination_branch',
                'The selected branch is not valid for the destination callflow action.',
            );
        }

        $children = is_array($destination['children'] ?? null) ? $destination['children'] : [];

        if (array_key_exists($destinationBranch, $children)) {
            $this->fail('destination_branch', 'The selected destination branch is already occupied.');
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $path
     * @return array<string, mixed>
     */
    private function nodeAt(array $node, array $path, string $field): array
    {
        foreach ($path as $segment) {
            if (! CallflowBranchPolicy::supports($node, $segment)) {
                $this->fail($field, 'The selected callflow path contains a preserved branch.');
            }

            $children = is_array($node['children'] ?? null) ? $node['children'] : [];
            $child = $children[$segment] ?? null;

            if (! is_array($child)) {
                $this->fail($field, 'The selected callflow path no longer exists.');
            }

            $node = $child;
        }

        return $node;
    }

    /** @param list<string> $path */
    private function pathStartsWith(array $path, array $prefix): bool
    {
        return count($path) >= count($prefix)
            && array_slice($path, 0, count($prefix)) === $prefix;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
