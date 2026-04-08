<?php

use PhpOffice\PhpSpreadsheet\IOFactory;

use function Jotapegue\Phpxform\helpers\app_base;

require __DIR__ .'/vendor/autoload.php';

$file = app_base('examples/example-1.xlsx');
$document = IOFactory::load($file);

$spredSheet = $document->getActiveSheet();

$workseeh = $spredSheet->toArray();

$header = ['header_1', 'header_2', 'header_3', 'header_4', 'header_5'];
$label = ['Label 1', 'Label 2', 'Label 3', 'Label 4', 'Label 5'];

$linhaValidaAtual = [];

foreach ($workseeh as $key => $row) {
    
    if( count(array_intersect($row, $label)) == 0 && count(array_intersect($row, $header)) == 0)
    {
        if ((new index)->linhaValida($row)) {
            echo 'linha valida';
            $linhaValidaAtual = $row;
        } else {
            echo 'Linha anterio'. PHP_EOL;
            foreach($row as $cell)
            {
                echo $cell;
            }
            echo PHP_EOL;
            $row = (new index)->alteraLinhaInvalida($row, $linhaValidaAtual);
            echo 'Linha alterada'. PHP_EOL;
            echo 'Nova linha:'.PHP_EOL;
            foreach($row as $cell)
            {
                echo $cell;
            }
            echo PHP_EOL;
        }
        
        echo PHP_EOL;

    }
}

class index {

    public function linhaValida(array $row) : bool
    {
        $validaNumeroDeLinhas = 0;
        foreach ($row as $key => $cell) {
            if(!is_null($cell)){
                $validaNumeroDeLinhas ++;
            }
        }
        if($validaNumeroDeLinhas >= (count($row) -1))
        {
            return true;
        }else
        {
            return false;
        }
    }

    public function alteraLinhaInvalida($row, $linhaValidaAtual) : array
    {
        foreach ($row as $key => $value) {
            if(is_null($value)){
                $row[$key] = $linhaValidaAtual[$key];
            }
        }
        return $row;
    }

}

// dd($workseeh);