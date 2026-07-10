<?php

declare(strict_types=1);

namespace Icmbio\ValidateRegister;

final class SpreadsheetInstanceSeparator
{
    /**
     * @param array<int, array<int, mixed>> $worksheet
     * @return array{
     *     headers: array<int, mixed>,
     *     labels: array<int, mixed>,
     *     collects: list<array{start_line: int, collect: list<array<int, mixed>>}>
     * }
     */
    public function separate(array $worksheet): array
    {
        $worksheet = array_values($worksheet);

        if ($worksheet === []) {
            return [
                'headers' => [],
                'labels' => [],
                'collects' => [],
            ];
        }

        $header = $worksheet[0] ?? [];
        $label = $worksheet[1] ?? [];
        $rows = array_slice($worksheet, 2);

        $collects = [];
        $currentRows = [];
        $currentStartLine = null;

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 3;

            if ($this->isNewInstanceRow($row) && $currentRows !== []) {
                $collects[] = [
                    'start_line' => $currentStartLine ?? 3,
                    'collect' => $currentRows,
                ];

                $currentRows = [$row];
                $currentStartLine = $lineNumber;
                continue;
            }

            if ($currentRows === []) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $currentRows = [$row];
                $currentStartLine = $lineNumber;
                continue;
            }

            $currentRows[] = $row;
        }

        if ($currentRows !== []) {
            $collects[] = [
                'start_line' => $currentStartLine ?? 3,
                'collect' => $currentRows,
            ];
        }

        return [
            'headers' => $header,
            'labels' => $label,
            'collects' => $collects,
        ];
    }

    /**
     * @param array<int, mixed> $row
     */
    protected function isNewInstanceRow(array $row): bool
    {
        $firstCell = $row[0] ?? null;

        return ! $this->isEmptyCell($firstCell);
    }

    /**
     * @param array<int, mixed> $row
     */
    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (! $this->isEmptyCell($cell)) {
                return false;
            }
        }

        return true;
    }

    protected function isEmptyCell(mixed $cell): bool
    {
        if ($cell === null) {
            return true;
        }

        return is_string($cell) && trim($cell) === '';
    }
}
