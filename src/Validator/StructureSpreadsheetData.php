<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister\Validator;

use Generator;

class StructureSpreadsheetData
{
    /** @var array<int, array<int, mixed>> */
    protected array $worksheet = [];
    /** @var array<int, string> */
    protected array $headers = [];
    /** @var array<string, true> */
    protected array $repeatGroupKeys = [];
    /** @var array<int, int> */
    protected array $structuredRowLines = [];
    protected bool $shouldStructure = false;
    protected int $currentNormalizedRowLine = 0;

    /**
     * @param array<int, array<int, mixed>> $worksheet
     * @param array<int, string> $repeatGroupKeys
     */
    public function __construct(array $worksheet, array $repeatGroupKeys = [])
    {
        $this->worksheet = array_values($worksheet);
        $this->headers = $this->worksheet[0] ?? [];
        foreach ($repeatGroupKeys as $groupKey) {
            if (! is_string($groupKey) || $groupKey === '') {
                continue;
            }

            $this->repeatGroupKeys[$groupKey] = true;
        }
    }

    public function estruture(): self
    {
        $this->shouldStructure = true;

        return $this;
    }

    /** @return array<int, mixed> */
    public function toArray(): array
    {
        if ($this->shouldStructure) {
            return iterator_to_array($this->structuredCollections(), false);
        }

        return $this->output();
    }

    /**
     * @return array<int, int>
     */
    public function structuredRowLines(): array
    {
        if ($this->shouldStructure && $this->structuredRowLines === []) {
            iterator_to_array($this->structuredCollections(), false);
        }

        return $this->structuredRowLines;
    }

    /** @return array<int, array<int, mixed>> */
    public function output(): array
    {
        return [
            $this->headers,
            ...iterator_to_array($this->normalizedRows(), false),
        ];
    }

    /** @return Generator<int, array<int, mixed>> */
    protected function normalizedRows(): Generator
    {
        $rows = array_slice($this->worksheet, 2);
        $currentModel = null;
        $currentModelLine = null;
        $numCols = count($this->headers);
        $lineNumber = 3;

        foreach ($rows as $row) {
            $row = array_pad($row, $numCols, null);
            $isModel = $this->isModelRow($row);

            if ($isModel) {
                if ($currentModel !== null) {
                    $this->currentNormalizedRowLine = $currentModelLine ?? 0;
                    yield $currentModel;
                }

                $currentModel = $row;
                $currentModelLine = $lineNumber;
                $lineNumber++;
                continue;
            }

            if ($currentModel === null) {
                $lineNumber++;
                continue;
            }

            $currentModel = $this->mergeRow($currentModel, $row);
            $lineNumber++;
        }

        if ($currentModel !== null) {
            $this->currentNormalizedRowLine = $currentModelLine ?? 0;
            yield $currentModel;
        }
    }

    /** @param array<int, mixed> $row */
    protected function isModelRow(array $row): bool
    {
        foreach ($row as $index => $cell) {
            if ($this->isEmptyCell($cell)) {
                continue;
            }

            return $index === 0;
        }

        return false;
    }

    /** @return Generator<int, array<string, mixed>> */
    protected function structuredCollections(): Generator
    {
        foreach ($this->normalizedRows() as $row) {
            $this->structuredRowLines[] = $this->currentNormalizedRowLine;
            yield $this->structureRow($row);
        }
    }

