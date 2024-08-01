<?php

namespace Icmbio\ValidateRegister\Validator;

class StructureSpreadsheetData 
{
    protected array $dataSet = [];
    protected array $headers = [];
    protected array $labels = [];

    public function __construct($worksheet)
    {
        $this->dataSet = $worksheet;
        $this->setHeaders();
        $this->setLabels();
    }

    private function setHeaders() : void
    {
        $this->headers = $this->dataSet[0];
        unset($this->dataSet[0]);
    }

    private function setLabels() : void
    {
        $this->labels = $this->dataSet[1];
        unset($this->dataSet[1]);
    }

    public function output() : array
    {
        return [
            $this->headers,
            $this->labels,
            ...$this->structureDataSet()
        ];
    }

    protected function structureDataSet() : array
    {
        $model = [];
        
        foreach ($this->dataSet as $key => $row) {
            if (!in_array(null, $row)) {
                $model = $row;
            } else {
                $this->dataSet[$key] = $this->fillRowWithModel($row, $model);
            }
        }
        
        return $this->dataSet;
    }

    protected function fillRowWithModel($row, $rowModel) : array
    {
        return array_map(function ($cell, $model) {
            return $this->validateCell($cell) ? $cell = $model : $cell;
        }, $row, $rowModel);
    }

    protected function validateCell (mixed $cell) : bool
    {
        return is_null($cell) || $cell == " " || $cell == "";
    }
}
