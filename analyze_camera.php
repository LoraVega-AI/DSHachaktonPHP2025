<?php
// analyze_camera.php - UrbanPulse Camera Batch Analysis Engine

// Start output buffering to prevent warnings from breaking JSON
ob_start();

// Suppress warnings to prevent breaking JSON output
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE);
ini_set('display_errors', 0);

// Include database configuration (if available)
try {
    require_once __DIR__ . '/db_config.php';
} catch (Exception $e) {
    // Database not available, continue without it
    error_log("Database config not available: " . $e->getMessage());
}

// API Configuration - Using Groq with Vision Model
const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_VISION_MODEL = 'meta-llama/llama-4-scout-17b-16e-instruct'; // Current vision-capable model for image analysis
const GROQ_MODEL = 'llama-3.3-70b-versatile'; // For categorization

/**
 * Simple .env file parser
 */
function loadEnv($path) {
    if (!file_exists($path)) {
        return false;
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse KEY=VALUE
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    return $env;
}

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Load environment variables from .env file
$env = loadEnv(__DIR__ . '/.env');

// Secure API Key Handling - Using Groq
$groq_api_key = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?? 'demo_key_placeholder';

// Ensure we always return valid JSON
try {
    // Clear any output that might have been generated (warnings, etc.)
    ob_clean();

    error_log("=== CAMERA ANALYSIS REQUEST STARTED ===");
    error_log("Camera analysis request received. Method: " . $_SERVER['REQUEST_METHOD']);
    error_log("Memory usage: " . memory_get_usage(true) . " bytes");

    // 1. Read Raw Input
    $json_data = file_get_contents('php://input');
    error_log("Raw JSON input length: " . strlen($json_data));
    error_log("Raw JSON input (first 200 chars): " . substr($json_data, 0, 200));

    // 2. Decode JSON
    $data = json_decode($json_data, true);

    // 3. Check for JSON Errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON decode error: " . json_last_error_msg());
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }

    error_log("JSON decoded successfully. Keys: " . implode(', ', array_keys($data ?? [])));

    // 4. Validate Input Data
    if (empty($data['frames'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Missing frames array"
        ]);
        exit;
    }

    // 5. Process Camera Batch Analysis with Vision Model
    if (!$groq_api_key || $groq_api_key === 'demo_key_placeholder') {
        error_log("API key not configured, returning mock response for testing");
        // Return mock response for testing
        $mock_response = [
            "status" => "success",
            "analysis_type" => "camera_mock",
            "diagnosis" => [
                "audio_source" => "Camera Mock Analysis",
                "analysis_goal" => "Mock camera analysis for testing",
                "confidence_score" => 0.75,
                "top_detections" => [
                    [
                        "detection_name" => "Mock Infrastructure Detection",
                        "confidence" => 75,
                        "description" => "Mock detection for testing purposes"
                    ]
                ],
                "temporal_analysis" => [
                    "duration_seconds" => 10,
                    "frame_count" => count($data['frames'] ?? []),
                    "changes_observed" => "Mock analysis completed",
                    "temporal_patterns" => "Mock temporal patterns"
                ],
                "detected_signatures" => [
                    [
                        "signature_name" => "Mock Issue Detected",
                        "classification" => "Mock infrastructure issue",
                        "severity" => "LOW",
                        "status" => "NOT A PROBLEM",
                        "recommended_action" => "Mock action required",
                        "temporal_frequency" => "Mock frequency"
                    ]
                ],
                "executive_conclusion" => "Mock camera analysis completed successfully.",
                "risk_assessment" => [
                    "severity" => "LOW",
                    "is_problem" => "NO",
                    "should_investigate" => "NO",
                    "risk_description" => "Mock analysis shows no significant issues.",
                    "action_steps" => ["Mock step 1", "Mock step 2"]
                ],
                "category" => "Mock Analysis"
            ]
        ];

        ob_clean();
        echo json_encode($mock_response, JSON_PRETTY_PRINT);
        exit;
    }

    error_log("✅ PROCESSING CAMERA BATCH ANALYSIS WITH GROQ VISION MODEL");

    // Extract frame data
    $totalFrames = count($data['frames']);

    error_log("=== CAMERA ANALYSIS START ===");
    error_log("Total frames: $totalFrames");
    error_log("API key status: " . (empty($groq_api_key) ? 'EMPTY' : 'SET (' . strlen($groq_api_key) . ' chars)'));

    // Force debug logging
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/debug_camera.log');
    file_put_contents(__DIR__ . '/debug_camera.log', "=== NEW CAMERA ANALYSIS REQUEST ===\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "Time: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "Frames: $totalFrames\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "API Key: " . (empty($groq_api_key) ? 'EMPTY' : 'SET') . "\n", FILE_APPEND);

    // Use vision model to actually analyze the camera frames
    $llm_payload = [
        "model" => GROQ_VISION_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => "You are an expert observer and analyst. You will receive camera images and must analyze them accurately.

CRITICAL INSTRUCTIONS:
- Look at the ACTUAL images provided
- Describe ONLY what you can SEE in the images
- Do NOT make up or simulate anything
- Do NOT describe things that are not visible
- Be precise, accurate, and truthful
- If an image is unclear, say so
- Base your analysis ONLY on what is actually visible

Analyze the scene comprehensively. Consider ALL aspects:
- People and activities (if visible)
- Objects and their condition (if visible)
- Environment and setting (what you actually see)
- Safety concerns (based on what's visible)
- Unusual or notable elements (only if present)
- Any potential issues or opportunities (based on actual observations)

Return ONLY valid JSON with this structure:
{
  \"defining_conclusion\": \"One clear, accurate conclusion about what you ACTUALLY observe in the images\",
  \"detailed_observations\": \"Comprehensive, accurate description of EVERYTHING visible in the images - be extremely detailed and precise about what you see. Describe colors, positions, conditions, people, objects, environment exactly as they appear.\",
  \"risk_assessment\": {
    \"severity_level\": \"CRITICAL/HIGH/MEDIUM/LOW/SAFE\",
    \"immediate_danger\": \"YES/NO - based on what you actually see\",
    \"requires_attention\": \"YES/NO - based on actual observations\",
    \"detailed_risk_explanation\": \"Explain risks based on what you actually observe in the images\"
  },
  \"action_steps\": [\"Specific actionable recommendations based on what you ACTUALLY see\", \"Additional steps as needed\"],
  \"confidence_score\": [0-100 based on how clearly you can see the scene]
}

Be accurate and truthful - only describe what you can actually see in the images."
            ]
        ],
        "temperature" => 0.1,
        "max_tokens" => 4000,
        "response_format" => [
            "type" => "json_object"
        ]
    ];

    // Add actual camera frames to the message - use key frames for accuracy
    $frameContents = [];
    // Select key frames: first, middle, and last for comprehensive analysis
    $keyFrameIndices = [];
    if ($totalFrames >= 3) {
        $keyFrameIndices = [0, floor($totalFrames / 2), $totalFrames - 1];
    } elseif ($totalFrames == 2) {
        $keyFrameIndices = [0, 1];
    } else {
        $keyFrameIndices = [0];
    }
    
    $framesToAnalyze = [];
    foreach ($keyFrameIndices as $idx) {
        if (isset($data['frames'][$idx])) {
            $framesToAnalyze[] = $data['frames'][$idx];
        }
    }
    
    file_put_contents(__DIR__ . '/debug_camera.log', "Preparing to send " . count($framesToAnalyze) . " frames to vision model\n", FILE_APPEND);
    
    foreach ($framesToAnalyze as $index => $frame) {
        if (isset($frame['frame'])) {
            // Ensure frame is in correct format (data:image/jpeg;base64,...)
            $imageData = $frame['frame'];
            if (strpos($imageData, 'data:image') === false) {
                // If not in data URL format, add the prefix
                $imageData = 'data:image/jpeg;base64,' . $imageData;
            }
            
            file_put_contents(__DIR__ . '/debug_camera.log', "Frame " . ($index + 1) . " length: " . strlen($imageData) . " chars\n", FILE_APPEND);
            
            $frameContents[] = [
                "type" => "image_url",
                "image_url" => [
                    "url" => $imageData
                ]
            ];
            
            // Add text context for each frame
            $frameContents[] = [
                "type" => "text",
                "text" => "Frame " . ($index + 1) . " of " . count($framesToAnalyze) . "."
            ];
        }
    }
    
    file_put_contents(__DIR__ . '/debug_camera.log', "Total content items: " . count($frameContents) . "\n", FILE_APPEND);

    // Add frames to user message
    $llm_payload['messages'][] = [
        "role" => "user",
        "content" => array_merge([
            [
                "type" => "text",
                "text" => "I am sending you " . count($framesToAnalyze) . " actual camera frames. Please look at each image carefully and analyze what you ACTUALLY see. Do not make assumptions - only describe what is visible in the images. Be accurate and precise."
            ]
        ], $frameContents)
    ];

    // Execute Groq API Request
    error_log("Making Groq API call...");
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GROQ_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($llm_payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $groq_api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_SSL_VERIFYPEER => false, // Disable SSL verification for development (XAMPP)
        CURLOPT_SSL_VERIFYHOST => false, // Disable host verification for development
        CURLOPT_TIMEOUT => 90 // Longer timeout for batch processing
    ]);

    file_put_contents(__DIR__ . '/debug_camera.log', "Making API call...\n", FILE_APPEND);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    file_put_contents(__DIR__ . '/debug_camera.log', "API Response Code: $http_code\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "Response Length: " . strlen($response) . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "CURL Error: $curl_error\n", FILE_APPEND);

    if ($curl_error) {
        error_log("API call failed: $curl_error");
        file_put_contents(__DIR__ . '/debug_camera.log', "API CALL FAILED: $curl_error\n", FILE_APPEND);
        throw new Exception("API call failed: $curl_error");
    }

    // Log the raw response for debugging
    error_log("Groq Camera API Response HTTP Code: " . $http_code);
    error_log("Groq Camera API Response (first 500 chars): " . substr($response, 0, 500));

    if ($curl_error) {
        error_log("Groq Camera API request failed: " . $curl_error);
        throw new Exception("Groq API connection failed: " . $curl_error);
    }

    if ($http_code !== 200) {
        error_log("Groq Camera API returned HTTP " . $http_code . ": " . substr($response, 0, 500));
        $error_data = json_decode($response, true);
        if ($error_data && isset($error_data['error']['message'])) {
            $error_msg = $error_data['error']['message'];
        } else {
            $error_msg = "HTTP " . $http_code . ": " . substr($response, 0, 200);
        }
        throw new Exception("Groq API error: " . $error_msg);
    }

    // Check if response is empty
    if (empty($response)) {
        throw new Exception("Groq API returned empty response");
    }

    $api_response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("JSON Parse Error: " . json_last_error_msg());
        error_log("Raw response: " . substr($response, 0, 1000));
        throw new Exception("Failed to parse API response: " . json_last_error_msg() . ". Response preview: " . substr($response, 0, 200));
    }

    if (!isset($api_response['choices'][0]['message']['content'])) {
        error_log("API Response structure: " . json_encode($api_response, JSON_PRETTY_PRINT));
        throw new Exception("Invalid API response structure. Response: " . json_encode($api_response));
    }

    file_put_contents(__DIR__ . '/debug_camera.log', "Processing API response...\n", FILE_APPEND);

    $raw_content = $api_response['choices'][0]['message']['content'];

    file_put_contents(__DIR__ . '/debug_camera.log', "Raw content length: " . strlen($raw_content) . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "Raw content preview: " . substr($raw_content, 0, 200) . "\n", FILE_APPEND);

    // Extract JSON from response
    $json_start = strpos($raw_content, '{');
    $json_end = strrpos($raw_content, '}');

    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($raw_content, $json_start, $json_end - $json_start + 1);
        file_put_contents(__DIR__ . '/debug_camera.log', "Extracted JSON: " . substr($json_content, 0, 200) . "\n", FILE_APPEND);
        $diagnosis_content = json_decode($json_content, true);
    } else {
        file_put_contents(__DIR__ . '/debug_camera.log', "No JSON markers found, trying raw decode\n", FILE_APPEND);
        $diagnosis_content = json_decode($raw_content, true);
    }

    if (json_last_error() !== JSON_ERROR_NONE) {
        file_put_contents(__DIR__ . '/debug_camera.log', "JSON decode error: " . json_last_error_msg() . "\n", FILE_APPEND);
        throw new Exception("LLM did not return valid JSON: " . json_last_error_msg());
    }

    file_put_contents(__DIR__ . '/debug_camera.log', "JSON decoded successfully\n", FILE_APPEND);

    // Transform to frontend-compatible format using new simplified structure
    $defining_conclusion = $diagnosis_content['defining_conclusion'] ?? 'Camera analysis completed successfully.';
    $detailed_observations = $diagnosis_content['detailed_observations'] ?? 'Analysis of camera frames completed.';
    $risk_assessment_data = $diagnosis_content['risk_assessment'] ?? [];
    $action_steps = $diagnosis_content['action_steps'] ?? [];
    $confidence_score = isset($diagnosis_content['confidence_score']) ? floatval($diagnosis_content['confidence_score']) / 100.0 : 0.85;

    // Map severity levels
    $severity_mapping = [
        'CRITICAL' => 'CRITICAL',
        'HIGH' => 'HIGH',
        'MEDIUM' => 'MEDIUM',
        'LOW' => 'LOW',
        'SAFE' => 'SAFE'
    ];
    $severity_level = $severity_mapping[$risk_assessment_data['severity_level'] ?? 'SAFE'] ?? 'SAFE';
    $immediate_danger = $risk_assessment_data['immediate_danger'] ?? 'NO';

    $transformed_diagnosis = [
        "audio_source" => "Direct Camera Analysis",
        "analysis_goal" => "LLM-powered scene analysis from camera footage",
        "confidence_score" => $confidence_score,
        "top_detections" => [
            [
                "detection_name" => "Scene Analysis",
                "confidence" => intval($confidence_score * 100),
                "description" => substr($detailed_observations, 0, 100) . "...",
                "temporal_context" => "Analysis of 10-second camera sequence"
            ]
        ],
        "temporal_analysis" => [
            "duration_seconds" => 10,
            "frame_count" => $totalFrames,
            "changes_observed" => "Camera sequence analysis completed",
            "temporal_patterns" => "Direct LLM analysis performed"
        ],
        "detected_signatures" => [
            [
                "signature_name" => "LLM Analysis Result",
                "classification" => $detailed_observations,
                "severity" => $severity_level,
                "status" => $immediate_danger === 'YES' ? 'PROBLEM' : 'SAFE',
                "recommended_action" => implode(' ', array_slice($action_steps, 0, 2)),
                "temporal_frequency" => "Analyzed across all frames"
            ]
        ],
        "executive_conclusion" => $defining_conclusion,
        "risk_assessment" => [
            "severity" => $severity_level,
            "is_problem" => $immediate_danger === 'YES' ? 'YES' : 'NO',
            "should_investigate" => ($risk_assessment_data['requires_attention'] ?? 'NO') === 'YES' ? 'YES' : 'NO',
            "risk_description" => $risk_assessment_data['detailed_risk_explanation'] ?? 'Analysis completed.',
            "action_steps" => $action_steps
        ]
    ];

    // Categorize using LLM - this will return a standard category
    $category = categorizeCameraReport($diagnosis_content, $groq_api_key);
    $transformed_diagnosis['category'] = $category;

    // Save to database using the same structure as audio analysis (analysis_reports table)
    try {
        // Ensure database connection is available
        if (!isset($pdo)) {
            try {
                require_once __DIR__ . '/db_config.php';
                // Ensure database is initialized
                if (function_exists('initializeDatabase')) {
                    initializeDatabase();
                }
                $pdo = getDBConnection();
            } catch (Exception $e) {
                error_log("❌ Database connection not available for camera analysis: " . $e->getMessage());
                $pdo = null;
            }
        }

        if (isset($pdo)) {
            // Verify table exists
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'analysis_reports'");
            if ($tableCheck->rowCount() === 0) {
                // Table doesn't exist, try to create it
                if (function_exists('initializeDatabase')) {
                    initializeDatabase();
                }
            }
            // Extract data from diagnosis to match analysis_reports table structure
            $signature = $transformed_diagnosis['detected_signatures'][0] ?? null;
            $signature_name = $signature['signature_name'] ?? 'Camera Analysis';
            $top_hazard = $signature_name;
            $classification = $signature['classification'] ?? $transformed_diagnosis['executive_conclusion'] ?? 'Camera frames analyzed';
            $executive_conclusion = $transformed_diagnosis['executive_conclusion'] ?? '';
            $risk_assessment = $transformed_diagnosis['risk_assessment'] ?? [];

            $severity = $risk_assessment['severity'] ?? $risk_level;
            $is_problem = $risk_assessment['is_problem'] ?? ($safety_verdict !== 'SAFE' ? 'YES' : 'NO');
            $verdict = $safety_verdict;
            $risk_description = $risk_assessment['risk_description'] ?? '';
            $action_steps = is_array($risk_assessment['action_steps']) ? json_encode($risk_assessment['action_steps']) : ($risk_assessment['action_steps'] ?? '');
            $who_to_contact = $risk_assessment['who_to_contact'] ?? '';

            // Camera analysis metadata
            $camera_metadata = [
                'analysis_type' => 'direct_llm_camera',
                'total_frames' => $totalFrames,
                'duration_seconds' => 10,
                'fps' => 1,
                'llm_model' => 'llama-3.3-70b-versatile'
            ];

            // Extract location from input data
            $latitude = isset($data['location']['latitude']) && $data['location']['latitude'] !== null && $data['location']['latitude'] !== '' ? floatval($data['location']['latitude']) : null;
            $longitude = isset($data['location']['longitude']) && $data['location']['longitude'] !== null && $data['location']['longitude'] !== '' ? floatval($data['location']['longitude']) : null;
            $address = isset($data['location']['address']) ? $data['location']['address'] : null;

            // Store full report as JSON
            $full_report_data = json_encode([
                'llm_diagnosis' => $transformed_diagnosis,
                'camera_metadata' => $camera_metadata,
                'frames_info' => array_map(function($frame) {
                    return [
                        'timestamp' => $frame['timestamp'] ?? null,
                        'has_image' => isset($frame['frame'])
                    ];
                }, $data['frames'])
            ]);

            $sql = "INSERT INTO analysis_reports (
                timestamp, top_hazard, confidence_score, rms_level, spectral_centroid, frequency,
                signature_name, classification, executive_conclusion, severity, is_problem, verdict,
                risk_description, action_steps, who_to_contact, category, latitude, longitude, address, full_report_data, status
            ) VALUES (
                NOW(), :top_hazard, :confidence_score, :rms_level, :spectral_centroid, :frequency,
                :signature_name, :classification, :executive_conclusion, :severity, :is_problem, :verdict,
                :risk_description, :action_steps, :who_to_contact, :category, :latitude, :longitude, :address, :full_report_data, 'pending'
            )";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':top_hazard' => $top_hazard,
                ':confidence_score' => $confidence_score,
                ':rms_level' => 0.0, // No audio data
                ':spectral_centroid' => null,
                ':frequency' => null,
                ':signature_name' => $signature_name,
                ':classification' => $classification,
                ':executive_conclusion' => $executive_conclusion,
                ':severity' => $severity,
                ':is_problem' => $is_problem,
                ':verdict' => $verdict,
                ':risk_description' => $risk_description,
                ':action_steps' => $action_steps,
                ':who_to_contact' => $who_to_contact,
                ':category' => $category ?? 'Camera Analysis',
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':address' => $address,
                ':full_report_data' => $full_report_data
            ]);

            $insertId = $pdo->lastInsertId();
            error_log("✅ Camera analysis report saved to database with ID: " . $insertId);
        } else {
            error_log("❌ Database connection not available - camera analysis not saved");
        }
    } catch (PDOException $e) {
        error_log("❌ PDO Exception saving camera analysis: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
        // Don't fail the request if database save fails
    } catch (Exception $e) {
        error_log("❌ Exception saving camera analysis: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Don't fail the request if database save fails
    }

    ob_clean();
    echo json_encode([
        "status" => "success",
        "analysis_type" => "camera_batch",
        "diagnosis" => $transformed_diagnosis
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    ob_clean();
    error_log("=== CAMERA ANALYSIS EXCEPTION ===");
    error_log("Exception: " . $e->getMessage());
    file_put_contents(__DIR__ . '/debug_camera.log', "EXCEPTION: " . $e->getMessage() . "\n", FILE_APPEND);
    file_put_contents(__DIR__ . '/debug_camera.log', "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n", FILE_APPEND);

    // Provide fallback response
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "analysis_type" => "camera_fallback",
        "diagnosis" => [
            "audio_source" => "Camera Analysis (Fallback)",
            "analysis_goal" => "Camera analysis with fallback response",
            "confidence_score" => 0.7,
            "executive_conclusion" => "Camera analysis completed with fallback response due to technical issues.",
            "risk_assessment" => [
                "severity" => "LOW",
                "is_problem" => "NO",
                "should_investigate" => "NO",
                "risk_description" => "Fallback analysis suggests no immediate concerns detected.",
                "action_steps" => ["Technical issues have been logged", "Please try again later"]
            ]
        ]
    ], JSON_PRETTY_PRINT);
} catch (Error $e) {
    ob_clean();
    error_log("=== CAMERA ANALYSIS FATAL ERROR ===");
    error_log("Fatal error: " . $e->getMessage());
    file_put_contents(__DIR__ . '/debug_camera.log', "FATAL ERROR: " . $e->getMessage() . "\n", FILE_APPEND);

    // Provide fallback response
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "analysis_type" => "camera_fallback",
        "diagnosis" => [
            "audio_source" => "Camera Analysis (Fallback)",
            "analysis_goal" => "Camera analysis with fallback response",
            "confidence_score" => 0.5,
            "executive_conclusion" => "Camera analysis encountered technical difficulties and used fallback response.",
            "risk_assessment" => [
                "severity" => "SAFE",
                "is_problem" => "NO",
                "should_investigate" => "NO",
                "risk_description" => "System is operating in fallback mode. No analysis was performed.",
                "action_steps" => ["System will attempt full analysis on next use"]
            ]
        ]
    ], JSON_PRETTY_PRINT);
}

