<?php
// sse_stream.php - Server-Sent Events endpoint for real-time updates

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Disable nginx buffering

// Prevent PHP from timing out
set_time_limit(0);
ini_set('max_execution_time', 0);

require_once __DIR__ . '/broadcast_helper.php';
require_once __DIR__ . '/auth.php';

// Check authentication (optional - uncomment to require auth)
// startSession();
// if (!isLoggedIn()) {
//     echo "event: error\n";
//     echo "data: {\"message\": \"Authentication required\"}\n\n";
//     ob_end_flush();
//     flush();
//     exit;
// }

// Get last event ID from query parameter
$lastEventId = isset($_GET['lastEventId']) ? $_GET['lastEventId'] : null;

// Function to send an SSE message
function sendSSE($id, $event, $data) {
    echo "id: $id\n";
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    
    // Flush output buffers
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
}

// Send initial connection message
sendSSE(time(), 'connected', [
    'message' => 'SSE connection established',
    'timestamp' => date('c')
]);

// Main SSE loop
$lastCheck = time();
$iteration = 0;

while (true) {
    $iteration++;
    
    // Check for new events every second
    if (time() - $lastCheck >= 1) {
        $lastCheck = time();
        
        // Get new events since last check
        $events = getEventsSince($lastEventId);
        
        foreach ($events as $event) {
            sendSSE(
                $event['id'],
                $event['type'],
                $event['data']
            );
            
            // Update last event ID
            $lastEventId = $event['id'];
        }
    }
    
    // Send periodic heartbeat (every 30 seconds) to keep connection alive
    if ($iteration % 30 === 0) {
        sendSSE(time(), 'heartbeat', [
            'timestamp' => date('c')
        ]);
    }
    
    // Check if client disconnected
    if (connection_aborted()) {
        error_log("SSE client disconnected");
        break;
    }
    
    // Sleep for 1 second
    sleep(1);
    
    // Stop after 5 minutes (clients should reconnect)
    if ($iteration > 300) {
        sendSSE(time(), 'timeout', [
            'message' => 'Connection timeout, please reconnect'
        ]);
        break;
    }
}
?>

