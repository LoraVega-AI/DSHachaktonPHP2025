<?php
// API Key Status Page

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

$env = loadEnv(__DIR__ . '/.env');
$openrouter_api_key = $env['OPENROUTER_API_KEY'] ?? getenv('OPENROUTER_API_KEY');
?>

<!DOCTYPE html>
<html>
<head>
    <title>API Key Status - CISA v4.0</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
        pre { background: #f8f8f8; padding: 10px; border-radius: 3px; overflow-x: auto; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        button:hover { background: #0056b3; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>🔑 CISA v4.0 API Key Status</h1>

    <div class="status <?php
        if ($openrouter_api_key && $openrouter_api_key !== 'demo_key_placeholder' && strpos($openrouter_api_key, 'sk-or-v1-') === 0) {
            echo 'success';
        } elseif ($openrouter_api_key && $openrouter_api_key !== 'demo_key_placeholder') {
            echo 'warning';
        } else {
            echo 'error';
        }
    ?>">
        <h3>API Key Configuration Status</h3>
        <p><strong>.env file exists:</strong> <?php echo file_exists(__DIR__ . '/.env') ? '✅ YES' : '❌ NO'; ?></p>
        <p><strong>API key from .env:</strong> <?php echo !empty($env['OPENROUTER_API_KEY']) ? '✅ PRESENT' : '❌ MISSING'; ?></p>
        <p><strong>API key from environment:</strong> <?php echo !empty(getenv('OPENROUTER_API_KEY')) ? '✅ PRESENT' : '❌ MISSING'; ?></p>
        <p><strong>Final API key status:</strong> <?php
            if ($openrouter_api_key && $openrouter_api_key !== 'demo_key_placeholder' && strpos($openrouter_api_key, 'sk-or-v1-') === 0) {
                echo '✅ CONFIGURED AND VALID';
            } elseif ($openrouter_api_key && $openrouter_api_key !== 'demo_key_placeholder') {
                echo '⚠️ CONFIGURED BUT FORMAT MAY BE WRONG';
            } else {
                echo '❌ NOT CONFIGURED';
            }
        ?></p>

        <?php if ($openrouter_api_key): ?>
        <p><strong>Key format check:</strong> <?php echo strpos($openrouter_api_key, 'sk-or-v1-') === 0 ? '✅ Correct (starts with sk-or-v1-)' : '❌ Incorrect format'; ?></p>
        <p><strong>Key length:</strong> <?php echo strlen($openrouter_api_key); ?> characters</p>
        <p><strong>Key preview:</strong> <?php echo substr($openrouter_api_key, 0, 20) . '...'; ?></p>
        <?php endif; ?>
    </div>

    <?php if (!$openrouter_api_key || $openrouter_api_key === 'demo_key_placeholder' || strpos($openrouter_api_key, 'sk-or-v1-') !== 0): ?>
    <div class="status error">
        <h3>🔧 How to Fix API Key</h3>
        <ol>
            <li>Go to <a href="https://openrouter.ai/keys" target="_blank">https://openrouter.ai/keys</a></li>
            <li>Create or copy your API key</li>
            <li>Create a file named <code>.env</code> in this directory</li>
            <li>Add this line: <code>OPENROUTER_API_KEY=sk-or-v1-your-actual-key-here</code></li>
            <li>Replace <code>your-actual-key-here</code> with your real key</li>
            <li>Refresh this page</li>
        </ol>
    </div>
    <?php endif; ?>

    <div class="test-section">
        <h3>🧪 Test LLM Connection</h3>
        <p>Click below to test if your API key works with the LLM:</p>
        <button onclick="testAPI()">Test API Connection</button>
        <div id="test-results"></div>
    </div>

    <div class="test-section">
        <h3>📋 Next Steps</h3>
        <p>Once your API key is working:</p>
        <ol>
            <li>Go back to the main acoustic analysis page</li>
            <li>Start audio analysis</li>
            <li>You should now see detailed CISA v4.0 reports instead of generic responses</li>
        </ol>
    </div>

    <script>
        async function testAPI() {
            const resultsDiv = document.getElementById('test-results');
            resultsDiv.innerHTML = '<div class="status info">Testing API connection...</div>';

            try {
                const response = await fetch('test_llm_simple.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' }
                });

                const data = await response.json();

                let html = '<div class="status ' + (data.http_code === 200 ? 'success' : 'error') + '">';
                html += '<h4>API Test Results</h4>';
                html += '<p><strong>HTTP Status:</strong> ' + data.http_code + '</p>';
                html += '<p><strong>Response Length:</strong> ' + data.response_length + ' characters</p>';

                if (data.curl_error) {
                    html += '<p><strong>CURL Error:</strong> ' + data.curl_error + '</p>';
                }

                if (data.http_code === 200 && data.response?.choices?.[0]?.message?.content) {
                    html += '<p><strong>✅ SUCCESS:</strong> LLM responded!</p>';
                    html += '<p><strong>Response:</strong></p>';
                    html += '<pre>' + data.response.choices[0].message.content.substring(0, 200) + '...</pre>';

                    try {
                        const llmResponse = JSON.parse(data.response.choices[0].message.content);
                        html += '<p><strong>✅ JSON parsing successful!</strong></p>';
                        if (llmResponse.unified_sound_event_identification) {
                            html += '<p><strong>🎉 CISA v4.0 format detected!</strong></p>';
                        }
                    } catch (e) {
                        html += '<p><strong>❌ JSON parsing failed:</strong> ' + e.message + '</p>';
                        html += '<p><strong>Issue:</strong> LLM not returning pure JSON format</p>';
                    }
                } else if (data.http_code === 401) {
                    html += '<p><strong>❌ API Key Invalid:</strong> Check your OpenRouter API key</p>';
                } else if (data.http_code === 402) {
                    html += '<p><strong>❌ Insufficient Credits:</strong> Add credits to OpenRouter account</p>';
                } else {
                    html += '<p><strong>❌ API Error:</strong> HTTP ' + data.http_code + '</p>';
                }

                if (data.raw_response && data.raw_response.length > 500) {
                    html += '<details><summary>Show Full Raw Response</summary>';
                    html += '<pre>' + data.raw_response + '</pre></details>';
                }

                html += '</div>';
                resultsDiv.innerHTML = html;

            } catch (error) {
                resultsDiv.innerHTML = '<div class="status error"><h4>Test Failed</h4><p>Error: ' + error.message + '</p></div>';
            }
        }
    </script>
</body>
</html>
