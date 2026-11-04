<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/Domain.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\ConflictError;
use CampusMarketplace\Domain\OwnershipError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\assertCanRespond;
use function CampusMarketplace\Domain\assertConversationParticipant;
use function CampusMarketplace\Domain\assertPostOwned;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\findRecord;

$userId = 'usr_current';
$ownedPost = ['id' => 'lst_owned', 'owner_id' => $userId];
$otherPost = ['id' => 'lst_other', 'owner_id' => 'usr_other', 'status' => 'available'];

assertPostOwned($ownedPost, $userId);
$error = expectException(
    fn () => assertPostOwned($otherPost, $userId),
    OwnershipError::class,
    'Another student\'s post should be protected'
);
checkSame('You can only change your own posts.', $error->getMessage(), 'Ownership failure should be clear');

$posts = [$otherPost, $ownedPost];
checkSame(1, findPostIndex($posts, 'lst_owned'), 'The requested post should be found');
checkSame(-1, findPostIndex($posts, 'lst_missing'), 'A missing post should not be found');
checkSame($ownedPost, findRecord($posts, 'lst_owned'), 'The requested record should be returned');
checkSame(null, findRecord($posts, 'lst_missing'), 'A missing record should be null');

assertCanRespond($otherPost, $userId);
expectException(
    fn () => assertCanRespond($ownedPost, $userId),
    ValidationError::class,
    'A user should not respond to their own post'
);
expectException(
    fn () => assertCanRespond([...$otherPost, 'status' => 'sold'], $userId),
    ConflictError::class,
    'A sold listing should not accept responses'
);

$conversation = ['owner_id' => 'usr_owner', 'responder_id' => 'usr_responder'];
assertConversationParticipant($conversation, 'usr_owner');
assertConversationParticipant($conversation, 'usr_responder');
expectException(
    fn () => assertConversationParticipant($conversation, 'usr_outsider'),
    OwnershipError::class,
    'An outsider should not read a conversation'
);

echo "Ownership unit tests passed.\n";
