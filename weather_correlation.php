<?php
// weather_correlation.php - Correlate reports with weather conditions

require_once __DIR__ . '/get_weather_data.php';
require_once __DIR__ . '/db_config.php';

/**
 * Check weather correlation for a report and adjust severity if needed
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @param string $category Report category
 * @param float $latitude Report latitude
 * @param float $longitude Report longitude
 * @param string $severity Current severity
 * @return array Correlation results
 */
function correlateWithWeather($reportId, $reportType, $category, $latitude, $longitude, $severity) {
    // Only correlate for relevant categories
    $weatherSensitiveCategories = ['Water & Sewage', 'Roads & Infrastructure', 'Roads', 'Environment & Pollution'];
    
    if (!in_array($category, $weatherSensitiveCategories)) {
        return [
            'status' => 'skipped',
            'message' => 'Category not weather-sensitive'
        ];
    }
    
    // Get weather data
    $weatherData = getWeatherData($latitude, $longitude);
    
    if ($weatherData['status'] !== 'success') {
        return [
            'status' => 'error',
            'message' => 'Failed to fetch weather data'
        ];
    }
    
    // Check for heavy rainfall (threshold: 10mm in last 3 hours)
    $rainfall = max($weatherData['rain_1h'], $weatherData['rain_3h'] / 3);
    $heavyRain = $rainfall >= 10;
    
    $severityBonus = false;
    $newSeverity = $severity;
    $reason = '';
    
    if ($heavyRain && in_array($category, ['Water & Sewage', 'Roads & Infrastructure', 'Roads'])) {
        // Increase severity priority due to weather
        $severityMap = ['LOW' => 'MEDIUM', 'MEDIUM' => 'HIGH', 'HIGH' => 'CRITICAL'];
        $newSeverity = $severityMap[$severity] ?? $severity;
        
        if ($newSeverity !== $severity) {
            $severityBonus = true;
            $reason = "Heavy rainfall detected ({$rainfall}mm/h) - severity increased due to weather correlation";
        }
    }
    
    // Update report with weather data
    try {
        $pdo = getDBConnection();
        $table = $reportType === 'analysis' ? 'analysis_reports' : 'general_reports';
        
        // Store weather data as JSON
        $weatherJson = json_encode([
            'temperature' => $weatherData['temperature'],
            'humidity' => $weatherData['humidity'],
            'description' => $weatherData['description'],
            'rainfall_mm' => $rainfall,
            'heavy_rain' => $heavyRain,
            'fetched_at' => date('c')
        ]);
        
        $sql = "UPDATE $table SET weather_data = :weather";
        $params = [':weather' => $weatherJson];
        
        // Update severity if bonus applied
        if ($severityBonus) {
            $sql .= ", severity = :severity";
            $params[':severity'] = $newSeverity;
        }
        
        $sql .= " WHERE id = :id";
        $params[':id'] = $reportId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        if ($severityBonus) {
            error_log("🌧️ Weather correlation: Report #$reportId severity increased from $severity to $newSeverity due to heavy rain");
        } else {
            error_log("☁️ Weather data stored for report #$reportId");
        }
        
        return [
            'status' => 'success',
            'weather_correlated' => true,
            'severity_adjusted' => $severityBonus,
            'old_severity' => $severity,
            'new_severity' => $newSeverity,
            'reason' => $reason,
            'weather' => $weatherData
        ];
        
    } catch (PDOException $e) {
        error_log("❌ Weather correlation error: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}
?>

