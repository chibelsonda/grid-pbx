<?php

namespace App\Domains\CallRouting\Services;

final class CallflowJsonNormalizer
{
    /** @param array<string, mixed> $document */
    public function document(array $document): array
    {
        if (is_array($document['flow'] ?? null)) {
            $document['flow'] = $this->flow($document['flow']);
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    public function flow(array $node): array
    {
        $children = [];
        $rawChildren = match (true) {
            is_array($node['children'] ?? null) => $node['children'],
            is_object($node['children'] ?? null) => get_object_vars($node['children']),
            default => [],
        };

        foreach ($rawChildren as $key => $child) {
            if ((is_string($key) || is_int($key)) && is_array($child)) {
                $children[(string) $key] = $this->flow($child);
            }
        }

        $node['children'] = (object) $children;

        return $node;
    }
}
