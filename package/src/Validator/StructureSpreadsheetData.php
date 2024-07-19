<?php

namespace Icmbio\ValidateRegister\Validator;

use PhpOffice\PhpSpreadsheet\IOFactory;
use function Jotapegue\Phpxform\helpers\app_base;

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

    public function setHeaders() : void
    {
        $this->headers = $this->dataSet[0];
        unset($this->dataSet[0]);
    }

    public function setLabels() : void
    {
        $this->labels = $this->dataSet[1];
        unset($this->dataSet[1]);
    }

    public function reorderDataSet() : void
    {
        array_unshift($this->dataSet, $this->labels);
        array_unshift($this->dataSet, $this->headers);
    }

    public function output() : array
    {
        $currentValidLine = [];   
        foreach ($this->dataSet as $key => $row) {
            if ($this->validLine($row)) {
                $currentValidLine = $row;
            } else {
                $this->dataSet[$key] = $this->changeValidLine($row, $currentValidLine);
            }
        }
        $this->reorderDataSet();
        return $this->dataSet;

    }

    public function validLine(array $row) : bool
    {
        $numberOfValidRows = 0;
        foreach ($row as $key => $cell) {
            if(!is_null($cell)){
                $numberOfValidRows ++;
            }
        }
        return $numberOfValidRows >= (count($row) -1);
    }

    public function changeValidLine($row, $currentValidLine) : array
    {
        foreach ($row as $key => $value) {
            if(is_null($value)){
                $row[$key] = $currentValidLine[$key];
            }
        }
        return $row;
    }
}
