<?php
echo "Raw API Response Debug\n";
echo "=====================\n\n";

// Get the raw response from categories endpoint
$url = 'http://localhost:8000/api/categories';
$response = @file_get_contents($url);

echo "URL: $url\n";
echo "Response received: " . ($response ? "YES" : "NO") . "\n";

if ($response) {
    echo "Response length: " . strlen($response) . " characters\n\n";
    echo "Raw response:\n";
    echo $response . "\n\n";
    
    $data = json_decode($response, true);
    if ($data) {
        echo "JSON decoded successfully\n";
        echo "Keys in response: " . implode(', ', array_keys($data)) . "\n";
        
        if (isset($data['hydra:member'])) {
            echo "Found hydra:member with " . count($data['hydra:member']) . " items\n";
        } elseif (isset($data['@type']) && $data['@type'] === 'hydra:Collection') {
            echo "It's a Hydra collection\n";
        }
    } else {
        echo "JSON decode failed\n";
        echo "JSON error: " . json_last_error_msg() . "\n";
    }
} else {
    $error = error_get_last();
    echo "Error fetching response: " . ($error['message'] ?? 'Unknown error') . "\n";
}
?>