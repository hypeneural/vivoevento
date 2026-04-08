<?php

declare(strict_types=1);

use Aws\Exception\AwsException;
use Aws\Rekognition\RekognitionClient;
use Aws\Sts\StsClient;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

/**
 * @return string|null
 */
function envValue(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) && $value !== '' ? $value : null;
}

$accessKey = envValue('FACE_SEARCH_AWS_SMOKE_ACCESS_KEY_ID') ?? envValue('AWS_ACCESS_KEY_ID');
$secretKey = envValue('FACE_SEARCH_AWS_SMOKE_SECRET_ACCESS_KEY') ?? envValue('AWS_SECRET_ACCESS_KEY');
$region = envValue('FACE_SEARCH_AWS_SMOKE_REGION')
    ?? envValue('AWS_REGION')
    ?? envValue('AWS_DEFAULT_REGION')
    ?: 'eu-central-1';

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
}

exit(0);
