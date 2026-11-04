<?php
declare(strict_types=1);

require_once __DIR__ . '/src/PostModel.php';

use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\PostNotFoundError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Model\createPost;
use function CampusMarketplace\Model\isKnownResource;
use function CampusMarketplace\Model\listPosts;
use function CampusMarketplace\Model\removePost;
use function CampusMarketplace\Model\sellListing;
use function CampusMarketplace\Model\updatePost;

const DATA_FILE = __DIR__ . '/data/store.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function requestBody(): array
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody ?: '{}', true);
    if (!is_array($data)) {
        respond(400, ['error' => 'Request body must be valid JSON.']);
    }

    return $data;
}

function routeRequest(string $method, string $resource, string $postId, string $action): never
{
    if ($method === 'GET') {
        respond(200, ['data' => listPosts(DATA_FILE, $resource)]);
    }
    if ($method === 'POST' && $postId !== '' && $resource === 'listings' && $action === 'sell') {
        respond(200, ['data' => sellListing(DATA_FILE, $postId)]);
    }
    if ($method === 'POST') {
        respond(201, ['data' => createPost(DATA_FILE, $resource, requestBody())]);
    }
    if ($method !== 'PUT' && $method !== 'DELETE') {
        respond(405, ['error' => 'Method not allowed.']);
    }
    if ($postId === '') {
        respond(400, ['error' => 'A post ID is required.']);
    }
    if ($method === 'PUT') {
        respond(200, ['data' => updatePost(DATA_FILE, $resource, $postId, requestBody())]);
    }
    respond(200, ['data' => removePost(DATA_FILE, $resource, $postId)]);
}

$resource = (string) ($_GET['resource'] ?? '');
if (!isKnownResource($resource)) {
    respond(404, ['error' => 'Unknown resource.']);
}

try {
    routeRequest(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        $resource,
        trim((string) ($_GET['id'] ?? '')),
        trim((string) ($_GET['action'] ?? ''))
    );
} catch (ValidationError $error) {
    respond(422, ['error' => $error->getMessage(), 'field' => $error->field]);
} catch (OwnershipError $error) {
    respond(403, ['error' => $error->getMessage()]);
} catch (PostNotFoundError $error) {
    respond(404, ['error' => $error->getMessage()]);
} catch (\RuntimeException $error) {
    respond(500, ['error' => $error->getMessage()]);
}
