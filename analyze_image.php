<?php
// analyze_image.php - UrbanPulse Infrastructure Image Analysis Engine

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
$groq_api_key = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

// Ensure we always return valid JSON
try {
    // Clear any output that might have been generated (warnings, etc.)
    ob_clean();
    
    // 1. Read Raw Input
    $json_data = file_get_contents('php://input');

    // 2. Decode JSON
    $data = json_decode($json_data, true);

    // 3. Check for JSON Errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }

    // 4. Validate Image Data
    if (empty($data['image_base64'])) {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Missing image_base64 field"
        ]);
        exit;
    }

    // 5. Process Image Analysis with Vision Model
    if (!$groq_api_key || $groq_api_key === 'demo_key_placeholder') {
        http_response_code(400);
        echo json_encode([
            "status" => "error",
            "message" => "Groq API key not configured. Image analysis requires API key."
        ]);
        exit;
    }

    error_log("✅ PROCESSING IMAGE ANALYSIS WITH GROQ VISION MODEL");
    
    // Prepare image data URL for LLM (Groq vision models support base64 images)
    $image_data_url = 'data:image/jpeg;base64,' . $data['image_base64'];
    
    // Create LLM payload with vision support
    $llm_payload = [
        "model" => GROQ_VISION_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => "You are an infrastructure safety analyst helping to identify potential infrastructure issues through image analysis. Write clear, human-readable reports that anyone can understand.

CRITICAL RULES:
- NEVER use overly technical terms unless you explain them in simple words
- ALWAYS explain what you see in the image and what it means in everyday language
- Use simple words: 'crack in the wall' not 'structural fissure'
- Write as if explaining to a neighbor or property manager who has no technical background
- Focus on: What do you see? What is the issue? Is it dangerous? What should be done?

REQUIRED OUTPUT FORMAT - Return ONLY valid JSON:

{
  \"unified_sound_event_identification\": {
    \"primary_sound_event\": \"[Description of what you see in the image - e.g., 'Crack in concrete wall', 'Leaking pipe', 'Damaged equipment']\",
    \"detected_signatures\": [
      {
        \"signature_name\": \"[Name of the issue detected]\",
        \"classification\": \"[Detailed description of what you see]\",
        \"severity\": \"[CRITICAL, HIGH, LOW, or SAFE]\",
        \"status\": \"[PROBLEM or NOT A PROBLEM]\",
        \"recommended_action\": \"[What should be done about this issue]\"
      }
    ]
  },
  \"conclusion_and_safety_verdict\": {
    \"verdict\": \"[DANGEROUS, ATTENTION, or SAFE]\",
    \"analysis_summary\": \"[2-3 sentence summary of what you see and whether it's a problem]\",
    \"recommended_action\": \"[Specific action steps to address the issue]\"
  },
  \"risk_assessment\": {
    \"severity\": \"[CRITICAL, HIGH, or LOW]\",
    \"is_problem\": \"[YES or NO]\",
    \"should_investigate\": \"[YES or NO]\",
    \"risk_description\": \"[2-3 sentence explanation of why this is or isn't a problem]\",
    \"action_steps\": [
      \"[Step 1]\",
      \"[Step 2]\",
      \"[Step 3]\"
    ]
  }
}

