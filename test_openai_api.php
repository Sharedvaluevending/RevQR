<?php
$api_key = 'sk-243ea165c22b4c3ca4992c29220a95f1';
$api_url = 'https://api.deepseek.com/chat/completions';

echo "Testing DeepSeek AI API...\n";
echo "API Key: " . substr($api_key, 0, 10) . "...\n\n";

$data = [
    'model' => 'deepseek-chat',
    'messages' => [
        [
            'role' => 'system',
            'content' => 'You are a helpful assistant.'
        ],
        [
            'role' => 'user',
            'content' => 'Say "API is working" if you can respond.'
        ]
    ],
    'max_tokens' => 50,
    'temperature' => 0.7
];

$headers = [
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Status Code: " . $http_code . "\n";
echo "cURL Error: " . ($curl_error ?: 'None') . "\n";
echo "Response: " . $response . "\n\n";

if ($http_code === 200 && $response) {
    $decoded = json_decode($response, true);
    if (isset($decoded['choices'][0]['message']['content'])) {
        echo "✅ API WORKING: " . $decoded['choices'][0]['message']['content'] . "\n";
    } else {
        echo "❌ API Response format unexpected\n";
        echo "Decoded response: " . print_r($decoded, true) . "\n";
    }
} else {
    echo "❌ API NOT WORKING\n";
    if ($response) {
        $error = json_decode($response, true);
        if (isset($error['error'])) {
            echo "Error: " . $error['error']['message'] . "\n";
        }
    }
}
?> 