<?php
declare(strict_types=1);

require_once __DIR__ . '/src/Domain.php';
require_once __DIR__ . '/src/Store.php';

use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\assertPostOwned;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\validateHelpPost;
use function CampusMarketplace\Domain\validateListing;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

const DATA_FILE = __DIR__ . '/data/store.json';
const CURRENT_OWNER = 'You';

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

function validatedRecord(string $resource, array $data, ?array $existingPost = null): array
{
    try {
        return $resource === 'listings'
            ? validateListing($data, $existingPost)
            : validateHelpPost($data, $existingPost);
    } catch (ValidationError $error) {
        respond(422, ['error' => $error->getMessage(), 'field' => $error->field]);
    }
}

function persistStore(array $store): void
{
    try {
        writeStore(DATA_FILE, $store);
    } catch (\RuntimeException $error) {
        respond(500, ['error' => $error->getMessage()]);
    }
}

function requireOwnedPost(array $post): void
{
    try {
        assertPostOwned($post, CURRENT_OWNER);
    } catch (OwnershipError $error) {
        respond(403, ['error' => $error->getMessage()]);
    }
}

$resource = $_GET['resource'] ?? '';
$collection = match ($resource) {
    'listings' => 'listings',
    'help' => 'help_posts',
    default => null,
};
if ($collection === null) {
    respond(404, ['error' => 'Unknown resource.']);
}

try {
    $store = readStore(DATA_FILE);
} catch (\RuntimeException $error) {
    respond(500, ['error' => $error->getMessage()]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    respond(200, ['data' => $store[$collection]]);
}

$postId = trim((string) ($_GET['id'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));

if ($method === 'POST' && $postId !== '' && $resource === 'listings' && $action === 'sell') {
    $postIndex = findPostIndex($store[$collection], $postId);
    if ($postIndex < 0) {
        respond(404, ['error' => 'Listing not found.']);
    }
    requireOwnedPost($store[$collection][$postIndex]);
    $store[$collection][$postIndex]['status'] = 'sold';
    $store[$collection][$postIndex]['updated_at'] = gmdate('c');
    persistStore($store);
    respond(200, ['data' => $store[$collection][$postIndex]]);
}

if ($method === 'POST') {
    $record = validatedRecord($resource, requestBody());
    $record = array_merge([
        'id' => ($resource === 'listings' ? 'lst_' : 'hlp_') . bin2hex(random_bytes(5)),
    ], $record, ['created_at' => gmdate('c')]);
    array_unshift($store[$collection], $record);
    persistStore($store);
    respond(201, ['data' => $record]);
}

if ($postId === '') {
    respond(400, ['error' => 'A post ID is required.']);
}

$postIndex = findPostIndex($store[$collection], $postId);
if ($postIndex < 0) {
    respond(404, ['error' => 'Post not found.']);
}
requireOwnedPost($store[$collection][$postIndex]);

if ($method === 'PUT') {
    $existingPost = $store[$collection][$postIndex];
    $validatedPost = validatedRecord($resource, requestBody(), $existingPost);
    $store[$collection][$postIndex] = array_merge($existingPost, $validatedPost, ['updated_at' => gmdate('c')]);
    persistStore($store);
    respond(200, ['data' => $store[$collection][$postIndex]]);
}

if ($method === 'DELETE') {
    $deletedPost = $store[$collection][$postIndex];
    array_splice($store[$collection], $postIndex, 1);
    persistStore($store);
    respond(200, ['data' => $deletedPost]);
}

respond(405, ['error' => 'Method not allowed.']);
