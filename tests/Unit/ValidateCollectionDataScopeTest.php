<?php

declare(strict_types=1);

namespace Tests\ValidateRegister\Unit;

use Icmbio\ValidateRegister\CreateValidatorsStructure;
use Icmbio\ValidateRegister\ValidateCollectionData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ValidateCollectionDataScopeTest extends TestCase
{
    #[Test]
    public function pos_fixo_repeat_de_um_nivel_enxerga_campo_do_grupo_pai_no_contexto_correto(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodesOneLevel());
        $data = $this->collectionDataOneLevel();

        $context = $this->buildContext($data);
        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertArrayHasKey('grupo_1', $context);
        self::assertArrayHasKey('campo_1', $context);
        self::assertSame([], $errors);
    }

    #[Test]
    public function pos_fixo_repeat_aninhado_enxerga_campo_do_grupo_interno_no_contexto_correto(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodesNested());
        $data = $this->collectionDataNested();

        $rootContext = $this->buildContext($data);
        $itemContext = $this->buildContext($data['lista_1'][0], $rootContext);
        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertArrayHasKey('grupo_1', $rootContext);
        self::assertArrayHasKey('campo_1', $rootContext);
        self::assertArrayHasKey('grupo_2', $itemContext);
        self::assertArrayHasKey('campo_2', $itemContext);
        self::assertSame([], $errors);
    }

    #[Test]
    public function repete_no_topo_preserva_campo_do_grupo_irmao_no_contexto(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodesTopLevelRepeat());
        $data = $this->collectionDataTopLevelRepeat();

        $context = $this->buildContext($data);
        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertArrayHasKey('campo_a', $context);
        self::assertSame('valor_a', $context['campo_a']);
        self::assertSame([], $errors);
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionDataOneLevel(): array
    {
        return [
            'grupo_1' => [
                'campo_1' => 'sim',
            ],
            'lista_1' => [
                [
                    'campo_2' => 'valor',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionDataNested(): array
    {
        return [
            'grupo_1' => [
                'campo_1' => 'sim',
            ],
            'lista_1' => [
                [
                    'grupo_2' => [
                        'campo_2' => 'sim',
                    ],
                    'sublista' => [
                        [
                            'campo_3' => 'valor',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionDataTopLevelRepeat(): array
    {
        return [
            'lista_a' => [
                [
                    'campo_a' => 'valor_a',
                    'campo_b' => 'valor_b',
                ],
            ],
            'lista_b' => [
                [
                    'campo_c' => 'valor_c',
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xformNodesOneLevel(): array
    {
        return [
            [
                'name' => 'grupo_1',
                'type' => 'group',
                'children' => [
                    [
                        'name' => 'campo_1',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'name' => 'lista_1',
                'type' => 'repeat',
                'bind' => [
                    'relevant' => "selected( \${campo_1}, 'sim')",
                ],
                'children' => [
                    [
                        'name' => 'campo_2',
                        'type' => 'text',
                        'bind' => [
                            'required' => 'yes',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xformNodesNested(): array
    {
        return [
            [
                'name' => 'grupo_1',
                'type' => 'group',
                'children' => [
                    [
                        'name' => 'campo_1',
                        'type' => 'text',
                    ],
                ],
            ],
            [
                'name' => 'lista_1',
                'type' => 'repeat',
                'children' => [
                    [
                        'name' => 'grupo_2',
                        'type' => 'group',
                        'children' => [
                            [
                                'name' => 'campo_2',
                                'type' => 'text',
                            ],
                        ],
                    ],
                    [
                        'name' => 'sublista',
                        'type' => 'repeat',
                        'bind' => [
                            'relevant' => "selected( \${campo_2}, 'sim')",
                        ],
                        'children' => [
                            [
                                'name' => 'campo_3',
                                'type' => 'text',
                                'bind' => [
                                    'required' => 'yes',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xformNodesTopLevelRepeat(): array
    {
        return [
            [
                'name' => 'lista_a',
                'type' => 'repeat',
                'children' => [
                    [
                        'name' => 'campo_a',
                        'type' => 'select1',
                    ],
                    [
                        'name' => 'campo_b',
                        'type' => 'select',
                    ],
                ],
            ],
            [
                'name' => 'lista_b',
                'type' => 'repeat',
                'bind' => [
                    'relevant' => "selected( \${campo_a}, 'valor_a')",
                ],
                'children' => [
                    [
                        'name' => 'campo_c',
                        'type' => 'select1',
                        'bind' => [
                            'required' => 'yes',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $parentContext
     * @return array<string, mixed>
     */
    private function buildContext(array $data, ?array $parentContext = null): array
    {
        $reflection = new ReflectionClass(ValidateCollectionData::class);
        $method = $reflection->getMethod('buildContext');
        $method->setAccessible(true);

        /** @var array<string, mixed> $context */
        $context = $method->invoke(new ValidateCollectionData(), $data, $parentContext);

        return $context;
    }
}
