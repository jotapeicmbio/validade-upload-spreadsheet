<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class XformSchemaNode
{
    /**
     * @param list<XformSchemaNode> $children
     */
    public function __construct(
        public readonly string $name,
        public readonly string $path,
        public readonly string $type,
        public readonly array $children = [],
        public readonly ?string $label = null,
        public readonly ?string $relevant = null,
        public readonly ?string $required = null,
        public readonly ?string $constraint = null,
        public readonly ?string $calculate = null,
    ) {
    }

    public function isRepeat(): bool
    {
        return $this->type === 'repeat';
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    public function isContainer(): bool
    {
        return $this->isGroup() || $this->isRepeat();
    }

    public function childByName(string $name): ?self
    {
        foreach ($this->children as $child) {
            if ($child->name === $name) {
                return $child;
            }
        }

        return null;
    }
}
