<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\validateHelpPost;

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

$helpPost = validateHelpPost($validHelpPost);
checkSame('open', $helpPost['status'], 'A new help post should be open');
checkSame(15.0, $helpPost['offer'], 'A valid offer should be normalized');

foreach ([1, 10000] as $validOffer) {
    $result = validateHelpPost([...$validHelpPost, 'offer' => $validOffer]);
    checkSame((float) $validOffer, $result['offer'], 'Offer boundary should be valid');
}

$invalidHelpPosts = [
    ['changes' => ['title' => '   '], 'field' => 'title'],
    ['changes' => ['title' => str_repeat('A', 101)], 'field' => 'title'],
    ['changes' => ['type' => 'Cleaning'], 'field' => 'type'],
    ['changes' => ['from_location' => ''], 'field' => 'from_location'],
    ['changes' => ['to_location' => ''], 'field' => 'to_location'],
    ['changes' => ['date' => '11/05/2026'], 'field' => 'date'],
    ['changes' => ['time' => '2:30 PM'], 'field' => 'time'],
    ['changes' => ['offer' => 0], 'field' => 'offer'],
    ['changes' => ['offer' => 10001], 'field' => 'offer'],
    ['changes' => ['offer' => 'free'], 'field' => 'offer'],
    ['changes' => ['details' => ''], 'field' => 'details'],
    ['changes' => ['details' => str_repeat('A', 601)], 'field' => 'details'],
];

foreach ($invalidHelpPosts as $case) {
    $error = expectException(
        fn () => validateHelpPost([...$validHelpPost, ...$case['changes']]),
        ValidationError::class,
        "Invalid {$case['field']} should be rejected"
    );
    checkSame($case['field'], $error->field, 'Validation should identify the invalid help field');
}

$existingHelpPost = [...$helpPost, 'owner' => 'Another student'];
$updatedHelpPost = validateHelpPost([...$validHelpPost, 'title' => 'Updated request'], $existingHelpPost);
checkSame('Another student', $updatedHelpPost['owner'], 'Editing should preserve help-post ownership');
checkSame('open', $updatedHelpPost['status'], 'Editing should preserve help-post status');

echo "Help validation unit tests passed.\n";
