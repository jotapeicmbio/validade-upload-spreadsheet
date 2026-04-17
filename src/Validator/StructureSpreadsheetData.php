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
    protected bool $shouldStructure = false;

    /** @param array<int, array<int, mixed>> $worksheet */
    public function __construct(array $worksheet)
    {
        $this->worksheet = array_values($worksheet);
        $this->headers = $this->worksheet[0] ?? [];
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
        $numCols = count($this->headers);

        foreach ($rows as $row) {
            $row = array_pad($row, $numCols, null);
            $isModel = $this->isModelRow($row);

            if ($isModel) {
                if ($currentModel !== null) {
                    yield $currentModel;
                }

                $currentModel = $row;
                continue;
            }

            if ($currentModel === null) {
                continue;
            }

            $currentModel = $this->mergeRow($currentModel, $row);
        }

        if ($currentModel !== null) {
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

        for ($i = 0; $i < $numCols; $i++) {
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
            $structured[$groupKey] = $this->buildGroupedValue($columns);
        }

        return $structured;
    }

    /**
     * @param array<int, array{header: string, value: mixed}> $columns
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    protected function buildGroupedValue(array $columns): array
    {
        if (! $this->groupHasRepeatedItems($columns)) {
            return $this->buildSingleGroupItem($columns);
        }

        return $this->buildGroupedItems($columns);
    }

    /**
     * @param array<int, array{header: string, value: mixed}> $columns
     * @return array<int, array<string, mixed>>
     */
    protected function buildGroupedItems(array $columns): array
    {
        $itemCount = 1;

        foreach ($columns as $column) {
            if (is_array($column['value'])) {
                $itemCount = max($itemCount, count($column['value']));
            }
        }

        $items = [];

        for ($index = 0; $index < $itemCount; $index++) {
            $item = [];

            foreach ($columns as $column) {
                $value = $column['value'];
                $item[$column['header']] = is_array($value)
                    ? ($value[$index] ?? null)
                    : $value;
            }

            if ($this->itemHasValue($item)) {
                $items[] = $item;
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
}
