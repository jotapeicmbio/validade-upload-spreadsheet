<?php

declare(strict_types=1);

namespace Tests\Unit;

use Icmbio\ValidateRegister\SpreadsheetStructureBuilder;
use Icmbio\ValidateRegister\XformSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpreadsheetStructureBuilderTest extends TestCase
{
    #[Test]
    public function normalizes_repeat_wrapper_into_list(): void
    {
        $schema = XformSchema::fromArray([
            'children' => [
                [
                    'name' => 'grupo_a',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_a',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_a', 'type' => 'text'],
                                ['name' => 'campo_b', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $builder = new SpreadsheetStructureBuilder($schema);
        $result = $builder->build([
            [
                'grupo_a' => [
                    'campo_a' => null,
                    'campo_b' => 'valor_a',
                ],
            ],
        ]);

        self::assertCount(1, $result);
        self::assertIsArray($result[0]['grupo_a']);
        self::assertCount(1, $result[0]['grupo_a']);
        self::assertSame('valor_a', $result[0]['grupo_a'][0]['campo_b']);
        self::assertArrayHasKey('campo_a', $result[0]['grupo_a'][0]);
    }

    #[Test]
    public function keeps_nested_repeat_inside_parent_repeat(): void
    {
        $schema = XformSchema::fromArray([
            'children' => [
                [
                    'name' => 'grupo_b',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_b',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_c', 'type' => 'select1'],
                                [
                                    'name' => 'grupo_c',
                                    'type' => 'group',
                                    'children' => [
                                        [
                                            'name' => 'grupo_c',
                                            'type' => 'repeat',
                                            'children' => [
                                                ['name' => 'campo_d', 'type' => 'decimal'],
                                                ['name' => 'campo_e', 'type' => 'decimal'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $builder = new SpreadsheetStructureBuilder($schema);
        $result = $builder->build([
            [
                'grupo_b' => [
                    'campo_c' => 'valor_b',
                    'grupo_c' => [
                        [
                            'campo_d' => '80',
                            'campo_e' => '90',
                        ],
                        [
                            'campo_d' => '85',
                            'campo_e' => '95',
                        ],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $result);
        self::assertCount(1, $result[0]['grupo_b']);
        self::assertSame('valor_b', $result[0]['grupo_b'][0]['campo_c']);
        self::assertCount(2, $result[0]['grupo_b'][0]['grupo_c']);
        self::assertSame('80', $result[0]['grupo_b'][0]['grupo_c'][0]['campo_d']);
        self::assertSame('90', $result[0]['grupo_b'][0]['grupo_c'][0]['campo_e']);
        self::assertSame('85', $result[0]['grupo_b'][0]['grupo_c'][1]['campo_d']);
        self::assertSame('95', $result[0]['grupo_b'][0]['grupo_c'][1]['campo_e']);
    }

    #[Test]
    public function structures_anonymous_sample_with_nested_groups_and_repeats(): void
    {
        $schema = XformSchema::fromArray([
            'children' => [
                ['name' => 'campo_1', 'type' => 'text'],
                ['name' => 'campo_2', 'type' => 'text'],
                [
                    'name' => 'grupo_a',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_a',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_a', 'type' => 'text'],
                                ['name' => 'campo_b', 'type' => 'text'],
                            ],
                        ],
                    ],
                ],
                [
                    'name' => 'grupo_1',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_1',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_1', 'type' => 'text'],
                                ['name' => 'campo_2', 'type' => 'text'],
                                [
                                    'name' => 'grupo_2',
                                    'type' => 'group',
                                    'children' => [
                                        [
                                            'name' => 'grupo_2',
                                            'type' => 'repeat',
                                            'children' => [
                                                ['name' => 'campo_1', 'type' => 'text'],
                                                ['name' => 'campo_2', 'type' => 'text'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $builder = new SpreadsheetStructureBuilder($schema);
        $result = $builder->build([
            [
                'campo_1' => 'valor_1',
                'campo_2' => 'valor_2',
                'grupo_a' => [
                    [
                        'grupo_a/campo_a' => 'valor_a',
                        'grupo_a/campo_b' => 'valor_b',
                    ],
                    [
                        'grupo_a/campo_a' => 'valor_c',
                        'grupo_a/campo_b' => 'valor_d',
                    ],
                ],
                'grupo_1' => [
                    [
                        'grupo_1/campo_1' => 'grupo_1_valor_1',
                        'grupo_1/campo_2' => 'grupo_1_valor_2',
                        'grupo_1/grupo_2' => [
                            [
                                'grupo_1/grupo_2/campo_1' => 'grupo_2_valor_1',
                                'grupo_1/grupo_2/campo_2' => 'grupo_2_valor_2',
                            ],
                        ],
                    ],
                    [
                        'grupo_1/campo_1' => 'grupo_1_valor_1',
                        'grupo_1/campo_2' => 'grupo_1_valor_2',
                        'grupo_1/grupo_2' => [
                            [
                                'grupo_1/grupo_2/campo_1' => 'grupo_2_valor_3',
                                'grupo_1/grupo_2/campo_2' => 'grupo_2_valor_4',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            [
                'campo_1' => 'valor_1',
                'campo_2' => 'valor_2',
                'grupo_a' => [
                    [
                        'grupo_a/campo_a' => 'valor_a',
                        'grupo_a/campo_b' => 'valor_b',
                    ],
                    [
                        'grupo_a/campo_a' => 'valor_c',
                        'grupo_a/campo_b' => 'valor_d',
                    ],
                ],
                'grupo_1' => [
                    [
                        'grupo_1/campo_1' => 'grupo_1_valor_1',
                        'grupo_1/campo_2' => 'grupo_1_valor_2',
                        'grupo_1/grupo_2' => [
                            [
                                'grupo_1/grupo_2/campo_1' => 'grupo_2_valor_1',
                                'grupo_1/grupo_2/campo_2' => 'grupo_2_valor_2',
                            ],
                            [
                                'grupo_1/grupo_2/campo_1' => 'grupo_2_valor_3',
                                'grupo_1/grupo_2/campo_2' => 'grupo_2_valor_4',
                            ],
                        ],
                    ],
                ],
            ],
        ], $result);
    }

    #[Test]
    public function collapses_consecutive_parent_repeat_items_using_stable_schema_fields(): void
    {
        $schema = XformSchema::fromArray([
            'children' => [
                [
                    'name' => 'grupo_d',
                    'type' => 'repeat',
                    'children' => [
                        ['name' => 'campo_f', 'type' => 'select1'],
                        ['name' => 'campo_g', 'type' => 'select1'],
                        ['name' => 'campo_h', 'type' => 'integer'],
                        [
                            'name' => 'campo_i',
                            'type' => 'calculate',
                            'bind' => [
                                'calculate' => 'if( ../campo_h <=20, ../campo_h, 20)',
                            ],
                        ],
                        [
                            'name' => 'grupo_e',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_j', 'type' => 'decimal'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $builder = new SpreadsheetStructureBuilder($schema);
        $result = $builder->build([
            [
                'grupo_d' => [
                    [
                        'campo_f' => 'valor_e',
                        'campo_g' => 'valor_e',
                        'campo_h' => '5',
                        'grupo_e' => [
                            ['campo_j' => '20'],
                        ],
                    ],
                    [
                        'campo_f' => 'valor_e',
                        'campo_g' => 'valor_e',
                        'campo_h' => '5',
                        'grupo_e' => [
                            ['campo_j' => '19'],
                        ],
                    ],
                    [
                        'campo_f' => 'valor_f',
                        'campo_g' => 'valor_f',
                        'campo_h' => '7',
                        'grupo_e' => [
                            ['campo_j' => '30'],
                        ],
                    ],
                ],
            ],
        ]);

        self::assertCount(1, $result);
        self::assertCount(2, $result[0]['grupo_d']);
        self::assertSame('valor_e', $result[0]['grupo_d'][0]['campo_f']);
        self::assertCount(2, $result[0]['grupo_d'][0]['grupo_e']);
        self::assertSame('20', $result[0]['grupo_d'][0]['grupo_e'][0]['campo_j']);
        self::assertSame('19', $result[0]['grupo_d'][0]['grupo_e'][1]['campo_j']);
        self::assertSame('valor_f', $result[0]['grupo_d'][1]['campo_f']);
        self::assertCount(1, $result[0]['grupo_d'][1]['grupo_e']);
        self::assertSame('30', $result[0]['grupo_d'][1]['grupo_e'][0]['campo_j']);
    }
}
