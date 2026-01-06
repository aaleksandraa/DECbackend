<?php

// Test capacity endpoint
$month = date('Y-m');
echo "Testing capacity endpoint for month: $month\n\n";

$url = "https://api.frizerino.com/api/v1/appointments/capacity/month?month=$month";
echo "URL: $url\n\n";

// You need to add authentication token here
$token = "YOUR_AUTH_TOKEN_HERE";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Authorization: Bearer ' . $token
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Response:\n";
echo $response;
echo "\n";
