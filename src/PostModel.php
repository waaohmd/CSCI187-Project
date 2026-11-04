<?php
declare(strict_types=1);

namespace CampusMarketplace\Model;

require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Store.php';

use CampusMarketplace\Domain\PostNotFoundError;
use function CampusMarketplace\Domain\assertPostOwned;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\validateHelpPost;
use function CampusMarketplace\Domain\validateListing;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;
use const CampusMarketplace\Domain\CURRENT_OWNER;

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

function ownedPostIndex(array $posts, string $postId, string $notFoundMessage): int
{
    $postIndex = findPostIndex($posts, $postId);
    if ($postIndex < 0) {
        throw new PostNotFoundError($notFoundMessage);
    }
    assertPostOwned($posts[$postIndex], CURRENT_OWNER);

    return $postIndex;
}

function listPosts(string $dataFile, string $resource): array
{
    return readStore($dataFile)[RESOURCE_COLLECTIONS[$resource]];
}

function createPost(string $dataFile, string $resource, array $data): array
{
    $store = readStore($dataFile);
    $post = array_merge(
        ['id' => RESOURCE_ID_PREFIXES[$resource] . bin2hex(random_bytes(5))],
        validatedPost($resource, $data),
        ['created_at' => gmdate('c')]
    );
    array_unshift($store[RESOURCE_COLLECTIONS[$resource]], $post);
    writeStore($dataFile, $store);

    return $post;
}

function updatePost(string $dataFile, string $resource, string $postId, array $data): array
{
    $store = readStore($dataFile);
    $collection = RESOURCE_COLLECTIONS[$resource];
    $postIndex = ownedPostIndex($store[$collection], $postId, 'Post not found.');
    $existingPost = $store[$collection][$postIndex];
    $store[$collection][$postIndex] = array_merge(
        $existingPost,
        validatedPost($resource, $data, $existingPost),
        ['updated_at' => gmdate('c')]
    );
    writeStore($dataFile, $store);

    return $store[$collection][$postIndex];
}

function sellListing(string $dataFile, string $listingId): array
{
    $store = readStore($dataFile);
    $listingIndex = ownedPostIndex($store['listings'], $listingId, 'Listing not found.');
    $store['listings'][$listingIndex]['status'] = 'sold';
    $store['listings'][$listingIndex]['updated_at'] = gmdate('c');
    writeStore($dataFile, $store);

    return $store['listings'][$listingIndex];
}

function removePost(string $dataFile, string $resource, string $postId): array
{
    $store = readStore($dataFile);
    $collection = RESOURCE_COLLECTIONS[$resource];
    $postIndex = ownedPostIndex($store[$collection], $postId, 'Post not found.');
    [$removedPost] = array_splice($store[$collection], $postIndex, 1);
    writeStore($dataFile, $store);

    return $removedPost;
}
