<?php

declare(strict_types=1);

namespace GridPbx\Switch\Dto\Callflows;

use GridPbx\Switch\Dto\Common\EntitySnapshot;

final readonly class CallflowSnapshot extends EntitySnapshot
{
    public ?string $name;

    /** @var list<string> */
    public array $numbers;

    /** @var list<string> */
    public array $patterns;

    /** @var list<string> */
    public array $flags;

    public ?string $featureCodeName;

    public ?string $featureCodeNumber;

    public ?CallflowNode $flow;

    /** @var list<string> */
    public array $modules;

    public int $nodeCount;

    public int $maxDepth;

    /** @param array<string, mixed> $data */
    public function __construct(array $data)
    {
        parent::__construct($data);

        $this->name = $this->nullableString($data['name'] ?? null);
        $this->numbers = $this->stringList($data['numbers'] ?? null);
        $this->patterns = $this->stringList($data['patterns'] ?? null);
        $this->flags = $this->stringList($data['flags'] ?? null);
        $featureCode = is_array($data['featurecode'] ?? null)
            ? $data['featurecode']
            : (is_array($data['feature_code'] ?? null) ? $data['feature_code'] : []);
        $this->featureCodeName = $this->nullableString($featureCode['name'] ?? null);
        $this->featureCodeNumber = $this->nullableString($featureCode['number'] ?? null);
        $this->flow = is_array($data['flow'] ?? null) ? CallflowNode::fromArray($data['flow']) : null;
        $this->modules = $this->flow === null
            ? $this->stringList($data['modules'] ?? null)
            : array_values(array_unique($this->flow->modules()));
        $this->nodeCount = $this->flow?->nodeCount() ?? 0;
        $this->maxDepth = $this->flow?->maxDepth() ?? 0;
    }
}
