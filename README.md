# Validate Upload Registers from Spreadsheet

Biblioteca PHP para ler planilhas Excel, estruturar linhas repetidas, validar os dados com base em uma definição de formulário, calcular campos derivados, verificar anexos de imagem e gerar XML no formato esperado.

## Requisitos

- PHP 8.2+
- `phpoffice/phpspreadsheet`
- `icmbio/validate-xpath-expression`
- `ramsey/uuid`

## Instalação

```bash
composer require icmbio/validate-upload-registers-from-spreadsheet
```

## Visão geral

O pacote cobre o fluxo abaixo:

1. Lê a planilha XLSX.
2. Estrutura blocos repetidos a partir de linhas-modelo.
3. Monta os validadores a partir da definição do formulário.
4. Valida campos obrigatórios, regras XPath, escolhas e tipos.
5. Resolve escolhas dinâmicas.
6. Calcula campos derivados, como `uuid()`.
7. Preenche UUIDs faltantes em caminhos declarados.
8. Gera XML final para cada coleta.

## Fluxo recomendado

### 1. Ler e estruturar a planilha

Use `DataCollectionSpreadsheetReviewPipeline` para carregar a planilha e preparar os dados:

```php
use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;

$reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
    ->setDataCollection(__DIR__ . '/planilha.xlsx')
    ->fillMissingUuidFields(['coletor/uuid'])
    ->validateCollectionFromJson($formDefinition, $dynamicChoices)
    ->validateCollection();

if (! $reviewPipeline->valid()) {
    var_dump($reviewPipeline->errors());
    exit;
}

$collection = $reviewPipeline->process();
```

### 2. Gerar XML

Depois de validar, use `DataCollectionSpreadsheetConvertPipeline`:

```php
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;

$files = (new DataCollectionSpreadsheetConvertPipeline())
    ->collection($reviewPipeline)
    ->instanceInfo('Nome-da-instancia', '2026.1')
    ->timestamp('2026-04-10T18:01:45.000-03:00')
    ->outputDirectory(__DIR__ . '/xmls')
    ->generate();
```

`generate()` retorna uma lista com os caminhos dos arquivos XML criados.

## Exemplo completo

```php
use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;

$formDefinition = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
$dynamicChoices = [
    'uc' => [
        ['label' => 'Reserva Extrativista do Tapajós', 'name' => 789],
    ],
];

$reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
    ->setDataCollection(__DIR__ . '/planilha.xlsx')
    ->validateCollectionFromJson($formDefinition, $dynamicChoices)
    ->validateCollection();

if (! $reviewPipeline->valid()) {
    foreach ($reviewPipeline->errors() as $error) {
        echo $error['message'] . PHP_EOL;
    }
    exit(1);
}

$xmlFiles = (new DataCollectionSpreadsheetConvertPipeline())
    ->collection($reviewPipeline)
    ->instanceInfo('MinhaInstancia', '2026.1')
    ->generate();
```

## Definição do formulário

`CreateValidatorsStructure::build()` converte a árvore do formulário em uma lista de validadores indexada pelo caminho do campo.

```php
use Icmbio\ValidateRegister\CreateValidatorsStructure;

$validators = CreateValidatorsStructure::build($formDefinition['children'] ?? [], $dynamicChoices);
```

Os validadores gerados guardam, entre outros dados:

- `type`
- `label`
- `required`
- `required_message`
- `constraint`
- `constraint_message`
- `relevant`
- `calculate`
- `choices`
- `choices_labels`

## Leitura da planilha

`SpreadsheetReader::load($path)` lê a planilha como matriz.

Regras importantes:

- Se existir apenas uma aba, ela será usada automaticamente.
- Se houver mais de uma aba, o pacote tenta usar a aba `Preenchimento`.
- Se a planilha não existir, uma exceção é lançada.

## Estruturação de dados

`StructureSpreadsheetData` transforma uma matriz de worksheet em uma coleção estruturada.

```php
use Icmbio\ValidateRegister\Validator\StructureSpreadsheetData;

$structured = (new StructureSpreadsheetData($worksheet))
    ->estruture()
    ->toArray();
```

