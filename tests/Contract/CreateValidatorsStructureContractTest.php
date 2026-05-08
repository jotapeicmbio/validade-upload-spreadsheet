<?php



namespace Icmbio\SrcPhp\Tests\Contract;

use Icmbio\ValidateRegister\CreateValidatorsStructure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CreateValidatorsStructureContractTest extends TestCase
{
    #[Test]
    public function aplica_prefixo_e_configuracoes_padrao_em_campo_simples_obrigatorio(): void
    {
        [$input, $expected, $prefix] = $this->loadCase('case_01_simple_required');

        self::assertSame($expected, $this->buildValidators($input, $prefix));
    }

    #[Test]
    public function extrai_choices_estaticas_de_select_one(): void
    {
        [$input, $expected] = $this->loadCase('case_02_select_static');

        self::assertSame($expected, $this->buildValidators($input));
    }

    #[Test]
    public function inclui_validadores_de_grupo_e_de_campo_filho(): void
    {
        [$input, $expected] = $this->loadCase('case_03_group_with_child');

        self::assertSame($expected, $this->buildValidators($input));
    }

    #[Test]
    public function constroi_validadores_a_partir_de_lista_na_raiz(): void
    {
        [$input, $expected] = $this->loadCase('case_04_list_root');

        self::assertSame($expected, $this->buildValidators($input));
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $input
     * @return array<string, array<string, mixed>>
     */
    private function buildValidators(array $input, ?string $prefix = null): array
    {
        return CreateValidatorsStructure::build($input, [
            'uc' => 'generate_choices_uc',
            'estacao_amostral' => 'generate_choices_ea',
            'unidade_amostral' => 'generate_choices_ua',
            'taxon_lista' => 'generate_choices_taxon',
        ], $prefix);
    }

    /**
     * @return array{0: array<mixed>, 1: array<mixed>, 2: ?string}
     */
    private function loadCase(string $caseName): array
    {
        $casePath = __DIR__ . '/../Fixtures/create_validators_structure/' . $caseName;
        $input = json_decode((string) file_get_contents($casePath . '/input.json'), true, 512, JSON_THROW_ON_ERROR);
        $expected = json_decode((string) file_get_contents($casePath . '/expected.json'), true, 512, JSON_THROW_ON_ERROR);
        $metaPath = $casePath . '/meta.json';
        $prefix = null;

        if (is_file($metaPath)) {
            $meta = json_decode((string) file_get_contents($metaPath), true, 512, JSON_THROW_ON_ERROR);
            $prefix = $meta['prefix'] ?? null;
        }

        return [$input, $expected, $prefix];
    }
}
