<?php
// broadcast_helper.php - Helper functions for broadcasting events to SSE clients

/**
 * Broadcast an event to all SSE listeners
 * Uses a simple file-based queue system
 * 
 * @param string $eventType Type of event (new_report, status_change, assignment_change)
 * @param array $data Event data to broadcast
 * @return bool Success status
 */
function broadcastEvent($eventType, $data) {
    try {
        // Create events directory if it doesn't exist
        $eventsDir = __DIR__ . '/sse_events';
        if (!file_exists($eventsDir)) {
            mkdir($eventsDir, 0755, true);
        }
        
        // Create event file with timestamp
        $eventId = time() . '_' . uniqid();
        $eventFile = $eventsDir . '/' . $eventId . '.json';
        
        $eventData = [
            'id' => $eventId,
            'type' => $eventType,
            'data' => $data,
            'timestamp' => date('c')
        ];
        
        file_put_contents($eventFile, json_encode($eventData));
        
        // Clean up old event files (older than 1 hour)
        $files = glob($eventsDir . '/*.json');
        $oneHourAgo = time() - 3600;
        foreach ($files as $file) {
            if (filemtime($file) < $oneHourAgo) {
                @unlink($file);
            }
        }
        
        error_log("📡 Broadcast: $eventType event (ID: $eventId)");
        return true;
        
    } catch (Exception $e) {
        error_log("❌ Broadcast error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get all events since a specific event ID
 * 
 * @param string $lastEventId Last event ID received by client
 * @return array Array of events
 */
function getEventsSince($lastEventId = null) {
    $eventsDir = __DIR__ . '/sse_events';
    
    if (!file_exists($eventsDir)) {
        return [];
    }
    
    $files = glob($eventsDir . '/*.json');
    sort($files); // Sort by filename (which includes timestamp)
    
    $events = [];
    $foundLast = ($lastEventId === null);
    
    foreach ($files as $file) {
        $eventData = json_decode(file_get_contents($file), true);
        
        if (!$foundLast) {
            if ($eventData['id'] === $lastEventId) {
                $foundLast = true;
            }
            continue;
        }
        
        $events[] = $eventData;
    }
    
    return $events;
}
?>

