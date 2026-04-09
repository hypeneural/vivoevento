<?php

declare(strict_types=1);

use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;
use App\Modules\FaceSearch\Services\AwsImagePreprocessor;
use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

/** @var \Illuminate\Foundation\Application $app */
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

/**
 * @return string|null
 */
function envValue(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * @return string|null
 */
function optionValue(string $name): ?string
{
    global $argv;

    foreach ((array) $argv as $argument) {
        if (! is_string($argument)) {
            continue;
        }

        if (str_starts_with($argument, "--{$name}=")) {
            $value = substr($argument, strlen($name) + 3);

            return $value !== '' ? $value : null;
        }
    }

    return null;
}

/**
 * @return array{binary:string,size_bytes:int,width:int,height:int,mime_type:string,used_derivative:bool}
 */
function loadPreparedImage(string $path): array
{
    if (! is_file($path)) {
        throw new RuntimeException("Arquivo de imagem nao encontrado: {$path}");
    }

    $binary = file_get_contents($path);

    if (! is_string($binary) || $binary === '') {
        throw new RuntimeException("Nao foi possivel ler a imagem: {$path}");
    }

    return (new AwsImagePreprocessor())->prepare($binary);
}

$accessKey = envValue('FACE_SEARCH_AWS_SMOKE_ACCESS_KEY_ID') ?? envValue('AWS_ACCESS_KEY_ID');
$secretKey = envValue('FACE_SEARCH_AWS_SMOKE_SECRET_ACCESS_KEY') ?? envValue('AWS_SECRET_ACCESS_KEY');
$region = envValue('FACE_SEARCH_AWS_SMOKE_REGION')
    ?? envValue('AWS_REGION')
    ?? envValue('AWS_DEFAULT_REGION')
    ?: 'eu-central-1';
$indexImagePath = optionValue('index-image') ?? envValue('FACE_SEARCH_AWS_SMOKE_INDEX_IMAGE');
$queryImagePath = optionValue('query-image') ?? envValue('FACE_SEARCH_AWS_SMOKE_QUERY_IMAGE');

if (! is_string($accessKey) || $accessKey === '' || ! is_string($secretKey) || $secretKey === '') {
    fwrite(STDERR, "Credenciais AWS de smoke ausentes no ambiente.\n");
    exit(10);
}

$baseConfig = [
    'region' => $region,
    'credentials' => [
        'key' => $accessKey,
        'secret' => $secretKey,
    ],
    'retries' => [
        'mode' => 'standard',
        'max_attempts' => 3,
    ],
    'http' => [
        'connect_timeout' => 3,
        'timeout' => 10,
    ],
];

echo "== Teste 1: STS GetCallerIdentity ==\n";

try {
    $sts = new StsClient([
        ...$baseConfig,
        'version' => '2011-06-15',
    ]);

    $identity = $sts->getCallerIdentity();

    echo "OK\n";
    echo 'Account: ' . ($identity['Account'] ?? 'n/a') . PHP_EOL;
    echo 'Arn: ' . ($identity['Arn'] ?? 'n/a') . PHP_EOL;
    echo 'UserId: ' . ($identity['UserId'] ?? 'n/a') . PHP_EOL;
} catch (AwsException $exception) {
    echo "FALHOU STS\n";
    echo ($exception->getAwsErrorCode() ?: 'unknown') . ' - ' . $exception->getAwsErrorMessage() . PHP_EOL;
    exit(1);
}

echo PHP_EOL . "== Teste 2: Rekognition CreateCollection/DescribeCollection/DeleteCollection ==\n";

$collectionId = 'eventovivo-smoke-' . date('YmdHis');
$collectionCreated = false;
$rekognition = null;

try {
    $rekognition = new RekognitionClient([
        ...$baseConfig,
        'version' => '2016-06-27',
    ]);

    $create = $rekognition->createCollection([
        'CollectionId' => $collectionId,
    ]);

    $collectionCreated = true;

    echo "Collection criada: {$collectionId}\n";
    echo 'StatusCode: ' . ($create['StatusCode'] ?? 'n/a') . PHP_EOL;

    $describe = $rekognition->describeCollection([
        'CollectionId' => $collectionId,
    ]);

    echo 'FaceModelVersion: ' . ($describe['FaceModelVersion'] ?? 'n/a') . PHP_EOL;
    echo 'FaceCount: ' . ($describe['FaceCount'] ?? '0') . PHP_EOL;

    $collections = $rekognition->listCollections([
        'MaxResults' => 10,
    ]);

    echo 'ListCollections OK: ' . count($collections['CollectionIds'] ?? []) . " collections retornadas\n";

    $faces = $rekognition->listFaces([
        'CollectionId' => $collectionId,
        'MaxResults' => 5,
    ]);

    echo 'ListFaces OK: ' . count($faces['Faces'] ?? []) . " faces retornadas\n";

    if (is_string($indexImagePath) && $indexImagePath !== '' && is_string($queryImagePath) && $queryImagePath !== '') {
        echo PHP_EOL . "== Teste 3: Rekognition IndexFaces/SearchFacesByImage/DeleteFaces ==\n";

        $indexImage = loadPreparedImage($indexImagePath);
        $queryImage = loadPreparedImage($queryImagePath);

        echo 'Imagem de indexacao: ' . $indexImagePath . PHP_EOL;
        echo 'Index bytes preparados: ' . $indexImage['size_bytes'] . ' bytes (' . $indexImage['width'] . 'x' . $indexImage['height'] . ')' . PHP_EOL;
        echo 'Imagem de busca: ' . $queryImagePath . PHP_EOL;
        echo 'Query bytes preparados: ' . $queryImage['size_bytes'] . ' bytes (' . $queryImage['width'] . 'x' . $queryImage['height'] . ')' . PHP_EOL;

        $indexResponse = $rekognition->indexFaces([
            'CollectionId' => $collectionId,
            'Image' => [
                'Bytes' => $indexImage['binary'],
            ],
            'ExternalImageId' => 'smoke:' . pathinfo($indexImagePath, PATHINFO_FILENAME),
            'QualityFilter' => 'AUTO',
            'MaxFaces' => 10,
            'DetectionAttributes' => ['DEFAULT', 'FACE_OCCLUDED'],
        ]);

        $faceRecords = (array) ($indexResponse['FaceRecords'] ?? []);
        $indexedFaceIds = array_values(array_filter(array_map(
            static fn (mixed $record): ?string => is_array($record)
                ? (is_string($record['Face']['FaceId'] ?? null) ? $record['Face']['FaceId'] : null)
                : null,
            $faceRecords,
        )));

        echo 'IndexFaces OK: ' . count($faceRecords) . " faces indexadas\n";
        echo 'UnindexedFaces: ' . count((array) ($indexResponse['UnindexedFaces'] ?? [])) . PHP_EOL;

        if ($indexedFaceIds === []) {
            throw new RuntimeException('IndexFaces nao retornou nenhuma face indexada para o smoke real.');
        }

        $searchResponse = $rekognition->searchFacesByImage([
            'CollectionId' => $collectionId,
            'Image' => [
                'Bytes' => $queryImage['binary'],
            ],
            'FaceMatchThreshold' => 80,
            'MaxFaces' => 5,
            'QualityFilter' => 'NONE',
        ]);

        $faceMatches = (array) ($searchResponse['FaceMatches'] ?? []);
        echo 'SearchFacesByImage OK: ' . count($faceMatches) . " matches retornados\n";

        if ($faceMatches === []) {
            throw new RuntimeException('SearchFacesByImage nao retornou match para o par de imagens fornecido.');
        }

        $topMatch = (array) ($faceMatches[0] ?? []);
        $topFaceId = is_array($topMatch['Face'] ?? null) && is_string($topMatch['Face']['FaceId'] ?? null)
            ? $topMatch['Face']['FaceId']
            : null;
        $topSimilarity = isset($topMatch['Similarity']) ? (float) $topMatch['Similarity'] : null;

        echo 'Top match FaceId: ' . ($topFaceId ?? 'n/a') . PHP_EOL;
        echo 'Top match Similarity: ' . ($topSimilarity !== null ? number_format($topSimilarity, 2) : 'n/a') . PHP_EOL;

        if ($topFaceId === null || ! in_array($topFaceId, $indexedFaceIds, true)) {
            throw new RuntimeException('O top match retornado nao corresponde a uma face indexada neste smoke.');
        }

        $deleteFaces = $rekognition->deleteFaces([
            'CollectionId' => $collectionId,
            'FaceIds' => $indexedFaceIds,
        ]);

        echo 'DeleteFaces OK: ' . count((array) ($deleteFaces['DeletedFaces'] ?? [])) . " faces removidas\n";
    }

    $rekognition->deleteCollection([
        'CollectionId' => $collectionId,
    ]);

    $collectionCreated = false;

    echo "Collection removida com sucesso.\n";
} catch (AwsException $exception) {
    echo "FALHOU REKOGNITION\n";
    echo ($exception->getAwsErrorCode() ?: 'unknown') . ' - ' . $exception->getAwsErrorMessage() . PHP_EOL;

    if ($collectionCreated ?? false) {
        try {
            $rekognition->deleteCollection([
                'CollectionId' => $collectionId,
            ]);
            echo "Collection temporaria removida no cleanup.\n";
        } catch (Throwable $cleanupException) {
            echo "Falha no cleanup da collection temporaria: " . $cleanupException->getMessage() . PHP_EOL;
        }
    }

    exit(2);
} catch (Throwable $exception) {
    echo "FALHOU SMOKE DE IMAGEM\n";
    echo $exception::class . ' - ' . $exception->getMessage() . PHP_EOL;

    if ($collectionCreated ?? false) {
        try {
            $rekognition?->deleteCollection([
                'CollectionId' => $collectionId,
            ]);
            echo "Collection temporaria removida no cleanup.\n";
        } catch (Throwable $cleanupException) {
            echo "Falha no cleanup da collection temporaria: " . $cleanupException->getMessage() . PHP_EOL;
        }
    }

    exit(3);
}

exit(0);
