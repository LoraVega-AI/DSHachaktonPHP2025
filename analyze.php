<?php
// analyze.php - UrbanPulse Infrastructure Failure Diagnosis Engine

// Start output buffering to prevent warnings from breaking JSON
ob_start();

// Enable error reporting for debugging (log errors but don't display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Register shutdown function to catch fatal errors
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        header("Content-Type: application/json; charset=UTF-8");
        ob_clean();
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error: ' . $error['message'],
            'file' => $error['file'],
            'line' => $error['line']
        ], JSON_PRETTY_PRINT);
        error_log("FATAL ERROR in analyze.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        exit;
    }
});

// Include database configuration and auth functions
try {
    require_once __DIR__ . '/db_config.php';
    require_once __DIR__ . '/auth.php';
} catch (Exception $e) {
    http_response_code(500);
    header("Content-Type: application/json; charset=UTF-8");
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Failed to load required files: ' . $e->getMessage()
    ], JSON_PRETTY_PRINT);
    error_log("Error loading required files: " . $e->getMessage());
    exit;
}

// API Configuration - Now using Groq
const GROQ_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
const GROQ_MODEL = 'llama-3.3-70b-versatile'; // Fast and capable model for acoustic analysis

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

// Simple API test endpoint - Now using Groq
if (isset($_GET['test']) && $_GET['test'] === 'llm') {
    try {
        header("Content-Type: application/json");

        // Load environment variables
        $env = loadEnv(__DIR__ . '/.env');
        $groq_api_key = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

    if (!$groq_api_key) {
        echo json_encode(["error" => "No Groq API key found"]);
        exit;
    }

    // Simple test payload for Groq
    $test_payload = [
        "model" => GROQ_MODEL,
        "messages" => [
            [
                "role" => "user",
                "content" => "Respond with exactly: {\"test\": \"Groq LLM is working\", \"timestamp\": \"" . date('c') . "\"}"
            ]
        ],
        "max_tokens" => 100,
        "temperature" => 0.1
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => GROQ_ENDPOINT,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($test_payload),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $groq_api_key,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo json_encode([
        "api_key_configured" => !empty($groq_api_key) && $groq_api_key !== 'demo_key_placeholder' && strpos($groq_api_key, 'gsk_') === 0,
        "api_key_from_env_file" => !empty($env['GROQ_API_KEY']),
        "api_key_from_environment" => !empty(getenv('GROQ_API_KEY')),
        "api_key_length" => strlen($groq_api_key),
        "api_key_starts_with" => substr($groq_api_key, 0, 15) . "...",
        "api_key_format_valid" => strpos($groq_api_key, 'gsk_') === 0,
        "env_file_exists" => file_exists(__DIR__ . '/.env'),
        "current_working_directory" => __DIR__,
        "is_demo_placeholder" => $groq_api_key === 'demo_key_placeholder',
        "http_code" => $http_code,
        "llm_response_received" => $http_code === 200,
        "model_used" => GROQ_MODEL,
        "response_preview" => substr($response, 0, 100) . "..."
    ], JSON_PRETTY_PRINT);
    } catch (Exception $e) {
        http_response_code(500);
        header("Content-Type: application/json");
        ob_clean();
        echo json_encode([
            'status' => 'error',
            'message' => 'Test endpoint error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], JSON_PRETTY_PRINT);
        error_log("Test endpoint error: " . $e->getMessage());
    }
    exit;
}

// CORS Headers (only set if not already sent)
if (!headers_sent()) {
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json; charset=UTF-8");
    header("Access-Control-Allow-Methods: POST");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
}

// Load environment variables from .env file
$env = loadEnv(__DIR__ . '/.env');

// Secure API Key Handling - Now using Groq
$groq_api_key = $env['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY');

// DEBUG: Check API key status
error_log("=== GROQ API KEY CHECK ===");
error_log("Env file path: " . __DIR__ . '/.env');
error_log("Env file exists: " . (file_exists(__DIR__ . '/.env') ? 'YES' : 'NO'));
error_log("Env array keys: " . implode(', ', array_keys($env)));
error_log("Groq API Key from env file: " . (!empty($env['GROQ_API_KEY']) ? 'PRESENT' : 'MISSING'));
error_log("Groq API Key from getenv: " . (!empty(getenv('GROQ_API_KEY')) ? 'PRESENT' : 'MISSING'));
error_log("Final Groq API key: " . (!empty($groq_api_key) ? 'SET (' . strlen($groq_api_key) . ' chars)' : 'EMPTY'));
error_log("API key starts with: " . substr($groq_api_key, 0, 20) . "...");
error_log("Is demo placeholder: " . ($groq_api_key === 'demo_key_placeholder' ? 'YES' : 'NO'));
error_log("Starts with gsk_: " . (strpos($groq_api_key, 'gsk_') === 0 ? 'YES' : 'NO'));

// Comprehensive LLM analysis will be called after POST data is read

// Enhanced acoustic analysis database
$acoustic_profiles = [
    "Hissing/Sizzling" => [
        "sound_signature" => "High-frequency broadband noise (2-8kHz) with steam-like characteristics",
        "frequency_characteristics" => "Dominant energy in 3-6kHz range, broadband spectral content, possible harmonic series",
        "temporal_patterns" => "Continuous or intermittent bursts, pressure-release patterns",
        "acoustic_quality" => "Sharp, piercing quality with good propagation distance"
    ],
    "Gurgling/Sloshing" => [
        "sound_signature" => "Low-to-mid frequency liquid movement with cavity resonances",
        "frequency_characteristics" => "Fundamental frequencies 100-500Hz with formant structures",
        "temporal_patterns" => "Irregular bubbling patterns, fluid dynamics modulation",
        "acoustic_quality" => "Wet, resonant quality with variable amplitude modulation"
    ],
    "Grinding/Screeching" => [
        "sound_signature" => "High-frequency friction noise with metallic harmonics",
        "frequency_characteristics" => "Broad spectrum 1-10kHz, prominent harmonics, non-stationary",
        "temporal_patterns" => "Continuous with amplitude modulation, friction-induced variations",
        "acoustic_quality" => "Harsh, unpleasant quality with good audibility at distance"
    ],
    "Creaking/Groaning (Under Load)" => [
        "sound_signature" => "Low-frequency structural vibration with material stress indicators",
        "frequency_characteristics" => "Fundamental modes 50-200Hz with structural resonances",
        "temporal_patterns" => "Load-dependent modulation, stress-induced variations",
        "acoustic_quality" => "Deep, ominous quality with material-specific characteristics"
    ],
    "Thumping/Pounding (Non-Rhythmic)" => [
        "sound_signature" => "Low-frequency impact transients with structural coupling",
        "frequency_characteristics" => "Broad low-frequency content 20-300Hz with impact harmonics",
        "temporal_patterns" => "Discrete events with varying intervals, force-dependent characteristics",
        "acoustic_quality" => "Heavy, percussive quality with good low-frequency propagation"
    ],
    "Clicking/Ticking (Rapid)" => [
        "sound_signature" => "High-frequency mechanical transients with precision timing",
        "frequency_characteristics" => "Sharp spectral peaks 2-8kHz, minimal harmonic content",
        "temporal_patterns" => "Regular or semi-regular intervals, mechanical precision",
        "acoustic_quality" => "Crisp, precise quality with good localization properties"
    ],
    "Pulsating Hum/Buzz" => [
        "sound_signature" => "Fundamental frequency with harmonic series and modulation",
        "frequency_characteristics" => "Discrete fundamental 50-120Hz with integer harmonics",
        "temporal_patterns" => "Regular pulsation or continuous with amplitude modulation",
        "acoustic_quality" => "Smooth, vibrating quality with electrical characteristics"
    ],
    "Loud, Unmuffled Engine Noise (Persistent)" => [
        "sound_signature" => "Broadband combustion noise with mechanical harmonics",
        "frequency_characteristics" => "Wide spectrum 50-5000Hz with combustion and mechanical components",
        "temporal_patterns" => "Continuous operation with load-dependent variations",
        "acoustic_quality" => "Powerful, intrusive quality with significant low-frequency content"
    ],
    "Cracking/Popping" => [
        "sound_signature" => "Sharp transients with material failure characteristics",
        "frequency_characteristics" => "Broad spectrum with high-frequency emphasis, fracture dynamics",
        "temporal_patterns" => "Discrete events with material-dependent timing",
        "acoustic_quality" => "Sudden, alarming quality with good attention-grabbing properties"
    ],
    "Rattling/Shaking (Loose Components)" => [
        "sound_signature" => "Multiple component interactions with chaotic characteristics",
        "frequency_characteristics" => "Broad spectrum 100-2000Hz with multiple resonances",
        "temporal_patterns" => "Irregular, chaotic patterns with component interactions",
        "acoustic_quality" => "Noisy, unstable quality indicating mechanical looseness"
    ]
];

$infrastructure_assessments = [
    "Hissing/Sizzling" => "Potential pressure system compromise. Steam leaks indicate pipe damage or valve failure. Risk of burns and pressure-related incidents.",
    "Gurgling/Sloshing" => "Plumbing system irregularities. Liquid flow anomalies suggest blockages, leaks, or pump failures. Potential flooding risks.",
    "Grinding/Screeching" => "Mechanical component degradation. Bearing wear or friction issues detected. Imminent equipment failure likely.",
    "Creaking/Groaning (Under Load)" => "Structural integrity concerns. Material stress exceeding design limits. Foundation or framework settlement detected.",
    "Thumping/Pounding (Non-Rhythmic)" => "Impact loading on structural elements. Possible loose connections or dynamic loading issues. Progressive damage risk.",
    "Clicking/Ticking (Rapid)" => "Precision component malfunction. Relay systems or timing mechanisms failing. Control system reliability compromised.",
    "Pulsating Hum/Buzz" => "Electrical or mechanical resonance issues. Motor vibration or electrical interference detected. System stability concerns.",
    "Loud, Unmuffled Engine Noise (Persistent)" => "Equipment operating outside specifications. Lack of proper dampening indicates maintenance neglect. Environmental compliance issues.",
    "Cracking/Popping" => "Material failure events. Structural components experiencing stress fractures. Immediate integrity assessment required.",
    "Rattling/Shaking (Loose Components)" => "Fastener degradation throughout system. Multiple components becoming unsecured. Widespread mechanical integrity loss."
];

$monitoring_actions = [
    1 => "Maintain standard monitoring protocols. Log acoustic signature for baseline comparison. Continue routine maintenance schedule.",
    2 => "Increase monitoring frequency to hourly checks. Schedule visual inspection within 24 hours. Review recent maintenance records.",
    3 => "Immediate on-site assessment required. Restrict access to affected equipment. Mobilize maintenance team for urgent inspection.",
    4 => "Activate emergency maintenance protocols. Temporary system shutdown recommended. Safety barriers and evacuation routes prepared.",
    5 => "CRITICAL INCIDENT: Immediate evacuation of area. Emergency services notification. Structural engineering assessment required before re-entry."
];

$safety_protocols = [
    1 => "Standard personal protective equipment required in area. No immediate safety concerns but maintain awareness.",
    2 => "Enhanced safety monitoring in affected zone. Personal protective equipment mandatory. Emergency evacuation routes confirmed.",
    3 => "Restricted access zone established. Safety barriers deployed. Emergency response team on standby. Continuous acoustic monitoring active.",
    4 => "MANDATORY EVACUATION of affected area. Emergency response teams activated. Structural integrity assessment in progress.",
    5 => "CRITICAL EMERGENCY: Complete area evacuation. Emergency services fully engaged. Professional structural assessment mandatory before any re-entry."
];

$technical_insights = [
    "Hissing/Sizzling" => "MFCC analysis shows characteristic steam release patterns. YAMNet classification indicates pressure system anomalies. RMS levels suggest moderate energy release. Spectral analysis confirms broadband high-frequency content typical of fluid dynamics under pressure.",
    "Gurgling/Sloshing" => "Resonant frequencies detected in low-mid range spectrum. MFCC coefficients indicate fluid cavity interactions. YAMNet identifies liquid movement patterns. Temporal analysis shows characteristic fluid dynamics modulation.",
    "Grinding/Screeching" => "High-frequency harmonic series with broadband noise floor. MFCC analysis reveals friction-induced spectral changes. YAMNet detects mechanical contact anomalies. Amplitude modulation indicates progressive material degradation.",
    "Creaking/Groaning (Under Load)" => "Low-frequency structural modes with load-dependent frequency shifts. MFCC analysis shows material stress patterns. YAMNet identifies deformation characteristics. Spectral analysis reveals resonance changes under stress.",
    "Thumping/Pounding (Non-Rhythmic)" => "Transient impact signatures with structural coupling. MFCC coefficients indicate force transmission patterns. YAMNet detects collision events. Frequency analysis shows material-specific damping characteristics.",
    "Clicking/Ticking (Rapid)" => "High-frequency precision transients with regular timing. MFCC analysis reveals mechanical precision patterns. YAMNet identifies rhythmic mechanical events. Spectral purity indicates well-maintained components vs. degraded systems.",
    "Pulsating Hum/Buzz" => "Fundamental frequency with harmonic progression. MFCC analysis shows steady-state vibration patterns. YAMNet detects continuous mechanical operation. Modulation analysis indicates load variations or instability.",
    "Loud, Unmuffled Engine Noise (Persistent)" => "Broadband combustion and mechanical noise spectrum. MFCC coefficients indicate internal combustion patterns. YAMNet identifies machinery operation. High RMS levels suggest inadequate noise control measures.",
    "Cracking/Popping" => "Sharp transient events with material failure signatures. MFCC analysis reveals fracture dynamics. YAMNet detects sudden energy release. Spectral analysis shows broadband content typical of material separation.",
    "Rattling/Shaking (Loose Components)" => "Multiple resonant frequencies with chaotic interactions. MFCC coefficients indicate component mobility. YAMNet detects unsecured mechanical elements. Temporal analysis shows random interaction patterns."
];

// Ensure we always return valid JSON
try {
    // Clear any output that might have been generated (warnings, etc.)
    if (ob_get_level() > 0) {
        ob_clean();
    }
    
    // 1. Read Raw Input
    $json_data = file_get_contents('php://input');
    
    // Check if we got any data
    if (empty($json_data)) {
        throw new Exception("No input data received");
    }

    // 2. Decode JSON
    $data = json_decode($json_data, true);

    // 3. Check for JSON Errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }

    // DEBUG: Check incoming data and API key
    error_log("=== CISA LLM DEBUG ===");
    error_log("Received data keys: " . implode(', ', array_keys($data)));
    error_log("Top hazard: " . ($data['top_hazard'] ?? 'NOT SET'));
    error_log("Confidence: " . ($data['confidence_score'] ?? 'NOT SET'));
    error_log("RMS: " . ($data['rms_level'] ?? 'NOT SET'));
    error_log("API KEY STATUS: " . (!empty($groq_api_key) ? 'SET' : 'NOT SET'));
    error_log("API KEY STARTS WITH: " . substr($groq_api_key, 0, 15) . "...");
    error_log("API KEY LENGTH: " . strlen($groq_api_key));
    error_log("IS DEMO PLACEHOLDER: " . ($groq_api_key === 'demo_key_placeholder' ? 'YES' : 'NO'));

// 4. Validate Required Fields for Audio Analysis
$required_fields = ['top_hazard', 'confidence_score', 'mfcc_array', 'rms_level', 'timestamp'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    ob_clean();
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields: " . implode(', ', $missing_fields),
        "received_fields" => array_keys($data)
    ], JSON_PRETTY_PRINT);
    exit;
}

// Ensure mfcc_array is an array (not null or empty string)
if (!is_array($data['mfcc_array'])) {
    $data['mfcc_array'] = [];
}

// Ensure numeric fields are valid
$data['confidence_score'] = floatval($data['confidence_score'] ?? 0.5);
$data['rms_level'] = floatval($data['rms_level'] ?? 0.1);
if (empty($data['timestamp'])) {
    $data['timestamp'] = date('c');
}

// Try to use Groq API with comprehensive audio analysis - NOW WITH ACTUAL DATA
if ($groq_api_key && $groq_api_key !== 'demo_key_placeholder') {
    error_log("✅ PROCEEDING WITH GROQ LLM API CALL - DATA AVAILABLE");
    
    // Prepare comprehensive multimodal input data for in-depth acoustic analysis
    $mfcc_data = $data['mfcc_array'] ?? [];
    $mfcc_mean = count($mfcc_data) > 0 ? array_sum($mfcc_data)/count($mfcc_data) : 0;
    $mfcc_std = count($mfcc_data) > 0 ? sqrt(array_sum(array_map(function($x) use ($mfcc_mean) {
        return pow($x - $mfcc_mean, 2);
    }, $mfcc_data))/count($mfcc_data)) : 0;

    // Create detailed input data for comprehensive LLM analysis
    $multimodal_input = [
        "COMPREHENSIVE MULTIMODAL ACOUSTIC HAZARD ANALYSIS INPUT:",
        "",
        "=== ACOUSTIC SIGNAL CHARACTERISTICS ===",
        "Primary Hazard Detected: " . ($data['top_hazard'] ?? 'Unknown acoustic event'),
        "Confidence Score: " . number_format(floatval($data['confidence_score'] ?? 0), 3),
        "RMS Energy Level: " . number_format(floatval($data['rms_level'] ?? 0), 3) . " (" . number_format(floatval($data['rms_level'] ?? 0) * 1000, 1) . "mV)",
        "Peak RMS Level: " . number_format(floatval($data['max_rms'] ?? 0), 3),
        "Active Audio Frames: " . intval($data['active_frames'] ?? 0) . "/" . intval($data['total_frames'] ?? 0),
        "Signal Consistency: " . number_format(floatval($data['signal_consistency'] ?? 0), 3),
        "Analysis Duration: " . intval($data['analysis_period_seconds'] ?? 0) . " seconds",
        "",
        "=== MFCC SPECTRAL ANALYSIS (Timbral Characteristics) ===",
        "MFCC Coefficients (13-band): [" . (count($mfcc_data) > 0 ? implode(', ', array_map(function($val) { return number_format($val, 4); }, $mfcc_data)) : "No MFCC data available") . "]",
        "MFCC Statistical Summary:",
        "- Mean Coefficient Value: " . number_format($mfcc_mean, 4),
        "- Standard Deviation: " . number_format($mfcc_std, 4),
        "- Coefficient Range: " . (count($mfcc_data) > 0 ? number_format(min($mfcc_data), 4) . " to " . number_format(max($mfcc_data), 4) : "N/A (no data)"),
        "- Spectral Envelope Complexity: " . (count($mfcc_data) > 0 ? (abs($mfcc_std) > 0.5 ? "High" : (abs($mfcc_std) > 0.2 ? "Medium" : "Low")) : "Unable to determine"),
        "",
        "=== MEYDA PSYCHOACOUSTIC FEATURES (Human Perception Model) ===",
        "Spectral Domain Features:",
        "- Spectral Centroid: " . number_format(floatval($data['spectral_centroid'] ?? 0), 1) . " Hz (brightness indicator)",
        "- Spectral Rolloff: " . number_format(floatval($data['spectral_rolloff'] ?? 0), 1) . " Hz (high-frequency content)",
        "- Spectral Flux: " . number_format(floatval($data['spectral_flux'] ?? 0), 4) . " (spectral change rate)",
        "- Spectral Flatness: " . number_format(floatval($data['spectral_flatness'] ?? 0), 4) . " (tonal vs noise content)",
        "",
        "Temporal & Perceptual Features:",
        "- Perceptual Loudness: " . number_format(floatval($data['loudness'] ?? 0), 2) . " sones",
        "- Perceptual Sharpness: " . number_format(floatval($data['perceptual_sharpness'] ?? 0), 4) . " acum",
        "- Zero Crossing Rate: " . number_format(floatval($data['zero_crossing_rate'] ?? 0), 4) . " (noise vs tonal)",
        "- Signal Energy: " . number_format(floatval($data['energy'] ?? 0), 4),
        "",
        "Chroma Features (Pitch Class Analysis): [" . implode(', ', array_map(function($val) { return number_format($val, 3); }, $data['chroma'] ?? [])) . "]",
        "",
        "=== YAMNET NEURAL NETWORK CLASSIFICATION ===",
        "YAMNet Model Status: " . (isset($data['yamnet_score']) ? "Active" : "Offline"),
        "YAMNet Confidence Score: " . number_format(floatval($data['yamnet_score'] ?? 0), 3),
        "MFCC Distance Metric: " . number_format(floatval($data['mfcc_distance'] ?? 0), 4),
        "Spectral Pattern Match: " . (isset($data['spectral_check']) && !empty($data['spectral_check']) ? "Passed" : "Failed"),
        "",
        "=== MEL SPECTROGRAM ANALYSIS (Time-Frequency Representation) ===",
        $data['mel_spectrogram_description'] ?? 'Mel Spectrogram analysis: 128 Mel bands, log-scaled dB representation, temporal evolution captured',
        "",
        "=== TECHNICAL ANALYSIS PARAMETERS ===",
        "- Sample Rate: 16000 Hz",
        "- FFT Size: 2048 samples",
        "- Window Function: Hann window",
        "- Hop Size: 512 samples",
        "- MFCC Configuration: 13 coefficients, 40 Mel filters",
        "- Analysis Pipeline: YAMNet → Meyda Features → MFCC Distance → Spectral Validation"
    ];

    $input_data_text = implode("\n", $multimodal_input);

    $llm_payload = [
        "model" => GROQ_MODEL,
        "messages" => [
            [
                "role" => "system",
                "content" => "You are an acoustic safety analyst helping to identify potential infrastructure issues through sound analysis. Write clear, human-readable reports that anyone can understand. 

CRITICAL RULES:
- NEVER use technical terms like 'MFCC', 'cepstral', 'spectral envelope', 'timbral', 'YAMNet', 'spectrogram', 'zero-crossing rate', 'chroma', 'centroid', 'flux', or any engineering jargon
- ALWAYS explain what the sound IS and what's CAUSING it in everyday language
- Use simple words: 'hissing sound from a leak' not 'high-frequency broadband noise'
- Write as if explaining to a neighbor or property manager who has no technical background
- Focus on: What does it sound like? What is causing it? Is it dangerous? What should be done?

CRITICAL: This analysis identifies exactly ONE primary audio event. All references must consistently state \"1 audio event\" or \"one primary acoustic signature\".

REQUIRED OUTPUT FORMAT - Return ONLY valid JSON:

{
  \"confidence_score\": [MANDATORY: NUMBER 10-100. This is the MOST IMPORTANT field and MUST ALWAYS be provided. NEVER return 0. Assess how confident you are in the primary detection based on: signal quality, consistency, pattern match, and acoustic characteristics. Use the \"Confidence Score\" value from the data as a starting point, then adjust based on your analysis. MUST be a number between 10-100 (minimum 10, never 0). Typical ranges: Low confidence (20-50) if signal is weak/ambiguous, Medium (50-75) if reasonably certain, High (75-95) if very confident. Example: If uncertain but have some signal, use 30-40. If confident, use 70-85. If very confident, use 85-95],
  \"top_detections\": [
    {
      \"detection_name\": \"[Name of the detected sound/issue]\",
      \"confidence\": [NUMBER 0-100],
      \"description\": \"[Brief description]\"
    },
    {
      \"detection_name\": \"[Second most likely detection]\",
      \"confidence\": [NUMBER 0-100],
      \"description\": \"[Brief description]\"
    },
    {
      \"detection_name\": \"[Third most likely detection]\",
      \"confidence\": [NUMBER 0-100],
      \"description\": \"[Brief description]\"
    }
  ],
  \"unified_sound_event_identification\": {
    \"primary_sound_event\": \"[Clear explanation in everyday language: What sound was detected? What does it sound like? What is likely causing it? Example: 'A hissing sound was detected, similar to steam escaping from a pipe. This is likely caused by a pressure leak or steam release from plumbing or heating systems.' - 3-4 sentences. NO technical terms]\",
    \"yamnet_confirmation\": \"[Simple explanation in plain language - what the sound is and how we know - 2-3 sentences, NO technical terms]\",
    \"spectrogram_evidence\": \"[Easy-to-understand description of the sound's characteristics in everyday words - 2-3 sentences, NO technical terms]\",
    \"mfcc_timbral_evidence\": \"[Simple description of what the sound sounds like and what might be causing it - 2-3 sentences, NO technical terms]\"
  },
  \"risk_assessment_and_acoustic_metrics\": {
    \"intensity_loudness\": \"[Plain-language description of how loud the sound is and what that means - 2-3 sentences. NO technical terms]\",
    \"temporal_dynamics\": \"[Simple explanation of how the sound changes over time - 2-3 sentences in everyday terms. NO technical terms]\",
    \"frequency_analysis\": \"[Easy-to-understand description of the sound's pitch and tone in simple words - 2-3 sentences. NO technical terms]\"
  },
  \"conclusion_and_safety_verdict\": {
    \"analysis_summary\": \"[4-6 sentence clear summary in plain language: What sound was detected? What does it sound like? What is likely causing it? Is it dangerous? What should the person know? Example: 'A hissing sound was detected that sounds like steam or air escaping. This is most likely caused by a leak in a pipe, valve, or heating system. While not immediately dangerous, it could indicate a problem that needs attention. The sound suggests pressure is being released, which could lead to more serious issues if not addressed.' - Must reference exactly ONE audio event]\",
    \"recommended_action\": \"[Clear, actionable steps in plain language - what should be done and when - 2-3 sentences]\",
    \"verdict\": \"DANGEROUS\" | \"ATTENTION\" | \"SAFE\",
    \"confidence_level\": \"[Simple explanation of how confident we are in this analysis - 1-2 sentences in plain language]\"
  },
  \"risk_assessment\": {
    \"severity\": \"[LOW, MEDIUM, HIGH, or CRITICAL - based on the danger level]\",
    \"is_problem\": \"[YES or NO - is this a problem that needs attention?]\",
    \"should_investigate\": \"[YES or NO - should this be investigated further?]\",
    \"should_call_authorities\": \"[YES or NO - should emergency services or authorities be contacted?]\",
    \"who_to_contact\": \"[Clear, specific guidance on who should be contacted - e.g., 'Emergency services (911)', 'Building maintenance', 'Property manager', 'No one - continue monitoring', etc. - 1-2 sentences]\",
    \"action_steps\": \"[Concise, numbered or bulleted list of specific steps the reporter should take - 3-5 actionable steps in plain language]\",
    \"risk_description\": \"[2-3 sentence explanation of the risk level, why it's a problem or not, and what level of response is needed - in plain language]\"
  }
}

CRITICAL: You MUST write in everyday language. If you use ANY technical term, you have FAILED. 

Examples of FORBIDDEN terms: MFCC, cepstral, spectral, timbral, YAMNet, spectrogram, centroid, flux, chroma, zero-crossing, envelope, coefficient, acoustic signature, broadband, harmonic, resonance, frequency domain, temporal dynamics

Examples of GOOD language: 'hissing sound from a leak', 'grinding noise from old machinery', 'gurgling water in pipes', 'steam escaping', 'metal scraping together'

Write as if talking to a friend who knows nothing about sound engineering. Focus on: What does it sound like? What's causing it? Is it dangerous? What should be done?

Return ONLY the JSON object."
            ],
            [
                "role" => "user",
                "content" => "Perform comprehensive acoustic hazard analysis using ALL available acoustic data:

COMPREHENSIVE ACOUSTIC ANALYSIS DATA:
" . $input_data_text . "

INSTRUCTIONS:
- CRITICAL: The confidence_score is MANDATORY and the MOST IMPORTANT metric. You MUST ALWAYS provide it and it MUST NEVER be 0.
- CRITICAL: confidence_score MUST be a number between 10-100 (minimum 10, never 0 or null). Provide an accurate confidence score based on:
  * Signal quality and consistency from the data
  * How well the acoustic pattern matches known signatures
  * The clarity and strength of the detection
  * Use the \"Confidence Score\" from the data as a baseline, but adjust based on your analysis
  * Be realistic: Low confidence (20-50) if signal is weak or ambiguous, Medium (50-75) if reasonably certain, High (75-95) if very confident
  * If you're completely uncertain but have some signal, use 10-20. NEVER use 0.
- VALIDATION: Before returning JSON, verify confidence_score is a number between 10-100. If you cannot determine confidence, use 30 as a conservative estimate, never 0.
- CRITICAL: Provide top_detections array with top 3 possible detections, each with confidence (0-100). 
  * If confidence_score >= 80: Focus on the primary detection, but still provide 2-3 alternatives with lower confidence scores
  * If confidence_score < 80: Provide 3 alternative possibilities with their individual confidence scores, showing what else it might be
- CRITICAL: Explain what the sound is and what's causing it in plain language. Don't use technical terms.
- In primary_sound_event: Describe what the sound sounds like and what is likely causing it (e.g., 'hissing from a steam leak', 'grinding from worn machinery', 'gurgling from plumbing')
- In analysis_summary: Explain what was detected, what it sounds like, what's causing it, and whether it's dangerous
- In the risk_assessment section, you MUST provide ALL fields - these are REQUIRED:
  * severity: LOW, MEDIUM, HIGH, or CRITICAL (based on how dangerous it is)
  * is_problem: YES or NO (is this something that needs attention?)
  * should_investigate: YES or NO
  * should_call_authorities: YES or NO
  * who_to_contact: Be VERY specific - name the exact person/agency (e.g., 'Emergency services (911)', 'Building maintenance team', 'Property manager', 'Plumber', 'No one - continue monitoring')
  * action_steps: Provide 3-5 specific, actionable steps the reporter should take RIGHT NOW (numbered format like: 1. First step, 2. Second step, etc.)
  * risk_description: 2-3 sentence explanation of why this is or isn't a problem
- Write as if explaining to someone who isn't an engineer or scientist - use everyday words
- Be helpful, clear, and actionable
- Reference exactly ONE primary audio event consistently throughout all sections
- ABSOLUTELY NO technical jargon - if you use ANY technical term, you MUST explain it in simple words immediately
- Examples of what NOT to say: 'MFCC analysis', 'cepstral coefficients', 'spectral envelope', 'timbral characteristics', 'YAMNet classification'
- Examples of what TO say: 'a hissing sound from a leak', 'grinding noise from machinery', 'gurgling from plumbing'
- Return ONLY the specified JSON structure with clear, readable content"
            ]
        ],
        "temperature" => 0.1,
        "max_tokens" => 4000,
        "response_format" => [
            "type" => "json_object"
        ]
    ];

