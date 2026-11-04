<?php
declare(strict_types=1);

require_once __DIR__ . '/src/PostModel.php';
require_once __DIR__ . '/src/UserModel.php';
require_once __DIR__ . '/src/ConversationModel.php';

use CampusMarketplace\Domain\AuthenticationError;
use CampusMarketplace\Domain\ConflictError;
use CampusMarketplace\Domain\NotFoundError;
use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Model\authenticateUser;
use function CampusMarketplace\Model\createPost;
use function CampusMarketplace\Model\findAccount;
use function CampusMarketplace\Model\getConversation;
use function CampusMarketplace\Model\getPublicProfile;
use function CampusMarketplace\Model\isKnownResource;
use function CampusMarketplace\Model\listConversations;
use function CampusMarketplace\Model\listPosts;
use function CampusMarketplace\Model\registerUser;
use function CampusMarketplace\Model\removePost;
use function CampusMarketplace\Model\respondToPost;
use function CampusMarketplace\Model\sellListing;
use function CampusMarketplace\Model\sendMessage;
use function CampusMarketplace\Model\updateMeetingLocations;
use function CampusMarketplace\Model\updatePost;
use function CampusMarketplace\Model\updateProfile;

// Integration tests point this at a temporary copy so they never change real data.
define('DATA_FILE', getenv('MARKETPLACE_DATA_FILE') ?: __DIR__ . '/data/store.json');

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

function signedInUserId(): string
{
    $userId = (string) ($_SESSION['user_id'] ?? '');
    if ($userId === '') {
        throw new AuthenticationError('Sign in to continue.');
    }

    return $userId;
}

function startSession(array $account): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $account['id'];
}

function routeAuth(string $method, string $action): never
{
    if ($method === 'GET') {
        $userId = (string) ($_SESSION['user_id'] ?? '');
        respond(200, ['data' => $userId === '' ? null : findAccount(DATA_FILE, $userId)]);
    }
    if ($method !== 'POST') {
        respond(405, ['error' => 'Method not allowed.']);
    }
    if ($action === 'register') {
        $account = registerUser(DATA_FILE, requestBody());
        startSession($account);
        respond(201, ['data' => $account]);
    }
    if ($action === 'login') {
        $account = authenticateUser(DATA_FILE, requestBody());
        startSession($account);
        respond(200, ['data' => $account]);
    }
    if ($action === 'logout') {
        $_SESSION = [];
        session_destroy();
        respond(200, ['data' => null]);
    }
    respond(404, ['error' => 'Unknown action.']);
}

function routeProfile(string $method, string $userId): never
{
    if ($method === 'GET') {
        respond(200, ['data' => getPublicProfile(DATA_FILE, $userId)]);
    }
    if ($method === 'PUT') {
        respond(200, ['data' => updateProfile(DATA_FILE, signedInUserId(), requestBody())]);
    }
    respond(405, ['error' => 'Method not allowed.']);
}

function routeConversations(string $method, string $conversationId, string $action): never
{
    $userId = signedInUserId();
    if ($method === 'GET' && $conversationId === '') {
        respond(200, ['data' => listConversations(DATA_FILE, $userId)]);
    }
    if ($method === 'GET') {
        respond(200, ['data' => getConversation(DATA_FILE, $userId, $conversationId)]);
    }
    if ($method === 'POST' && $conversationId === '') {
        respond(201, ['data' => respondToPost(DATA_FILE, $userId, requestBody())]);
    }
    if ($method === 'POST' && $action === 'message') {
        respond(201, ['data' => sendMessage(DATA_FILE, $userId, $conversationId, requestBody())]);
    }
    if ($method === 'PUT' && $conversationId !== '') {
        respond(200, ['data' => updateMeetingLocations(DATA_FILE, $userId, $conversationId, requestBody())]);
    }
    respond(405, ['error' => 'Method not allowed.']);
}

function routePosts(string $method, string $resource, string $postId, string $action): never
{
    if ($method === 'GET') {
        respond(200, ['data' => listPosts(DATA_FILE, $resource)]);
    }
    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        respond(405, ['error' => 'Method not allowed.']);
    }

    $userId = signedInUserId();
    if ($method === 'POST' && $postId !== '' && $resource === 'listings' && $action === 'sell') {
        respond(200, ['data' => sellListing(DATA_FILE, $userId, $postId)]);
    }
    if ($method === 'POST') {
        respond(201, ['data' => createPost(DATA_FILE, $resource, $userId, requestBody())]);
    }
    if ($postId === '') {
        respond(400, ['error' => 'A post ID is required.']);
    }
    if ($method === 'PUT') {
        respond(200, ['data' => updatePost(DATA_FILE, $resource, $userId, $postId, requestBody())]);
    }
    respond(200, ['data' => removePost(DATA_FILE, $resource, $userId, $postId)]);
}

function routeRequest(string $method, string $resource, string $recordId, string $action): never
{
    if ($resource === 'auth') {
        routeAuth($method, $action);
    }
    if ($resource === 'profile') {
        routeProfile($method, $recordId);
    }
    if ($resource === 'conversations') {
        routeConversations($method, $recordId, $action);
    }
    if (isKnownResource($resource)) {
        routePosts($method, $resource, $recordId, $action);
    }
    respond(404, ['error' => 'Unknown resource.']);
}

session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Strict']);
session_start();

try {
    routeRequest(
        $_SERVER['REQUEST_METHOD'] ?? 'GET',
        (string) ($_GET['resource'] ?? ''),
        trim((string) ($_GET['id'] ?? '')),
        trim((string) ($_GET['action'] ?? ''))
    );
} catch (ValidationError $error) {
    respond(422, ['error' => $error->getMessage(), 'field' => $error->field]);
} catch (AuthenticationError $error) {
    respond(401, ['error' => $error->getMessage()]);
} catch (OwnershipError $error) {
    respond(403, ['error' => $error->getMessage()]);
} catch (NotFoundError $error) {
    respond(404, ['error' => $error->getMessage()]);
} catch (ConflictError $error) {
    respond(409, ['error' => $error->getMessage()]);
} catch (\RuntimeException $error) {
    respond(500, ['error' => $error->getMessage()]);
}
