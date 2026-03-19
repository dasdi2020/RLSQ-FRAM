<?php

declare(strict_types=1);

namespace RLSQ\Config\Definition;

use RLSQ\Config\Exception\ParseException;

class ArrayNode implements NodeInterface
{
    /** @var NodeInterface[] */
    private array $children = [];
    private bool $required = false;

    public function __construct(
        private readonly string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function addChild(NodeInterface $node): static
    {
        $this->children[$node->getName()] = $node;
        return $this;
    }

    public function isRequired(): static
    {
        $this->required = true;
        return $this;
    }

    /**
     * @return NodeInterface[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function process(mixed $value): mixed
    {
        if ($value === null) {
            $value = [];
        }

        if (!is_array($value)) {
            throw new ParseException(sprintf('La clé "%s" attend un tableau.', $this->name));
        }

        $result = [];

        foreach ($this->children as $name => $childNode) {
            $childValue = $value[$name] ?? null;
            $result[$name] = $childNode->process($childValue);
        }

        // Clés inconnues : les garder telles quelles
        foreach ($value as $key => $val) {
            if (!isset($this->children[$key])) {
                $result[$key] = $val;
            }
        }

        return $result;
    }
}
