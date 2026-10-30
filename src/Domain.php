<?php
declare(strict_types=1);

namespace CampusMarketplace\Domain;

use RuntimeException;

const MAX_PHOTOS = 4;
const LISTING_CATEGORIES = ['Furniture', 'Appliances', 'Electronics', 'Clothing', 'School supplies', 'Other'];
const LISTING_CONDITIONS = ['Like new', 'Good', 'Fair', 'Well loved'];
const HELP_TYPES = ['Delivery', 'Moving & lifting', 'Hauling', 'Driving'];

final class ValidationError extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}

final class OwnershipError extends RuntimeException
{
}

function requiredText(array $data, string $key, int $maximumLength, string $label): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        throw new ValidationError($key, "$label is required.");
    }

    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > $maximumLength) {
        throw new ValidationError($key, "$label is too long.");
    }

    return $value;
}

function moneyValue(array $data, string $key, float $minimum, float $maximum, string $label): float
{
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < $minimum || $value > $maximum) {
        throw new ValidationError($key, "$label is invalid.");
    }

    return round((float) $value, 2);
}

function allowedValue(array $data, string $key, array $allowedValues, string $label): string
{
    $value = (string) ($data[$key] ?? '');
    if (!in_array($value, $allowedValues, true)) {
        throw new ValidationError($key, "$label is invalid.");
    }

    return $value;
}

function validatedPhotos(array $data): array
{
    $photos = $data['photos'] ?? [];
    if (!is_array($photos) || count($photos) > MAX_PHOTOS) {
        throw new ValidationError('photos', 'Add no more than four photos.');
    }

    foreach ($photos as $photo) {
        $isSupportedPhoto = is_string($photo)
            && strlen($photo) <= 1_500_000
            && preg_match('#^(data:image/(jpeg|png|webp);base64,|assets/)#', $photo) === 1;
        if (!$isSupportedPhoto) {
            throw new ValidationError('photos', 'A photo is invalid or too large.');
        }
    }

    return array_values($photos);
}

function validateListing(array $data, ?array $existingListing = null): array
{
    $photos = array_key_exists('photos', $data)
        ? validatedPhotos($data)
        : ($existingListing['photos'] ?? []);

    return [
        'owner' => $existingListing['owner'] ?? 'You',
        'name' => requiredText($data, 'name', 80, 'Item name'),
        'description' => requiredText($data, 'description', 800, 'Description'),
        'photos' => $photos,
        'price' => moneyValue($data, 'price', 0, 100000, 'Price'),
        'category' => allowedValue($data, 'category', LISTING_CATEGORIES, 'Category'),
        'condition' => allowedValue($data, 'condition', LISTING_CONDITIONS, 'Condition'),
        'location' => requiredText($data, 'location', 100, 'Pickup location'),
        'status' => $existingListing['status'] ?? 'available',
    ];
}

function validateHelpPost(array $data, ?array $existingHelpPost = null): array
{
    $date = requiredText($data, 'date', 10, 'Date');
    $time = requiredText($data, 'time', 5, 'Time');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new ValidationError('date', 'Date is invalid.');
    }
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
        throw new ValidationError('time', 'Time is invalid.');
    }

    return [
        'owner' => $existingHelpPost['owner'] ?? 'You',
        'title' => requiredText($data, 'title', 100, 'Title'),
        'type' => allowedValue($data, 'type', HELP_TYPES, 'Help type'),
        'from_location' => requiredText($data, 'from_location', 120, 'From location'),
        'to_location' => requiredText($data, 'to_location', 120, 'To location'),
        'date' => $date,
        'time' => $time,
        'offer' => moneyValue($data, 'offer', 1, 10000, 'Cash offer'),
        'details' => requiredText($data, 'details', 600, 'Details'),
        'status' => $existingHelpPost['status'] ?? 'open',
    ];
}

function assertPostOwned(array $post, string $currentOwner): void
{
    if (($post['owner'] ?? '') !== $currentOwner) {
        throw new OwnershipError('You can only change your own posts.');
    }
}

function findPostIndex(array $posts, string $postId): int
{
    foreach ($posts as $index => $post) {
        if (($post['id'] ?? '') === $postId) {
            return $index;
        }
    }

    return -1;
}
