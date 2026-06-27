<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class XformSchema
{
    public function __construct(
        private readonly XformSchemaNode $root,
        /** @var array<string, XformSchemaNode> */
        private readonly array $nodesByPath,
    ) {
    }

    public static function fromArray(array $formDefinition): self
    {
        $parser = new self(
            new XformSchemaNode(name: '', path: '', type: 'root', children: []),
            [],
        );

        return $parser->buildFromArray($formDefinition);
    }

    public function root(): XformSchemaNode
    {
        return $this->root;
    }

    public function find(string $path): ?XformSchemaNode
    {
        return $this->nodesByPath[$path] ?? null;
    }

    public function isRepeat(string $path): bool
    {
        $node = $this->find($path);

        return $node?->isRepeat() ?? false;
    }

    public function isGroup(string $path): bool
    {
        $node = $this->find($path);

        return $node?->isGroup() ?? false;
    }

    /**
     * @return list<string>
     */
    public function repeatPaths(): array
    {
        $paths = [];

        foreach ($this->nodesByPath as $path => $node) {
            if ($node->isRepeat()) {
                $paths[] = $path;
            }
        }

        return $paths;
    }

    /**
     * @param array<int, mixed> $formDefinition
     */
    protected function buildFromArray(array $formDefinition): self
    {
        $nodesByPath = [];
        $children = $this->buildNodes($formDefinition['children'] ?? [], '', $nodesByPath);
        $root = new XformSchemaNode(
            name: '',
            path: '',
            type: 'root',
            children: $children,
        );

        return new self($root, $nodesByPath);
    }

    /**
     * @param array<int, mixed> $nodes
     * @param array<string, XformSchemaNode> $nodesByPath
     * @return list<XformSchemaNode>
     */
    protected function buildNodes(array $nodes, string $prefix, array &$nodesByPath): array
    {
        $built = [];

        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $builtNode = $this->buildNode($node, $prefix, $nodesByPath);
            if ($builtNode !== null) {
                $built[] = $builtNode;
            }
        }

        return $built;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, XformSchemaNode> $nodesByPath
     */
    protected function buildNode(array $node, string $prefix, array &$nodesByPath): ?XformSchemaNode
    {
        $name = (string) ($node['name'] ?? '');
        if ($name === '') {
            return null;
        }

        $type = (string) ($node['type'] ?? 'text');
        $path = $prefix !== '' ? $prefix . '/' . $name : $name;

        $children = [];
        if (isset($node['children']) && is_array($node['children'])) {
            $children = $this->buildNodes($node['children'], $path, $nodesByPath);
        }

        $normalizedNode = new XformSchemaNode(
            name: $name,
            path: $path,
            type: $type,
            children: $children,
            label: $this->normalizeStringOrNull($node['label'] ?? null),
            relevant: $this->normalizeStringOrNull($node['bind']['relevant'] ?? null),
            required: $this->normalizeStringOrNull($node['bind']['required'] ?? null),
            constraint: $this->normalizeStringOrNull($node['bind']['constraint'] ?? null),
            calculate: $this->normalizeStringOrNull($node['bind']['calculate'] ?? null),
        );

        $effectiveNode = $this->collapseSameNameRepeatWrapper($normalizedNode);
        $this->syncNodePaths($normalizedNode, $effectiveNode, $nodesByPath);

        return $effectiveNode;
    }

    protected function collapseSameNameRepeatWrapper(XformSchemaNode $node): XformSchemaNode
    {
        if (
            ! $node->isGroup()
            || count($node->children) !== 1
            || ! $node->children[0]->isRepeat()
            || $node->children[0]->name !== $node->name
        ) {
            return $node;
        }

        $repeatChild = $node->children[0];

        return $this->rewriteSubtreePaths(
            new XformSchemaNode(
                name: $node->name,
                path: $node->path,
                type: 'repeat',
                children: $repeatChild->children,
                label: $node->label,
                relevant: $node->relevant,
                required: $node->required,
                constraint: $node->constraint,
                calculate: $node->calculate,
            ),
            $repeatChild->path,
            $node->path,
        );
    }

    protected function rewriteSubtreePaths(XformSchemaNode $node, string $fromPrefix, string $toPrefix): XformSchemaNode
    {
        $path = $this->rewritePathPrefix($node->path, $fromPrefix, $toPrefix);

        $children = [];
        foreach ($node->children as $child) {
            $children[] = $this->rewriteSubtreePaths($child, $fromPrefix, $toPrefix);
        }

        return new XformSchemaNode(
            name: $node->name,
            path: $path,
            type: $node->type,
            children: $children,
            label: $node->label,
            relevant: $node->relevant,
            required: $node->required,
            constraint: $node->constraint,
            calculate: $node->calculate,
        );
    }

    protected function rewritePathPrefix(string $path, string $fromPrefix, string $toPrefix): string
    {
        if ($fromPrefix === '' || $path === '') {
            return $path;
        }

        if ($path === $fromPrefix) {
            return $toPrefix;
        }

        $fromPrefixWithSlash = $fromPrefix . '/';
        if (str_starts_with($path, $fromPrefixWithSlash)) {
            return $toPrefix . substr($path, strlen($fromPrefix));
        }

        return $path;
    }

    /**
     * @param array<string, XformSchemaNode> $nodesByPath
     */
    protected function syncNodePaths(XformSchemaNode $original, XformSchemaNode $effective, array &$nodesByPath): void
    {
        if ($original->path !== '') {
            unset($nodesByPath[$original->path]);
        }

        if ($effective->path !== '') {
            $nodesByPath[$effective->path] = $effective;
        }

        foreach ($original->children as $index => $originalChild) {
            $effectiveChild = $effective->children[$index] ?? null;

            if ($effectiveChild instanceof XformSchemaNode) {
                $this->syncNodePaths($originalChild, $effectiveChild, $nodesByPath);
            }
        }
    }

    protected function normalizeStringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