    /**
     * @param array<int, mixed> $modelRow
     * @param array<int, mixed> $currentRow
     * @return array<int, mixed>
     */
    protected function mergeRow(array $modelRow, array $currentRow): array
    {
        $numCols = count($this->headers);
        $currentRow = array_pad($currentRow, $numCols, null);
        $groupedIndices = $this->groupedColumnIndices();
        $processedIndices = [];

        foreach ($groupedIndices as $groupKey => $indices) {
            $groupHasValuesInCurrentRow = false;
            $groupAlreadyExpanded = false;
            $groupHasNestedValuesInCurrentRow = false;
            $groupHasParentValuesInCurrentRow = false;

            foreach ($indices as $index) {
                $isNestedColumn = $this->isNestedGroupColumn($groupKey, $index);

                if (! $this->isEmptyCell($currentRow[$index] ?? null)) {
                    $groupHasValuesInCurrentRow = true;
                    if ($isNestedColumn) {
                        $groupHasNestedValuesInCurrentRow = true;
                    } else {
                        $groupHasParentValuesInCurrentRow = true;
                    }
                }

                if (is_array($modelRow[$index] ?? null)) {
                    $groupAlreadyExpanded = true;
                }
            }

            if (! $groupHasValuesInCurrentRow) {
                continue;
            }

            $shouldPropagateParentValues = $groupHasNestedValuesInCurrentRow && ! $groupHasParentValuesInCurrentRow;

            foreach ($indices as $index) {
                $processedIndices[$index] = true;
                $cell = $currentRow[$index] ?? null;
                $isNestedColumn = $this->isNestedGroupColumn($groupKey, $index);

                if (! is_array($modelRow[$index])) {
                    $baseValue = $modelRow[$index] ?? null;
                    $modelRow[$index] = [$this->isEmptyCell($baseValue) ? null : $baseValue];
                }

                if (! $groupAlreadyExpanded && ! array_key_exists($index, $modelRow)) {
                    $modelRow[$index] = [null];
                }

                $valueToAppend = $this->isEmptyCell($cell) ? null : $cell;

                if ($valueToAppend === null && $shouldPropagateParentValues && ! $isNestedColumn) {
                    $valueToAppend = $this->lastNonEmptyValue($modelRow[$index]);
                }

                $modelRow[$index][] = $valueToAppend;
            }
        }

        for ($i = 0; $i < $numCols; $i++) {
            if (isset($processedIndices[$i])) {
                continue;
            }

            $cell = $currentRow[$i];

            if ($this->isEmptyCell($cell)) {
                continue;
            }

            if (array_key_exists($i, $modelRow) && ! $this->isEmptyCell($modelRow[$i])) {
                if (is_array($modelRow[$i])) {
                    $modelRow[$i][] = $cell;
                } else {
                    $modelRow[$i] = [$modelRow[$i], $cell];
                }

                continue;
            }

            $modelRow[$i] = $cell;
        }

        return $modelRow;
    }

    /**
     * @return array<string, list<int>>
     */
    protected function groupedColumnIndices(): array
    {
        $groups = [];

        foreach ($this->headers as $index => $header) {
            if (! str_contains($header, '/')) {
                continue;
            }

            [$groupKey] = explode('/', $header, 2);
            $groups[$groupKey][] = $index;
        }

        return $groups;
    }

    protected function isEmptyCell(mixed $cell): bool
    {
        if ($cell === null) {
            return true;
        }

        return is_string($cell) && trim($cell) === '';
    }

    /**
     * @param array<int, mixed> $row
     * @return array<string, mixed>
     */
    protected function structureRow(array $row): array
    {
        $row = array_pad($row, count($this->headers), null);
        $structured = [];
        $groupedColumns = [];

        foreach ($this->headers as $index => $header) {
            $cell = $row[$index] ?? null;

            if (! str_contains($header, '/')) {
                $structured[$header] = $cell;
                continue;
            }

            [$groupKey] = explode('/', $header, 2);
            $groupedColumns[$groupKey][] = [
                'header' => $header,
                'value' => $cell,
            ];
        }

        foreach ($groupedColumns as $groupKey => $columns) {
            $structured[$groupKey] = $this->buildGroupedValue($columns, $groupKey);
        }

        return $structured;
    }

