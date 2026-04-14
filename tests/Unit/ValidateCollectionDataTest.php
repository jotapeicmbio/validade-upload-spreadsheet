<?php

declare (strict_types=1);

namespace Icmbio\SrcPhp\Tests\Unit;

use Icmbio\ValidateRegister\ValidateCollectionData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidateCollectionDataTest extends TestCase
{
    #[Test]
    public function retorna_erro_quando_constraint_e_falsa(): void
    {
        $validators = [
            'coletor/nome' => [
                'type'               => 'text',
                'relevant'           => 'true()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'string-length(.) >= 3',
                'constraint_message' => 'Nome muito curto',
                'choices'            => null,
            ],
        ];

        $data = ['coletor/nome' => 'Al'];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertSame('coletor/nome', $errors[0]['key']);
        self::assertSame('Nome muito curto', $errors[0]['message']);
    }

    #[Test]
    public function retorna_erro_quando_required_e_true_e_valor_e_vazio(): void
    {
        $validators = [
            'coletor/nome' => [
                'type'               => 'text',
                'relevant'           => 'true()',
                'required'           => 'true()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
        ];

        $data = ['coletor/nome' => ''];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertSame('Campo obrigatorio', $errors[0]['message']);
    }

    #[Test]
    public function pula_validacao_quando_relevant_e_falso(): void
    {
        $validators = [
            'coletor/nome' => [
                'type'               => 'text',
                'relevant'           => 'false()',
                'required'           => 'true()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'string-length(.) >= 20',
                'constraint_message' => 'Nome muito curto',
                'choices'            => null,
            ],
        ];

        $data = ['coletor/nome' => 'A'];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertSame([], $errors);
    }

    #[Test]
    public function retorna_erro_quando_escolha_select_one_e_invalida(): void
    {
        $validators = [
            'coletor/sexo' => [
                'type'               => 'select one',
                'relevant'           => 'true()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => ['M', 'F'],
            ],
        ];

        $data = ['coletor/sexo' => 'X'];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertStringContainsString('nao e uma das escolhas validas', $errors[0]['message']);
    }

    #[Test]
    public function retorna_erro_quando_campo_inteiro_tem_valor_nao_inteiro(): void
    {
        $validators = [
            'coletor/idade' => [
                'type'               => 'integer',
                'relevant'           => 'true()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
        ];

        $data = ['coletor/idade' => 'dez'];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertSame('E esperado um valor inteiro.', $errors[0]['message']);
    }

    #[Test]
    public function retorna_erro_quando_avaliador_de_expressao_lanca_excecao(): void
    {
        $validators = [
            'coletor/nome' => [
                'type'               => 'text',
                'relevant'           => 'unsupported_expression()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
        ];

        $data = ['coletor/nome' => 'Ana'];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertStringContainsString('Erro ao validar a expressao', $errors[0]['message']);
    }

    #[Test]
    public function valida_filhos_repetidos_aninhados_quando_grupo_e_relevante(): void
    {
        $validators = [
            'coletor'       => [
                'type'               => 'repeat',
                'relevant'           => 'true()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
            'coletor/nome'  => [
                'type'               => 'text',
                'relevant'           => 'true()',
                'required'           => 'true()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'string-length(.) >= 3',
                'constraint_message' => 'Nome muito curto',
                'choices'            => null,
            ],
            'coletor/idade' => [
                'type'               => 'integer',
                'relevant'           => 'true()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => '. >= 18',
                'constraint_message' => 'Idade minima 18',
                'choices'            => null,
            ],
        ];

        $data = [
            'coletor' => [
                [
                    'coletor/nome'  => 'Al',
                    'coletor/idade' => 'dez',
                ],
                [
                    'coletor/nome'  => '',
                    'coletor/idade' => 17,
                ],
            ],
        ];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(5, $errors);
        self::assertSame('coletor/nome', $errors[0]['key']);
        self::assertSame('Nome muito curto', $errors[0]['message']);
        self::assertSame('coletor/idade', $errors[1]['key']);
        self::assertSame('E esperado um valor inteiro.', $errors[1]['message']);
        self::assertSame('coletor/idade', $errors[2]['key']);
        self::assertSame('Idade minima 18', $errors[2]['message']);
        self::assertSame('coletor/nome', $errors[3]['key']);
        self::assertSame('Campo obrigatorio', $errors[3]['message']);
        self::assertSame('coletor/idade', $errors[4]['key']);
        self::assertSame('Idade minima 18', $errors[4]['message']);
    }

    #[Test]
    public function retorna_erro_quando_grupo_repeat_nao_e_relevante_e_tem_valores(): void
    {
        $validators = [
            'coletor'      => [
                'type'               => 'repeat',
                'relevant'           => 'false()',
                'required'           => 'false()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
            'coletor/nome' => [
                'type'               => 'text',
                'relevant'           => 'true()',
                'required'           => 'true()',
                'required_message'   => 'Campo obrigatorio',
                'constraint'         => 'true()',
                'constraint_message' => 'Erro',
                'choices'            => null,
            ],
        ];

        $data = [
            'coletor' => [
                ['coletor/nome' => 'Ana'],
            ],
        ];

        $errors = ValidateCollectionData::createErrorsList($data, $validators);

        self::assertCount(1, $errors);
        self::assertSame('coletor', $errors[0]['key']);
        self::assertStringContainsString('Nao e permitido entrar com valores para este grupo', $errors[0]['message']);
    }
}
