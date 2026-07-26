<?php
$ch = curl_init('http://127.0.0.1:5001/health');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
echo 'Error: ' . curl_error($ch) . "\n";
echo 'Response: ' . $response;