    /**
     * @param array<int, array{header: string, value: mixed}> $columns
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    protected function buildGroupedValue(array $columns, string $groupKey): array
    {
        if (! $this->groupHasRepeatedItems($columns)) {
            return $this->buildSingleGroupItem($columns);
        }

        $items = $this->buildGroupedItems($columns, $groupKey);

        if ($this->shouldCollapseGroupedItems($items)) {
            return $this->collapseGroupedItems($items);
        }

        return $items;
    }

    /**
     * @param array<int, array{header: string, value: mixed}> $columns
     * @return array<int, array<string, mixed>>
     */
    protected function buildGroupedItems(array $columns, string $groupKey): array
    {
        $itemCount = 1;

        foreach ($columns as $column) {
            if (is_array($column['value'])) {
                $itemCount = max($itemCount, count($column['value']));
            }
        }

        $items = [];
        $previousItem = null;

        for ($index = 0; $index < $itemCount; $index++) {
            $item = [];

            foreach ($columns as $column) {
                $value = $column['value'];
                $item[$column['header']] = is_array($value)
                    ? ($value[$index] ?? null)
                    : $value;
            }

            $item = $this->normalizeNestedRepeatItem($item, $groupKey);
            $item = $this->propagateRepeatItemValues($item, $previousItem, $groupKey);

            if ($this->itemHasValue($item)) {
                $items[] = $item;
                $previousItem = $item;
            }
        }

        return $items;
    }

    /**
     * @param array<int, array{header: string, value: mixed}> $columns
     * @return array<string, mixed>
     */
    protected function buildSingleGroupItem(array $columns): array
    {
        $item = [];

        foreach ($columns as $column) {
            $item[$this->lastSegment($column['header'])] = $column['value'];
        }

        return $item;
    }

    /** @param array<int, array{header: string, value: mixed}> $columns */
    protected function groupHasRepeatedItems(array $columns): bool
    {
        foreach ($columns as $column) {
            if (is_array($column['value'])) {
                return true;
            }
        }

        return false;
    }

    /** @param array<string, mixed> $item */
    protected function itemHasValue(array $item): bool
    {
        foreach ($item as $value) {
            if (! $this->isEmptyCell($value)) {
                return true;
            }
        }

        return false;
    }

    protected function lastSegment(string $fieldPath): string
    {
        $segments = explode('/', $fieldPath);

        return (string) end($segments);
    }

    protected function isNestedGroupColumn(string $groupKey, int $index): bool
    {
        $header = (string) ($this->headers[$index] ?? '');

        if (! str_starts_with($header, $groupKey . '/')) {
            return false;
        }

        $segments = explode('/', $header);

        return count($segments) > 2;
    }

