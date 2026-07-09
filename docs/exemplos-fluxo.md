# Exemplos do Fluxo

Exemplos genéricos de entrada e saída das principais peças do fluxo.

## `SpreadsheetReader`

Entrada:

- caminho da planilha

Saída:

```php
[
    ['campo_1', 'campo_2', 'grupo_1/campo_1'],
    ['Label 1', 'Label 2', 'Grupo 1 Campo 1'],
    ['valor_1', 'valor_2', 'valor_3'],
]
```

## `SpreadsheetInstanceSeparator`

Entrada:

```php
[
    ['campo_1', 'campo_2'],
    ['Label 1', 'Label 2'],
    ['instancia_1', 'valor_1'],
    [null, 'valor_2'],
    ['instancia_2', 'valor_3'],
]
```

Saída:

```php
[
    'headers' => ['campo_1', 'campo_2'],
    'labels' => ['Label 1', 'Label 2'],
    'collects' => [
        [
            'start_line' => 3,
            'collect' => [
                ['instancia_1', 'valor_1'],
                [null, 'valor_2'],
            ],
        ],
        [
            'start_line' => 5,
            'collect' => [
                ['instancia_2', 'valor_3'],
            ],
        ],
    ],
]
```

## `XformSchema`

Entrada:

```php
[
    'children' => [
        ['name' => 'campo_1', 'type' => 'text'],
        [
            'name' => 'grupo_1',
            'type' => 'group',
            'children' => [
                [
                    'name' => 'grupo_1',
                    'type' => 'repeat',
                    'children' => [
                        ['name' => 'campo_a', 'type' => 'text'],
                    ],
                ],
            ],
        ],
    ],
]
```

Saída:

```php
XformSchemaNode(root)
```

## `XformCollectionBuilder`

Entrada:

```php
[
    'headers' => ['campo_1', 'grupo_1/campo_a'],
    'labels' => ['Campo 1', 'Campo A'],
    'collects' => [
        [
            'start_line' => 3,
            'collect' => [
                ['valor_1', 'valor_2'],
                ['valor_3', 'valor_4'],
            ],
        ],
    ],
]
```

Saída:

```php
[
    'campo_1' => 'valor_1',
    'grupo_1' => [
        [
            'campo_a' => 'valor_2',
        ],
        [
            'campo_a' => 'valor_4',
        ],
    ],
]
```

## `DataCollectionSpreadsheetReviewPipeline`

Entrada:

- planilha carregada
- `formDefinition`
- `dynamicChoices`

Saída:

```php
[
    [
        'campo_1' => 'valor_1',
        'grupo_1' => [
            [
                'campo_a' => 'valor_2',
            ],
        ],
    ],
]
```

## `DataCollectionSpreadsheetConvertPipeline`

Entrada:

- coleção validada
- nome da instância
- versão
- diretório de saída

Saída:

```php
[
    '/tmp/xmls/Instancia_1',
]
```

## `XformXmlBuilder`

Entrada:

```php
[
    'campo_1' => 'valor_1',
    'grupo_1' => [
        [
            'campo_a' => 'valor_2',
        ],
    ],
]
```

Saída:

```xml
<formulario>
  <campo_1>valor_1</campo_1>
  <grupo_1>
    <campo_a>valor_2</campo_a>
  </grupo_1>
</formulario>
```
