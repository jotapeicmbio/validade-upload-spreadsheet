<?php

declare(strict_types=1);

include "vendor/autoload.php";

use Icmbio\ValidateRegister\DataCollectionSpreadsheetConvertPipeline;
use Icmbio\ValidateRegister\DataCollectionSpreadsheetReviewPipeline;

use function Jotapegue\Phpxform\helpers\dd;

$host = 'localhost';
$port = '54003';
$database = 'db_dev_cotec';
$user = 'postgres';
$password = 'postgres';

$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $database);

$ucId = 167;
$protocolId = 441;
$planilha = "/home/icmbio/Downloads/planilhas_com_erros/upload_2024_cassuruba_vg_mng.xlsx";

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // echo sprintf(
    //     "Conexão OK: host=%s port=%d dbname=%s user=%s",
    //     $host,
    //     $port,
    //     $database,
    //     $user,
    // ) . PHP_EOL;

    $sql = <<<'SQL'
        SELECT json
        FROM monitora.xform_xform
        WHERE id = (
            SELECT xform_id
            FROM monitora.core_protocolo
            WHERE id = :protocol_id
        )
    SQL;

    $statement = $pdo->prepare($sql);
    $statement->execute(['protocol_id' => $protocolId]);

    $formDefinitionRaw = $statement->fetchColumn();
    if (! is_string($formDefinitionRaw) || trim($formDefinitionRaw) === '') {
        throw new RuntimeException('Form definition não encontrado para o protocolo informado.');
    }

    $formDefinition = json_decode($formDefinitionRaw, true, 512, JSON_THROW_ON_ERROR);

    // echo sprintf(
    //     "formDefinition carregado em variável: name=%s id=%s version=%s",
    //     (string) ($formDefinition['name'] ?? '-'),
    //     (string) ($formDefinition['id_string'] ?? '-'),
    //     (string) ($formDefinition['version'] ?? '-'),
    // ) . PHP_EOL;

    $ucStatement = $pdo->prepare(
        <<<'SQL'
            SELECT uca.id AS name, p.no_pessoa AS label
            FROM monitora.core_ucadesao uca
            JOIN corporativo.pessoa p
              ON p.sq_pessoa = uca.uc_id
            WHERE uca.id = :uc_id
        SQL
    );
    $ucStatement->execute(['uc_id' => $ucId]);
    $ucChoices = $ucStatement->fetchAll();

    $estacaoStatement = $pdo->prepare(
        <<<'SQL'
            SELECT ea.id AS name, ea.nome AS label, ea.uc_id as uc
            FROM monitora.core_estacaoamostral ea
            WHERE ea.uc_id = :uc_id
              AND EXISTS (
                  SELECT 1
                  FROM monitora.core_unidadeamostral ua
                  JOIN monitora.core_unidadeamostral_protocolos uap
                      ON uap.unidadeamostral_id = ua.id
                  WHERE ua.ea_id = ea.id
                    AND uap.protocolo_id = :protocolo_id
              )
        SQL
    );
    $estacaoStatement->execute([
        'uc_id' => $ucId,
        'protocolo_id' => $protocolId,
    ]);
    $estacaoChoices = $estacaoStatement->fetchAll();

    $unidadeStatement = $pdo->prepare(
        <<<'SQL'
            SELECT ua.id AS name, ua.nome AS label, ua.ea_id as estacao_amostral
            FROM monitora.core_unidadeamostral ua
            WHERE EXISTS (
                SELECT 1
                FROM monitora.core_unidadeamostral_protocolos uap
                WHERE uap.unidadeamostral_id = ua.id
                  AND uap.protocolo_id = :protocolo_id
            )
              AND EXISTS (
                SELECT 1
                FROM monitora.core_estacaoamostral ea
                WHERE ea.id = ua.ea_id
                  AND ea.uc_id = :uc_id
            )
        SQL
    );
    $unidadeStatement->execute([
        'protocolo_id' => $protocolId,
        'uc_id' => $ucId,
    ]);
    $unidadeChoices = $unidadeStatement->fetchAll();

    $dynamicChoices = [
        'uc' => $ucChoices,
        'estacao_amostral' => $estacaoChoices,
        'unidade_amostral' => $unidadeChoices,
    ];

    // echo sprintf(
    //     'dynamicChoices carregado: uc=%d estacao_amostral=%d unidade_amostral=%d',
    //     count($dynamicChoices['uc']),
    //     count($dynamicChoices['estacao_amostral']),
    //     count($dynamicChoices['unidade_amostral']),
    // ) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Falha ao conectar no Postgres: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}

// $formDefinition['children']['uc'] = $dynamicChoices['uc'];
// $formDefinition['children']['estacao_amostral'] = $dynamicChoices['estacao_amostral'];
// $formDefinition['children']['unidade_amostral'] = $dynamicChoices['unidade_amostral'];

