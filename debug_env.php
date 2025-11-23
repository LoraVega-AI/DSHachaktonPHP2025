<?php
// Debug .env file content

echo "=== .ENV FILE DEBUG ===\n\n";

$envPath = __DIR__ . '/.env';

if (file_exists($envPath)) {
    echo "✅ .env file exists at: $envPath\n\n";
    echo "RAW FILE CONTENT:\n";
    echo "----------------\n";
    $content = file_get_contents($envPath);
    echo $content . "\n";
    echo "----------------\n\n";

    // Parse the content
    echo "PARSED CONTENT:\n";
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) {
            echo "Comment: $line\n";
            continue;
        }

        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            echo "Key: '$key' = '" . substr($value, 0, 20) . "...' (length: " . strlen($value) . ")\n";
        } else {
            echo "Invalid line: $line\n";
        }
    }
} else {
    echo "❌ .env file does NOT exist at: $envPath\n";
    echo "Creating template .env file...\n";

    $template = "# OpenRouter API Configuration\n# Get your API key from: https://openrouter.ai/keys\nOPENROUTER_API_KEY=your-api-key-here\n";

    if (file_put_contents($envPath, $template)) {
        echo "✅ Created template .env file\n";
        echo "Edit it with your actual OpenRouter API key\n";
    } else {
        echo "❌ Failed to create .env file\n";
    }
}

echo "\n=== END DEBUG ===\n";
?>
