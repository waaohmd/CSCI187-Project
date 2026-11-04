<?php
declare(strict_types=1);

// Author: Abhinav Raghavendra

require_once __DIR__ . '/../src/ConversationModel.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\ConflictError;
use CampusMarketplace\Domain\NotFoundError;
use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Model\getConversation;
use function CampusMarketplace\Model\listConversations;
use function CampusMarketplace\Model\respondToPost;
use function CampusMarketplace\Model\sendMessage;
use function CampusMarketplace\Model\updateMeetingLocations;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

$sellerId = 'usr_seller';
$buyerId = 'usr_buyer';
$moverId = 'usr_mover';
$store = [
    'users' => [
        ['id' => $sellerId, 'name' => 'Seller', 'email' => 'seller@scu.edu'],
        ['id' => $buyerId, 'name' => 'Buyer', 'email' => 'buyer@scu.edu'],
        ['id' => $moverId, 'name' => 'Mover', 'email' => 'mover@example.com'],
    ],
    'listings' => [
        ['id' => 'lst_open', 'owner_id' => $sellerId, 'name' => 'Desk', 'location' => 'West Hall', 'status' => 'available'],
        ['id' => 'lst_sold', 'owner_id' => $sellerId, 'name' => 'Chair', 'location' => 'West Hall', 'status' => 'sold'],
    ],
    'help_posts' => [
        [
            'id' => 'hlp_open',
            'owner_id' => $buyerId,
            'title' => 'Carry a desk',
            'from_location' => 'West Hall',
            'to_location' => 'East Hall',
            'status' => 'open',
        ],
        ['id' => 'hlp_closed', 'owner_id' => $buyerId, 'title' => 'Done', 'status' => 'closed'],
    ],
];
$listingResponse = ['post_type' => 'listing', 'post_id' => 'lst_open', 'body' => 'Is this available?'];

$dataFile = tempnam(sys_get_temp_dir(), 'conversation_model_test');
check($dataFile !== false, 'A temporary data file should be created');

try {
    writeStore($dataFile, $store);

    $buyerConversation = respondToPost($dataFile, $buyerId, [...$listingResponse, 'dropoff_location' => 'East Hall']);
    check(str_starts_with($buyerConversation['id'], 'cnv_'), 'A conversation ID should use the conversation prefix');
    checkSame('Desk', $buyerConversation['post_title'], 'A buyer response should name the listing');
    checkSame($sellerId, $buyerConversation['other_user']['id'], 'A buyer should talk to the seller');
    checkSame('West Hall', $buyerConversation['pickup_location'], 'Pickup should default to the listing location');
    checkSame('East Hall', $buyerConversation['dropoff_location'], 'A buyer should set a drop-off location');
    checkSame('Is this available?', $buyerConversation['messages'][0]['body'], 'A response should send its first message');

    $moverConversation = respondToPost(
        $dataFile,
        $moverId,
        ['post_type' => 'help', 'post_id' => 'hlp_open', 'body' => 'I can help.']
    );
    checkSame('Carry a desk', $moverConversation['post_title'], 'A mover response should name the help post');
    checkSame('West Hall', $moverConversation['pickup_location'], 'Pickup should default to the help post');
    checkSame('East Hall', $moverConversation['dropoff_location'], 'Drop-off should default to the help post');
    checkSame(true, $moverConversation['other_user']['verified'], 'The poster should be a verified student');

    $repeatedResponse = respondToPost($dataFile, $buyerId, [...$listingResponse, 'body' => 'Still interested.']);
    checkSame($buyerConversation['id'], $repeatedResponse['id'], 'Responding twice should reuse the conversation');
    checkSame(2, count($repeatedResponse['messages']), 'Responding twice should add a message');

    $rejectedResponses = [
        ['data' => $listingResponse, 'userId' => $sellerId, 'error' => ValidationError::class, 'label' => 'own post'],
        ['data' => [...$listingResponse, 'post_id' => 'lst_sold'], 'userId' => $buyerId, 'error' => ConflictError::class, 'label' => 'sold listing'],
        ['data' => [...$listingResponse, 'post_type' => 'help', 'post_id' => 'hlp_closed'], 'userId' => $moverId, 'error' => ConflictError::class, 'label' => 'closed help post'],
        ['data' => [...$listingResponse, 'post_id' => 'lst_missing'], 'userId' => $buyerId, 'error' => NotFoundError::class, 'label' => 'missing post'],
        ['data' => [...$listingResponse, 'post_type' => 'car'], 'userId' => $buyerId, 'error' => ValidationError::class, 'label' => 'unknown post type'],
        ['data' => [...$listingResponse, 'body' => ' '], 'userId' => $moverId, 'error' => ValidationError::class, 'label' => 'empty message'],
    ];
    foreach ($rejectedResponses as $case) {
        expectException(
            fn () => respondToPost($dataFile, $case['userId'], $case['data']),
            $case['error'],
            "A response to a {$case['label']} should be rejected"
        );
    }
    checkSame(2, count(readStore($dataFile)['conversations']), 'Rejected responses should not create conversations');

    $sellerReply = sendMessage($dataFile, $sellerId, $buyerConversation['id'], ['body' => 'Yes, it is.']);
    checkSame($buyerId, $sellerReply['other_user']['id'], 'A seller should talk to the buyer');
    checkSame($sellerId, $sellerReply['messages'][2]['sender_id'], 'A reply should record its sender');
    check($sellerReply['messages'][2]['created_at'] !== '', 'A reply should record its time');
    checkSame(
        ['Is this available?', 'Still interested.', 'Yes, it is.'],
        array_column(getConversation($dataFile, $buyerId, $buyerConversation['id'])['messages'], 'body'),
        'Messages should stay in the order they were sent'
    );

    $updatedLocations = updateMeetingLocations(
        $dataFile,
        $sellerId,
        $buyerConversation['id'],
        ['pickup_location' => 'West Hall lobby', 'dropoff_location' => 'East Hall, room 2']
    );
    checkSame('West Hall lobby', $updatedLocations['pickup_location'], 'A participant should update pickup');
    checkSame('East Hall, room 2', $updatedLocations['dropoff_location'], 'A participant should update drop-off');

    $outsiderActions = [
        fn () => getConversation($dataFile, $moverId, $buyerConversation['id']),
        fn () => sendMessage($dataFile, $moverId, $buyerConversation['id'], ['body' => 'Hello']),
        fn () => updateMeetingLocations($dataFile, $moverId, $buyerConversation['id'], ['pickup_location' => 'Elsewhere']),
    ];
    foreach ($outsiderActions as $outsiderAction) {
        expectException($outsiderAction, OwnershipError::class, 'An outsider should be rejected');
    }

    expectException(
        fn () => sendMessage($dataFile, $buyerId, 'cnv_missing', ['body' => 'Hello']),
        NotFoundError::class,
        'A missing conversation should be reported'
    );
    expectException(
        fn () => sendMessage($dataFile, $buyerId, $buyerConversation['id'], ['body' => '']),
        ValidationError::class,
        'An empty message should be rejected'
    );

    checkSame(2, count(listConversations($dataFile, $buyerId)), 'A user should see each of their conversations');
    checkSame(1, count(listConversations($dataFile, $sellerId)), 'A user should see only their conversations');
    checkSame([], listConversations($dataFile, 'usr_stranger'), 'A stranger should see no conversations');
} finally {
    unlink($dataFile);
}

echo "Conversation model unit tests passed.\n";
