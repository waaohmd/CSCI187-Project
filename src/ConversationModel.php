<?php
declare(strict_types=1);

namespace CampusMarketplace\Model;

require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Store.php';

use CampusMarketplace\Domain\NotFoundError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\assertCanRespond;
use function CampusMarketplace\Domain\assertConversationParticipant;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\findRecord;
use function CampusMarketplace\Domain\publicUser;
use function CampusMarketplace\Domain\validateMeetingLocations;
use function CampusMarketplace\Domain\validateMessageBody;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

const POST_TYPE_COLLECTIONS = ['listing' => 'listings', 'help' => 'help_posts'];

function respondedPost(array $store, array $data): array
{
    $postType = (string) ($data['post_type'] ?? '');
    if (!array_key_exists($postType, POST_TYPE_COLLECTIONS)) {
        throw new ValidationError('post_type', 'Post type is invalid.');
    }

    $post = findRecord($store[POST_TYPE_COLLECTIONS[$postType]], (string) ($data['post_id'] ?? ''));
    if ($post === null) {
        throw new NotFoundError('Post not found.');
    }

    return $post;
}

function newConversation(array $post, string $postType, string $userId, array $locations): array
{
    $isListing = $postType === 'listing';

    return [
        'id' => 'cnv_' . bin2hex(random_bytes(5)),
        'post_type' => $postType,
        'post_id' => $post['id'],
        'post_title' => $isListing ? $post['name'] : $post['title'],
        'owner_id' => $post['owner_id'],
        'responder_id' => $userId,
        'pickup_location' => $locations['pickup_location'] ?: ($isListing ? $post['location'] : $post['from_location']),
        'dropoff_location' => $locations['dropoff_location'] ?: ($isListing ? '' : $post['to_location']),
        'created_at' => gmdate('c'),
    ];
}

function findResponderConversationIndex(array $conversations, array $post, string $postType, string $userId): int
{
    foreach ($conversations as $index => $conversation) {
        $isSamePost = $conversation['post_type'] === $postType && $conversation['post_id'] === $post['id'];
        if ($isSamePost && $conversation['responder_id'] === $userId) {
            return $index;
        }
    }

    return -1;
}

function appendMessage(array $store, int $conversationIndex, string $senderId, string $body): array
{
    $sentAt = gmdate('c');
    $store['messages'][] = [
        'id' => 'msg_' . bin2hex(random_bytes(5)),
        'conversation_id' => $store['conversations'][$conversationIndex]['id'],
        'sender_id' => $senderId,
        'body' => $body,
        'created_at' => $sentAt,
    ];
    $store['conversations'][$conversationIndex]['updated_at'] = $sentAt;

    return $store;
}

function conversationView(array $conversation, array $store, string $userId): array
{
    $otherUserId = $conversation['owner_id'] === $userId ? $conversation['responder_id'] : $conversation['owner_id'];
    $otherUser = findRecord($store['users'], $otherUserId);
    $messages = array_filter(
        $store['messages'],
        fn (array $message) => $message['conversation_id'] === $conversation['id']
    );

    return [
        ...$conversation,
        'other_user' => $otherUser === null ? null : publicUser($otherUser),
        'messages' => array_values($messages),
    ];
}

function participantConversationIndex(array $store, string $conversationId, string $userId): int
{
    $conversationIndex = findPostIndex($store['conversations'], $conversationId);
    if ($conversationIndex < 0) {
        throw new NotFoundError('Conversation not found.');
    }
    assertConversationParticipant($store['conversations'][$conversationIndex], $userId);

    return $conversationIndex;
}

function listConversations(string $dataFile, string $userId): array
{
    $store = readStore($dataFile);
    $conversations = array_filter(
        $store['conversations'],
        fn (array $conversation) => in_array($userId, [$conversation['owner_id'], $conversation['responder_id']], true)
    );
    usort($conversations, fn (array $first, array $second) => strcmp($second['updated_at'], $first['updated_at']));

    return array_map(fn (array $conversation) => conversationView($conversation, $store, $userId), $conversations);
}

function getConversation(string $dataFile, string $userId, string $conversationId): array
{
    $store = readStore($dataFile);
    $conversationIndex = participantConversationIndex($store, $conversationId, $userId);

    return conversationView($store['conversations'][$conversationIndex], $store, $userId);
}

function respondToPost(string $dataFile, string $userId, array $data): array
{
    $store = readStore($dataFile);
    $post = respondedPost($store, $data);
    assertCanRespond($post, $userId);
    $body = validateMessageBody($data);
    $locations = validateMeetingLocations($data);

    $postType = (string) $data['post_type'];
    $conversationIndex = findResponderConversationIndex($store['conversations'], $post, $postType, $userId);
    if ($conversationIndex < 0) {
        array_unshift($store['conversations'], newConversation($post, $postType, $userId, $locations));
        $conversationIndex = 0;
    }

    $store = appendMessage($store, $conversationIndex, $userId, $body);
    writeStore($dataFile, $store);

    return conversationView($store['conversations'][$conversationIndex], $store, $userId);
}

function sendMessage(string $dataFile, string $userId, string $conversationId, array $data): array
{
    $store = readStore($dataFile);
    $conversationIndex = participantConversationIndex($store, $conversationId, $userId);
    $store = appendMessage($store, $conversationIndex, $userId, validateMessageBody($data));
    writeStore($dataFile, $store);

    return conversationView($store['conversations'][$conversationIndex], $store, $userId);
}

function updateMeetingLocations(string $dataFile, string $userId, string $conversationId, array $data): array
{
    $store = readStore($dataFile);
    $conversationIndex = participantConversationIndex($store, $conversationId, $userId);
    $store['conversations'][$conversationIndex] = array_merge(
        $store['conversations'][$conversationIndex],
        validateMeetingLocations($data),
        ['updated_at' => gmdate('c')]
    );
    writeStore($dataFile, $store);

    return conversationView($store['conversations'][$conversationIndex], $store, $userId);
}
