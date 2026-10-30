<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\OwnershipError;
use function CampusMarketplace\Domain\assertPostOwned;
use function CampusMarketplace\Domain\findPostIndex;

$currentOwner = 'You';
$ownedPost = ['id' => 'lst_owned', 'owner' => $currentOwner];
$otherPost = ['id' => 'lst_other', 'owner' => 'Another student'];

assertPostOwned($ownedPost, $currentOwner);
$error = expectException(
    fn () => assertPostOwned($otherPost, $currentOwner),
    OwnershipError::class,
    'Another student\'s post should be protected'
);
checkSame('You can only change your own posts.', $error->getMessage(), 'Ownership failure should be clear');

$posts = [$otherPost, $ownedPost];
checkSame(1, findPostIndex($posts, 'lst_owned'), 'The requested post should be found');
checkSame(-1, findPostIndex($posts, 'lst_missing'), 'A missing post should not be found');

echo "Ownership unit tests passed.\n";
