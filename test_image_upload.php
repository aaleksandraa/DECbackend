<?php

/**
 * Test script to understand how Laravel handles file uploads
 * This simulates what happens when FormData sends images[]
 */

// Simulate $_FILES array when FormData sends images[]
$_FILES = [
    'images' => [
        'name' => ['image1.jpg', 'image2.jpg'],
        'type' => ['image/jpeg', 'image/jpeg'],
        'tmp_name' => ['/tmp/php123', '/tmp/php456'],
        'error' => [0, 0],
        'size' => [1024, 2048]
    ]
];

echo "=== Testing File Upload Handling ===\n\n";

echo "When FormData sends 'images[]', PHP receives:\n";
echo "\$_FILES structure:\n";
print_r($_FILES);

echo "\n\nLaravel's Request::file('images') would return:\n";
echo "An array of UploadedFile objects\n";

echo "\n\nKey points:\n";
echo "1. FormData.append('images[]', file) creates an array in PHP\n";
echo "2. Laravel's Request::file('images') returns array of files\n";
echo "3. Validation rule 'images' => 'array' checks if it's an array\n";
echo "4. Validation rule 'images.*' validates each file in the array\n";

echo "\n=== Test Complete ===\n";
