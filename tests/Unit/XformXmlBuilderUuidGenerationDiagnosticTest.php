<?php

declare(strict_types=1);

namespace Icmbio\SrcPhp\Tests\Unit;

use DOMDocument;
use DOMXPath;
use Icmbio\ValidateRegister\XformXmlBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XformXmlBuilderUuidGenerationDiagnosticTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: array<string, mixed>, 2: list<string>, 3: string}>
     */
    public static function uuidScenariosProvider(): array
    {
        return [
            'uuid_no_repeat_em_group' => [
                [
                    'bloco_a' => [
                        'campo_texto' => 'valor',
                    ],
                ],
                [
                    'children' => [[
                        'name' => 'bloco_a',
                        'type' => 'group',
                        'children' => [
                            ['name' => 'campo_texto', 'type' => 'text'],
                            ['name' => 'id_unico_a', 'type' => 'calculate', 'bind' => ['calculate' => 'uuid()']],
                        ],
                    ]],
                ],
                ['bloco_a', 'bloco_a/campo_texto'],
                '/xml/bloco_a/id_unico_a',
            ],
            'uuid_em_repeat_simples' => [
                [
                    'lista_b' => [[
                        'lista_b/nome' => 'item-1',
                    ]],
                ],
                [
                    'children' => [[
                        'name' => 'lista_b',
                        'type' => 'repeat',
                        'children' => [
                            ['name' => 'nome', 'type' => 'text'],
                            ['name' => 'uuid_item_b', 'type' => 'calculate', 'bind' => ['calculate' => 'uuid()']],
                        ],
                    ]],
                ],
                ['lista_b', 'lista_b/nome'],
                '/xml/lista_b/uuid_item_b',
            ],
            'uuid_em_repeat_aninhado' => [
                [
                    'grupo_c' => [[
                        'grupo_c/sub_c' => [[
                            'grupo_c/sub_c/rotulo' => 'x',
                        ]],
                    ]],
                ],
                [
                    'children' => [[
                        'name' => 'grupo_c',
                        'type' => 'repeat',
                        'children' => [[
                            'name' => 'sub_c',
                            'type' => 'repeat',
                            'children' => [
                                ['name' => 'rotulo', 'type' => 'text'],
                                ['name' => 'token_uuid_c', 'type' => 'calculate', 'bind' => ['calculate' => 'uuid()']],
                            ],
                        ]],
                    ]],
                ],
                ['grupo_c', 'grupo_c/sub_c', 'grupo_c/sub_c/rotulo'],
                '/xml/grupo_c/sub_c/token_uuid_c',
            ],
        ];
    }

    #[DataProvider('uuidScenariosProvider')]
    public function test_uuid_is_generated_for_any_calculate_uuid_in_group_or_repeat(
        array $data,
        array $formDefinition,
        array $keys,
        string $uuidXPath,
    ): void {
        $xml = XformXmlBuilder::build(
            $data,
            $keys,
            'xml',
            'xml_id',
            '1.0',
            null,
            '2026-05-15T10:00:00.000-03:00',
            $formDefinition,
        );

        $document = new DOMDocument();
        $document->loadXML($xml);
        $xpath = new DOMXPath($document);

        $uuidValue = (string) $xpath->evaluate(sprintf('string(%s)', $uuidXPath));

        self::assertNotSame('', $uuidValue, sprintf('UUID vazio no caminho %s', $uuidXPath));
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $uuidValue,
            sprintf('UUID inválido no caminho %s: %s', $uuidXPath, $uuidValue),
        );
    }
}
