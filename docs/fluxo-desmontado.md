# Fluxo Desmontado

Documento de apoio para entender o pipeline em peças pequenas: o que cada classe recebe, o que entrega e onde ela entra no fluxo.

## Visão Geral

1. `SpreadsheetReader` lê a planilha.
2. `SpreadsheetInstanceSeparator` separa header, label e coletas.
3. `XformSchema` carrega a árvore do formulário.
4. `XformCollectionBuilder` monta a estrutura guiada pelo `xform`.
5. `DataCollectionSpreadsheetReviewPipeline` valida, normaliza tipos e prepara a coleta.
6. `DataCollectionSpreadsheetConvertPipeline` gera os XMLs finais.
7. `XformXmlBuilder` escreve o XML.

## Diagrama

```text
planilha.xlsx
    |
    v
SpreadsheetReader
    |
    v
worksheet
    |
    v
SpreadsheetInstanceSeparator
    |
    v
{ headers, labels, collects }
    |
    v
XformSchema::fromArray(formDefinition)
    |
    v
XformCollectionBuilder
    |
    v
coleção estruturada
    |
    v
DataCollectionSpreadsheetReviewPipeline
    |
    v
DataCollectionSpreadsheetConvertPipeline
    |
    v
XformXmlBuilder
    |
    v
xml final
```

## Peças

### `SpreadsheetReader`

Entrada:

- caminho do arquivo da planilha

Saída:

- `array<int, array<int, mixed>>` com as linhas da planilha

Responsabilidade:

- abrir XLSX, CSV ou XLS
- devolver a worksheet como matriz

### `SpreadsheetInstanceSeparator`

Entrada:

- worksheet bruta

Saída:

- `headers`
- `labels`
- `collects`

Formato:

```php
[
    'headers' => array<int, mixed>,
    'labels' => array<int, mixed>,
    'collects' => [
        [
            'start_line' => 3,
            'collect' => [
                array<int, mixed>,
            ],
        ],
    ],
]
```

Responsabilidade:

- isolar cada instância da planilha
- manter a linha inicial da coleta

### `XformSchema`

Entrada:

- array com a definição do formulário

Saída:

- árvore de nós `XformSchemaNode`

Responsabilidade:

- representar a estrutura do formulário
- expor `group`, `repeat`, `children`, `path`, `type`, `label`, `required`, `relevant`, `calculate`

### `XformCollectionBuilder`

Entrada:

- estrutura separada da planilha
- `XformSchema`

Saída:

- coleção estruturada em um array associativo

Responsabilidade:

- usar o `xform` como guia da estrutura
- montar grupos e repeats
- manter campos simples
- remover grupos/repeats vazios
- ignorar nós técnicos

Exemplo de entrada esperada:

```php
[
    'headers' => [...],
    'labels' => [...],
    'collects' => [
        [
            'start_line' => 3,
            'collect' => [
                [...],
            ],
        ],
    ],
]
```

Exemplo de saída:

```php
[
    'campo_1' => 'valor',
    'grupo_1' => [
        [
            'campo_a' => 'valor',
        ],
    ],
]
```

### `DataCollectionSpreadsheetReviewPipeline`

Entrada:

- caminho da planilha ou worksheet carregada
- definição do formulário
- escolhas dinâmicas
- caminhos de UUID opcionais

Saída:

- coleção validada e preparada para conversão em XML

Responsabilidade:

- ler e preparar a coleta
- validar compatibilidade com o formulário
- resolver escolhas dinâmicas
- aplicar cast de tipos
- preencher UUIDs
- retornar a coleção pronta para XML

Observação:

- a classe ainda possui compatibilidade com o fluxo legado
- o objetivo atual é manter o `xform` como guia principal da estrutura

### `DataCollectionSpreadsheetConvertPipeline`

Entrada:

- coleção pronta ou `DataCollectionSpreadsheetReviewPipeline`
- metadados do formulário/instância
- diretório de saída

Saída:

- lista com os caminhos dos XMLs gerados

Responsabilidade:

- consumir a coleção final
- gerar um XML por instância

### `XformXmlBuilder`

Entrada:

- coleção estruturada
- lista de chaves
- nome da instância
- versão
- timestamp
- definição do formulário

Saída:

- string XML

Responsabilidade:

- serializar a coleção no formato final
- respeitar a hierarquia do formulário

## Fluxo de Dados

### 1. Planilha para matriz

```php
$worksheet = SpreadsheetReader::load($path);
```

### 2. Matriz para instâncias

```php
$spreadsheet = (new SpreadsheetInstanceSeparator())->separate($worksheet);
```

### 3. Formulário para schema

```php
$schema = XformSchema::fromArray($formDefinition);
```

### 4. Instância para coleção estruturada

```php
$collection = (new XformCollectionBuilder($schema))->build($spreadsheet);
```

### 5. Coleção para XML

```php
$xml = XformXmlBuilder::build(
    $collection,
    array_keys($validators),
    $instanceName,
    $instanceName,
    $version,
    null,
    $timestamp,
    $formDefinition,
);
```

## Legado

Estas classes ainda existem e podem ser usadas em cenários antigos:

- `StructureSpreadsheetData`
- `SpreadsheetStructureBuilder`

Elas não são o caminho principal do fluxo novo, mas continuam no repositório para compatibilidade.

## Regra prática

- Se o objetivo é entender a planilha, comece por `SpreadsheetReader` e `SpreadsheetInstanceSeparator`.
- Se o objetivo é entender a modelagem, comece por `XformSchema`.
- Se o objetivo é entender a estrutura final, leia `XformCollectionBuilder`.
- Se o objetivo é entender o XML final, leia `XformXmlBuilder`.
