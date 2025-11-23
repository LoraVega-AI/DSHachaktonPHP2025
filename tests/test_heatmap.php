<?php
// test_heatmap.php - Test heatmap data endpoint

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<html><head><title>Heatmap Endpoint Test</title>";
echo "<style>
    body { font-family: 'Courier New', monospace; background: #0f1419; color: #ffffff; padding: 20px; }
    .success { color: #10b981; }
    .error { color: #ef4444; }
    .warning { color: #fbbf24; }
    .info { color: #3b82f6; }
    pre { background: #1e293b; padding: 15px; border-radius: 8px; overflow-x: auto; }
    h2 { border-bottom: 2px solid #3b82f6; padding-bottom: 10px; }
</style></head><body>";

echo "<h1>🔥 Heatmap Data Endpoint Test</h1>";

// Test 1: Check if file exists
echo "<h2>Test 1: File Existence</h2>";
if (file_exists('../get_heatmap_data.php')) {
    echo "<p class='success'>✅ get_heatmap_data.php exists</p>";
} else {
    echo "<p class='error'>❌ get_heatmap_data.php not found</p>";
    exit;
}

// Test 2: Fetch heatmap data
echo "<h2>Test 2: Fetch Heatmap Data</h2>";
try {
    $url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/../get_heatmap_data.php';
    
    echo "<p class='info'>📡 Fetching from: $url</p>";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'Accept: application/json',
            'timeout' => 10
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "<p class='error'>❌ Failed to fetch data</p>";
        echo "<p class='warning'>Try accessing directly: <a href='../get_heatmap_data.php' target='_blank'>get_heatmap_data.php</a></p>";
    } else {
        echo "<p class='success'>✅ Data fetched successfully</p>";
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "<p class='error'>❌ Invalid JSON response</p>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        } else {
            echo "<p class='success'>✅ Valid JSON response</p>";
            
            // Test 3: Validate response structure
            echo "<h2>Test 3: Response Structure</h2>";
            
            if (isset($data['status'])) {
                echo "<p class='success'>✅ Status field present: " . $data['status'] . "</p>";
            } else {
                echo "<p class='error'>❌ Status field missing</p>";
            }
            
            if ($data['status'] === 'success') {
                if (isset($data['heatmap_data'])) {
                    echo "<p class='success'>✅ Heatmap data field present</p>";
                    echo "<p class='info'>📊 Total points: " . count($data['heatmap_data']) . "</p>";
                    
                    // Show sample data
                    if (count($data['heatmap_data']) > 0) {
                        echo "<h3>Sample Data Points (first 5):</h3>";
                        echo "<pre>";
                        $sample = array_slice($data['heatmap_data'], 0, 5);
                        foreach ($sample as $point) {
                            echo sprintf("Lat: %.6f, Lng: %.6f, Intensity: %.2f\n", 
                                        $point[0], $point[1], $point[2]);
                        }
                        echo "</pre>";
                    } else {
                        echo "<p class='warning'>⚠️ No heatmap points found (database may be empty)</p>";
                    }
                } else {
                    echo "<p class='error'>❌ Heatmap data field missing</p>";
                }
                
                if (isset($data['stats'])) {
                    echo "<p class='success'>✅ Stats field present</p>";
                    echo "<h3>Statistics:</h3>";
                    echo "<pre>" . json_encode($data['stats'], JSON_PRETTY_PRINT) . "</pre>";
                } else {
                    echo "<p class='error'>❌ Stats field missing</p>";
                }
                
                // Test 4: Data validation
                echo "<h2>Test 4: Data Validation</h2>";
                
                $validPoints = 0;
                $invalidPoints = 0;
                
                foreach ($data['heatmap_data'] as $point) {
                    if (is_array($point) && count($point) === 3) {
                        $lat = $point[0];
                        $lng = $point[1];
                        $intensity = $point[2];
                        
                        if (is_numeric($lat) && is_numeric($lng) && is_numeric($intensity) &&
                            $lat >= -90 && $lat <= 90 &&
                            $lng >= -180 && $lng <= 180 &&
                            $intensity >= 0 && $intensity <= 1) {
                            $validPoints++;
                        } else {
                            $invalidPoints++;
                        }
                    } else {
                        $invalidPoints++;
                    }
                }
                
                echo "<p class='success'>✅ Valid points: $validPoints</p>";
                if ($invalidPoints > 0) {
                    echo "<p class='error'>❌ Invalid points: $invalidPoints</p>";
                } else {
                    echo "<p class='success'>✅ No invalid points</p>";
                }
                
            } else {
                echo "<p class='error'>❌ Response status is not 'success'</p>";
                if (isset($data['message'])) {
                    echo "<p class='error'>Error message: " . htmlspecialchars($data['message']) . "</p>";
                }
            }
            
            // Display full response
            echo "<h2>Full Response</h2>";
            echo "<pre>" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Test 5: Test with filters
echo "<h2>Test 5: Test with Filters</h2>";
echo "<p class='info'>🔗 Test different filter combinations:</p>";
echo "<ul>";
echo "<li><a href='../get_heatmap_data.php?severity=CRITICAL' target='_blank'>Critical severity only</a></li>";
echo "<li><a href='../get_heatmap_data.php?severity=HIGH' target='_blank'>High severity only</a></li>";
echo "<li><a href='../get_heatmap_data.php?category=Roads%20%26%20Infrastructure' target='_blank'>Roads & Infrastructure category</a></li>";
echo "<li><a href='../get_heatmap_data.php' target='_blank'>All data (no filters)</a></li>";
echo "</ul>";

echo "<h2>Summary</h2>";
echo "<p class='success'>✅ All tests completed!</p>";
echo "<p class='info'>🗺️ You can now test the heatmap on the <a href='../map.html' target='_blank'>Map Page</a></p>";

echo "</body></html>";
?>

