<?php
// get_weather_data.php - Fetch weather data from OpenWeatherMap API

/**
 * Get weather data for a location
 * Uses OpenWeatherMap Current Weather Data API
 * 
 * @param float $lat Latitude
 * @param float $lon Longitude
 * @return array Weather data or error
 */
function getWeatherData($lat, $lon) {
    // Load API key from .env file
    $env = loadEnv(__DIR__ . '/.env');
    $apiKey = $env['OPENWEATHER_API_KEY'] ?? getenv('OPENWEATHER_API_KEY');
    
    if (!$apiKey || $apiKey === 'your_api_key_here') {
        return [
            'status' => 'error',
            'message' => 'OpenWeatherMap API key not configured'
        ];
    }
    
    // Check cache first (1 hour TTL)
    $cacheFile = __DIR__ . '/weather_cache/' . md5("{$lat}_{$lon}") . '.json';
    $cacheDir = dirname($cacheFile);
    
    if (!file_exists($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $cachedData = json_decode(file_get_contents($cacheFile), true);
        if ($cachedData) {
            error_log("☁️ Weather data from cache for ($lat, $lon)");
            return $cachedData;
        }
    }
    
    // Fetch from API
    $url = "https://api.openweathermap.org/data/2.5/weather?lat={$lat}&lon={$lon}&appid={$apiKey}&units=metric";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("❌ OpenWeatherMap API error: HTTP $httpCode");
        return [
            'status' => 'error',
            'message' => 'Failed to fetch weather data',
            'http_code' => $httpCode
        ];
    }
    
    $weatherData = json_decode($response, true);
    
    if (!$weatherData) {
        return [
            'status' => 'error',
            'message' => 'Invalid weather API response'
        ];
    }
    
    // Extract relevant data
    $result = [
        'status' => 'success',
        'temperature' => $weatherData['main']['temp'] ?? null,
        'humidity' => $weatherData['main']['humidity'] ?? null,
        'pressure' => $weatherData['main']['pressure'] ?? null,
        'description' => $weatherData['weather'][0]['description'] ?? null,
        'wind_speed' => $weatherData['wind']['speed'] ?? null,
        'rain_1h' => $weatherData['rain']['1h'] ?? 0, // Rainfall in last hour (mm)
        'rain_3h' => $weatherData['rain']['3h'] ?? 0, // Rainfall in last 3 hours (mm)
        'timestamp' => time(),
        'location' => [
            'lat' => $lat,
            'lon' => $lon
        ]
    ];
    
    // Cache the result
    file_put_contents($cacheFile, json_encode($result));
    
    error_log("☁️ Weather data fetched for ($lat, $lon): Temp {$result['temperature']}°C, Rain: {$result['rain_1h']}mm/h");
    
    return $result;
}

/**
 * Simple .env file parser
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return [];
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

/**
 * API endpoint
 */
if (php_sapi_name() !== 'cli') {
    header("Content-Type: application/json; charset=UTF-8");
    
    $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
    $lon = isset($_GET['lon']) ? floatval($_GET['lon']) : null;
    
    if ($lat === null || $lon === null) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Latitude and longitude required']);
        exit;
    }
    
    $result = getWeatherData($lat, $lon);
    echo json_encode($result, JSON_PRETTY_PRINT);
}
?>

