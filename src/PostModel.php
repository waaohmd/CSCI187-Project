<?php
declare(strict_types=1);

namespace CampusMarketplace\Model;

require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Store.php';

use CampusMarketplace\Domain\NotFoundError;
use function CampusMarketplace\Domain\assertPostOwned;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\findRecord;
use function CampusMarketplace\Domain\publicUser;
use function CampusMarketplace\Domain\validateHelpPost;
use function CampusMarketplace\Domain\validateListing;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

const RESOURCE_COLLECTIONS = ['listings' => 'listings', 'help' => 'help_posts'];
const RESOURCE_ID_PREFIXES = ['listings' => 'lst_', 'help' => 'hlp_'];

function isKnownResource(string $resource): bool
{
    return array_key_exists($resource, RESOURCE_COLLECTIONS);
}

function validatedPost(string $resource, array $data, ?array $existingPost = null): array
{
    return $resource === 'listings'
        ? validateListing($data, $existingPost)
        : validateHelpPost($data, $existingPost);
}

function ownedPostIndex(array $posts, string $postId, string $userId, string $notFoundMessage): int
{
    $postIndex = findPostIndex($posts, $postId);
    if ($postIndex < 0) {
        throw new NotFoundError($notFoundMessage);
    }
    assertPostOwned($posts[$postIndex], $userId);

    return $postIndex;
}

function postWithOwner(array $post, array $users): array
{
    $owner = findRecord($users, $post['owner_id'] ?? '');

    return [...$post, 'owner' => $owner === null ? null : publicUser($owner)];
}

function listPosts(string $dataFile, string $resource): array
{
    $store = readStore($dataFile);

    return array_map(
        fn (array $post) => postWithOwner($post, $store['users']),
        $store[RESOURCE_COLLECTIONS[$resource]]
    );
}

function createPost(string $dataFile, string $resource, string $userId, array $data): array
{
    $store = readStore($dataFile);
    $post = array_merge(
        ['id' => RESOURCE_ID_PREFIXES[$resource] . bin2hex(random_bytes(5)), 'owner_id' => $userId],
        validatedPost($resource, $data),
        ['created_at' => gmdate('c')]
    );
    array_unshift($store[RESOURCE_COLLECTIONS[$resource]], $post);
    writeStore($dataFile, $store);

    return postWithOwner($post, $store['users']);
}

function updatePost(string $dataFile, string $resource, string $userId, string $postId, array $data): array
{
    $store = readStore($dataFile);
    $collection = RESOURCE_COLLECTIONS[$resource];
    $postIndex = ownedPostIndex($store[$collection], $postId, $userId, 'Post not found.');
    $existingPost = $store[$collection][$postIndex];
    $store[$collection][$postIndex] = array_merge(
        $existingPost,
        validatedPost($resource, $data, $existingPost),
        ['updated_at' => gmdate('c')]
    );
    writeStore($dataFile, $store);

    return postWithOwner($store[$collection][$postIndex], $store['users']);
}

function sellListing(string $dataFile, string $userId, string $listingId): array
{
    $store = readStore($dataFile);
    $listingIndex = ownedPostIndex($store['listings'], $listingId, $userId, 'Listing not found.');
    $store['listings'][$listingIndex]['status'] = 'sold';
    $store['listings'][$listingIndex]['updated_at'] = gmdate('c');
    writeStore($dataFile, $store);

    return postWithOwner($store['listings'][$listingIndex], $store['users']);
}

function closeHelpPost(string $dataFile, string $userId, string $helpPostId): array
{
    $store = readStore($dataFile);
    $helpPostIndex = ownedPostIndex($store['help_posts'], $helpPostId, $userId, 'Help post not found.');
    $store['help_posts'][$helpPostIndex]['status'] = 'closed';
    $store['help_posts'][$helpPostIndex]['updated_at'] = gmdate('c');
    writeStore($dataFile, $store);

    return postWithOwner($store['help_posts'][$helpPostIndex], $store['users']);
}

function removePost(string $dataFile, string $resource, string $userId, string $postId): array
{
    $store = readStore($dataFile);
    $collection = RESOURCE_COLLECTIONS[$resource];
    $postIndex = ownedPostIndex($store[$collection], $postId, $userId, 'Post not found.');
    [$removedPost] = array_splice($store[$collection], $postIndex, 1);
    writeStore($dataFile, $store);

    return $removedPost;
}
