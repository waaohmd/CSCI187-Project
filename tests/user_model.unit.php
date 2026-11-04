<?php
declare(strict_types=1);

// Author: Abhinav Raghavendra

require_once __DIR__ . '/../src/UserModel.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\AuthenticationError;
use CampusMarketplace\Domain\ConflictError;
use CampusMarketplace\Domain\NotFoundError;
use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Model\authenticateUser;
use function CampusMarketplace\Model\findAccount;
use function CampusMarketplace\Model\getPublicProfile;
use function CampusMarketplace\Model\registerUser;
use function CampusMarketplace\Model\updateProfile;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

$registration = ['name' => 'Hue Nguyen', 'email' => 'hue@scu.edu', 'password' => 'correct-password'];
$sampleUser = ['id' => 'usr_sample', 'name' => 'Sample', 'email' => 'sample@scu.edu', 'password_hash' => ''];

$dataFile = tempnam(sys_get_temp_dir(), 'user_model_test');
check($dataFile !== false, 'A temporary data file should be created');

try {
    writeStore($dataFile, ['users' => [$sampleUser]]);

    $account = registerUser($dataFile, $registration);
    check(str_starts_with($account['id'], 'usr_'), 'A user ID should use the user prefix');
    checkSame('hue@scu.edu', $account['email'], 'An account should include its own email');
    checkSame(true, $account['verified'], 'An SCU account should be verified');
    check(!array_key_exists('password_hash', $account), 'An account should not expose its password hash');

    $storedUser = readStore($dataFile)['users'][0];
    check($storedUser['password_hash'] !== $registration['password'], 'A password should not be stored as plain text');
    check(password_verify($registration['password'], $storedUser['password_hash']), 'A password should be hashed');

    expectException(
        fn () => registerUser($dataFile, [...$registration, 'email' => 'HUE@scu.edu']),
        ConflictError::class,
        'An email should only be registered once, ignoring case'
    );
    expectException(
        fn () => registerUser($dataFile, [...$registration, 'email' => 'hue@gmail.com']),
        ValidationError::class,
        'A non-SCU email should not register'
    );
    checkSame(2, count(readStore($dataFile)['users']), 'Rejected registrations should not be saved');

    $signedIn = authenticateUser($dataFile, ['email' => 'HUE@scu.edu', 'password' => 'correct-password']);
    checkSame($account['id'], $signedIn['id'], 'Correct credentials should sign in');

    $failedSignIns = [
        ['email' => 'hue@scu.edu', 'password' => 'wrong-password'],
        ['email' => 'nobody@scu.edu', 'password' => 'correct-password'],
        ['email' => 'hue@scu.edu'],
        ['email' => 'sample@scu.edu', 'password' => ''],
    ];
    foreach ($failedSignIns as $failedSignIn) {
        $error = expectException(
            fn () => authenticateUser($dataFile, $failedSignIn),
            AuthenticationError::class,
            'Incorrect credentials should not sign in'
        );
        checkSame('Email or password is incorrect.', $error->getMessage(), 'Sign-in errors should not reveal which field failed');
    }

    $profile = updateProfile($dataFile, $account['id'], ['name' => 'Hue N.', 'residence' => 'West Hall', 'bio' => 'Senior']);
    checkSame('Hue N.', $profile['name'], 'A profile should update its name');
    checkSame('West Hall', $profile['residence'], 'A profile should update its residence');
    checkSame('hue@scu.edu', $profile['email'], 'Updating a profile should keep its email');
    authenticateUser($dataFile, ['email' => 'hue@scu.edu', 'password' => 'correct-password']);

    expectException(
        fn () => updateProfile($dataFile, $account['id'], ['name' => '']),
        ValidationError::class,
        'A profile should require a name'
    );
    expectException(
        fn () => updateProfile($dataFile, 'usr_missing', ['name' => 'Nobody']),
        NotFoundError::class,
        'A missing user should not be updated'
    );

    checkSame('Senior', getPublicProfile($dataFile, $account['id'])['bio'], 'A public profile should be readable');
    check(!array_key_exists('email', getPublicProfile($dataFile, $account['id'])), 'A public profile should hide the email');
    expectException(
        fn () => getPublicProfile($dataFile, 'usr_missing'),
        NotFoundError::class,
        'A missing profile should be reported'
    );

    checkSame($account['id'], findAccount($dataFile, $account['id'])['id'], 'A signed-in account should be found');
    checkSame(null, findAccount($dataFile, 'usr_missing'), 'A missing account should be null');
} finally {
    unlink($dataFile);
}

echo "User model unit tests passed.\n";
