<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class SpreadsheetStructureBuilder
{
    public function __construct(private readonly XformSchema $schema)
    {
    }

    /**
     * @param list<array<string, mixed>> $collection
     * @return list<array<string, mixed>>
     */
    public function build(array $collection): array
    {
        $items = [];

        foreach ($collection as $item) {
            if (! is_array($item)) {
                continue;
            }

            $items[] = $this->normalizeScope($item, $this->schema->root()->children);
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $context
     * @param list<XformSchemaNode> $nodes
     * @return array<string, mixed>
     */
    protected function normalizeScope(array $context, array $nodes): array
    {
        foreach ($nodes as $node) {
            $context = $this->normalizeNode($context, $node);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return mixed
     */
    protected function normalizeNode(array $context, XformSchemaNode $node): array
    {
        if ($node->isRepeat()) {
            return $this->normalizeRepeatNode($context, $node);
        }

        if ($node->isGroup()) {
            return $this->normalizeGroupNode($context, $node);
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function normalizeRepeatNode(array $context, XformSchemaNode $node): array
    {
        $rawValue = $this->extractValue($context, $node);

        if (is_array($rawValue) && array_is_list($rawValue)) {
            $items = $this->normalizeRepeatItems($rawValue, $node);
            $context = $this->replaceNodeValue($context, $node, $items !== [] ? $items : [[]]);

            return $context;
        }

        if (is_array($rawValue)) {
            $items = $this->normalizeRepeatItems([$rawValue], $node);
            $context = $this->replaceNodeValue($context, $node, $items !== [] ? $items : [[]]);

            return $context;
        }

        $context = $this->replaceNodeValue($context, $node, [[]]);

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function normalizeGroupNode(array $context, XformSchemaNode $node): array
    {
        $rawValue = $this->extractValue($context, $node);
        $groupContext = is_array($rawValue) && ! array_is_list($rawValue) ? $rawValue : $context;

        $normalizedGroupContext = $this->normalizeScope($groupContext, $node->children);

        return $this->replaceNodeValue($context, $node, $normalizedGroupContext);
    }

    /**
     * @param list<mixed> $rawItems
     * @return list<array<string, mixed>>
     */
    protected function normalizeRepeatItems(array $rawItems, XformSchemaNode $node): array
    {
        $normalizedItems = [];

        foreach ($rawItems as $rawItem) {
            $normalizedItems[] = $this->normalizeScope(is_array($rawItem) ? $rawItem : [], $node->children);
        }

        if (
            $normalizedItems === []
            || ! $this->hasRepeatDescendant($node)
        ) {
            return $normalizedItems;
        }

        return $this->collapseRepeatItemsBySignature($normalizedItems, $node);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    protected function collapseRepeatItemsBySignature(array $items, XformSchemaNode $node): array
    {
        $groups = [];
        $currentSignature = null;

        foreach ($items as $item) {
            $signature = $this->buildStableSignature($item, $node->children);
            $signatureKey = $signature === []
                ? null
                : json_encode($signature, JSON_THROW_ON_ERROR);

            if ($signatureKey === null) {
                $groups[] = [$item];
                $currentSignature = null;
                continue;
            }

            if ($currentSignature !== null && $currentSignature === $signatureKey) {
                $groups[array_key_last($groups)][] = $item;
                continue;
            }

            $groups[] = [$item];
            $currentSignature = $signatureKey;
        }

        $collapsed = [];

        foreach ($groups as $groupItems) {
            $collapsed[] = $this->mergeRepeatItems($groupItems, $node);
        }

        return $collapsed;
    }

    /**
     * @param array<string, mixed> $item
     * @param list<XformSchemaNode> $nodes
     * @return array<string, mixed>
     */
    protected function buildStableSignature(array $item, array $nodes): array
    {
        $signature = [];

        foreach ($nodes as $node) {
            if ($node->isRepeat()) {
                continue;
            }

            if ($node->isGroup()) {
                $signature = array_merge($signature, $this->buildStableSignature($item, $node->children));
                continue;
            }

            $value = $this->extractValue($item, $node);
            if ($this->isEmptyValue($value)) {
                continue;
            }

            if (is_array($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            }

            $signature[$node->path] = $value;
        }

        return $signature;
    }

    /**
     * @param list<array<string, mixed>> $groupItems
     * @return array<string, mixed>
     */
    protected function mergeRepeatItems(array $groupItems, XformSchemaNode $node): array
    {
        $merged = $groupItems[0] ?? [];

        foreach (array_slice($groupItems, 1) as $item) {
            $merged = $this->mergeContextNode($merged, $item, $node->children);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @param list<XformSchemaNode> $nodes
     * @return array<string, mixed>
     */
    protected function mergeContextNode(array $current, array $incoming, array $nodes): array
    {
        foreach ($nodes as $node) {
            $current = $this->mergeNodeValue($current, $incoming, $node);
        }

        return $current;
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    protected function mergeNodeValue(array $current, array $incoming, XformSchemaNode $node): array
    {
        $currentValue = $this->extractValue($current, $node);
        $incomingValue = $this->extractValue($incoming, $node);

        if ($node->isRepeat()) {
            $mergedValue = $this->mergeRepeatValue($currentValue, $incomingValue, $node);
            return $this->replaceNodeValue($current, $node, $mergedValue);
        }

        if ($node->isGroup()) {
            if (! is_array($currentValue) || ! is_array($incomingValue)) {
                $replacement = ! $this->isEmptyValue($currentValue) ? $currentValue : $incomingValue;
                if ($this->isEmptyValue($replacement)) {
                    return $current;
                }

                return $this->replaceNodeValue($current, $node, $replacement);
            }

            $mergedGroup = $this->mergeContextNode($currentValue, $incomingValue, $node->children);
            return $this->replaceNodeValue($current, $node, $mergedGroup);
        }

        if ($this->isEmptyValue($currentValue) && ! $this->isEmptyValue($incomingValue)) {
            return $this->replaceNodeValue($current, $node, $incomingValue);
        }

        return $current;
    }

    /**
     * @param mixed $currentValue
     * @param mixed $incomingValue
     * @return array<int, mixed>
     */
    protected function mergeRepeatValue(mixed $currentValue, mixed $incomingValue, XformSchemaNode $node): array
    {
        $currentItems = is_array($currentValue) && array_is_list($currentValue) ? $currentValue : [];
        $incomingItems = is_array($incomingValue) && array_is_list($incomingValue) ? $incomingValue : [];

        $mergedItems = [];

        foreach (array_merge($currentItems, $incomingItems) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $mergedItems[] = $item;
        }

        if ($mergedItems === []) {
            return [[]];
        }

        return $this->hasRepeatDescendant($node)
            ? $this->collapseRepeatItemsBySignature($mergedItems, $node)
            : $mergedItems;
    }

    protected function hasRepeatDescendant(XformSchemaNode $node): bool
    {
        foreach ($node->children as $child) {
            if ($child->isRepeat() || $this->hasRepeatDescendant($child)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    protected function extractValue(array $context, XformSchemaNode $node): mixed
    {
        if (array_key_exists($node->name, $context)) {
            return $context[$node->name];
        }

        if ($node->path !== '' && array_key_exists($node->path, $context)) {
            return $context[$node->path];
        }

        $pathSuffix = '/' . $node->path;
        $nameSuffix = '/' . $node->name;

        foreach ($context as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if ($key === $node->path || $key === $node->name) {
                return $value;
            }

            if (
                ($node->path !== '' && str_ends_with($key, $pathSuffix))
                || str_ends_with($key, $nameSuffix)
            ) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @param mixed $value
     * @return array<string, mixed>
     */
    protected function replaceNodeValue(array $context, XformSchemaNode $node, mixed $value): array
    {
        if (array_key_exists($node->name, $context)) {
            $context[$node->name] = $value;

            return $context;
        }

        if ($node->path !== '' && array_key_exists($node->path, $context)) {
            $context[$node->path] = $value;

            return $context;
        }

        return $context;
    }

    protected function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return $value === [];
        }

        return false;
    }
}