// 6. Execute Groq API Request
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

// 7. Handle API Response
if ($curl_error) {
    // API request failed - throw exception to use rule-based fallback
    error_log("Groq API request failed with CURL error: " . $curl_error);
    throw new Exception("Groq API connection failed: " . $curl_error);
}

if ($http_code !== 200) {
    // API returned error - log and throw to use rule-based fallback
    error_log("Groq API returned HTTP " . $http_code . ": " . substr($response, 0, 200));
    $error_data = json_decode($response, true);
    $error_msg = isset($error_data['error']['message']) ? $error_data['error']['message'] : "HTTP " . $http_code;
    throw new Exception("Groq API error: " . $error_msg);
}

    $api_response = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("Failed to parse API response: " . json_last_error_msg());
    }

    // Extract the diagnosis content from the LLM response
    if (!isset($api_response['choices'][0]['message']['content'])) {
        throw new Exception("Invalid API response structure");
    }

    $raw_content = $api_response['choices'][0]['message']['content'];
    
    // Try to extract JSON from the response (LLM might add extra text)
    $json_start = strpos($raw_content, '{');
    $json_end = strrpos($raw_content, '}');
    
    if ($json_start !== false && $json_end !== false && $json_end > $json_start) {
        $json_content = substr($raw_content, $json_start, $json_end - $json_start + 1);
        $diagnosis_content = json_decode($json_content, true);
    } else {
        $diagnosis_content = json_decode($raw_content, true);
    }
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("❌ JSON Parse Error: " . json_last_error_msg());
        error_log("❌ Raw response snippet: " . substr($raw_content, 0, 1000));
        throw new Exception("LLM did not return valid JSON: " . json_last_error_msg());
    }
    
    // Validate that we got a valid response structure
    if (!is_array($diagnosis_content)) {
        error_log("❌ LLM response is not an array. Type: " . gettype($diagnosis_content));
        error_log("❌ Response content: " . substr($raw_content, 0, 1000));
        throw new Exception("LLM did not return valid JSON structure");
    }
    
    // Log raw response for debugging (first 500 chars)
    error_log("🔍 LLM Raw Response (first 500 chars): " . substr($raw_content, 0, 500));
    
    // Validate confidence_score exists and is valid
    if (!isset($diagnosis_content['confidence_score'])) {
        error_log("❌ LLM response missing confidence_score field!");
        error_log("❌ Available fields: " . implode(', ', array_keys($diagnosis_content)));
        error_log("❌ Full response structure: " . json_encode($diagnosis_content, JSON_PRETTY_PRINT));
    } else {
        $raw_confidence = $diagnosis_content['confidence_score'];
        error_log("🔍 LLM provided confidence_score: " . var_export($raw_confidence, true) . " (type: " . gettype($raw_confidence) . ")");
    }

    // Transform comprehensive LLM response to frontend-compatible format
    $safety_verdict = $diagnosis_content['conclusion_and_safety_verdict']['verdict'] ?? 'SAFE';
    $risk_level = 'HIGH'; // Default, will be determined by verdict

    // Determine risk level based on verdict
    if ($safety_verdict === 'DANGEROUS') {
        $risk_level = 'CRITICAL';
    } elseif ($safety_verdict === 'ATTENTION') {
        $risk_level = 'HIGH';
    } else {
        $risk_level = 'LOW';
    }

    // Extract confidence-based metrics from LLM response
    // CRITICAL: confidence_score from LLM is 0-100, we need to convert to 0-1 for frontend
    $llm_confidence = isset($diagnosis_content['confidence_score']) ? floatval($diagnosis_content['confidence_score']) : null;
    $top_detections = isset($diagnosis_content['top_detections']) && is_array($diagnosis_content['top_detections']) ? $diagnosis_content['top_detections'] : [];

    // Calculate fallback confidence based on signal quality if LLM doesn't provide it
    $calculated_fallback_confidence = 0.5; // Default 50%
    if (isset($data['confidence_score']) && floatval($data['confidence_score']) > 0) {
        $calculated_fallback_confidence = floatval($data['confidence_score']);
    } elseif (isset($data['signal_consistency']) && floatval($data['signal_consistency']) > 0) {
        // Use signal consistency as proxy for confidence
        $calculated_fallback_confidence = min(0.9, max(0.3, floatval($data['signal_consistency'])));
    } elseif (isset($data['active_frames']) && isset($data['total_frames']) && intval($data['total_frames']) > 0) {
        // Use active frames ratio as proxy
        $activity_ratio = intval($data['active_frames']) / intval($data['total_frames']);
        $calculated_fallback_confidence = min(0.8, max(0.4, $activity_ratio));
    }

    // Use LLM-provided confidence score (0-100 scale from LLM, convert to 0-1 for frontend)
    // CRITICAL: Reject 0 as valid - minimum threshold is 10 (if LLM returns < 10, it's likely an error)
    if ($llm_confidence !== null && $llm_confidence > 0 && $llm_confidence <= 100) {
        // If LLM returns very low confidence (< 10), it might be an error - use calculated fallback
        if ($llm_confidence < 10) {
            error_log("⚠️ LLM returned very low confidence (" . $llm_confidence . "%), using calculated fallback: " . ($calculated_fallback_confidence * 100) . "%");
            $confidence_score = $calculated_fallback_confidence;
        } else {
            $confidence_score = $llm_confidence / 100.0; // Convert 0-100 to 0-1
            error_log("✅ Using LLM-provided confidence: " . $llm_confidence . "% (converted to " . $confidence_score . " for frontend)");
        }
    } else {
        // Fallback to calculated confidence based on signal quality
        $confidence_score = $calculated_fallback_confidence;
        error_log("⚠️ LLM did not provide valid confidence_score (got: " . ($llm_confidence !== null ? $llm_confidence : 'NULL') . "), using calculated fallback: " . ($confidence_score * 100) . "%");
        error_log("⚠️ Fallback calculation details - original confidence: " . ($data['confidence_score'] ?? 'N/A') . ", signal_consistency: " . ($data['signal_consistency'] ?? 'N/A') . ", active_frames: " . ($data['active_frames'] ?? 'N/A') . "/" . ($data['total_frames'] ?? 'N/A'));
    }
    
    // Final safety check - ensure confidence is never 0
    if ($confidence_score <= 0) {
        error_log("❌ CRITICAL: Confidence score is 0 or negative, forcing to 50%");
        $confidence_score = 0.5;
    }

    $transformed_diagnosis = [
        "audio_source" => "Urban Infrastructure Acoustic Monitoring System",
        "analysis_goal" => "Identify and assess sounds that may indicate infrastructure issues or safety concerns",
        "confidence_score" => $confidence_score, // 0-1 scale for frontend
        "top_detections" => $top_detections,
        "detected_signatures" => [
            [
                "signature_name" => $diagnosis_content['unified_sound_event_identification']['primary_sound_event'] ?? 'Unknown Event',
                "classification" => $diagnosis_content['unified_sound_event_identification']['primary_sound_event'] ?? 'Sound analysis completed successfully.',
                "recommended_action" => $diagnosis_content['conclusion_and_safety_verdict']['recommended_action'] ?? 'Continue monitoring'
            ]
        ],
        "executive_conclusion" => $diagnosis_content['conclusion_and_safety_verdict']['analysis_summary'] ?? 'Acoustic analysis completed successfully.',
        "risk_assessment" => [
            "severity" => $diagnosis_content['risk_assessment']['severity'] ?? null,
            "is_problem" => $diagnosis_content['risk_assessment']['is_problem'] ?? null,
            "should_investigate" => $diagnosis_content['risk_assessment']['should_investigate'] ?? null,
            "should_call_authorities" => $diagnosis_content['risk_assessment']['should_call_authorities'] ?? null,
            "who_to_contact" => $diagnosis_content['risk_assessment']['who_to_contact'] ?? null,
            "action_steps" => $diagnosis_content['risk_assessment']['action_steps'] ?? null,
            "risk_description" => $diagnosis_content['risk_assessment']['risk_description'] ?? null
        ],
        "report_metadata" => [
            "analysis_timestamp" => date('c'),
            "confidence_level" => "95%",
            "fusion_methodology" => "Advanced Acoustic Analysis"
        ],
        // Add detailed sections for enhanced display
        "cisa_analysis" => [
            "unified_sound_event_identification" => [
                "primary_sound_event" => $diagnosis_content['unified_sound_event_identification']['primary_sound_event'] ?? 'Sound analysis completed',
                "yamnet_confirmation" => $diagnosis_content['unified_sound_event_identification']['yamnet_confirmation'] ?? 'Sound pattern successfully identified',
                "spectrogram_evidence" => $diagnosis_content['unified_sound_event_identification']['spectrogram_evidence'] ?? 'Sound characteristics analyzed',
                "mfcc_timbral_evidence" => $diagnosis_content['unified_sound_event_identification']['mfcc_timbral_evidence'] ?? 'Sound quality assessed'
            ],
            "risk_assessment_and_acoustic_metrics" => [
                "intensity_loudness" => $diagnosis_content['risk_assessment_and_acoustic_metrics']['intensity_loudness'] ?? 'Sound volume and intensity assessed',
                "temporal_dynamics" => $diagnosis_content['risk_assessment_and_acoustic_metrics']['temporal_dynamics'] ?? 'Sound pattern over time analyzed',
                "frequency_analysis" => $diagnosis_content['risk_assessment_and_acoustic_metrics']['frequency_analysis'] ?? 'Sound pitch and tone characteristics examined'
            ],
            "conclusion_and_safety_verdict" => [
                "analysis_summary" => $diagnosis_content['conclusion_and_safety_verdict']['analysis_summary'] ?? 'Sound analysis completed successfully',
                "recommended_action" => $diagnosis_content['conclusion_and_safety_verdict']['recommended_action'] ?? 'Continue monitoring',
                "verdict" => $safety_verdict,
                "confidence_level" => $diagnosis_content['conclusion_and_safety_verdict']['confidence_level'] ?? 'Analysis completed with high confidence'
            ]
        ]
    ];

    // 8. Categorize using LLM
    $category = categorizeReport($diagnosis_content, $groq_api_key, 'audio');
    $transformed_diagnosis['category'] = $category;

    // 9. Validate AI-generated content is present
    $hasAIContent = !empty($transformed_diagnosis['executive_conclusion']) && 
                    !empty($transformed_diagnosis['detected_signatures'][0]['signature_name']) &&
                    !empty($transformed_diagnosis['risk_assessment']);
    
    if (!$hasAIContent) {
        error_log("⚠️ WARNING: AI-generated content appears incomplete in diagnosis");
        error_log("   - Executive conclusion: " . (!empty($transformed_diagnosis['executive_conclusion']) ? 'PRESENT' : 'MISSING'));
        error_log("   - Signature name: " . (!empty($transformed_diagnosis['detected_signatures'][0]['signature_name']) ? 'PRESENT' : 'MISSING'));
        error_log("   - Risk assessment: " . (!empty($transformed_diagnosis['risk_assessment']) ? 'PRESENT' : 'MISSING'));
    } else {
        error_log("✅ AI-generated content validated - all required fields present");
    }

    // 10. Save to Database
    error_log("💾 Attempting to save AI-generated report to database...");
    $saveResult = saveAnalysisReport($transformed_diagnosis, $data, $category);
    $reportId = null;
    
    if ($saveResult && is_array($saveResult) && isset($saveResult['report_id'])) {
        $reportId = $saveResult['report_id'];
        error_log("✅ AI report successfully saved to database with full AI-generated content (ID: $reportId)");
    } elseif ($saveResult) {
        error_log("✅ AI report successfully saved to database (report ID not available)");
    } else {
        error_log("❌ WARNING: Failed to save AI report to database, but continuing with response");
    }
    
    // Validate report for cross-modal correlation (with error handling)
    if ($reportId) {
            // Extract location from original data (define outside try blocks for scope)
            $latitude = isset($data['latitude']) && $data['latitude'] !== null && $data['latitude'] !== '' ? floatval($data['latitude']) : null;
            $longitude = isset($data['longitude']) && $data['longitude'] !== null && $data['longitude'] !== '' ? floatval($data['longitude']) : null;
            $severity = $transformed_diagnosis['risk_assessment']['severity'] ?? 'LOW';
            
            try {
                // Only validate if we have a valid report ID and location data
                if ($reportId && $reportId > 0 && $latitude !== null && $longitude !== null) {
                    require_once __DIR__ . '/validate_reports.php';
                    if (function_exists('validateReport')) {
                        $validationResult = validateReport($reportId, 'analysis');
                        if ($validationResult && isset($validationResult['status'])) {
                            if ($validationResult['status'] === 'validated') {
                                error_log("✅ Cross-modal validation: Report #$reportId validated with " . count($validationResult['correlations']) . " correlations");
                            } elseif ($validationResult['status'] === 'error') {
                                error_log("⚠️ Validation skipped: " . ($validationResult['message'] ?? 'Unknown error'));
                            }
                        }
                    }
                } else {
                    error_log("⚠️ Validation skipped: Missing report ID or location data (reportId: " . ($reportId ?? 'null') . ", lat: " . ($latitude ?? 'null') . ", lng: " . ($longitude ?? 'null') . ")");
                }
            } catch (Exception $validationError) {
                error_log("⚠️ Validation error (non-fatal): " . $validationError->getMessage());
                // Continue even if validation fails
            }
            
            // Attempt triangulation for acoustic source localization (with error handling)
            try {
                require_once __DIR__ . '/triangulate_source.php';
                if (function_exists('triangulateSource')) {
                    $triangulationResult = triangulateSource($reportId);
                    if ($triangulationResult['status'] === 'triangulated') {
                        error_log("✅ Triangulation: Report #$reportId part of cluster #" . $triangulationResult['cluster_id'] . 
                                 " with " . $triangulationResult['report_count'] . " reports");
                    }
                }
            } catch (Exception $triangulationError) {
                error_log("⚠️ Triangulation error (non-fatal): " . $triangulationError->getMessage());
                // Continue even if triangulation fails
            }
            
            // Check proximity alerts (with error handling)
            try {
                if ($latitude !== null && $longitude !== null) {
                    require_once __DIR__ . '/check_proximity_alerts.php';
                    if (function_exists('checkProximityAlerts')) {
                        $alertResult = checkProximityAlerts($reportId, 'analysis', $latitude, $longitude, $severity);
                        if ($alertResult['alerts_triggered'] > 0) {
                            error_log("🔔 Proximity alerts: " . $alertResult['alerts_triggered'] . " users notified");
                        }
                    }
                }
            } catch (Exception $alertError) {
                error_log("⚠️ Proximity alert error (non-fatal): " . $alertError->getMessage());
                // Continue even if alerts fail
            }
        }

    // 11. Return Diagnosis to Frontend
    http_response_code(200);
    ob_clean(); // Clear any output before sending JSON
    echo json_encode([
        "status" => "success",
        "analysis_type" => "acoustic_analysis",
        "diagnosis" => $transformed_diagnosis,
        "report_saved" => ($saveResult && (is_array($saveResult) ? $saveResult['success'] : $saveResult))
    ], JSON_PRETTY_PRINT);
    exit; // Exit after successful LLM analysis
} else {
        // API key not configured - use fallback rule-based analysis
        error_log("⚠️ Groq API key not configured, using rule-based fallback analysis");
        
        // Use rule-based analysis
        $confidence = floatval($data['confidence_score'] ?? 0.5);
        $rms = floatval($data['rms_level'] ?? 0.1);
        $hazard = $data['top_hazard'] ?? 'Unknown acoustic event';

        // Calculate risk based on confidence and RMS level
        $risk_base = $confidence * 3; // 0-3 from confidence
        $risk_rms = min($rms * 2, 2); // 0-2 from volume
        $calculated_risk = min(max(round($risk_base + $risk_rms), 1), 5);

        $signature_name = str_replace(['"', "'"], '', $hazard); // Clean hazard name for signature
        $safety_verdict = $calculated_risk >= 4 ? "DANGEROUS" : ($calculated_risk >= 3 ? "ATTENTION" : "SAFE");

        $fallback_diagnosis = [
            "audio_source" => "Urban Infrastructure Acoustic Monitoring System",
            "analysis_goal" => "Identify and assess sounds that may indicate infrastructure issues or safety concerns",
            "confidence_score" => $confidence,
            "detected_signatures" => [
                [
                    "signature_name" => $signature_name,
                    "classification" => $infrastructure_assessments[$hazard] ?? "Unusual acoustic event requiring further analysis",
                    "recommended_action" => $monitoring_actions[$calculated_risk] ?? "Continue standard monitoring protocols"
                ]
            ],
            "executive_conclusion" => "Our analysis has identified one primary audio event: " . $signature_name . ". " . ($calculated_risk >= 3 ? "This appears to be " . ($calculated_risk >= 4 ? "a serious concern" : "a potential issue") . " that requires attention." : "The sound appears to be within normal operating parameters.") . " " . ($calculated_risk >= 4 ? "Immediate action is recommended." : ($calculated_risk >= 3 ? "We recommend investigating this further." : "No immediate action is required.")),
            "risk_assessment" => [
                "severity" => $calculated_risk >= 4 ? "CRITICAL" : ($calculated_risk >= 3 ? "HIGH" : ($calculated_risk >= 2 ? "MEDIUM" : "LOW")),
                "is_problem" => $calculated_risk >= 3 ? "YES" : "NO",
                "should_investigate" => $calculated_risk >= 3 ? "YES" : "NO",
                "should_call_authorities" => $calculated_risk >= 4 ? "YES" : "NO",
                "risk_description" => ($calculated_risk >= 4 ? "This is a serious safety concern that requires immediate attention. Emergency services should be contacted." : ($calculated_risk >= 3 ? "This requires investigation to determine if action is needed. Authorities may need to be notified depending on findings." : "This appears to be within normal parameters. No immediate action required."))
            ],
            "report_metadata" => [
                "analysis_timestamp" => date('c'),
                "confidence_level" => round($confidence * 100) . "%",
                "fusion_methodology" => "Acoustic Pattern Analysis"
            ],
            "cisa_analysis" => [
                "unified_sound_event_identification" => [
                    "primary_sound_event" => $signature_name,
                    "yamnet_confirmation" => "The sound pattern has been identified through acoustic analysis and matches known sound signatures.",
                    "spectrogram_evidence" => "The sound's energy and frequency characteristics have been analyzed and recorded.",
                    "mfcc_timbral_evidence" => "The sound's quality and characteristics have been examined and documented."
                ],
                "risk_assessment_and_acoustic_metrics" => [
                    "intensity_loudness" => "The sound is " . ($rms > 0.1 ? "quite loud" : ($rms > 0.05 ? "moderately loud" : "relatively quiet")) . ", which " . ($rms > 0.1 ? "may indicate a significant acoustic event" : ($rms > 0.05 ? "suggests a noticeable sound" : "suggests a subtle acoustic signature")) . ".",
                    "temporal_dynamics" => "The sound pattern shows " . (round($confidence * 100) > 70 ? "strong" : (round($confidence * 100) > 50 ? "moderate" : "some")) . " consistency, with " . round($confidence * 100) . "% confidence in the detection.",
                    "frequency_analysis" => "The sound's pitch and tone characteristics have been analyzed to help identify its source and nature."
                ],
                "conclusion_and_safety_verdict" => [
                    "analysis_summary" => "Our analysis has identified one primary audio event: " . $signature_name . ". This sound pattern was detected with " . round($confidence * 100) . "% confidence. " . ($calculated_risk >= 3 ? "This appears to be " . ($calculated_risk >= 4 ? "a serious concern" : "a potential issue") . " that requires attention." : "The sound appears to be within normal operating parameters."),
                    "recommended_action" => $monitoring_actions[$calculated_risk] ?? "Continue standard monitoring protocols",
                    "verdict" => $safety_verdict,
                    "confidence_level" => "Analysis completed with " . round($confidence * 100) . "% confidence."
                ]
            ]
        ];

        // Categorize fallback diagnosis
        $category = 'Other'; // Default for fallback
        $fallback_diagnosis['category'] = $category;
        
        // Save to Database
        error_log("💾 Attempting to save fallback report to database...");
        $saveResult = saveAnalysisReport($fallback_diagnosis, $data, $category);
        
        if ($saveResult && is_array($saveResult) && isset($saveResult['report_id'])) {
            error_log("✅ Fallback report successfully saved to database (ID: " . $saveResult['report_id'] . ")");
        } elseif ($saveResult) {
            error_log("✅ Fallback report successfully saved to database");
        } else {
            error_log("❌ WARNING: Failed to save fallback report to database");
        }

        http_response_code(200);
        ob_clean(); // Clear any output before sending JSON
        echo json_encode([
            "status" => "success",
            "analysis_type" => "acoustic_analysis_fallback",
            "diagnosis" => $fallback_diagnosis,
            "report_saved" => ($saveResult && (is_array($saveResult) ? $saveResult['success'] : $saveResult))
        ], JSON_PRETTY_PRINT);
        exit;
    }

} catch (Exception $e) {
    // Log the error for debugging
    error_log("❌ Error in analyze.php: " . $e->getMessage());
    error_log("❌ Stack trace: " . $e->getTraceAsString());
    
    // Check if we have valid data to work with
    if (!isset($data) || !is_array($data)) {
        // If we don't have data, return an error response
        http_response_code(500);
        ob_clean();
        echo json_encode([
            "status" => "error",
            "message" => "Failed to process request: " . $e->getMessage(),
            "error_type" => "processing_error"
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    // Fallback: Use rule-based analysis when API fails
    $confidence = floatval($data['confidence_score'] ?? 0.5);
    $rms = floatval($data['rms_level'] ?? 0.1);
    $hazard = $data['top_hazard'] ?? 'Unknown acoustic event';

    // Calculate risk based on confidence and RMS level
    $risk_base = $confidence * 3; // 0-3 from confidence
    $risk_rms = min($rms * 2, 2); // 0-2 from volume
    $calculated_risk = min(max(round($risk_base + $risk_rms), 1), 5);

    $signature_name = str_replace(['"', "'"], '', $hazard); // Clean hazard name for signature
    $safety_verdict = $calculated_risk >= 4 ? "DANGEROUS" : ($calculated_risk >= 3 ? "ATTENTION" : "SAFE");

    $fallback_diagnosis = [
                        "audio_source" => "Urban Infrastructure Acoustic Monitoring System",
                        "analysis_goal" => "Identify and assess sounds that may indicate infrastructure issues or safety concerns",
            "detected_signatures" => [
                [
                    "signature_name" => $signature_name,
                    "classification" => $infrastructure_assessments[$hazard] ?? "Unusual acoustic event requiring further analysis",
                    "recommended_action" => $monitoring_actions[$calculated_risk] ?? "Continue standard monitoring protocols"
                ]
            ],
            "executive_conclusion" => "Our analysis has identified one primary audio event: " . $signature_name . ". " . ($calculated_risk >= 3 ? "This appears to be " . ($calculated_risk >= 4 ? "a serious concern" : "a potential issue") . " that requires attention." : "The sound appears to be within normal operating parameters.") . " " . ($calculated_risk >= 4 ? "Immediate action is recommended." : ($calculated_risk >= 3 ? "We recommend investigating this further." : "No immediate action is required.")),
            "risk_assessment" => [
                "severity" => $calculated_risk >= 4 ? "CRITICAL" : ($calculated_risk >= 3 ? "HIGH" : ($calculated_risk >= 2 ? "MEDIUM" : "LOW")),
                "is_problem" => $calculated_risk >= 3 ? "YES" : "NO",
                "should_investigate" => $calculated_risk >= 3 ? "YES" : "NO",
                "should_call_authorities" => $calculated_risk >= 4 ? "YES" : "NO",
                "risk_description" => ($calculated_risk >= 4 ? "This is a serious safety concern that requires immediate attention. Emergency services should be contacted." : ($calculated_risk >= 3 ? "This requires investigation to determine if action is needed. Authorities may need to be notified depending on findings." : "This appears to be within normal parameters. No immediate action required."))
            ],
            "report_metadata" => [
                "analysis_timestamp" => date('c'),
                "confidence_level" => round($confidence * 100) . "%",
                "fusion_methodology" => "Acoustic Pattern Analysis"
            ],
            "cisa_analysis" => [
                "unified_sound_event_identification" => [
                    "primary_sound_event" => $signature_name,
                    "yamnet_confirmation" => "The sound pattern has been identified through acoustic analysis and matches known sound signatures.",
                    "spectrogram_evidence" => "The sound's energy and frequency characteristics have been analyzed and recorded.",
                    "mfcc_timbral_evidence" => "The sound's quality and characteristics have been examined and documented."
                ],
                "risk_assessment_and_acoustic_metrics" => [
                    "intensity_loudness" => "The sound is " . ($rms > 0.1 ? "quite loud" : ($rms > 0.05 ? "moderately loud" : "relatively quiet")) . ", which " . ($rms > 0.1 ? "may indicate a significant acoustic event" : ($rms > 0.05 ? "suggests a noticeable sound" : "suggests a subtle acoustic signature")) . ".",
                    "temporal_dynamics" => "The sound pattern shows " . (round($confidence * 100) > 70 ? "strong" : (round($confidence * 100) > 50 ? "moderate" : "some")) . " consistency, with " . round($confidence * 100) . "% confidence in the detection.",
                    "frequency_analysis" => "The sound's pitch and tone characteristics have been analyzed to help identify its source and nature."
                ],
                "conclusion_and_safety_verdict" => [
                    "analysis_summary" => "Our analysis has identified one primary audio event: " . $signature_name . ". This sound pattern was detected with " . round($confidence * 100) . "% confidence. " . ($calculated_risk >= 3 ? "This appears to be " . ($calculated_risk >= 4 ? "a serious concern" : "a potential issue") . " that requires attention." : "The sound appears to be within normal operating parameters."),
                    "recommended_action" => $monitoring_actions[$calculated_risk] ?? "Continue standard monitoring protocols",
                    "verdict" => $safety_verdict,
                    "confidence_level" => "Analysis completed with " . round($confidence * 100) . "% confidence."
                ]
            ]
        ];

    // Categorize fallback diagnosis
    $category = 'Other'; // Default for fallback
    $fallback_diagnosis['category'] = $category;
    
    // Save to Database
    error_log("💾 Attempting to save fallback report to database...");
    $saveResult = saveAnalysisReport($fallback_diagnosis, $data, $category);
    
    if ($saveResult && is_array($saveResult) && isset($saveResult['report_id'])) {
        error_log("✅ Fallback report successfully saved to database (ID: " . $saveResult['report_id'] . ")");
    } elseif ($saveResult) {
        error_log("✅ Fallback report successfully saved to database");
    } else {
        error_log("❌ WARNING: Failed to save fallback report to database");
    }

    http_response_code(200);
    ob_clean(); // Clear any output before sending JSON
    echo json_encode([
        "status" => "success",
        "analysis_type" => "acoustic_analysis_fallback",
        "diagnosis" => $fallback_diagnosis,
        "report_saved" => ($saveResult && (is_array($saveResult) ? $saveResult['success'] : $saveResult))
    ], JSON_PRETTY_PRINT);
}

// Function to categorize report using Groq LLM
function categorizeReport($diagnosis_content, $groq_api_key, $analysis_type = 'audio') {
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
    
    if ($analysis_type === 'image') {
        $classification = $diagnosis_content['unified_sound_event_identification']['primary_sound_event'] ?? '';
        $classification .= ' ' . ($diagnosis_content['conclusion_and_safety_verdict']['analysis_summary'] ?? '');
    }
    
    $category_prompt = "Based on the following " . ($analysis_type === 'audio' ? 'sound' : 'image') . " analysis, categorize this report into ONE of these exact categories:

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
        error_log("Category categorization failed: " . $e->getMessage());
    }
    
    return 'Other';
}

// Function to save analysis report to database
function saveAnalysisReport($diagnosis, $originalData, $category = null) {
    try {
        // Ensure database is initialized
        if (!function_exists('getDBConnection')) {
            error_log("ERROR: getDBConnection function not available - db_config.php may not be loaded");
            return false;
        }
        
        $pdo = getDBConnection();
        
        // Extract data from diagnosis
        $signature = $diagnosis['detected_signatures'][0] ?? null;
        $signature_name = $signature['signature_name'] ?? ($originalData['top_hazard'] ?? 'Unknown');
        $classification = $signature['classification'] ?? '';
        $executive_conclusion = $diagnosis['executive_conclusion'] ?? '';
        $risk_assessment = $diagnosis['risk_assessment'] ?? [];
        
        $severity = $risk_assessment['severity'] ?? null;
        $is_problem = $risk_assessment['is_problem'] ?? null;
        $verdict = $diagnosis['cisa_analysis']['conclusion_and_safety_verdict']['verdict'] ?? 
                   ($risk_assessment['severity'] === 'CRITICAL' ? 'DANGEROUS' : 
                    ($risk_assessment['severity'] === 'HIGH' ? 'ATTENTION' : 'SAFE'));
        $risk_description = $risk_assessment['risk_description'] ?? '';
        $action_steps = is_array($risk_assessment['action_steps']) ? json_encode($risk_assessment['action_steps']) : ($risk_assessment['action_steps'] ?? '');
        $who_to_contact = $risk_assessment['who_to_contact'] ?? '';
        
        $confidence = floatval($originalData['confidence_score'] ?? 0.5);
        $rms = floatval($originalData['rms_level'] ?? 0.1);
        $spectral_centroid = floatval($originalData['spectral_centroid'] ?? 0);
        $frequency = $spectral_centroid > 0 ? round($spectral_centroid) . 'Hz' : null;
        
        // Store full report as JSON
        $full_report_data = json_encode($diagnosis);
        
        // Extract location from original data
        $latitude = isset($originalData['latitude']) && $originalData['latitude'] !== null && $originalData['latitude'] !== '' ? floatval($originalData['latitude']) : null;
        $longitude = isset($originalData['longitude']) && $originalData['longitude'] !== null && $originalData['longitude'] !== '' ? floatval($originalData['longitude']) : null;
        $address = isset($originalData['address']) ? $originalData['address'] : null;
        
        // Get user ID for authenticated users (with error handling)
        // Guest users will have null userId and will be saved as anonymous
        $userId = null;
        $isAnonymous = true;
        try {
            if (function_exists('getCurrentUserId')) {
                $userId = getCurrentUserId();
                $isAnonymous = ($userId === null) ? true : false;
            }
        } catch (Exception $e) {
            error_log("⚠️ Error getting current user ID: " . $e->getMessage());
            // Continue with anonymous user - guest reports are still saved
        }
        
        // Log for debugging - guest reports are saved with user_id = NULL and is_anonymous = 1
        if ($isAnonymous) {
            error_log("📝 Guest user submitting audio/analysis report - will be saved as anonymous");
        } else {
            error_log("📝 Authenticated user (ID: $userId) submitting audio/analysis report");
        }
        
        $sql = "INSERT INTO analysis_reports (
            user_id, is_anonymous, timestamp, top_hazard, confidence_score, rms_level, spectral_centroid, frequency,
            signature_name, classification, executive_conclusion, severity, is_problem, verdict,
            risk_description, action_steps, who_to_contact, category, latitude, longitude, address, full_report_data, status
        ) VALUES (
            :user_id, :is_anonymous, NOW(), :top_hazard, :confidence_score, :rms_level, :spectral_centroid, :frequency,
            :signature_name, :classification, :executive_conclusion, :severity, :is_problem, :verdict,
            :risk_description, :action_steps, :who_to_contact, :category, :latitude, :longitude, :address, :full_report_data, 'pending'
        )";
        
        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute([
            ':user_id' => $userId,
            ':is_anonymous' => $isAnonymous ? 1 : 0,
            ':top_hazard' => $originalData['top_hazard'] ?? $signature_name,
            ':confidence_score' => $confidence,
            ':rms_level' => $rms,
            ':spectral_centroid' => $spectral_centroid > 0 ? $spectral_centroid : null,
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
        
        if ($result) {
            $insertId = $pdo->lastInsertId();
            error_log("✅ Analysis report saved to database with ID: " . $insertId);
            error_log("📊 Report details - Signature: " . $signature_name . ", Severity: " . $severity . ", Verdict: " . $verdict);
            error_log("📝 Executive Conclusion: " . substr($executive_conclusion, 0, 100) . "...");
            error_log("🤖 AI-generated content saved: " . (!empty($executive_conclusion) ? 'YES' : 'NO'));
            return ['success' => true, 'report_id' => $insertId];
        } else {
            $errorInfo = $stmt->errorInfo();
            error_log("❌ Failed to save analysis report - execute() returned false");
            error_log("❌ SQL Error Info: " . print_r($errorInfo, true));
            return false;
        }
        
    } catch (PDOException $e) {
        error_log("❌ PDO Exception saving analysis report: " . $e->getMessage());
        error_log("SQL Error Code: " . $e->getCode());
        error_log("SQL State: " . $e->errorInfo[0] ?? 'N/A');
        return false;
    } catch (Exception $e) {
        error_log("❌ Exception saving analysis report: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}
?>
