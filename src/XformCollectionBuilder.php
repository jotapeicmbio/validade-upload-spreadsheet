<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class XformCollectionBuilder
{
    protected ?TypeNormalizerRegistry $typeNormalizers = null;

    public function __construct(private readonly XformSchema $schema)
    {
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $spreadsheetStructure
     * @return array<string, mixed>
     */
    public function build(array $spreadsheetStructure): array
    {
        $headers = $this->extractHeaders($spreadsheetStructure);
        $rows = $this->extractRows($spreadsheetStructure);
        $rowMaps = $this->mapRowsByHeaders($headers, $rows);

        return $this->buildScope($this->schema->root()->children, $rowMaps);
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $spreadsheetStructure
     * @return array<int, string>
     */
    protected function extractHeaders(array $spreadsheetStructure): array
    {
        $headers = $spreadsheetStructure['headers'] ?? $spreadsheetStructure[0] ?? [];

        if (! is_array($headers)) {
            return [];
        }

        $normalized = [];

        foreach ($headers as $header) {
            if (! is_string($header) || trim($header) === '') {
                continue;
            }

            $normalized[] = $header;
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed>|array<int, mixed> $spreadsheetStructure
     * @return array<int, array<int, mixed>>
     */
    protected function extractRows(array $spreadsheetStructure): array
    {
        if (isset($spreadsheetStructure['collects']) && is_array($spreadsheetStructure['collects'])) {
            $firstCollect = $spreadsheetStructure['collects'][0]['collect'] ?? [];

            return is_array($firstCollect) ? $firstCollect : [];
        }

        if (! array_is_list($spreadsheetStructure)) {
            return [];
        }

        return array_slice($spreadsheetStructure, 2);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function mapRowsByHeaders(array $headers, array $rows): array
    {
        $mapped = [];
        $columnCount = count($headers);

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalizedRow = array_pad(array_values($row), $columnCount, null);
            $mappedRow = [];

            foreach ($headers as $index => $header) {
                $mappedRow[$header] = $normalizedRow[$index] ?? null;
            }

            $mapped[] = $mappedRow;
        }

        return $mapped;
    }

    /**
     * @param list<XformSchemaNode> $nodes
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    protected function buildScope(array $nodes, array $rows): array
    {
        $scope = [];

        foreach ($nodes as $node) {
            if ($this->shouldSkipNode($node)) {
                continue;
            }

            if ($node->isRepeat()) {
                $items = $this->buildRepeatNode($node, $this->filterRowsForNode($rows, $node));

                if ($items !== []) {
                    $scope[$node->name] = $items;
                }

                continue;
            }

            if ($node->isGroup()) {
                $group = $this->buildGroupNode($node, $this->filterRowsForNode($rows, $node));

                if ($this->hasMeaningfulValue($group)) {
                    $scope[$node->name] = $group;
                }

                continue;
            }

            $scope[$node->name] = $this->resolveLeafValue($rows, $node);
        }

        return $scope;
    }

    protected function shouldSkipNode(XformSchemaNode $node): bool
    {
        if (str_starts_with($node->name, '_')) {
            return true;
        }

        if (in_array($node->type, ['start', 'end', 'deviceid', 'phonenumber', 'note'], true)) {
            return true;
        }

        return $node->type === 'calculate' && str_ends_with($node->name, '_count');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>
     */
    protected function buildGroupNode(XformSchemaNode $node, array $rows): array
    {
        return $this->buildScope($node->children, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    protected function buildRepeatNode(XformSchemaNode $node, array $rows): array
    {
        $groups = $this->splitRowsBySignature($node, $rows);
        $items = [];

        foreach ($groups as $groupRows) {
            $item = $this->buildScope($node->children, $groupRows);

            if ($this->hasAnyValue($item)) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return list<array<int, array<string, mixed>>>
     */
    protected function splitRowsBySignature(XformSchemaNode $node, array $rows): array
    {
        $signatureNodes = $this->collectSignatureNodes($node);
        $groups = [];
        $currentGroup = [];
        $currentSignature = null;

        foreach ($rows as $row) {
            $signature = $this->buildSignature($row, $signatureNodes);
            $signatureKey = $signature === [] ? null : json_encode($signature, JSON_THROW_ON_ERROR);

            if ($currentGroup === []) {
                if ($signatureKey === null) {
                    continue;
                }

                $currentGroup[] = $row;
                $currentSignature = $signatureKey;
                continue;
            }

            if ($signatureKey !== null && $currentSignature !== null && $signatureKey !== $currentSignature) {
                $groups[] = $currentGroup;
                $currentGroup = [$row];
                $currentSignature = $signatureKey;
                continue;
            }

            $currentGroup[] = $row;
            if ($signatureKey !== null) {
                $currentSignature = $signatureKey;
            }
        }

        if ($currentGroup !== []) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * @return list<XformSchemaNode>
     */
    protected function collectSignatureNodes(XformSchemaNode $node): array
    {
        $nodes = [];

        foreach ($node->children as $child) {
            if ($child->isRepeat()) {
                continue;
            }

            if ($child->isGroup()) {
                $nodes = array_merge($nodes, $this->collectSignatureNodes($child));
                continue;
            }

            $nodes[] = $child;
        }

        return $nodes;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<XformSchemaNode> $nodes
     * @return array<string, mixed>
     */
    protected function buildSignature(array $row, array $nodes): array
    {
        $signature = [];

        foreach ($nodes as $node) {
            $value = $this->rowValue($row, $node);

            if ($this->isEmptyValue($value)) {
                continue;
            }

            $signature[$node->path] = is_array($value)
                ? json_encode($value, JSON_THROW_ON_ERROR)
                : $value;
        }

        return $signature;
    }

    /**
     * @param array<string, mixed> $row
     * @return mixed
     */
    protected function rowValue(array $row, XformSchemaNode $node): mixed
    {
        if (array_key_exists($node->path, $row)) {
            return $row[$node->path];
        }

        if (array_key_exists($node->name, $row)) {
            return $row[$node->name];
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    protected function resolveLeafValue(array $rows, XformSchemaNode $node): mixed
    {
        foreach ($rows as $row) {
            $value = $this->rowValue($row, $node);

            if (! $this->isEmptyValue($value)) {
                return $this->normalizeValue($node, $value);
            }
        }

        return null;
    }

    protected function hasAnyValue(array $node): bool
    {
        foreach ($node as $value) {
            if ($this->isEmptyValue($value)) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function hasMeaningfulValue(array $node): bool
    {
        foreach ($node as $value) {
            if (is_array($value)) {
                if ($this->hasMeaningfulValue($value)) {
                    return true;
                }

                continue;
            }

            if ($this->isEmptyValue($value)) {
                continue;
            }

            return true;
        }

        return false;
    }

    protected function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        if (is_array($value) && $value === []) {
            return true;
        }

        return false;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function filterRowsForNode(array $rows, XformSchemaNode $node): array
    {
        if ($node->path === '') {
            return $rows;
        }

        $filtered = [];
        $prefix = $node->path . '/';

        foreach ($rows as $row) {
            $filteredRow = [];

            foreach ($row as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                if ($key === $node->path || str_starts_with($key, $prefix)) {
                    $filteredRow[$key] = $value;
                }
            }

            $filtered[] = $filteredRow;
        }

        return $filtered;
    }

    protected function normalizeValue(XformSchemaNode $node, mixed $value): mixed
    {
        return $this->typeNormalizers()->normalize($node->type, $value);
    }

    protected function typeNormalizers(): TypeNormalizerRegistry
    {
        if ($this->typeNormalizers === null) {
            $this->typeNormalizers = TypeNormalizerRegistry::default();
        }

        return $this->typeNormalizers;
    }

}
