<?php
// Check Groq API Key Configuration

echo "=== GROQ API KEY CONFIGURATION CHECK ===\n\n";

// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    return $env;
}

// Check .env file
echo "1. Checking .env file...\n";
$envFilePath = __DIR__ . '/.env';
if (file_exists($envFilePath)) {
    echo "   ✅ .env file exists at: $envFilePath\n";
    $env = loadEnv($envFilePath);
    $apiKeyFromEnv = $env['GROQ_API_KEY'] ?? null;
    if ($apiKeyFromEnv) {
        echo "   ✅ GROQ_API_KEY found in .env file\n";
        echo "   🔑 Key starts with: " . substr($apiKeyFromEnv, 0, 15) . "...\n";
        echo "   🔍 Key length: " . strlen($apiKeyFromEnv) . " characters\n";
        if (strpos($apiKeyFromEnv, 'gsk_') === 0) {
            echo "   ✅ Key format looks correct (starts with gsk_)\n";
        } else {
            echo "   ⚠️  Key format may be incorrect (should start with gsk_)\n";
        }
    } else {
        echo "   ❌ GROQ_API_KEY not found in .env file\n";
    }
} else {
    echo "   ❌ .env file does NOT exist at: $envFilePath\n";
    echo "   📝 Create .env file with: GROQ_API_KEY=your-key-here\n";
}

// Check environment variable
echo "\n2. Checking environment variable...\n";
$apiKeyFromEnvVar = getenv('GROQ_API_KEY');
if ($apiKeyFromEnvVar) {
    echo "   ✅ GROQ_API_KEY environment variable is set\n";
    echo "   🔑 Key starts with: " . substr($apiKeyFromEnvVar, 0, 15) . "...\n";
} else {
    echo "   ❌ GROQ_API_KEY environment variable is NOT set\n";
}

// Final status
echo "\n3. Final Configuration Status:\n";
$env = loadEnv(__DIR__ . '/.env');
$finalApiKey = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

if ($finalApiKey && $finalApiKey !== 'demo_key_placeholder' && strpos($finalApiKey, 'gsk_') === 0) {
    echo "   ✅ GROQ API KEY IS CONFIGURED AND LOOKS VALID\n";
    echo "   🚀 The system should use LLM analysis\n";
} else {
    echo "   ❌ GROQ API KEY IS NOT PROPERLY CONFIGURED\n";
    echo "   📝 Fix: Create .env file with valid Groq API key\n";
    echo "   🔗 Get key from: https://console.groq.com/keys\n";
}

// Test API call if key is configured
if ($finalApiKey && $finalApiKey !== 'demo_key_placeholder' && strpos($finalApiKey, 'gsk_') === 0) {
    echo "\n4. Testing Groq API connection...\n";

    $test_payload = [
        "model" => "llama-3.3-70b-versatile",
        "messages" => [
            ["role" => "user", "content" => "Respond with: {\"status\": \"Groq API working\"}"]
        ],
        "max_tokens" => 20,
        "temperature" => 0.1
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.groq.com/openai/v1/chat/completions',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($test_payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $finalApiKey,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "   HTTP Status: $http_code\n";

    if ($http_code === 200) {
        $api_response = json_decode($response, true);
        if (isset($api_response['choices'][0]['message']['content'])) {
            echo "   ✅ Groq API call successful!\n";
            echo "   🤖 LLM Response: " . $api_response['choices'][0]['message']['content'] . "\n";
        } else {
            echo "   ❌ Unexpected Groq API response format\n";
        }
    } elseif ($http_code === 401) {
        echo "   ❌ API Key Invalid (401 Unauthorized)\n";
        echo "   🔑 Check your Groq API key is correct\n";
    } elseif ($http_code === 429) {
        echo "   ❌ Rate Limited (429 Too Many Requests)\n";
        echo "   ⏱️  Try again later\n";
    } else {
        echo "   ❌ Groq API Error: HTTP $http_code\n";
        echo "   📄 Response: " . substr($response, 0, 100) . "...\n";
    }
}

echo "\n=== GROQ API CONFIGURATION CHECK COMPLETE ===\n";
?>