    protected function lastNonEmptyValue(array $values): mixed
    {
        for ($index = count($values) - 1; $index >= 0; $index--) {
            if (! $this->isEmptyCell($values[$index])) {
                return $values[$index];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    protected function normalizeNestedRepeatItem(array $item, string $groupKey): array
    {
        $normalized = [];
        $nestedRepeatRows = [];

        foreach ($item as $fieldPath => $value) {
            if (! is_string($fieldPath) || ! str_starts_with($fieldPath, $groupKey . '/')) {
                $normalized[$fieldPath] = $value;
                continue;
            }

            $relativePath = substr($fieldPath, strlen($groupKey) + 1);
            $segments = explode('/', $relativePath);

            if (count($segments) <= 1) {
                $normalized[$fieldPath] = $value;
                continue;
            }

            $nestedRepeatKey = $segments[0];
            $nestedFieldPath = $groupKey . '/' . $nestedRepeatKey . '/' . implode('/', array_slice($segments, 1));

            if (! array_key_exists($nestedRepeatKey, $nestedRepeatRows)) {
                $nestedRepeatRows[$nestedRepeatKey] = [];
            }

            if (! array_key_exists(0, $nestedRepeatRows[$nestedRepeatKey])) {
                $nestedRepeatRows[$nestedRepeatKey][0] = [];
            }

            $nestedRepeatRows[$nestedRepeatKey][0][$nestedFieldPath] = $value;
        }

        foreach ($nestedRepeatRows as $nestedRepeatKey => $rows) {
            $filteredRows = array_values(array_filter(
                $rows,
                fn (array $row): bool => $this->itemHasValue($row),
            ));

            if ($filteredRows !== []) {
                $normalized[$nestedRepeatKey] = $filteredRows;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, mixed>|null $previousItem
     * @return array<string, mixed>
     */
    protected function propagateRepeatItemValues(array $item, ?array $previousItem, string $groupKey): array
    {
        if ($previousItem === null || ! $this->isRepeatGroup($groupKey)) {
            return $item;
        }

        foreach ($item as $fieldPath => $value) {
            if (! is_string($fieldPath)) {
                continue;
            }

            $relativePath = str_starts_with($fieldPath, $groupKey . '/')
                ? substr($fieldPath, strlen($groupKey) + 1)
                : null;

            if ($relativePath === null || str_contains($relativePath, '/')) {
                continue;
            }

            if (! $this->isEmptyCell($value)) {
                continue;
            }

            $previousValue = $previousItem[$fieldPath] ?? null;
            if (! $this->isEmptyCell($previousValue)) {
                $item[$fieldPath] = $previousValue;
            }
        }

        return $item;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    protected function shouldCollapseGroupedItems(array $items): bool
    {
        if (count($items) <= 1) {
            return false;
        }

        $referenceItem = $items[0];
        $nestedRepeatKeys = $this->collectNestedRepeatKeys($items);

        if ($nestedRepeatKeys === []) {
            return false;
        }

        foreach ($items as $item) {
            foreach ($item as $fieldPath => $value) {
                if (isset($nestedRepeatKeys[$fieldPath])) {
                    continue;
                }

                if (! array_key_exists($fieldPath, $referenceItem) || $referenceItem[$fieldPath] !== $value) {
                    return false;
                }
            }

            foreach ($referenceItem as $fieldPath => $value) {
                if (isset($nestedRepeatKeys[$fieldPath])) {
                    continue;
                }

                if (! array_key_exists($fieldPath, $item) || $item[$fieldPath] !== $value) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    protected function collapseGroupedItems(array $items): array
    {
        $collapsed = $items[0];
        $nestedRepeatKeys = $this->collectNestedRepeatKeys($items);

        foreach ($items as $item) {
            foreach ($item as $fieldPath => $value) {
                if (! $this->isNestedRepeatValue($value)) {
                    continue;
                }

                $collapsed[$fieldPath] = array_merge($collapsed[$fieldPath] ?? [], $value);
            }
        }

        foreach ($nestedRepeatKeys as $fieldPath => $_) {
            if (! isset($collapsed[$fieldPath])) {
                $collapsed[$fieldPath] = [];
            }

            $collapsed[$fieldPath] = $this->deduplicateNestedRepeatRows($collapsed[$fieldPath]);
        }

        return $collapsed;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, true>
     */
    protected function collectNestedRepeatKeys(array $items): array
    {
        $nestedRepeatKeys = [];

        foreach ($items as $item) {
            foreach ($item as $fieldPath => $value) {
                if ($this->isNestedRepeatValue($value)) {
                    $nestedRepeatKeys[$fieldPath] = true;
                }
            }
        }

        return $nestedRepeatKeys;
    }

    /**
     * @param array<int, array<string, mixed>>|array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function deduplicateNestedRepeatRows(array $rows): array
    {
        $deduplicated = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $signature = json_encode($row, JSON_THROW_ON_ERROR);
            if (isset($seen[$signature])) {
                continue;
            }

            $seen[$signature] = true;
            $deduplicated[] = $row;
        }

        return $deduplicated;
    }

    protected function isRepeatGroup(string $groupKey): bool
    {
        return isset($this->repeatGroupKeys[$groupKey]);
    }

    /**
     * @param mixed $value
     */
    protected function isNestedRepeatValue(mixed $value): bool
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            return false;
        }

        foreach ($value as $item) {
            if (is_array($item)) {
                return true;
            }
        }

        return false;
    }
}