Isso é útil quando a planilha usa linhas repetidas ou colunas agrupadas por prefixo, como `coletor/nome`.

## Validação

`ValidateCollectionData::createErrorsList()` valida uma coleta já estruturada.

```php
use Icmbio\ValidateRegister\ValidateCollectionData;

$errors = ValidateCollectionData::createErrorsList($collection, $validators);
```

O pacote valida, entre outros pontos:

- campos obrigatórios
- expressões XPath de `constraint` e `relevant`
- escolhas válidas
- valores inteiros
- UUIDs gerados para campos `calculate` com `uuid()`

## Campos calculados

`CalculateFields::apply()` executa cálculos declarados nos validadores.

```php
use Icmbio\ValidateRegister\CalculateFields;

$collection = CalculateFields::apply($collection, $validators);
```

O caso mais comum é `uuid()`, que gera um UUID v4 para o campo correspondente.

## Anexos de foto

`PhotoAttachments` ajuda a identificar e validar fotos referenciadas na coleta.

```php
use Icmbio\ValidateRegister\PhotoAttachments;

$photos = PhotoAttachments::getPhotosFromCollection($collection, $validators);
$errors = PhotoAttachments::validatePhotos($collection, $validators, $existingPhotos);
```

## Geração de XML

`XformXmlBuilder::build()` gera o XML final.

```php
use Icmbio\ValidateRegister\XformXmlBuilder;

$xml = XformXmlBuilder::build(
    $collection,
    array_keys($validators),
    'coleta',
    'coleta_id',
    '2026.1',
    null,
    '2026-04-01T12:00:00.000-03:00',
    $formDefinition,
);
```

Se a definição do formulário for informada, a estrutura do XML segue o formulário. Caso contrário, o pacote usa as chaves recebidas para montar a saída.

## Pipeline de conversão

`DataCollectionSpreadsheetConvertPipeline` aceita tanto uma coleção pronta quanto o pipeline de revisão:

```php
use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;

$files = (new DataCollectionSpreadsheetConvertPipeline())
    ->collection($reviewPipeline)
    ->formInfo($formDefinition)
    ->instanceInfo('MinhaInstancia', '2026.1')
    ->outputDirectory(__DIR__ . '/xmls')
    ->timestamp('2026-04-10T18:01:45.000-03:00')
    ->generate();
```

### Métodos principais

- `collection(array|DataCollectionSpreadsheetReviewPipeline $collection)`
- `formInfo(array $formDefinition)`
- `outputDirectory(string $outputDirectory)`
- `instanceInfo(string $instanceName, string|int $instanceVersion = '1')`
- `timestamp(?string $timestamp = null)`
- `generate(): array`

## Pipeline de revisão

### Métodos principais

- `setDataCollection(string $path)`
- `transform(string|callable $fn, DataCollectionSelectorKey $selectorKey = DataCollectionSelectorKey::NAME, array $keys = [])`
- `fillMissingUuidFields(array $paths)`
- `validateCollectionFromJson(array $formDefinition = [], array $dynamicChoices = [])`
- `validateCollection()`
- `process(): array`
- `valid(): bool`
- `errors(): array`

### Transformação de colunas

Você pode transformar valores por nome de coluna ou por índice:

```php
use Icmbio\ValidateRegister\Enums\DataCollectionSelectorKey;

$reviewPipeline->transform(
    fn ($value) => mb_strtoupper($value),
    DataCollectionSelectorKey::NAME,
    ['estacao_amostral']
);
```

## Convenções importantes

- Campos com tipo `repeat` são tratados como listas de sub-registros.
- Campos com prefixo `_` são ignorados na geração do XML.
- O campo `meta.instanceID` é preservado quando já existe na entrada.
- Quando não houver `instanceID`, o XML recebe `uuid:...` automaticamente.

## Execução dos testes

```bash
composer test
```

Ou diretamente:

```bash
vendor/bin/phpunit --colors=always --testdox
```

## Licença

Consulte o repositório do projeto para detalhes de licenciamento.
