<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\validateListing;

$validListing = [
    'name' => 'Desk lamp',
    'description' => 'A working lamp for a dorm desk.',
    'photos' => ['assets/desk.svg'],
    'price' => 12,
    'category' => 'Other',
    'condition' => 'Good',
    'location' => 'West Hall',
];

$listing = validateListing($validListing);
checkSame('Desk lamp', $listing['name'], 'A valid name should be preserved');
checkSame(12.0, $listing['price'], 'A valid price should be normalized');
checkSame('available', $listing['status'], 'A new listing should be available');

$freeListing = validateListing([...$validListing, 'price' => 0]);
checkSame(0.0, $freeListing['price'], 'A free listing should be valid');

$invalidListings = [
    ['changes' => ['name' => '   '], 'field' => 'name'],
    ['changes' => ['name' => str_repeat('x', 81)], 'field' => 'name'],
    ['changes' => ['description' => ''], 'field' => 'description'],
    ['changes' => ['price' => -1], 'field' => 'price'],
    ['changes' => ['price' => 'free'], 'field' => 'price'],
    ['changes' => ['price' => 100001], 'field' => 'price'],
    ['changes' => ['category' => 'Cars'], 'field' => 'category'],
    ['changes' => ['condition' => 'Broken'], 'field' => 'condition'],
    ['changes' => ['location' => ''], 'field' => 'location'],
    ['changes' => ['photos' => array_fill(0, 5, 'assets/desk.svg')], 'field' => 'photos'],
    ['changes' => ['photos' => ['not-an-image']], 'field' => 'photos'],
];

foreach ($invalidListings as $case) {
    $error = expectException(
        fn () => validateListing([...$validListing, ...$case['changes']]),
        ValidationError::class,
        "Invalid {$case['field']} should be rejected"
    );
    checkSame($case['field'], $error->field, 'Validation should identify the invalid listing field');
}

$existingListing = [...$listing, 'owner' => 'Another student', 'status' => 'sold'];
$updatedListing = validateListing([...$validListing, 'name' => 'Updated lamp'], $existingListing);
checkSame('Another student', $updatedListing['owner'], 'Editing should preserve listing ownership');
checkSame('sold', $updatedListing['status'], 'Editing should preserve listing status');

echo "Listing validation unit tests passed.\n";