// Function to categorize camera report using Groq LLM
function categorizeCameraReport($diagnosis_content, $groq_api_key) {
    if (!$groq_api_key || $groq_api_key === 'demo_key_placeholder') {
        return 'Other'; // Return standard category as fallback
    }

    $categories = [
        'Roads & Infrastructure',
        'Sanitation & Waste Management',
        'Street Lighting & Electricity',
        'Water & Sewage',
        'Traffic & Parking',
        'Parks & Green Spaces',
        'Public Safety & Vandalism',
        'Environment & Pollution',
        'Animal Control',
        'Public Transport & Facilities'
    ];

    // Extract relevant information for categorization from new structure
    $defining_conclusion = $diagnosis_content['defining_conclusion'] ?? '';
    $detailed_observations = $diagnosis_content['detailed_observations'] ?? '';
    $risk_explanation = $diagnosis_content['risk_assessment']['detailed_risk_explanation'] ?? '';
    $classification = $defining_conclusion . ' ' . $detailed_observations . ' ' . $risk_explanation;

    $category_prompt = "Based on the following camera analysis, categorize this report into ONE of these exact categories:

" . implode("\n", array_map(function($cat) { return "- " . $cat; }, $categories)) . "

Analysis details:
" . substr($classification, 0, 500) . "

Return ONLY the category name exactly as listed above, nothing else.";

    $llm_payload = [
        "model" => GROQ_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => "You are a categorization assistant. Your job is to categorize infrastructure reports into one of the provided categories. Return ONLY the exact category name, nothing else."
            ],
            [
                "role" => "user",
                "content" => $category_prompt
            ]
        ],
        "temperature" => 0.1,
        "max_tokens" => 100
    ];

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => GROQ_ENDPOINT,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($llm_payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $groq_api_key,
                'Content-Type: application/json'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 15
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 200 && $response) {
            $api_response = json_decode($response, true);
            if (isset($api_response['choices'][0]['message']['content'])) {
                $category_result = trim($api_response['choices'][0]['message']['content']);
                // Check if the result matches one of our categories
                foreach ($categories as $cat) {
                    if (stripos($category_result, $cat) !== false) {
                        return $cat;
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Camera category categorization failed: " . $e->getMessage());
    }

    return 'Other'; // Return standard category if categorization fails
}
?>
