<?php
/**
 * Simple Groq API Key Test
 * Run from terminal: php tests/test_api_key.php
 */

// Load .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }
    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    return $env;
}

echo "🔑 Testing Groq API Key...\n\n";

// Load API key
$env = loadEnv(__DIR__ . '/../.env');
$api_key = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

// Check if key exists
if (!$api_key || $api_key === 'demo_key_placeholder') {
    echo "❌ ERROR: No API key found!\n";
    echo "   Make sure you have GROQ_API_KEY in your .env file\n";
    echo "   Expected format: GROQ_API_KEY=gsk_your_key_here\n\n";
    exit(1);
}

// Check key format
if (strpos($api_key, 'gsk_') !== 0) {
    echo "⚠️  WARNING: API key format looks wrong (should start with 'gsk_')\n";
    echo "   Key preview: " . substr($api_key, 0, 15) . "...\n\n";
} else {
    echo "✅ API key found and format looks correct\n";
    echo "   Key preview: " . substr($api_key, 0, 15) . "...\n\n";
}

// Test API call
echo "🌐 Testing API connection...\n";

$endpoint = 'https://api.groq.com/openai/v1/chat/completions';
$payload = [
    'model' => 'llama-3.3-70b-versatile',
    'messages' => [
        [
            'role' => 'user',
            'content' => 'Respond with exactly: {"status": "working", "test": "success"}'
        ]
    ],
    'max_tokens' => 50,
    'temperature' => 0.1
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => $endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $api_key,
        'Content-Type: application/json'
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false, // Disable for local testing (Windows SSL issues)
    CURLOPT_SSL_VERIFYHOST => false
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

// Check results
echo "\n📊 Results:\n";
echo "   HTTP Status: " . $http_code . "\n";

if ($curl_error) {
    echo "   ❌ CURL Error: " . $curl_error . "\n\n";
    exit(1);
}

if ($http_code === 200) {
    $data = json_decode($response, true);
    if (isset($data['choices'][0]['message']['content'])) {
        echo "   ✅ API is working!\n";
        echo "   Response: " . substr($data['choices'][0]['message']['content'], 0, 100) . "...\n\n";
        echo "✅ SUCCESS: API key is valid and working!\n\n";
        exit(0);
    } else {
        echo "   ⚠️  Unexpected response format\n";
        echo "   Response: " . substr($response, 0, 200) . "...\n\n";
        exit(1);
    }
} elseif ($http_code === 401) {
    echo "   ❌ ERROR: Invalid API key (401 Unauthorized)\n";
    echo "   Check your API key in the .env file\n\n";
    exit(1);
} elseif ($http_code === 402) {
    echo "   ❌ ERROR: Insufficient credits (402 Payment Required)\n";
    echo "   Add credits to your Groq account\n\n";
    exit(1);
} else {
    echo "   ❌ ERROR: HTTP " . $http_code . "\n";
    echo "   Response: " . substr($response, 0, 200) . "...\n\n";
    exit(1);
}
?>

