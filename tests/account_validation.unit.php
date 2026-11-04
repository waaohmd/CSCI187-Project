<?php
declare(strict_types=1);

// Author: Abhinav Raghavendra

require_once __DIR__ . '/../src/Domain.php';
require_once __DIR__ . '/php_test_helpers.php';

use CampusMarketplace\Domain\ValidationError;
use function CampusMarketplace\Domain\isScuEmail;
use function CampusMarketplace\Domain\publicUser;
use function CampusMarketplace\Domain\validateMeetingLocations;
use function CampusMarketplace\Domain\validateMessageBody;
use function CampusMarketplace\Domain\validateProfile;
use function CampusMarketplace\Domain\validateRegistration;

$validRegistration = ['name' => 'Hue Nguyen', 'email' => ' Hue@SCU.edu ', 'password' => 'long-enough'];

$registration = validateRegistration($validRegistration);
checkSame('hue@scu.edu', $registration['email'], 'An email should be trimmed and lowercased');
checkSame('', $registration['bio'], 'A bio should be optional');

foreach ([8, 72] as $validPasswordLength) {
    $result = validateRegistration([...$validRegistration, 'password' => str_repeat('p', $validPasswordLength)]);
    checkSame($validPasswordLength, strlen($result['password']), 'Password boundary should be valid');
}

$invalidRegistrations = [
    ['changes' => ['email' => 'hue@gmail.com'], 'field' => 'email'],
    ['changes' => ['email' => 'hue@notscu.edu'], 'field' => 'email'],
    ['changes' => ['email' => 'hue@scu.edu.example.com'], 'field' => 'email'],
    ['changes' => ['email' => '@scu.edu'], 'field' => 'email'],
    ['changes' => ['email' => ''], 'field' => 'email'],
    ['changes' => ['password' => str_repeat('p', 7)], 'field' => 'password'],
    ['changes' => ['password' => str_repeat('p', 73)], 'field' => 'password'],
    ['changes' => ['name' => '   '], 'field' => 'name'],
    ['changes' => ['name' => str_repeat('n', 81)], 'field' => 'name'],
];

foreach ($invalidRegistrations as $case) {
    $error = expectException(
        fn () => validateRegistration([...$validRegistration, ...$case['changes']]),
        ValidationError::class,
        "Invalid {$case['field']} should be rejected"
    );
    checkSame($case['field'], $error->field, 'Validation should identify the invalid account field');
}

$profile = validateProfile(['name' => 'Hue', 'residence' => ' West Hall ', 'bio' => str_repeat('b', 300)]);
checkSame('West Hall', $profile['residence'], 'A residence should be trimmed');
$error = expectException(
    fn () => validateProfile(['name' => 'Hue', 'bio' => str_repeat('b', 301)]),
    ValidationError::class,
    'A long bio should be rejected'
);
checkSame('bio', $error->field, 'Validation should identify the bio');

check(isScuEmail('student@scu.edu'), 'An SCU email should be verified');
check(isScuEmail('STUDENT@SCU.EDU'), 'SCU email checks should ignore case');
check(!isScuEmail('student@example.com'), 'A non-SCU email should not be verified');

$publicUser = publicUser([
    'id' => 'usr_1',
    'name' => 'Hue',
    'email' => 'hue@scu.edu',
    'password_hash' => 'secret',
    'residence' => 'West Hall',
    'bio' => 'Hello',
]);
checkSame(true, $publicUser['verified'], 'A public profile should show verification');
check(!array_key_exists('email', $publicUser), 'A public profile should hide the email');
check(!array_key_exists('password_hash', $publicUser), 'A public profile should hide the password hash');

checkSame('Is this available?', validateMessageBody(['body' => ' Is this available? ']), 'A message should be trimmed');
checkSame(1000, strlen(validateMessageBody(['body' => str_repeat('m', 1000)])), 'Message boundary should be valid');
foreach (['', '   ', str_repeat('m', 1001)] as $invalidBody) {
    expectException(
        fn () => validateMessageBody(['body' => $invalidBody]),
        ValidationError::class,
        'An empty or long message should be rejected'
    );
}

$locations = validateMeetingLocations(['pickup_location' => 'West Hall', 'dropoff_location' => '']);
checkSame('West Hall', $locations['pickup_location'], 'A pickup location should be kept');
checkSame('', $locations['dropoff_location'], 'A drop-off location should be optional');
$error = expectException(
    fn () => validateMeetingLocations(['dropoff_location' => str_repeat('d', 121)]),
    ValidationError::class,
    'A long drop-off location should be rejected'
);
checkSame('dropoff_location', $error->field, 'Validation should identify the drop-off location');

echo "Account validation unit tests passed.\n";
