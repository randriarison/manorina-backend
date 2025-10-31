<?php
// Simple test script to verify Article creation with Category cascade persist

echo "Testing Article Creation with Category Cascade Persist\n";
echo "=====================================================\n\n";

// Test 1: Create Article with existing category by IRI and new category
$testData1 = [
    'title' => 'API Test Article',
    'content' => 'Testing cascade persist functionality via API',
    'articleType' => 'simple',
    'status' => 'published',
    'category' => [
        '/api/categories/technology',  // Existing category by IRI
        [
            'name' => 'API Testing'    // New category to be created
        ]
    ]
];

echo "Test 1: POST /api/articles with mixed categories\n";
echo "Request Body:\n";
echo json_encode($testData1, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Create Article with only new categories  
$testData2 = [
    'title' => 'Another API Test',
    'content' => 'Testing with only new categories',
    'articleType' => 'simple', 
    'status' => 'published',
    'category' => [
        ['name' => 'Web Development'],
        ['name' => 'Backend Development']
    ]
];

echo "Test 2: POST /api/articles with only new categories\n";
echo "Request Body:\n";
echo json_encode($testData2, JSON_PRETTY_PRINT) . "\n\n";

echo "You can test these endpoints using:\n";
echo "1. Postman\n";
echo "2. curl commands\n";  
echo "3. Browser with API Platform interface at http://localhost:8000/api\n\n";

echo "Example curl command:\n";
echo "curl -X POST http://localhost:8000/api/articles \\\n";
echo '  -H "Content-Type: application/json" \\' . "\n";
echo '  -d \'' . json_encode($testData1, JSON_UNESCAPED_SLASHES) . "'\n\n";

echo "Note: You may need to authenticate first depending on your security configuration.\n";
?>