<?php
declare(strict_types=1);

namespace CampusMarketplace\Model;

require_once __DIR__ . '/Domain.php';
require_once __DIR__ . '/Store.php';

use CampusMarketplace\Domain\AuthenticationError;
use CampusMarketplace\Domain\ConflictError;
use CampusMarketplace\Domain\NotFoundError;
use function CampusMarketplace\Domain\findPostIndex;
use function CampusMarketplace\Domain\findRecord;
use function CampusMarketplace\Domain\normalizedEmail;
use function CampusMarketplace\Domain\publicUser;
use function CampusMarketplace\Domain\validateProfile;
use function CampusMarketplace\Domain\validateRegistration;
use function CampusMarketplace\Persistence\readStore;
use function CampusMarketplace\Persistence\writeStore;

function accountView(array $user): array
{
    return [...publicUser($user), 'email' => $user['email']];
}

function findUserByEmail(array $users, string $email): ?array
{
    foreach ($users as $user) {
        if ($user['email'] === $email) {
            return $user;
        }
    }

    return null;
}

function registerUser(string $dataFile, array $data): array
{
    $store = readStore($dataFile);
    $registration = validateRegistration($data);
    if (findUserByEmail($store['users'], $registration['email']) !== null) {
        throw new ConflictError('An account with this email already exists.');
    }

    $user = [
        'id' => 'usr_' . bin2hex(random_bytes(5)),
        'name' => $registration['name'],
        'email' => $registration['email'],
        'password_hash' => password_hash($registration['password'], PASSWORD_DEFAULT),
        'residence' => $registration['residence'],
        'bio' => $registration['bio'],
        'created_at' => gmdate('c'),
    ];
    array_unshift($store['users'], $user);
    writeStore($dataFile, $store);

    return accountView($user);
}

function authenticateUser(string $dataFile, array $data): array
{
    $user = findUserByEmail(readStore($dataFile)['users'], normalizedEmail($data));
    $password = (string) ($data['password'] ?? '');
    if ($user === null || !password_verify($password, $user['password_hash'])) {
        throw new AuthenticationError('Email or password is incorrect.');
    }

    return accountView($user);
}

function findAccount(string $dataFile, string $userId): ?array
{
    $user = findRecord(readStore($dataFile)['users'], $userId);

    return $user === null ? null : accountView($user);
}

function getPublicProfile(string $dataFile, string $userId): array
{
    $user = findRecord(readStore($dataFile)['users'], $userId);
    if ($user === null) {
        throw new NotFoundError('User not found.');
    }

    return publicUser($user);
}

function updateProfile(string $dataFile, string $userId, array $data): array
{
    $store = readStore($dataFile);
    $userIndex = findPostIndex($store['users'], $userId);
    if ($userIndex < 0) {
        throw new NotFoundError('User not found.');
    }

    $store['users'][$userIndex] = array_merge($store['users'][$userIndex], validateProfile($data));
    writeStore($dataFile, $store);

    return accountView($store['users'][$userIndex]);
}
