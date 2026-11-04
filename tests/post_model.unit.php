<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/PostModel.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\NotFoundError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Model\createPost;
use function CampusMarketplace\Model\isKnownResource;
use function CampusMarketplace\Model\listPosts;
use function CampusMarketplace\Model\removePost;
use function CampusMarketplace\Model\sellListing;
use function CampusMarketplace\Model\updatePost;
use function CampusMarketplace\Persistence\writeStore;

$validListing = [
    'name' => 'Desk lamp',
    'description' => 'A working lamp for a dorm desk.',
    'photos' => [],
    'price' => 12,
    'category' => 'Other',
    'condition' => 'Good',
    'location' => 'West Hall',
];
$validHelpPost = [
    'title' => 'Move a mini-fridge',
    'type' => 'Delivery',
    'from_location' => 'West Hall',
    'to_location' => 'East Hall',
    'date' => '2026-11-05',
    'time' => '14:30',
    'offer' => 15,
    'details' => 'Please help carry a mini-fridge across campus.',
];
$userId = 'usr_current';
$users = [
    ['id' => $userId, 'name' => 'Current Student', 'email' => 'current@scu.edu'],
    ['id' => 'usr_other', 'name' => 'Other Student', 'email' => 'other@example.com'],
];
$ownedListing = [...$validListing, 'id' => 'lst_owned', 'owner_id' => $userId, 'status' => 'available'];
$otherListing = [...$validListing, 'id' => 'lst_other', 'owner_id' => 'usr_other', 'status' => 'available'];

$dataFile = tempnam(sys_get_temp_dir(), 'post_model_test');
check($dataFile !== false, 'A temporary data file should be created');

try {
    writeStore($dataFile, ['users' => $users, 'listings' => [$ownedListing, $otherListing], 'help_posts' => []]);

    check(isKnownResource('listings'), 'Listings should be a known resource');
    check(isKnownResource('help'), 'Help should be a known resource');
    check(!isKnownResource('users'), 'Other resources should be unknown');

    $createdListing = createPost($dataFile, 'listings', $userId, $validListing);
    checkSame($userId, $createdListing['owner_id'], 'A new listing should belong to the signed-in user');
    checkSame(true, $createdListing['owner']['verified'], 'A listing should show its owner verification');
    checkSame(
        [$createdListing['id'], 'lst_owned', 'lst_other'],
        array_column(listPosts($dataFile, 'listings'), 'id'),
        'A new listing should be added first without replacing existing listings'
    );
    check(str_starts_with($createdListing['id'], 'lst_'), 'A listing ID should use the listing prefix');

    $listedOwners = array_column(array_column(listPosts($dataFile, 'listings'), 'owner'), 'name');
    checkSame(['Current Student', 'Current Student', 'Other Student'], $listedOwners, 'Listings should include seller details');
    checkSame(false, listPosts($dataFile, 'listings')[2]['owner']['verified'], 'A non-SCU seller should not be verified');
    check(!array_key_exists('email', listPosts($dataFile, 'listings')[0]['owner']), 'Listings should not expose seller email');

    $createdHelpPost = createPost($dataFile, 'help', $userId, $validHelpPost);
    checkSame('open', $createdHelpPost['status'], 'A new help post should be open');
    check(str_starts_with($createdHelpPost['id'], 'hlp_'), 'A help post ID should use the help prefix');
    checkSame(1, count(listPosts($dataFile, 'help')), 'A new help post should be saved');

    expectException(
        fn () => createPost($dataFile, 'listings', $userId, [...$validListing, 'name' => '']),
        ValidationError::class,
        'An invalid listing should be rejected'
    );
    checkSame(3, count(listPosts($dataFile, 'listings')), 'An invalid listing should not be saved');

    $updatedListing = updatePost($dataFile, 'listings', $userId, 'lst_owned', [...$validListing, 'name' => 'Updated lamp']);
    checkSame('Updated lamp', $updatedListing['name'], 'Editing should change the post');
    checkSame('lst_owned', $updatedListing['id'], 'Editing should keep the post ID');
    checkSame($userId, $updatedListing['owner_id'], 'Editing should keep the post owner');
    checkSame(3, count(listPosts($dataFile, 'listings')), 'Editing should not add a listing');

    $soldListing = sellListing($dataFile, $userId, 'lst_owned');
    checkSame('sold', $soldListing['status'], 'Selling should mark the listing as sold');

    $protectedActions = [
        fn () => updatePost($dataFile, 'listings', $userId, 'lst_other', $validListing),
        fn () => sellListing($dataFile, $userId, 'lst_other'),
        fn () => removePost($dataFile, 'listings', $userId, 'lst_other'),
    ];
    foreach ($protectedActions as $protectedAction) {
        expectException($protectedAction, OwnershipError::class, 'Another student\'s post should be protected');
    }

    $missingPostActions = [
        fn () => updatePost($dataFile, 'listings', $userId, 'lst_missing', $validListing),
        fn () => sellListing($dataFile, $userId, 'lst_missing'),
        fn () => removePost($dataFile, 'listings', $userId, 'lst_missing'),
    ];
    foreach ($missingPostActions as $missingPostAction) {
        expectException($missingPostAction, NotFoundError::class, 'A missing post should be reported');
    }

    $removedListing = removePost($dataFile, 'listings', $userId, 'lst_owned');
    checkSame('lst_owned', $removedListing['id'], 'Removing should return the removed post');
    checkSame(
        [$createdListing['id'], 'lst_other'],
        array_column(listPosts($dataFile, 'listings'), 'id'),
        'Removing should delete only the requested post'
    );
} finally {
    unlink($dataFile);
}

echo "Post model unit tests passed.\n";
