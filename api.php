<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

const DATA_FILE = __DIR__ . '/data/store.json';
const MAX_PHOTOS = 4;

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function readStore(): array {
    $handle = fopen(DATA_FILE, 'c+');
    if ($handle === false) respond(500, ['error' => 'Unable to open the data store.']);
    try {
        if (!flock($handle, LOCK_SH)) respond(500, ['error' => 'Unable to lock the data store.']);
        rewind($handle);
        $contents = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
    $decoded = json_decode($contents ?: '{}', true);
    if (!is_array($decoded)) respond(500, ['error' => 'The data store is invalid.']);
    return array_merge(['listings' => [], 'help_posts' => []], $decoded);
}

function writeStore(array $store): void {
    $directory = dirname(DATA_FILE);
    if (!is_dir($directory)) mkdir($directory, 0775, true);
    $handle = fopen(DATA_FILE, 'c+');
    if ($handle === false) respond(500, ['error' => 'Unable to write the data store.']);
    try {
        if (!flock($handle, LOCK_EX)) respond(500, ['error' => 'Unable to lock the data store.']);
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

function body(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    if (!is_array($data)) respond(400, ['error' => 'Request body must be valid JSON.']);
    return $data;
}

function textField(array $data, string $key, int $max, string $label): string {
    $value = trim((string)($data[$key] ?? ''));
    if ($value === '') respond(422, ['error' => "$label is required.", 'field' => $key]);
    $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
    if ($length > $max) respond(422, ['error' => "$label is too long.", 'field' => $key]);
    return $value;
}

function moneyField(array $data, string $key, float $min, float $max, string $label): float {
    $value = filter_var($data[$key] ?? null, FILTER_VALIDATE_FLOAT);
    if ($value === false || $value < $min || $value > $max) respond(422, ['error' => "$label is invalid.", 'field' => $key]);
    return round((float)$value, 2);
}

function enumField(array $data, string $key, array $allowed, string $label): string {
    $value = (string)($data[$key] ?? '');
    if (!in_array($value, $allowed, true)) respond(422, ['error' => "$label is invalid.", 'field' => $key]);
    return $value;
}

function validPhotos(array $data): array {
    $photos = $data['photos'] ?? [];
    if (!is_array($photos) || count($photos) > MAX_PHOTOS) respond(422, ['error' => 'Add no more than four photos.', 'field' => 'photos']);
    return array_values(array_filter($photos, static function ($photo): bool {
        if (!is_string($photo) || strlen($photo) > 1_500_000) return false;
        return preg_match('#^(data:image/(jpeg|png|webp);base64,|assets/)#', $photo) === 1;
    }));
}

function validateListing(array $data, ?array $existing = null): array {
    $photos = array_key_exists('photos', $data) ? validPhotos($data) : ($existing['photos'] ?? []);
    return [
        'owner' => $existing['owner'] ?? 'You',
        'name' => textField($data, 'name', 80, 'Item name'),
        'description' => textField($data, 'description', 800, 'Description'),
        'photos' => $photos,
        'price' => moneyField($data, 'price', 0, 100000, 'Price'),
        'category' => enumField($data, 'category', ['Furniture', 'Appliances', 'Electronics', 'Clothing', 'School supplies', 'Other'], 'Category'),
        'condition' => enumField($data, 'condition', ['Like new', 'Good', 'Fair', 'Well loved'], 'Condition'),
        'location' => textField($data, 'location', 100, 'Pickup location'),
        'status' => $existing['status'] ?? 'available',
    ];
}

function validateHelp(array $data, ?array $existing = null): array {
    $date = textField($data, 'date', 10, 'Date');
    $time = textField($data, 'time', 5, 'Time');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) respond(422, ['error' => 'Date is invalid.', 'field' => 'date']);
    if (!preg_match('/^\d{2}:\d{2}$/', $time)) respond(422, ['error' => 'Time is invalid.', 'field' => 'time']);
    return [
        'owner' => $existing['owner'] ?? 'You',
        'title' => textField($data, 'title', 100, 'Title'),
        'type' => enumField($data, 'type', ['Delivery', 'Moving & lifting', 'Hauling', 'Driving'], 'Help type'),
        'from_location' => textField($data, 'from_location', 120, 'From location'),
        'to_location' => textField($data, 'to_location', 120, 'To location'),
        'date' => $date,
        'time' => $time,
        'offer' => moneyField($data, 'offer', 1, 10000, 'Cash offer'),
        'details' => textField($data, 'details', 600, 'Details'),
        'status' => $existing['status'] ?? 'open',
    ];
}

function findIndex(array $items, string $id): int {
    foreach ($items as $index => $item) if (($item['id'] ?? '') === $id) return $index;
    return -1;
}

$resource = $_GET['resource'] ?? '';
$collection = match ($resource) {
    'listings' => 'listings',
    'help' => 'help_posts',
    default => null,
};
if ($collection === null) respond(404, ['error' => 'Unknown resource.']);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$store = readStore();

if ($method === 'GET') respond(200, ['data' => $store[$collection]]);

$id = trim((string)($_GET['id'] ?? ''));
$action = trim((string)($_GET['action'] ?? ''));

if ($method === 'POST' && $id !== '' && $resource === 'listings' && $action === 'sell') {
    $index = findIndex($store[$collection], $id);
    if ($index < 0) respond(404, ['error' => 'Listing not found.']);
    if (($store[$collection][$index]['owner'] ?? '') !== 'You') respond(403, ['error' => 'You can only update your own listing.']);
    $store[$collection][$index]['status'] = 'sold';
    $store[$collection][$index]['updated_at'] = gmdate('c');
    writeStore($store);
    respond(200, ['data' => $store[$collection][$index]]);
}

if ($method === 'POST') {
    $data = body();
    $record = $resource === 'listings' ? validateListing($data) : validateHelp($data);
    $record = array_merge([
        'id' => ($resource === 'listings' ? 'lst_' : 'hlp_') . bin2hex(random_bytes(5)),
    ], $record, ['created_at' => gmdate('c')]);
    array_unshift($store[$collection], $record);
    writeStore($store);
    respond(201, ['data' => $record]);
}

if ($id === '') respond(400, ['error' => 'A post ID is required.']);
$index = findIndex($store[$collection], $id);
if ($index < 0) respond(404, ['error' => 'Post not found.']);
if (($store[$collection][$index]['owner'] ?? '') !== 'You') respond(403, ['error' => 'You can only change your own posts.']);

if ($method === 'PUT') {
    $data = body();
    $existing = $store[$collection][$index];
    $validated = $resource === 'listings' ? validateListing($data, $existing) : validateHelp($data, $existing);
    $store[$collection][$index] = array_merge($existing, $validated, ['updated_at' => gmdate('c')]);
    writeStore($store);
    respond(200, ['data' => $store[$collection][$index]]);
}

if ($method === 'DELETE') {
    $deleted = $store[$collection][$index];
    array_splice($store[$collection], $index, 1);
    writeStore($store);
    respond(200, ['data' => $deleted]);
}

respond(405, ['error' => 'Method not allowed.']);

