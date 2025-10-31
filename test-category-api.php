<?php
// Quick API test to verify our public Category endpoints

echo "Testing Category API Endpoints\n";
echo "==============================\n\n";

// Test 1: Get all categories
echo "1. GET /api/categories\n";
$url = 'http://localhost:8000/api/categories';
$response = file_get_contents($url);
$data = json_decode($response, true);

if ($data && isset($data['member'])) {
    echo "   ✓ SUCCESS: Found " . count($data['member']) . " categories\n";
    foreach ($data['member'] as $category) {
        echo "   - " . ($category['name'] ?? 'Unknown') . " (slug: " . ($category['slug'] ?? 'N/A') . ")\n";
    }
} else {
    echo "   ✗ FAILED: Unexpected response format\n";
    if ($data) {
        echo "   Available keys: " . implode(', ', array_keys($data)) . "\n";
    }
}

echo "\n";

// Test 2: Get specific category
echo "2. GET /api/categories/technology\n";
$url = 'http://localhost:8000/api/categories/technology';
$response = @file_get_contents($url);

if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['name'])) {
        echo "   ✓ SUCCESS: Found category '" . $data['name'] . "'\n";
        echo "   - ID: " . ($data['id'] ?? 'N/A') . "\n";
        echo "   - Slug: " . ($data['slug'] ?? 'N/A') . "\n";
        echo "   - Level: " . ($data['level'] ?? 'N/A') . "\n";
    } else {
        echo "   ✗ FAILED: Unexpected response format\n";
    }
} else {
    echo "   ✗ FAILED: Could not fetch category\n";
}

echo "\n";

// Test 3: Verify no authentication headers needed
echo "3. Testing without any authentication headers\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => [
            'Accept: application/json'
        ]
    ]
]);

$response = @file_get_contents('http://localhost:8000/api/categories', false, $context);
if ($response) {
    $data = json_decode($response, true);
    if ($data && isset($data['member'])) {
        echo "   ✓ SUCCESS: Categories accessible without authentication (" . count($data['member']) . " items)\n";
    } else {
        echo "   ✓ SUCCESS: Categories endpoint accessible, but format unexpected\n";
    }
} else {
    echo "   ✗ FAILED: Authentication might be required\n";
}

echo "\nDone!\n";
?>