$reviewPipeline = (new DataCollectionSpreadsheetReviewPipeline())
    ->setDataCollection($planilha)
    ->validateCollectionFromJson($formDefinition, $dynamicChoices)
    ->validateCollection();

if ($reviewPipeline->valid()) {
    echo "deu certo" . PHP_EOL;
} else  {
    foreach ($reviewPipeline->errors() as $error) {
        echo "index: " . $error['index'] . " campo: " . $error['key'] . " messagem: " . $error['message'] . PHP_EOL;
    }
    exit(1);
}

// Checkpoint A: dados estruturados após review.
$processed = $reviewPipeline->process();
$firstWithTroncos = findFirstItemWithPath($processed, 'individuos_registro/troncos_registro');
echo '[A] reviewPipeline->process(): ' . PHP_EOL;
echo '    - itens processados: ' . count($processed) . PHP_EOL;
echo '    - encontrou item com troncos_registro: ' . ($firstWithTroncos === null ? 'nao' : 'sim') . PHP_EOL;
if ($firstWithTroncos !== null) {
    $hasTroncosUuidInData = hasPathValue($firstWithTroncos, 'individuos_registro/troncos_registro/troncos_uuid');
    echo '    - troncos_uuid presente nos dados estruturados: ' . ($hasTroncosUuidInData ? 'sim' : 'nao') . PHP_EOL;
}

// Checkpoint B: formDefinition no convert.
$hasFormChildren = isset($formDefinition['children']) && is_array($formDefinition['children']) && $formDefinition['children'] !== [];
$hasTroncosUuidInDefinition = findNodeByPath($formDefinition['children'] ?? [], 'individuos_registro/troncos_registro/troncos_uuid') !== null;
echo '[B] formDefinition no fluxo de conversao:' . PHP_EOL;
echo '    - possui children: ' . ($hasFormChildren ? 'sim' : 'nao') . PHP_EOL;
echo '    - possui nodo troncos_uuid: ' . ($hasTroncosUuidInDefinition ? 'sim' : 'nao') . PHP_EOL;

$xmlFiles = (new DataCollectionSpreadsheetConvertPipeline())
    ->collection($reviewPipeline)
    ->instanceInfo('MinhaInstancia', '2026.1')
    ->generate();

echo '[C] convertPipeline->generate():' . PHP_EOL;
echo '    - arquivos gerados: ' . count($xmlFiles) . PHP_EOL;

// Checkpoint D: XML final.
if ($xmlFiles !== []) {
    $firstXml = (string) file_get_contents($xmlFiles[0]);
    $dom = new DOMDocument();
    $dom->loadXML($firstXml);
    $xpath = new DOMXPath($dom);
    $troncosUuidCount = (int) $xpath->evaluate('count(//individuos_registro/troncos_registro/troncos_uuid)');
    echo '[D] XML final:' . PHP_EOL;
    echo '    - troncos_uuid em //individuos_registro/troncos_registro: ' . $troncosUuidCount . PHP_EOL;
}

function findFirstItemWithPath(array $items, string $path): ?array
{
    foreach ($items as $item) {
        if (! is_array($item)) {
            continue;
        }

        if (hasPathValue($item, $path)) {
            return $item;
        }
    }

    return null;
}

function hasPathValue(array $data, string $path): bool
{
    $segments = explode('/', $path);
    $current = $data;

    foreach ($segments as $segment) {
        if (! is_array($current)) {
            return false;
        }

        if (array_is_list($current)) {
            foreach ($current as $row) {
                if (is_array($row) && hasPathValue($row, implode('/', array_slice($segments, array_search($segment, $segments, true))))) {
                    return true;
                }
            }
            return false;
        }

        if (! array_key_exists($segment, $current)) {
            $prefixedKey = null;
            foreach (array_keys($current) as $key) {
                if (is_string($key) && str_ends_with($key, '/' . $segment)) {
                    $prefixedKey = $key;
                    break;
                }
            }

            if ($prefixedKey === null) {
                return false;
            }

            $current = $current[$prefixedKey];
            continue;
        }

        $current = $current[$segment];
    }

    return true;
}

function findNodeByPath(array $nodes, string $targetPath, string $prefix = ''): ?array
{
    foreach ($nodes as $node) {
        if (! is_array($node) || ! isset($node['name']) || ! is_string($node['name'])) {
            continue;
        }

        $path = $prefix === '' ? $node['name'] : $prefix . '/' . $node['name'];
        if ($path === $targetPath) {
            return $node;
        }

        if (isset($node['children']) && is_array($node['children'])) {
            $found = findNodeByPath($node['children'], $targetPath, $path);
            if ($found !== null) {
                return $found;
            }
        }
    }

    return null;
}
