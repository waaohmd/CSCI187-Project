<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/PostModel.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\PostNotFoundError;
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
$ownedListing = [...$validListing, 'id' => 'lst_owned', 'owner' => 'You', 'status' => 'available'];
$otherListing = [...$validListing, 'id' => 'lst_other', 'owner' => 'Another student', 'status' => 'available'];

$dataFile = tempnam(sys_get_temp_dir(), 'post_model_test');
check($dataFile !== false, 'A temporary data file should be created');

try {
    writeStore($dataFile, ['listings' => [$ownedListing, $otherListing], 'help_posts' => []]);

    check(isKnownResource('listings'), 'Listings should be a known resource');
    check(isKnownResource('help'), 'Help should be a known resource');
    check(!isKnownResource('users'), 'Other resources should be unknown');

    $createdListing = createPost($dataFile, 'listings', $validListing);
    checkSame(
        [$createdListing['id'], 'lst_owned', 'lst_other'],
        array_column(listPosts($dataFile, 'listings'), 'id'),
        'A new listing should be added first without replacing existing listings'
    );
    check(str_starts_with($createdListing['id'], 'lst_'), 'A listing ID should use the listing prefix');

    $createdHelpPost = createPost($dataFile, 'help', $validHelpPost);
    checkSame('open', $createdHelpPost['status'], 'A new help post should be open');
    check(str_starts_with($createdHelpPost['id'], 'hlp_'), 'A help post ID should use the help prefix');
    checkSame(1, count(listPosts($dataFile, 'help')), 'A new help post should be saved');

    expectException(
        fn () => createPost($dataFile, 'listings', [...$validListing, 'name' => '']),
        ValidationError::class,
        'An invalid listing should be rejected'
    );
    checkSame(3, count(listPosts($dataFile, 'listings')), 'An invalid listing should not be saved');

    $updatedListing = updatePost($dataFile, 'listings', 'lst_owned', [...$validListing, 'name' => 'Updated lamp']);
    checkSame('Updated lamp', $updatedListing['name'], 'Editing should change the post');
    checkSame('lst_owned', $updatedListing['id'], 'Editing should keep the post ID');
    checkSame(3, count(listPosts($dataFile, 'listings')), 'Editing should not add a listing');

    $soldListing = sellListing($dataFile, 'lst_owned');
    checkSame('sold', $soldListing['status'], 'Selling should mark the listing as sold');

    $protectedActions = [
        fn () => updatePost($dataFile, 'listings', 'lst_other', $validListing),
        fn () => sellListing($dataFile, 'lst_other'),
        fn () => removePost($dataFile, 'listings', 'lst_other'),
    ];
    foreach ($protectedActions as $protectedAction) {
        expectException($protectedAction, OwnershipError::class, 'Another student\'s post should be protected');
    }

    $missingPostActions = [
        fn () => updatePost($dataFile, 'listings', 'lst_missing', $validListing),
        fn () => sellListing($dataFile, 'lst_missing'),
        fn () => removePost($dataFile, 'listings', 'lst_missing'),
    ];
    foreach ($missingPostActions as $missingPostAction) {
        expectException($missingPostAction, PostNotFoundError::class, 'A missing post should be reported');
    }

    $removedListing = removePost($dataFile, 'listings', 'lst_owned');
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
