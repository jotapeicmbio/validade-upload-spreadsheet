<?php
namespace Icmbio\ValidateRegister\Validator;

class StructureSpreadsheetData
{
    /** @var array<int, array<int, mixed>> */
    protected array $worksheet = [];
    /** @var array<int, string> */
    protected array $headers = [];
    /** @var array<int, string> */
    protected array $labels = [];

    /** @param array<int, array<int, mixed>> $worksheet */
    public function __construct(array $worksheet)
    {
        $this->worksheet = $worksheet;
        $this->setHeaders();
        $this->setLabels();
    }

    private function setHeaders(): void
    {
        $this->headers = $this->worksheet[0] ?? [];
        unset($this->worksheet[0]);
    }

    private function setLabels(): void
    {
        $this->labels = $this->worksheet[1] ?? [];
        unset($this->worksheet[1]);
    }

    /** @return array<int, array<int, mixed>> */
    public function output(): array
    {
        return [
            $this->headers,
            $this->labels,
            ...$this->validadeRow(),
        ];
    }

    /** @return array<int, array<int, mixed>> */
    protected function validadeRow(): array
    {
        // reindex para garantir percorremos em ordem correta
        $rows         = array_values($this->worksheet);
        $result       = [];
        $currentModel = null;
        $numCols      = count($this->headers) ?: 0;

        foreach ($rows as $row) {
            // pad para garantir colunas consistentes
            $row = array_pad($row, $numCols, null);

            $isModel = true;
            foreach ($row as $cell) {
                if ($this->validateCell($cell)) {
                    $isModel = false;
                    break;
                }
            }

            if ($isModel) {
                // quando encontramos um novo modelo, salvamos o anterior (se existir)
                if ($currentModel !== null) {
                    $result[] = $currentModel;
                }
                $currentModel = $row;
                continue;
            }

            // linha com células vazias -> faz merge com o model atual
            if ($currentModel === null) {
                // proteção: se não tivermos model ainda, ignoramos a linha
                continue;
            }
            $currentModel = $this->validateColumn($currentModel, $row);
        }

        // adiciona o último model
        if ($currentModel !== null) {
            $result[] = $currentModel;
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $modelRow
     * @param array<int, mixed> $currentRow
     * @return array<int, mixed>
     */
    protected function validateColumn(array $modelRow, array $currentRow): array
    {
        $numCols = count($this->headers);

        // garantir mesmo comprimento
        $currentRow = array_pad($currentRow, $numCols, null);
        for ($i = 0; $i < $numCols; $i++) {
            $cell = $currentRow[$i];

            // se célula atual está "vazia" (null, "", " ") -> nada a fazer
            if ($this->validateCell($cell)) {
                continue;
            }

            // se no model já há um valor
            if (array_key_exists($i, $modelRow) && ! $this->validateCell($modelRow[$i])) {
                if (is_array($modelRow[$i])) {
                    // já é array -> append
                    $modelRow[$i][] = $cell;
                } else {
                    // convertido de scalar para array com os dois valores
                    $modelRow[$i] = [$modelRow[$i], $cell];
                }
            } else {
                // model não tinha valor nessa coluna -> atribui diretamente
                $modelRow[$i] = $cell;
            }
        }

        return $modelRow;
    }

    protected function validateCell(mixed $cell): bool
    {
        // considera vazio: null ou string composta só por espaços ("" / " " / "\t" etc)
        if (is_null($cell)) {
            return true;
        }
        if (is_string($cell) && trim($cell) === '') {
            return true;
        }
        return false;
    }
}