IMPORTANT:
- Analyze the image carefully for any signs of infrastructure issues, damage, wear, or safety concerns
- Be specific about what you see (cracks, leaks, corrosion, damage, etc.)
- Assess the severity based on what could happen if the issue is not addressed
- Provide clear, actionable recommendations
- Return ONLY the specified JSON structure with clear, readable content"
            ],
            [
                "role" => "user",
                "content" => [
                    [
                        "type" => "text",
                        "text" => "Analyze this infrastructure image for potential issues, damage, or safety concerns. Provide a detailed analysis following the required JSON format."
                    ],
                    [
                        "type" => "image_url",
                        "image_url" => [
                            "url" => $image_data_url
                        ]
                    ]
                ]
            ]
        ],
        "temperature" => 0.1,
        "max_tokens" => 4000,
        "response_format" => [
            "type" => "json_object"
        ]
    ];

    // Execute Groq API Request
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
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Log the raw response for debugging
    error_log("Groq API Response HTTP Code: " . $http_code);
    error_log("Groq API Response (first 500 chars): " . substr($response, 0, 500));

    if ($curl_error) {
        error_log("Groq API request failed: " . $curl_error);
        throw new Exception("Groq API connection failed: " . $curl_error);
    }

    if ($http_code !== 200) {
        error_log("Groq API returned HTTP " . $http_code . ": " . substr($response, 0, 500));
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

    $raw_content = $api_response['choices'][0]['message']['content'];
    
    // Extract JSON from response
    $json_start = strpos($raw_content, '{');
    $json_end = strrpos($raw_content, '}');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($raw_content, $json_start, $json_end - $json_start + 1);
        $diagnosis_content = json_decode($json_content, true);
    } else {
        $diagnosis_content = json_decode($raw_content, true);
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("LLM did not return valid JSON: " . json_last_error_msg());
    }

    // Transform to frontend-compatible format
    $safety_verdict = $diagnosis_content['conclusion_and_safety_verdict']['verdict'] ?? 'SAFE';
    $risk_level = 'HIGH';
    if ($safety_verdict === 'DANGEROUS') {
        $risk_level = 'CRITICAL';
    } elseif ($safety_verdict === 'ATTENTION') {
        $risk_level = 'HIGH';
    } else {
        $risk_level = 'LOW';
    }

    $transformed_diagnosis = [
        "audio_source" => "Infrastructure Image Analysis System",
        "analysis_goal" => "Detect and classify infrastructure issues, damage, and safety concerns from images",
        "detected_signatures" => [],
        "executive_conclusion" => $diagnosis_content['conclusion_and_safety_verdict']['analysis_summary'] ?? 'Image analysis completed.',
        "risk_assessment" => [
            "severity" => $risk_level,
            "is_problem" => $safety_verdict !== 'SAFE' ? 'YES' : 'NO',
            "should_investigate" => $safety_verdict !== 'SAFE' ? 'YES' : 'NO',
            "risk_description" => $diagnosis_content['risk_assessment']['risk_description'] ?? 'Analysis of image completed.',
            "action_steps" => $diagnosis_content['risk_assessment']['action_steps'] ?? []
        ]
    ];

    // Add detected signatures
    if (isset($diagnosis_content['unified_sound_event_identification']['detected_signatures'])) {
        foreach ($diagnosis_content['unified_sound_event_identification']['detected_signatures'] as $sig) {
            $transformed_diagnosis['detected_signatures'][] = [
                "signature_name" => $sig['signature_name'] ?? $sig['classification'] ?? 'Issue detected',
                "classification" => $sig['classification'] ?? 'Infrastructure issue detected',
                "severity" => $sig['severity'] ?? $risk_level,
                "status" => $sig['status'] ?? ($safety_verdict !== 'SAFE' ? 'PROBLEM' : 'NOT A PROBLEM'),
                "recommended_action" => $sig['recommended_action'] ?? 'Review and assess the situation.'
            ];
        }
    }

    // Categorize using LLM
    $category = categorizeImageReport($diagnosis_content, $groq_api_key);
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
                error_log("❌ Database connection not available for image analysis: " . $e->getMessage());
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
            $signature_name = $signature['signature_name'] ?? 'Image Analysis';
            $top_hazard = $signature_name;
            $classification = $signature['classification'] ?? $transformed_diagnosis['executive_conclusion'] ?? 'Infrastructure issue detected';
            $executive_conclusion = $transformed_diagnosis['executive_conclusion'] ?? '';
            $risk_assessment = $transformed_diagnosis['risk_assessment'] ?? [];
            
            $severity = $risk_assessment['severity'] ?? $risk_level;
            $is_problem = $risk_assessment['is_problem'] ?? ($safety_verdict !== 'SAFE' ? 'YES' : 'NO');
            $verdict = $safety_verdict;
            $risk_description = $risk_assessment['risk_description'] ?? '';
            $action_steps = is_array($risk_assessment['action_steps']) ? json_encode($risk_assessment['action_steps']) : ($risk_assessment['action_steps'] ?? '');
            $who_to_contact = $risk_assessment['who_to_contact'] ?? '';
            
            // Image analysis doesn't have audio metrics, so set defaults
            $confidence_score = 0.85; // 85% default confidence for image analysis
            $rms_level = 0.0; // No audio data
            $spectral_centroid = null;
            $frequency = null;
            
            // Extract location from input data
            $latitude = isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== '' ? floatval($data['latitude']) : null;
            $longitude = isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== '' ? floatval($data['longitude']) : null;
            $address = isset($data['address']) ? $data['address'] : null;
            
            // Store full report as JSON
            $full_report_data = json_encode($transformed_diagnosis);
            
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
                ':rms_level' => $rms_level,
                ':spectral_centroid' => $spectral_centroid,
                ':frequency' => $frequency,
                ':signature_name' => $signature_name,
                ':classification' => $classification,
                ':executive_conclusion' => $executive_conclusion,
                ':severity' => $severity,
                ':is_problem' => $is_problem,
                ':verdict' => $verdict,
                ':risk_description' => $risk_description,
                ':action_steps' => $action_steps,
                ':who_to_contact' => $who_to_contact,
                ':category' => $category ?? 'Other',
                ':latitude' => $latitude,
                ':longitude' => $longitude,
                ':address' => $address,
                ':full_report_data' => $full_report_data
            ]);
            
            $insertId = $pdo->lastInsertId();
            error_log("✅ Image analysis report saved to database with ID: " . $insertId);
        } else {
            error_log("❌ Database connection not available - image analysis not saved");
        }
    } catch (PDOException $e) {
        error_log("❌ PDO Exception saving image analysis: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
        // Don't fail the request if database save fails
    } catch (Exception $e) {
        error_log("❌ Exception saving image analysis: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        // Don't fail the request if database save fails
    }

    ob_clean();
    echo json_encode([
        "status" => "success",
        "analysis_type" => "image",
        "diagnosis" => $transformed_diagnosis
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    ob_clean();
    error_log("Image analysis error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage(),
        "error_type" => get_class($e)
    ], JSON_PRETTY_PRINT);
} catch (Error $e) {
    ob_clean();
    error_log("Image analysis fatal error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Fatal error: " . $e->getMessage(),
        "error_type" => get_class($e)
    ], JSON_PRETTY_PRINT);
}

// Function to categorize image report using Groq LLM
function categorizeImageReport($diagnosis_content, $groq_api_key) {
    if (!$groq_api_key || $groq_api_key === 'demo_key_placeholder') {
        return 'Other';
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
    
    // Extract relevant information for categorization
    $primary_event = $diagnosis_content['unified_sound_event_identification']['primary_sound_event'] ?? '';
    $analysis_summary = $diagnosis_content['conclusion_and_safety_verdict']['analysis_summary'] ?? '';
    $classification = $primary_event . ' ' . $analysis_summary;
    
    $category_prompt = "Based on the following image analysis, categorize this report into ONE of these exact categories:

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
        error_log("Image category categorization failed: " . $e->getMessage());
    }
    
    return 'Other';
}
?>

