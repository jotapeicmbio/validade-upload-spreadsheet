<?php



namespace Icmbio\SrcPhp\Tests\Integration;

use DOMDocument;
use DOMXPath;
use Icmbio\ValidateRegister\CalculateFields;
use Icmbio\ValidateRegister\CreateValidatorsStructure;
use Icmbio\ValidateRegister\PhotoAttachments;
use Icmbio\ValidateRegister\ValidateCollectionData;
use Icmbio\ValidateRegister\XformXmlBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompleteFlowTest extends TestCase
{
    #[Test]
    public function calcula_uuid_para_itens_do_repeat(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodes());
        $collectionData = CalculateFields::apply($this->collectionData(), $validators);

        self::assertArrayHasKey('coletor/uuid', $collectionData['coletor'][0]);
        self::assertArrayHasKey('coletor/uuid', $collectionData['coletor'][1]);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $collectionData['coletor'][0]['coletor/uuid'],
        );
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $collectionData['coletor'][1]['coletor/uuid'],
        );
    }

    #[Test]
    public function nao_reporta_erros_de_validacao_no_fluxo_integrado(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodes());
        $collectionData = CalculateFields::apply($this->collectionData(), $validators);

        self::assertSame([], ValidateCollectionData::createErrorsList($collectionData, $validators));
    }

    #[Test]
    public function valida_fotos_existentes_no_fluxo_completo(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodes());
        $collectionData = CalculateFields::apply($this->collectionData(), $validators);

        self::assertSame(
            [],
            PhotoAttachments::validatePhotos($collectionData, $validators, ['ana.jpg', 'bruno.jpg']),
        );
    }

    #[Test]
    public function gera_xml_com_estrutura_e_metadados_esperados(): void
    {
        $validators = CreateValidatorsStructure::build($this->xformNodes());
        $collectionData = CalculateFields::apply($this->collectionData(), $validators);
        $xml = XformXmlBuilder::build(
            $collectionData,
            array_keys($validators),
            'coleta',
            'coleta_id',
            '2026.1',
            null,
            '2026-04-01T12:00:00.000-03:00',
        );

        $xmlDocument = new DOMDocument();
        $xmlDocument->loadXML($xml);
        $xpath = new DOMXPath($xmlDocument);

        self::assertSame('coleta', $xmlDocument->documentElement?->nodeName);
        self::assertSame('coleta_id', $xpath->evaluate('string(/coleta/@id)'));
        self::assertSame('2026.1', $xpath->evaluate('string(/coleta/@version)'));
        self::assertSame('UC-001', $xpath->evaluate('string(/coleta/uc)'));
        self::assertSame('2', (string) $xpath->evaluate('count(/coleta/coletor)'));
        self::assertSame('Ana', $xpath->evaluate('string(/coleta/coletor[1]/nome)'));
        self::assertSame('Bruno', $xpath->evaluate('string(/coleta/coletor[2]/nome)'));
        self::assertSame('ana.jpg', $xpath->evaluate('string(/coleta/coletor[1]/foto)'));
        self::assertSame('bruno.jpg', $xpath->evaluate('string(/coleta/coletor[2]/foto)'));
        self::assertSame('uuid:None', $xpath->evaluate('string(/coleta/meta/instanceID)'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function xformNodes(): array
    {
        return [
            [
                'name' => 'uc',
                'type' => 'text',
                'bind' => [
                    'required' => 'yes',
                ],
            ],
            [
                'name' => 'coletor',
                'type' => 'repeat',
                'children' => [
                    [
                        'name' => 'uuid',
                        'type' => 'calculate',
                        'bind' => [
                            'calculate' => 'uuid()',
                        ],
                    ],
                    [
                        'name' => 'nome',
                        'type' => 'text',
                        'bind' => [
                            'required' => 'yes',
                            'constraint' => 'string-length(.) >= 3',
                            'jr:constraintMsg' => 'Nome invalido',
                        ],
                    ],
                    [
                        'name' => 'idade',
                        'type' => 'integer',
                        'bind' => [
                            'constraint' => '. >= 18',
                            'jr:constraintMsg' => 'Idade minima 18',
                        ],
                    ],
                    [
                        'name' => 'sexo',
                        'type' => 'select one',
                        'bind' => [
                            'required' => 'yes',
                            'jr:requiredMsg' => 'Sexo obrigatorio',
                        ],
                        'children' => [
                            ['name' => 'M', 'label' => 'Masculino'],
                            ['name' => 'F', 'label' => 'Feminino'],
                        ],
                    ],
                    [
                        'name' => 'foto',
                        'type' => 'photo',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectionData(): array
    {
        return [
            'uc' => 'UC-001',
            'coletor' => [
                [
                    'coletor/nome' => 'Ana',
                    'coletor/idade' => 22,
                    'coletor/sexo' => 'F',
                    'coletor/foto' => 'ana.jpg',
                ],
                [
                    'coletor/nome' => 'Bruno',
                    'coletor/idade' => 30,
                    'coletor/sexo' => 'M',
                    'coletor/foto' => 'bruno.jpg',
                ],
            ],
        ];
    }
}
