<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Integration;

use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SpreadsheetStructureSampleTest extends TestCase
{
    #[Test]
    public function SpreadsheetShouldMatchTheModeledStructure(): void
    {
        $pipeline = $this->makePipelineWithSpreadsheet($this->sampleWorksheet());
        $collection = $pipeline
            ->validateCollectionFromJson($this->sampleFormDefinition())
            ->enableCompatibilityValidation(false)
            ->collection();

        $expected = [
            [
                'campo_a' => 'valor_a',
                'campo_b' => 'valor_b',
                'grupo_a' => [
                    [
                        'grupo_a/campo_a' => 'valor_c',
                        'grupo_a/campo_b' => 'valor_d',
                    ],
                    [
                        'grupo_a/campo_a' => 'valor_e',
                        'grupo_a/campo_b' => 'valor_f',
                    ],
                ],
                'grupo_b' => [
                    [
                        'grupo_b/campo_a' => 'valor_g',
                        'grupo_b/campo_b' => 'valor_h',
                        'grupo_c' => [
                            [
                                'grupo_b/grupo_c/campo_a' => 'valor_i',
                                'grupo_b/grupo_c/campo_b' => 'valor_j',
                            ],
                            [
                                'grupo_b/grupo_c/campo_a' => 'valor_k',
                                'grupo_b/grupo_c/campo_b' => 'valor_l',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame($expected, $collection);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sampleWorksheet(): array
    {
        return [
            ['campo_a', 'campo_b', 'grupo_a/campo_a', 'grupo_a/campo_b', 'grupo_b/campo_a', 'grupo_b/campo_b', 'grupo_b/grupo_c/campo_a', 'grupo_b/grupo_c/campo_b'],
            ['Label A', 'Label B', 'Campo A', 'Campo B', 'Grupo B Campo A', 'Grupo B Campo B', 'Grupo C Campo A', 'Grupo C Campo B'],
            ['valor_a', 'valor_b', 'valor_c', 'valor_d', 'valor_g', 'valor_h', 'valor_i', 'valor_j'],
            [null, null, 'valor_e', 'valor_f', null, null, 'valor_k', 'valor_l'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sampleFormDefinition(): array
    {
        return [
            'children' => [
                ['name' => 'campo_a', 'type' => 'text'],
                ['name' => 'campo_b', 'type' => 'text'],
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
                    'name' => 'grupo_b',
                    'type' => 'group',
                    'children' => [
                        [
                            'name' => 'grupo_b',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'campo_a', 'type' => 'text'],
                                ['name' => 'campo_b', 'type' => 'text'],
                                [
                                    'name' => 'grupo_c',
                                    'type' => 'group',
                                    'children' => [
                                        [
                                            'name' => 'grupo_c',
                                            'type' => 'repeat',
                                            'children' => [
                                                ['name' => 'campo_a', 'type' => 'text'],
                                                ['name' => 'campo_b', 'type' => 'text'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<int, mixed>> $worksheet
     */
    private function makePipelineWithSpreadsheet(array $worksheet): DataCollectionSpreadsheetReviewPipeline
    {
        $pipeline = new DataCollectionSpreadsheetReviewPipeline();
        $reflection = new \ReflectionClass($pipeline);

        foreach ([
            'data_collection' => $worksheet,
            'spreadsheet_headers' => $worksheet[0] ?? [],
            'data_collection_prepared' => false,
            'compatibility_checked' => false,
        ] as $propertyName => $value) {
            $property = $reflection->getProperty($propertyName);
            $property->setAccessible(true);
            $property->setValue($pipeline, $value);
        }

        return $pipeline;
    }
}
