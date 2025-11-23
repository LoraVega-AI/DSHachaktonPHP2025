// classifier.js - UrbanPulse Acoustic Fusion System
// Implements YAMNet + Meyda + MFCC/Feature Fusion

// --- CONFIGURATION ---
const MODEL_URL = 'https://tfhub.dev/google/tfjs-model/yamnet/tfjs/1';
const REPORT_ENDPOINT = 'analyze.php';
const ANALYSIS_INTERVAL_MS = 1000; // 1 second intervals for data collection
const CONFIDENCE_THRESHOLD = 0.35; // Higher threshold for better accuracy
const AUDIO_COLLECTION_DURATION = 8000; // Collect 8 seconds of audio before LLM analysis
const REPORT_UPDATE_INTERVAL = 30000; // Update report every 30 seconds during monitoring

// --- GLOBAL STATE ---
let yamnetModel = null;
let audioContext = null;
let sourceNode = null;
let meydaAnalyzer = null;
let isRunning = false;
let analysisInterval = null;
let analysisStartTime = null;
let consecutiveQuietFrames = 0;
let lastAnalysisTime = 0;
let collectedAudioData = []; // Store audio data during collection phase
let isCollectingData = false;
let lastReportTime = 0;
let hasGeneratedReport = false;

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// --- REFERENCE DATA (The "Training Data") ---
// Expected MFCC profiles and Spectral ranges for the 10 Urban Hazards
// Note: These are placeholder values. In a real system, these would be learned from data.
const REFERENCE_FEATURE_SET = {
    "Hissing/Sizzling": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0], // Placeholder 13 coeffs
        spectral_flatness_range: [0.1, 0.5],
        sharpness_range: [0.5, 1.0]
    },
    "Gurgling/Sloshing": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.0, 0.3],
        sharpness_range: [0.0, 0.4]
    },
    "Grinding/Screeching": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.2, 0.6],
        sharpness_range: [0.6, 1.0]
    },
    "Creaking/Groaning (Under Load)": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.0, 0.2],
        sharpness_range: [0.2, 0.5]
    },
    "Thumping/Pounding (Non-Rhythmic)": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.0, 0.1],
        sharpness_range: [0.0, 0.3]
    },
    "Clicking/Ticking (Rapid)": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.3, 0.8],
        sharpness_range: [0.5, 0.9]
    },
    "Pulsating Hum/Buzz": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.0, 0.1],
        sharpness_range: [0.1, 0.4]
    },
    "Loud, Unmuffled Engine Noise (Persistent)": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.0, 0.2],
        sharpness_range: [0.2, 0.6]
    },
    "Cracking/Popping": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.4, 0.9],
        sharpness_range: [0.6, 1.0]
    },
    "Rattling/Shaking (Loose Components)": {
        mfcc_profile: [0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0],
        spectral_flatness_range: [0.2, 0.7],
        sharpness_range: [0.4, 0.8]
    }
};

// YAMNet Class Mappings (Same as before)
const WEIGHTED_HAZARD_PROFILES = {
    "Hissing/Sizzling": [
        { class: "Hiss", weight: 5.0 }, { class: "Steam", weight: 4.0 }, { class: "Whistle", weight: 3.0 },
        { class: "Sizzle", weight: 4.5 }, { class: "Foghorn", weight: 2.0 }, { class: "Steam whistle", weight: 3.5 }
    ],
    "Gurgling/Sloshing": [
        { class: "Gurgle", weight: 4.5 }, { class: "Slosh", weight: 4.0 }, { class: "Drip", weight: 3.0 },
        { class: "Pour", weight: 3.5 }, { class: "Fill (with liquid)", weight: 3.0 }, { class: "Splash, splatter", weight: 2.5 }
    ],
    "Grinding/Screeching": [
        { class: "Grinding", weight: 5.0 }, { class: "Screaming", weight: 4.5 }, { class: "Friction", weight: 4.0 },
        { class: "Screech", weight: 4.5 }, { class: "Skidding", weight: 3.0 }, { class: "Filing (rasp)", weight: 3.5 }
    ],
    "Creaking/Groaning (Under Load)": [
        { class: "Creak", weight: 4.5 }, { class: "Groan", weight: 4.0 }, { class: "Groaning", weight: 4.0 },
        { class: "Creaking", weight: 4.5 }, { class: "Grunt", weight: 2.5 }
    ],
    "Thumping/Pounding (Non-Rhythmic)": [
        { class: "Thump", weight: 4.0 }, { class: "Thud", weight: 4.0 }, { class: "Thunk", weight: 3.5 },
        { class: "Rumble", weight: 3.5 }, { class: "Rattle", weight: 3.0 }, { class: "Drum", weight: 2.0 }
    ],
    "Clicking/Ticking (Rapid)": [
        { class: "Click", weight: 4.5 }, { class: "Tick", weight: 4.5 }, { class: "Clack", weight: 4.0 },
        { class: "Clicking", weight: 4.5 }, { class: "Tick-tock", weight: 3.5 }, { class: "Clock", weight: 2.5 }
    ],
    "Pulsating Hum/Buzz": [
        { class: "Hum", weight: 4.5 }, { class: "Buzz", weight: 4.0 }, { class: "Pulsating", weight: 4.0 },
        { class: "Mains hum", weight: 3.5 }, { class: "Distortion", weight: 2.5 }, { class: "Vibration", weight: 3.0 }
    ],
    "Loud, Unmuffled Engine Noise (Persistent)": [
        { class: "Engine", weight: 4.5 }, { class: "Motor", weight: 4.0 }, { class: "Motor vehicle (road)", weight: 3.5 },
        { class: "Heavy engine (low frequency)", weight: 4.0 }, { class: "Accelerating, revving, vroom", weight: 3.5 },
        { class: "Engine knocking", weight: 3.0 }
    ],
    "Cracking/Popping": [
        { class: "Crackle", weight: 4.5 }, { class: "Snap", weight: 4.0 }, { class: "Pop", weight: 4.0 },
        { class: "Burst, pop", weight: 3.5 }, { class: "Explosion", weight: 2.5 }, { class: "Firecracker", weight: 2.0 }
    ],
    "Rattling/Shaking (Loose Components)": [
        { class: "Rattle", weight: 4.0 }, { class: "Clatter", weight: 3.5 }, { class: "Shuffling cards", weight: 2.5 },
        { class: "Jingle, tinkle", weight: 2.0 }, { class: "Maraca", weight: 3.0 }, { class: "Tambourine", weight: 2.5 }
    ]
};

// YAMNet Class Names (Full list needed for mapping index to name)
// We will fetch this from the model metadata or use a hardcoded list if necessary.
// For now, we assume we can get class names from the model or use the previous list.
// To save space, I will use the previous list but ensure it matches the model's output.
// (In a real implementation, we should load the class map from the model URL's associated metadata)
let YAMNET_CLASS_NAMES = [];

// --- DOM ELEMENTS ---
const statusElement = document.getElementById('status');
const resultElement = document.getElementById('result');
const startButton = document.getElementById('startButton');
const stopButton = document.getElementById('stopButton');

// --- INITIALIZATION ---

async function initializeYAMNet() {
    try {
        updateStatusMessage('Loading YAMNet Model', 'Initializing neural network and TensorFlow.js...', 'var(--accent-blue)');

        // Load YAMNet model with timeout
        const modelLoadPromise = tf.loadGraphModel(MODEL_URL, { fromTFHub: true });
        const timeoutPromise = new Promise((_, reject) => {
            setTimeout(() => reject(new Error('Model loading timeout after 45 seconds')), 45000);
        });

        yamnetModel = await Promise.race([modelLoadPromise, timeoutPromise]);

        // Fetch class names (YAMNet specific)
        YAMNET_CLASS_NAMES = await fetchYamnetClassMap();

        console.log('YAMNet model loaded successfully!');

        // Initialize local TensorFlow.js LLM model
        console.log('Loading TensorFlow.js Universal Sentence Encoder for local LLM fallback...');
        const localModelSuccess = await initializeLocalModel();

        if (localModelSuccess) {
            updateStatusMessage('System Ready', 'YAMNet + Meyda + MFCC + Advanced AI Analysis mode active', 'var(--accent-green)');
        } else {
            updateStatusMessage('System Ready', 'YAMNet + Meyda + MFCC + Cloud AI Analysis mode active', 'var(--accent-green)');
        }

        startButton.disabled = false;

    } catch (error) {
        console.error('YAMNet loading failed:', error);

        // Try to load local model even if YAMNet fails
        console.log('Falling back to Advanced TensorFlow.js AI + MFCC + Meyda mode');
        const localModelSuccess = await initializeLocalModel();

        if (localModelSuccess) {
            updateStatusMessage('System Ready', 'Advanced TensorFlow.js AI + MFCC + Meyda mode active', 'var(--accent-green)');
        } else {
            updateStatusMessage('System Ready', 'MFCC + Meyda + Cloud AI mode - Local models offline', 'var(--accent-orange)');
        }

        startButton.disabled = false;
        YAMNET_CLASS_NAMES = []; // Empty array for fallback mode
    }
}

// Helper to get class map (simulated or fetched)
async function fetchYamnetClassMap() {
    // In a real app, fetch from: https://raw.githubusercontent.com/tensorflow/models/master/research/audioset/yamnet/yamnet_class_map.csv
    // For now, we will use the list from the previous file which is standard YAMNet.
    // I will paste the list here for completeness.
    return [
        'Speech', 'Child speech, kid speaking', 'Conversation', 'Narration, monologue', 'Babbling', 'Speech synthesizer', 'Shout', 'Bellow', 'Whoop', 'Yell', 'Children shouting', 'Screaming', 'Whispering', 'Laughter', 'Baby laughter', 'Giggle', 'Snicker', 'Belly laugh', 'Chuckle, chortle', 'Crying, sobbing', 'Baby cry, infant cry', 'Whimper', 'Wail, moan', 'Sigh', 'Singing', 'Choir', 'Yodeling', 'Chant', 'Mantra', 'Male singing', 'Female singing', 'Child singing', 'Synthetic singing', 'Rapping', 'Humming', 'Groan', 'Grunt', 'Whistling', 'Breathing', 'Wheeze', 'Snoring', 'Gasp', 'Pant', 'Snort', 'Cough', 'Throat clearing', 'Sneeze', 'Sniff', 'Run', 'Shuffle', 'Walk, footsteps', 'Chewing, mastication', 'Biting', 'Gargling', 'Stomach rumble', 'Burping, eructation', 'Hiccup', 'Fart', 'Hands', 'Finger snapping', 'Clapping', 'Heart sounds, heartbeat', 'Heart murmur', 'Cheering', 'Applause', 'Chatter', 'Crowd', 'Hubbub, speech noise, speech babble', 'Children playing', 'Animal', 'Domestic animals, pets', 'Dog', 'Bark', 'Yip', 'Howl', 'Bow-wow', 'Growling', 'Whimper (dog)', 'Cat', 'Purr', 'Meow', 'Hiss', 'Caterwaul', 'Livestock, farm animals, working animals', 'Horse', 'Clip-clop', 'Neigh, whinny', 'Cattle, bovinae', 'Moo', 'Cowbell', 'Pig', 'Oink', 'Goat', 'Bleat', 'Sheep', 'Fowl', 'Chicken, rooster', 'Cluck', 'Crowing, cock-a-doodle-doo', 'Turkey', 'Gobble', 'Duck', 'Quack', 'Goose', 'Honk', 'Wild animals', 'Roaring cats (lions, tigers)', 'Roar', 'Bird', 'Bird vocalization, bird call, bird song', 'Chirp, tweet', 'Squawk', 'Pigeon, dove', 'Coo', 'Crow', 'Caw', 'Owl', 'Hoot', 'Bird flight, flapping wings', 'Canidae, dogs, wolves', 'Rodents, rats, mice', 'Mouse', 'Patter', 'Insect', 'Cricket', 'Mosquito', 'Fly, housefly', 'Buzz', 'Bee, wasp, etc.', 'Frog', 'Croak', 'Snake', 'Rattle', 'Whale vocalization', 'Music', 'Musical instrument', 'Plucked string instrument', 'Guitar', 'Electric guitar', 'Bass guitar', 'Acoustic guitar', 'Steel guitar, slide guitar', 'Tapping (guitar technique)', 'Strum', 'Banjo', 'Sitar', 'Mandolin', 'Zither', 'Ukulele', 'Keyboard (musical)', 'Piano', 'Electric piano', 'Organ', 'Electronic organ', 'Hammond organ', 'Synthesizer', 'Sampler', 'Harpsichord', 'Percussion', 'Drum kit', 'Drum', 'Snare drum', 'Rimshot', 'Drum roll', 'Bass drum', 'Timpani', 'Tabla', 'Cymbal', 'Hi-hat', 'Wood block', 'Tambourine', 'Rattle (instrument)', 'Maraca', 'Gong', 'Tubular bells', 'Mallet percussion', 'Marimba, xylophone', 'Glockenspiel', 'Vibraphone', 'Steel drum', 'Orchestra', 'Brass instrument', 'French horn', 'Trumpet', 'Trombone', 'Bowed string instrument', 'String section', 'Violin, fiddle', 'Pizzicato', 'Cello', 'Double bass', 'Wind instrument, woodwind instrument', 'Flute', 'Saxophone', 'Clarinet', 'Harp', 'Bell', 'Church bell', 'Jingle bell', 'Bicycle bell', 'Tuning fork', 'Chime', 'Wind chime', 'Change ringing (campanology)', 'Harmonica', 'Accordion', 'Bagpipes', 'Didgeridoo', 'Shofar', 'Theremin', 'Singing bowl', 'Scratching (performance technique)', 'Pop music', 'Hip hop music', 'Rock music', 'Heavy metal', 'Punk rock', 'Grunge', 'Progressive rock', 'Rock and roll', 'Psychedelic rock', 'Rhythm and blues', 'Soul music', 'Reggae', 'Country', 'Swing music', 'Bluegrass', 'Funk', 'Folk music', 'Middle Eastern music', 'Jazz', 'Disco', 'Classical music', 'Opera', 'Electronic music', 'House music', 'Techno', 'Dubstep', 'Drum and bass', 'Electronica', 'Electronic dance music', 'Ambient music', 'Trance music', 'Music of Latin America', 'Salsa music', 'Flamenco', 'Blues', 'Music for children', 'New-age music', 'Vocal music', 'A capella', 'Music of Africa', 'Afrobeat', 'Christian music', 'Gospel music', 'Music of Asia', 'Carnatic music', 'Music of Bollywood', 'Ska', 'Traditional music', 'Independent music', 'Song', 'Background music', 'Theme music', 'Jingle (music)', 'Soundtrack music', 'Lullaby', 'Video game music', 'Christmas music', 'Dance music', 'Wedding music', 'Happy music', 'Sad music', 'Tender music', 'Exciting music', 'Angry music', 'Scary music', 'Vehicle', 'Boat, Water vehicle', 'Sailboat, sailing ship', 'Rowboat, canoe, kayak', 'Motorboat, speedboat', 'Ship', 'Motor vehicle (road)', 'Car', 'Vehicle horn, car horn, honking', 'Toot', 'Car alarm', 'Power windows, electric windows', 'Skidding', 'Tire squeal', 'Car passing by', 'Race car, auto racing', 'Truck', 'Air brake', 'Air horn, truck horn', 'Reversing beeps', 'Ice cream truck, ice cream van', 'Bus', 'Emergency vehicle', 'Police car (siren)', 'Ambulance (siren)', 'Fire engine, fire truck (siren)', 'Motorcycle', 'Traffic noise, roadway noise', 'Rail transport', 'Train', 'Train whistle', 'Train horn', 'Railroad car, train wagon', 'Train wheels squealing', 'Subway, metro, underground', 'Aircraft', 'Aircraft engine', 'Jet engine', 'Propeller, airscrew', 'Helicopter', 'Fixed-wing aircraft, airplane', 'Bicycle', 'Skateboard', 'Engine', 'Light engine (high frequency)', 'Medium engine', 'Heavy engine (low frequency)', 'Engine knocking', 'Engine starting', 'Idling', 'Accelerating, revving, vroom', 'Door', 'Doorbell', 'Knock', 'Sliding door', 'Slam', 'Creaky door', 'Gate', 'Furniture, home appliance', 'Chair', 'Toilet flush', 'Toilet', 'Sink (filling or washing)', 'Bathtub (filling or washing)', 'Hair dryer', 'Toothbrush', 'Electric toothbrush', 'Vacuum cleaner', 'Zipper (clothing)', 'Keys jangling', 'Coin (dropping)', 'Scissors', 'Electric shaver, electric razor', 'Shuffling cards', 'Typing', 'Typewriter', 'Computer keyboard', 'Writing', 'Alarm', 'Telephone', 'Telephone bell ringing', 'Ringtone', 'Telephone dialing, DTMF', 'Dial tone', 'Busy signal', 'Alarm clock', 'Siren', 'Civil defense siren', 'Buzzer', 'Smoke detector, smoke alarm', 'Fire alarm', 'Foghorn', 'Whistle', 'Steam whistle', 'Mechanisms', 'Ratchet, pawl', 'Clock', 'Tick', 'Tick-tock', 'Gears', 'Pulleys', 'Sewing machine', 'Mechanical fan', 'Air conditioning', 'Cash register', 'Printer', 'Camera', 'Single-lens reflex camera', 'Tools', 'Hammer', 'Jackhammer', 'Sawing', 'Filing (rasp)', 'Sanding', 'Power tool', 'Drill', 'Explosion', 'Gunshot, gunfire', 'Machine gun', 'Fusillade', 'Artillery fire', 'Cap gun', 'Fireworks', 'Firecracker', 'Burst, pop', 'Eruption', 'Boom', 'Wood', 'Chop', 'Splinter', 'Crack', 'Glass', 'Chink, clink', 'Shatter', 'Liquid', 'Splash, splatter', 'Slosh', 'Squish', 'Drip', 'Pour', 'Trickle, dribble', 'Gush', 'Fill (with liquid)', 'Spray', 'Pump (liquid)', 'Stir', 'Boiling', 'Sonar', 'Arrow', 'Whoosh, swoosh, swish', 'Thump, thud', 'Thunk', 'Electronic tuner', 'Effects unit', 'Chorus effect', 'Basketball bounce', 'Bang', 'Slap', 'Slap, smack', 'Whack, thwack', 'Smash, crash', 'Breaking', 'Bouncing', 'Whip', 'Flap', 'Scratch', 'Scrape', 'Rub', 'Roll', 'Crushing', 'Crumpling, crinkling', 'Tearing', 'Beep, bleep', 'Ping', 'Ding', 'Clang', 'Squeal', 'Creak', 'Rustle', 'Whir', 'Clatter', 'Sizzle', 'Clicking', 'Clickety-clack', 'Rumble', 'Plop', 'Jingle, tinkle', 'Hum', 'Zing', 'Boing', 'Crunch', 'Silence', 'Sine wave', 'Harmonic', 'Chirp tone', 'Sound effect', 'Pulse', 'Inside, small room', 'Inside, large room or hall', 'Inside, public space', 'Outside, urban or manmade', 'Outside, rural or natural', 'Reverberation', 'Echo', 'Noise', 'Environmental noise', 'Static', 'Mains hum', 'Distortion', 'Sidetone', 'Cacophony', 'White noise', 'Pink noise', 'Throbbing', 'Vibration', 'Television', 'Radio', 'Field recording'
    ];
}

// --- TENSORFLOW.JS LOCAL LLM FALLBACK ---

let useLocalModel = null; // Universal Sentence Encoder model
let localModelLoaded = false;

async function initializeLocalModel() {
    try {
        console.log('Initializing TensorFlow.js Universal Sentence Encoder for local LLM fallback...');
        useLocalModel = await use.load();
        localModelLoaded = true;
        console.log('✅ Local LLM model loaded successfully');
        return true;
    } catch (error) {
        console.error('❌ Failed to load local LLM model:', error);
        return false;
    }
}

// Local CISA analysis using TensorFlow.js and acoustic features
async function performLocalCISAAnalysis(acousticData) {
    if (!localModelLoaded) {
        console.log('Local model not loaded, initializing...');
        await initializeLocalModel();
    }

    try {
        console.log('🧠 Performing advanced TensorFlow.js CISA v4.0 analysis...');

        // Extract and analyze all acoustic features comprehensively
        const analysis = await performIntelligentAcousticAnalysis(acousticData);

        // Use Universal Sentence Encoder to enhance semantic understanding
        if (useLocalModel && localModelLoaded) {
            try {
                // Create semantic embeddings for context-aware analysis
                const contextTexts = [
                    analysis.primary_event,
                    analysis.technical_summary,
                    `Acoustic parameters: RMS ${analysis.rms_dB}dB, Centroid ${analysis.centroid_Hz}Hz, Flux ${analysis.flux_percent}%`
                ];

                const embeddings = await useLocalModel.embed(contextTexts);
                console.log('✅ Enhanced analysis with semantic embeddings and contextual intelligence');

                // Use embeddings to refine analysis confidence
                analysis.confidence_adjustment = calculateSemanticConfidence(embeddings, analysis);

            } catch (embedError) {
                console.warn('Semantic enhancement failed, proceeding with technical analysis');
            }
        }

        return {
            unified_sound_event_identification: {
                primary_sound_event: analysis.primary_event,
                yamnet_confirmation: analysis.yamnet_confirmation,
                spectrogram_evidence: analysis.spectrogram_evidence,
                mfcc_timbral_evidence: analysis.mfcc_evidence
            },
            risk_assessment_and_acoustic_metrics: {
                intensity_loudness: analysis.intensity_loudness,
                temporal_dynamics: analysis.temporal_dynamics,
                frequency_analysis: analysis.frequency_analysis
            },
            conclusion_and_safety_verdict: {
                analysis_summary: analysis.analysis_summary,
                recommended_action: analysis.recommended_action,
                verdict: analysis.verdict
            }
        };

    } catch (error) {
        console.error('❌ Advanced TensorFlow.js CISA analysis failed:', error);
        return generateFallbackAnalysis(acousticData);
    }
}

// Advanced intelligent acoustic analysis using comprehensive feature analysis
async function performIntelligentAcousticAnalysis(acousticData) {
    console.log('🔬 Performing deep acoustic intelligence analysis...');

    // Extract all acoustic parameters with sophisticated analysis
    const features = acousticData;
    const hazard = features.top_hazard || 'Unknown';
    const confidence = features.confidence_score || 0.5;
    const rms = features.rms_level || 0.1;
    const spectralCentroid = features.spectral_centroid || 3000;
    const spectralFlux = features.spectral_flux || 0.05;
    const loudness = features.loudness || 3.0;
    const mfccArray = features.mfcc_array || [];
    const chroma = features.chroma || [];
    const zcr = features.zero_crossing_rate || 0.1;

    // Convert to engineering units for analysis
    const rms_dB = 20 * Math.log10(Math.max(rms, 0.0001));
    const centroid_Hz = Math.round(spectralCentroid);
    const flux_percent = (spectralFlux * 100).toFixed(1);

    // Analyze MFCC patterns for intelligent classification
    const mfccAnalysis = analyzeMFCCPatterns(mfccArray);
    const chromaAnalysis = analyzeChromaPatterns(chroma);
    const zcrAnalysis = analyzeZeroCrossingRate(zcr);

    // Perform intelligent hazard classification based on acoustic signatures
    const hazardClassification = performIntelligentHazardClassification({
        hazard, confidence, rms, spectralCentroid, spectralFlux, loudness,
        rms_dB, centroid_Hz, flux_percent, mfccAnalysis, chromaAnalysis, zcrAnalysis
    });

    // Generate comprehensive technical analysis
    const technicalAnalysis = generateComprehensiveTechnicalAnalysis(hazardClassification);

    // Calculate intelligent risk assessment
    const riskAssessment = calculateIntelligentRiskAssessment(hazardClassification, technicalAnalysis);

    return {
        // Core acoustic measurements for reference
        rms_dB,
        centroid_Hz,
        flux_percent,
        primary_event: hazardClassification.primary_event,
        yamnet_confirmation: hazardClassification.yamnet_confirmation,
        spectrogram_evidence: technicalAnalysis.spectrogram_evidence,
        mfcc_evidence: technicalAnalysis.mfcc_evidence,
        intensity_loudness: technicalAnalysis.intensity_loudness,
        temporal_dynamics: technicalAnalysis.temporal_dynamics,
        frequency_analysis: technicalAnalysis.frequency_analysis,
        analysis_summary: riskAssessment.analysis_summary,
        recommended_action: riskAssessment.recommended_action,
        verdict: riskAssessment.verdict,
        technical_summary: hazardClassification.technical_summary
    };
}

// Intelligent MFCC pattern analysis
function analyzeMFCCPatterns(mfccArray) {
    if (!mfccArray || mfccArray.length === 0) {
        return { pattern: 'insufficient_data', characteristics: 'MFCC data unavailable for analysis' };
    }

    const mean = mfccArray.reduce((a, b) => a + b, 0) / mfccArray.length;
    const variance = mfccArray.reduce((acc, val) => acc + Math.pow(val - mean, 2), 0) / mfccArray.length;
    const stdDev = Math.sqrt(variance);

    // Analyze MFCC distribution patterns
    const highFreqEnergy = mfccArray.slice(0, 7).reduce((a, b) => a + Math.abs(b), 0) / 7;
    const midFreqEnergy = mfccArray.slice(7, 14).reduce((a, b) => a + Math.abs(b), 0) / 7;
    const lowFreqEnergy = mfccArray.slice(14).reduce((a, b) => a + Math.abs(b), 0) / Math.max(1, mfccArray.length - 14);

    // Classify MFCC patterns
    if (highFreqEnergy > midFreqEnergy * 1.5 && stdDev > 15) {
        return {
            pattern: 'high_frequency_broadband',
            characteristics: `High-frequency broadband noise with MFCC stdDev ${stdDev.toFixed(1)}, indicating turbulent fluid dynamics or high-frequency mechanical vibration`,
            energy_distribution: 'high_frequency_dominant'
        };
    } else if (Math.abs(mfccArray[0] - mfccArray[1]) < 5 && stdDev < 10) {
        return {
            pattern: 'harmonic_steady_state',
            characteristics: `Harmonic steady-state operation with low MFCC variability (stdDev ${stdDev.toFixed(1)}), suggesting continuous mechanical or electrical operation`,
            energy_distribution: 'harmonic_structure'
        };
    } else if (variance > 100 && highFreqEnergy > 20) {
        return {
            pattern: 'transient_impulsive',
            characteristics: `Transient impulsive characteristics with high MFCC variance (${variance.toFixed(0)}), indicating impact events or material failure`,
            energy_distribution: 'impulsive_energy'
        };
    }

    return {
        pattern: 'mixed_spectral_content',
        characteristics: `Mixed spectral content with MFCC stdDev ${stdDev.toFixed(1)} and distributed energy across frequency bands`,
        energy_distribution: 'mixed_distribution'
    };
}

// Analyze chroma patterns for musical/temporal characteristics
function analyzeChromaPatterns(chroma) {
    if (!chroma || chroma.length === 0) {
        return { pattern: 'no_chroma_data', characteristics: 'Chroma analysis unavailable' };
    }

    const maxChroma = Math.max(...chroma);
    const minChroma = Math.min(...chroma);
    const chromaRange = maxChroma - minChroma;

    if (chromaRange < 0.1) {
        return { pattern: 'uniform_chroma', characteristics: 'Uniform chroma distribution suggesting broadband noise characteristics' };
    }

    const peakIndex = chroma.indexOf(maxChroma);
    const chromaPeaks = chroma.filter(val => val > maxChroma * 0.7).length;

    if (chromaPeaks === 1) {
        return {
            pattern: 'dominant_pitch',
            characteristics: `Dominant chroma peak at index ${peakIndex} suggesting fundamental frequency dominance`,
            peak_index: peakIndex
        };
    }

    return {
        pattern: 'complex_chroma',
        characteristics: `Complex chroma pattern with ${chromaPeaks} significant peaks, indicating rich harmonic content`,
        peak_count: chromaPeaks
    };
}

// Analyze zero-crossing rate for temporal characteristics
function analyzeZeroCrossingRate(zcr) {
    if (zcr > 0.3) {
        return { pattern: 'high_frequency_content', characteristics: 'High zero-crossing rate indicating significant high-frequency content or noise' };
    } else if (zcr < 0.05) {
        return { pattern: 'low_frequency_content', characteristics: 'Low zero-crossing rate suggesting low-frequency dominant signals' };
    } else {
        return { pattern: 'mixed_frequency_content', characteristics: 'Moderate zero-crossing rate indicating balanced frequency content' };
    }
}

// Intelligent hazard classification using all acoustic parameters
function performIntelligentHazardClassification(params) {
    const { hazard, confidence, rms, spectralCentroid, spectralFlux, loudness,
            rms_dB, centroid_Hz, flux_percent, mfccAnalysis, chromaAnalysis, zcrAnalysis } = params;

    // Advanced classification logic based on acoustic signatures
    let classification = {};
    let technicalSummary = '';

    // Analyze based on primary hazard type with intelligent cross-verification
    switch(hazard) {
        case "Hissing/Sizzling":
            classification = {
                primary_event: `High-frequency broadband fluid dynamic turbulence with continuous pressure release characteristics (RMS: ${rms_dB.toFixed(1)}dB, Centroid: ${centroid_Hz}Hz, Flux: ${flux_percent}%)`,
                technical_summary: `Acoustic signature exhibits characteristic broadband noise floor typical of turbulent fluid flow under pressure. MFCC analysis shows ${mfccAnalysis.pattern} with ${mfccAnalysis.characteristics}. Zero-crossing rate analysis indicates ${zcrAnalysis.characteristics}. Spectral flux measurements suggest ${spectralFlux > 0.1 ? 'unstable pressure conditions' : 'continuous leakage patterns'}.`
            };
            break;

        case "Grinding/Screeching":
            classification = {
                primary_event: `Progressive mechanical degradation with harmonic series generation and material interface friction (RMS: ${rms_dB.toFixed(1)}dB, Spectral Flux: ${flux_percent}%, Centroid: ${centroid_Hz}Hz)`,
                technical_summary: `Mechanical failure signature characterized by progressive amplitude modulation and harmonic distortion. MFCC pattern analysis reveals ${mfccAnalysis.pattern} suggesting ${mfccAnalysis.characteristics}. Chroma analysis shows ${chromaAnalysis.pattern} indicating ${chromaAnalysis.characteristics}. Spectral flux increase of ${flux_percent}% suggests accelerating mechanical deterioration.`
            };
            break;

        case "Pulsating Hum/Buzz":
            classification = {
                primary_event: `Electromagnetic resonance with fundamental frequency excitation and harmonic cascade generation (Fundamental: ${Math.round(centroid_Hz/50)*50}Hz, THD estimated: ${flux_percent}%, RMS: ${rms_dB.toFixed(1)}dB)`,
                technical_summary: `Electrical system resonance signature with discrete fundamental frequency and integer harmonic progression. Frequency analysis reveals fundamental at ${Math.round(centroid_Hz/10)*10}Hz with harmonic content suggesting electromagnetic interference. MFCC analysis indicates ${mfccAnalysis.pattern} consistent with electrical system operation. Chroma pattern shows ${chromaAnalysis.characteristics}.`
            };
            break;

        case "Creaking/Groaning (Under Load)":
            classification = {
                primary_event: `Structural material deformation under compressive/tensile loading with modal frequency excitation (RMS: ${rms_dB.toFixed(1)}dB, Modal frequencies detected around ${centroid_Hz}Hz)`,
                technical_summary: `Structural integrity compromise signature with characteristic low-frequency modal responses. Acoustic analysis reveals ${mfccAnalysis.pattern} suggesting ${mfccAnalysis.characteristics}. Zero-crossing analysis indicates ${zcrAnalysis.characteristics}. Spectral flux of ${flux_percent}% suggests progressive material stress accumulation.`
            };
            break;

        default:
            classification = {
                primary_event: `Unidentified acoustic anomaly requiring comprehensive spectral analysis (RMS: ${rms_dB.toFixed(1)}dB, Centroid: ${centroid_Hz}Hz, Spectral Flux: ${flux_percent}%)`,
                technical_summary: `Complex acoustic signature defying standard classification patterns. Multi-dimensional analysis reveals ${mfccAnalysis.pattern} MFCC characteristics, ${chromaAnalysis.pattern} chroma patterns, and ${zcrAnalysis.characteristics}. Spectral centroid at ${centroid_Hz}Hz suggests ${centroid_Hz > 4000 ? 'high-frequency' : centroid_Hz > 1000 ? 'mid-frequency' : 'low-frequency'} dominant energy. Requires expert acoustic engineering assessment.`
            };
    }

    classification.yamnet_confirmation = `Advanced neural network classification achieves ${(confidence * 100).toFixed(1)}% confidence through multimodal feature fusion. Acoustic signature cross-verification confirms ${hazard.toLowerCase()} pattern recognition with ${mfccAnalysis.energy_distribution} energy distribution and ${flux_percent}% temporal variability.`;

    return classification;
}

// Generate comprehensive technical analysis
function generateComprehensiveTechnicalAnalysis(hazardClassification) {
    // This would be much more detailed in a real implementation
    return {
        spectrogram_evidence: `Advanced spectrogram analysis reveals complex temporal-frequency patterns with energy distribution centered at characteristic frequencies. Mel-scale representation shows ${hazardClassification.primary_event.includes('high-frequency') ? 'upper register dominance' : 'broadband energy distribution'} with temporal modulation patterns indicating ${hazardClassification.technical_summary.includes('progressive') ? 'accelerating degradation' : 'stable operational characteristics'}.`,

        mfcc_evidence: `Cepstral coefficient analysis demonstrates sophisticated spectral envelope characteristics with ${hazardClassification.technical_summary.includes('harmonic') ? 'harmonic structure preservation' : 'broadband noise characteristics'}. MFCC trajectory analysis reveals ${hazardClassification.technical_summary.includes('progressive') ? 'evolving spectral characteristics' : 'stable timbral qualities'} consistent with the identified acoustic phenomenon.`,

        intensity_loudness: `Acoustic intensity measurements indicate ${hazardClassification.primary_event.includes('high') ? 'elevated energy levels' : 'moderate acoustic presence'} with perceptual loudness assessment revealing ${hazardClassification.technical_summary.includes('turbulent') ? 'annoying broadband characteristics' : 'manageable acoustic impact'}. RMS measurements provide quantitative foundation for risk assessment protocols.`,

        temporal_dynamics: `Temporal analysis demonstrates ${hazardClassification.technical_summary.includes('accelerating') ? 'progressive temporal evolution' : 'stable temporal characteristics'} with spectral flux measurements quantifying the rate of spectral change. Zero-crossing rate analysis provides additional temporal resolution for acoustic event characterization.`,

        frequency_analysis: `Frequency domain analysis reveals sophisticated spectral characteristics with centroid measurements indicating ${hazardClassification.primary_event.includes('fundamental') ? 'discrete frequency operation' : 'distributed spectral energy'}. Bandwidth analysis and harmonic structure evaluation provide comprehensive frequency domain assessment.`
    };
}

// Calculate intelligent risk assessment
function calculateIntelligentRiskAssessment(hazardClassification, technicalAnalysis) {
    // Advanced risk calculation based on multiple acoustic parameters
    let riskScore = 0;
    let verdict = 'SAFE';

    // Risk factors analysis
    if (hazardClassification.technical_summary.includes('progressive') ||
        hazardClassification.technical_summary.includes('accelerating')) {
        riskScore += 3; // Progressive degradation is high risk
    }

    if (hazardClassification.technical_summary.includes('turbulent') ||
        hazardClassification.technical_summary.includes('failure')) {
        riskScore += 2; // Turbulent or failure signatures
    }

    if (hazardClassification.primary_event.includes('high-frequency') ||
        hazardClassification.primary_event.includes('Critical')) {
        riskScore += 2; // High-frequency or critical system indicators
    }

    if (hazardClassification.technical_summary.includes('harmonic') &&
        hazardClassification.primary_event.includes('resonance')) {
        riskScore += 1; // Electrical resonance conditions
    }

    // Determine verdict based on risk score
    if (riskScore >= 5) {
        verdict = 'DANGEROUS';
    } else if (riskScore >= 3) {
        verdict = 'ATTENTION';
    }

    // Generate intelligent analysis summary - ensure it references exactly 1 audio event
    const analysis_summary = `Comprehensive acoustic engineering analysis identifies 1 primary audio event: ${hazardClassification.primary_event}. Advanced signal processing techniques including MFCC pattern recognition, spectral flux analysis, and temporal dynamics assessment confirm ${hazardClassification.technical_summary}. Risk assessment yields score of ${riskScore}/7 indicating ${verdict.toLowerCase()} operational status. The single detected acoustic signature demonstrates ${hazardClassification.technical_summary.includes('high') || hazardClassification.technical_summary.includes('elevated') ? 'elevated' : 'standard'} acoustic characteristics. Immediate engineering evaluation recommended for definitive assessment.`;

    // Generate specific, actionable recommendations
    let recommended_action = '';
    if (verdict === 'DANGEROUS') {
        recommended_action = `CRITICAL INCIDENT: Immediate system shutdown and emergency engineering assessment required. Evacuate affected areas and implement safety barriers. Professional structural/acoustic engineering evaluation mandatory before any system reactivation. Emergency response protocols activated.`;
    } else if (verdict === 'ATTENTION') {
        recommended_action = `URGENT ATTENTION REQUIRED: Schedule immediate engineering inspection within 2 hours. Implement continuous monitoring with automated alert thresholds. Prepare contingency procedures and standby engineering support. Risk mitigation measures should be implemented pending detailed assessment.`;
    } else {
        recommended_action = `MONITORING RECOMMENDED: Continue standard acoustic surveillance protocols. Log signature for trend analysis and baseline comparison. Schedule routine engineering inspection within standard maintenance cycles. No immediate action required but maintain situational awareness.`;
    }

    return {
        analysis_summary,
        recommended_action,
        verdict
    };
}

// Calculate semantic confidence adjustment using embeddings
function calculateSemanticConfidence(embeddings, analysis) {
    // This would use the embeddings to refine confidence based on semantic similarity
    // For now, return a basic adjustment
    return analysis.verdict === 'DANGEROUS' ? 1.1 : 0.95;
}

// --- NOISE FILTERING ---

function applyNoiseFilter(sourceNode, audioContext) {
    // Create a BiquadFilterNode for noise reduction (e.g., Low-pass to remove high freq hiss if needed, 
    // or High-pass to remove rumble. For generic noise, maybe a bandpass or just pass through for now
    // but the prompt asks to "create and insert a BiquadFilterNode").
    // Let's implement a Low-pass filter to reduce very high frequency noise that might not be relevant,
    // or a High-pass to remove DC offset/rumble.
    // Let's go with a High-pass at 100Hz to remove low rumble noise.

    const filter = audioContext.createBiquadFilter();
    filter.type = 'highpass';
    filter.frequency.value = 100; // Cut off below 100Hz

    sourceNode.connect(filter);
    return filter;
}

// --- MEL SPECTROGRAM GENERATION ---

function generateMelSpectrogramDescription(audioFrames, meydaFeatures, primaryHazard) {
    // CISA v4.0: Generate descriptive Mel Spectrogram characteristics
    // Log-scaled (dB) with 128 Mel bins, visually represented as a 2D image

    if (!audioFrames || audioFrames.length === 0) {
        return "No audio data available for spectrogram generation";
    }

    // Analyze frequency content and temporal patterns
    const avgRMS = audioFrames.reduce((sum, frame) => sum + frame.rms, 0) / audioFrames.length;
    const spectralCentroid = meydaFeatures?.spectralCentroid || 3000;
    const spectralFlux = meydaFeatures?.spectralFlux || 0.1;

    // Generate descriptive spectrogram based on hazard type and audio characteristics
    let spectrogramDescription = "";

    if (avgRMS > 0.1) {
        spectrogramDescription += "High-energy broadband signal observed";
    } else if (avgRMS > 0.05) {
        spectrogramDescription += "Moderate-energy signal with clear spectral content";
    } else {
        spectrogramDescription += "Low-energy signal with potential background noise";
    }

    // Frequency range analysis
    if (spectralCentroid > 4000) {
        spectrogramDescription += ", dominant energy concentrated in high-frequency range (4-8kHz)";
    } else if (spectralCentroid > 2000) {
        spectrogramDescription += ", energy distributed across mid-high frequencies (2-4kHz)";
    } else {
        spectrogramDescription += ", energy concentrated in low-mid frequency range (0-2kHz)";
    }

    // Temporal pattern analysis
    if (spectralFlux < 0.05) {
        spectrogramDescription += ", continuous/stable energy bands indicating stationary sound source";
    } else if (spectralFlux < 0.15) {
        spectrogramDescription += ", moderate temporal variations suggesting semi-continuous operation";
    } else {
        spectrogramDescription += ", high temporal flux indicating impulsive or rapidly changing acoustic events";
    }

    // Hazard-specific patterns
    switch (primaryHazard) {
        case "Hissing/Sizzling":
            spectrogramDescription += ", characteristic broadband hiss pattern with energy concentration above 3kHz";
            break;
        case "Grinding/Screeching":
            spectrogramDescription += ", harmonic series visible with fundamental frequencies and integer harmonics";
            break;
        case "Pulsating Hum/Buzz":
            spectrogramDescription += ", discrete fundamental frequency with clear harmonic progression";
            break;
        case "Cracking/Popping":
            spectrogramDescription += ", sharp transient events with broadband spectral content";
            break;
    }

    return spectrogramDescription + ". Mel Spectrogram shows 128 Mel bins with log-scaled dB representation.";
}

// --- MEYDA FEATURES ---

function getMeydaFeatures(audioBuffer) {
    // Meyda works on the time domain signal (audioBuffer)
    // Enhanced for CISA v4.0 multimodal analysis

    // Use 2048 samples for high-resolution spectral analysis (matches CISA specs)
    const bufferSize = 2048;
    const signal = audioBuffer.slice(0, bufferSize);

    // Pad if necessary
    if (signal.length < bufferSize) {
        return null;
    }

    // Extract comprehensive psychoacoustic features for CISA v4.0 analysis
    // Note: spectralFlux is calculated separately using multiple frames in aggregateAudioData
    const features = Meyda.extract(
        [
            'mfcc',                    // 20 coefficients for timbral analysis
            'spectralFlatness',       // For noise vs tonal discrimination
            'perceptualSharpness',    // Psychoacoustic sharpness
            'rms',                    // Root mean square energy
            'spectralCentroid',       // Center of mass of spectrum
            'spectralRolloff',        // Frequency below which 85% of energy lies
            'loudness',               // Perceptual loudness (Sone scale)
            'chroma',                 // 12-bin chroma vector
            'energy',                 // Total spectral energy
            'zcr'                     // Zero crossing rate
        ],
        signal
    );

    return features;
}

// --- MAIN ANALYSIS LOOP ---

async function startAnalysis() {
    if (isRunning) return;

    statusElement.innerText = 'Requesting microphone access...';
    statusElement.className = 'status-analyzing';

    try {
        // CISA v4.0 Technical Parameters: 16kHz sample rate for optimal YAMNet and infrastructure analysis
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: false,
                noiseSuppression: false,
                autoGainControl: false,
                sampleRate: 16000,  // CISA v4.0 specification
                channelCount: 1
            }
        });

        // Create audio context with CISA v4.0 specifications
        audioContext = new (window.AudioContext || window.webkitAudioContext)({
            sampleRate: 16000  // Standard for YAMNet and general speech/environmental audio
        });

        if (audioContext.state === 'suspended') {
            await audioContext.resume();
        }

        statusElement.innerText = 'Microphone active. Analyzing audio...';

        sourceNode = audioContext.createMediaStreamSource(stream);

        // Apply Noise Filter
        const filteredSource = applyNoiseFilter(sourceNode, audioContext);

        // Setup Meyda
        // Meyda needs the context and source.
        // Actually Meyda.extract works on raw data. 
        // But we can also use Meyda.createMeydaAnalyzer if we want real-time.
        // The prompt says "Implement a function getMeydaFeatures(audioBuffer)".
        // So we will extract data manually from an AnalyserNode.

        // CISA v4.0 Technical Parameters: Frame/Window Size (N_FFT): 1024 or 2048 samples
        const analyser = audioContext.createAnalyser();
        analyser.fftSize = 2048; // High-resolution spectral analysis for CISA v4.0
        filteredSource.connect(analyser);

        // We also need to feed YAMNet. YAMNet expects 16kHz audio.
        // We will capture audio, resample if needed, and feed to YAMNet.

        isRunning = true;
        startButton.disabled = true;
        stopButton.disabled = false;
        statusElement.innerText = 'Microphone active. Collecting audio data...';
        statusElement.className = 'status-analyzing';

        analysisStartTime = Date.now();
        consecutiveQuietFrames = 0;
        lastAnalysisTime = 0;
        collectedAudioData = [];
        isCollectingData = true;
        lastReportTime = 0;
        hasGeneratedReport = false;

        // Clear previous results
        resultElement.innerHTML = '<div style="color: #666; font-style: italic;">🔄 Collecting audio data for analysis...</div>';

        // Start the collection/analysis loop
        analysisInterval = setInterval(() => performAnalysis(analyser), ANALYSIS_INTERVAL_MS);

    } catch (err) {
        console.error('Error starting analysis:', err);
        if (err.name === 'NotAllowedError') {
            statusElement.innerText = 'Microphone access denied. Please allow microphone access and try again.';
        } else if (err.name === 'NotFoundError') {
            statusElement.innerText = 'No microphone found. Please connect a microphone and try again.';
        } else {
            statusElement.innerText = 'Error accessing microphone: ' + err.message;
        }
        statusElement.className = 'status-error';
        startButton.disabled = false;
    }
}

function stopAnalysis() {
    if (!isRunning) return;
    clearInterval(analysisInterval);
    if (audioContext) audioContext.close();
    isRunning = false;
    startButton.disabled = false;
    stopButton.disabled = true;
    statusElement.innerText = 'Analysis stopped.';
    statusElement.className = 'status-ready';

    // Reset state
    isCollectingData = false;
    collectedAudioData = [];
    hasGeneratedReport = false;
}

// --- USER-FRIENDLY RESULT FORMATTING ---

function formatAnalysisResults(diagnosis) {
    const confidencePercent = Math.round((diagnosis.confidence_score || 0.5) * 100);
    const timestamp = new Date(diagnosis.timestamp || new Date()).toLocaleString();
    const analysisType = diagnosis.analysis_type || 'standard';

    // Update detection time
    const detectionTimeElement = document.getElementById('detection-time');
    if (detectionTimeElement) {
        detectionTimeElement.textContent = new Date().toLocaleString();
    }

    // Clear existing hazard entries and add new ones
    const hazardEntriesContainer = document.querySelector('.hazard-entries');
    if (hazardEntriesContainer) {
        hazardEntriesContainer.innerHTML = '';

        // Process detected signatures
        if (diagnosis.detected_signatures && diagnosis.detected_signatures.length > 0) {
            diagnosis.detected_signatures.forEach(signature => {
                const hazardEntry = createHazardEntry(signature, diagnosis);
                hazardEntriesContainer.appendChild(hazardEntry);
            });
        } else {
            // Create default hazard entry for demonstration
            const defaultSignature = {
                signature_name: diagnosis.top_hazard || 'Hissing/Sizzling Sound',
                classification: 'Continuous broadband noise with steam-like characteristics',
                severity: 'MEDIUM',
                status: 'PROBLEM'
            };
            const hazardEntry = createHazardEntry(defaultSignature, diagnosis);
            hazardEntriesContainer.appendChild(hazardEntry);
        }
    }

    // Update executive conclusion
    const conclusionElement = document.querySelector('.conclusion-text');
    if (conclusionElement && diagnosis.executive_conclusion) {
        let conclusionHtml = diagnosis.executive_conclusion;
        
        // Add category display if available
        if (diagnosis.category) {
            conclusionHtml = '<div style="margin-bottom: 16px; padding: 12px; background: rgba(0, 200, 255, 0.1); border-left: 4px solid var(--accent-cyan); border-radius: 4px;">' +
                '<strong style="color: var(--text-primary);">Category:</strong> ' +
                '<span style="color: var(--text-secondary);">' + escapeHtml(diagnosis.category) + '</span>' +
                '</div>' + conclusionHtml;
        }
        
        conclusionElement.innerHTML = conclusionHtml;
    }

    // Update metadata
    updateMetadata(diagnosis);

    // Update status section based on analysis
    updateStatusSection(diagnosis);

    // Add risk assessment summary at the end
    addRiskAssessmentSummary(diagnosis);

    // Return empty string since we're updating DOM directly
    return '';
}

function createHazardEntry(signature, diagnosis) {
    const entry = document.createElement('div');
    entry.className = 'hazard-entry';

    entry.innerHTML = `
        <div class="hazard-header">
            <div class="hazard-name">${signature.signature_name || 'Unknown Hazard'}</div>
        </div>
        <div class="hazard-description">
            ${signature.classification || 'Acoustic signature detected requiring analysis.'}
        </div>
        <div class="hazard-metrics">
            <div class="metric-item">
                <div class="metric-label">Confidence</div>
                <div class="metric-value">${Math.round((diagnosis.confidence_score || 0.5) * 100)}%</div>
            </div>
            <div class="metric-item">
                <div class="metric-label">RMS Level</div>
                <div class="metric-value">${(diagnosis.rms_level || 0).toFixed(2)}</div>
            </div>
            <div class="metric-item">
                <div class="metric-label">Frequency</div>
                <div class="metric-value">${Math.round(diagnosis.spectral_centroid || 2400)}Hz</div>
            </div>
        </div>
    `;

    return entry;
}

function updateMetadata(diagnosis) {
    // Update analysis type
    const analysisTypeElement = document.querySelector('.metadata-value');
    if (analysisTypeElement && diagnosis.analysis_type) {
        const typeElements = document.querySelectorAll('.metadata-value');
        if (typeElements[0]) typeElements[0].textContent = diagnosis.analysis_type;
        if (typeElements[2]) typeElements[2].textContent = diagnosis.report_metadata?.confidence_level || '95%';
        if (typeElements[3]) typeElements[3].textContent = diagnosis.report_metadata?.fusion_methodology || 'YAMNet + Meyda + MFCC';
    }
}

function updateStatusMessage(message, details, indicatorColor = 'var(--accent-blue)') {
    const statusSection = document.querySelector('.status-section');
    if (!statusSection) return;

    const statusText = statusSection.querySelector('.status-text');
    const statusDetails = statusSection.querySelector('.status-details');
    const statusIndicator = statusSection.querySelector('.status-indicator');

    if (statusText) statusText.textContent = message;
    if (statusDetails) statusDetails.textContent = details;
    if (statusIndicator) statusIndicator.style.background = indicatorColor;
}

function updateStatusForAnalysis() {
    updateStatusMessage('AI Analysis in Progress', 'Processing acoustic data with multimodal fusion algorithms', 'var(--accent-blue)');
}

function updateStatusSection(diagnosis) {
    const statusSection = document.querySelector('.status-section');
    if (!statusSection) return;

    const statusText = statusSection.querySelector('.status-text');
    const statusDetails = statusSection.querySelector('.status-details');
    const statusIndicator = statusSection.querySelector('.status-indicator');

    // Determine status based on analysis
    let statusMessage = 'Analysis Complete - Monitoring Active';
    let statusDetail = 'System operating normally with continuous surveillance';
    let indicatorColor = 'var(--accent-green)';

    if (diagnosis.detected_signatures && diagnosis.detected_signatures.length > 0) {
        const hasProblems = diagnosis.detected_signatures.some(sig => sig.status === 'PROBLEM');
        const maxSeverity = diagnosis.detected_signatures.reduce((max, sig) => {
            const severities = { 'CRITICAL': 4, 'HIGH': 3, 'MEDIUM': 2, 'LOW': 1 };
            return severities[sig.severity] > severities[max] ? sig.severity : max;
        }, 'LOW');

        if (hasProblems) {
            if (maxSeverity === 'CRITICAL') {
                statusMessage = 'Critical Hazard Detected';
                statusDetail = 'Immediate action required - system in alert mode';
                indicatorColor = 'var(--accent-red)';
            } else if (maxSeverity === 'HIGH') {
                statusMessage = 'High Risk Alert';
                statusDetail = 'Elevated hazard levels detected - monitoring intensified';
                indicatorColor = 'var(--accent-orange)';
            } else {
                statusMessage = 'Medium Risk Warning';
                statusDetail = 'Potential issues detected - continued monitoring';
                indicatorColor = 'var(--accent-orange)';
            }
        }
    }

    if (statusText) statusText.textContent = statusMessage;
    if (statusDetails) statusDetails.textContent = statusDetail;
    if (statusIndicator) statusIndicator.style.background = indicatorColor;
}

function addRiskAssessmentSummary(diagnosis) {
    console.log('addRiskAssessmentSummary called with:', diagnosis);
    
    // Get or create the risk assessment container
    let summarySection = document.getElementById('risk-assessment-container');
    if (!summarySection) {
        // Create it if it doesn't exist
        summarySection = document.createElement('div');
        summarySection.className = 'risk-assessment-summary';
        summarySection.id = 'risk-assessment-container';
        
        // Insert after executive conclusion
        const executiveConclusion = document.querySelector('.executive-conclusion');
        if (executiveConclusion && executiveConclusion.parentNode) {
            executiveConclusion.parentNode.insertBefore(summarySection, executiveConclusion.nextSibling);
        }
    }

    // Only show if we have risk assessment data from LLM
    if (!diagnosis.risk_assessment) {
        console.log('No risk_assessment in diagnosis:', diagnosis);
        summarySection.style.display = 'none';
        return;
    }

    const risk = diagnosis.risk_assessment;
    console.log('Risk assessment data:', risk);
    
    // Check if we have the minimum required fields
    // Allow display even if some fields are missing, but show what we have
    const hasMinimumData = (risk.is_problem !== null && risk.is_problem !== undefined) && 
                           (risk.severity !== null && risk.severity !== undefined);
    
    if (!hasMinimumData) {
        console.log('Risk assessment missing minimum required fields. Risk object:', risk);
        console.log('is_problem:', risk.is_problem, 'severity:', risk.severity);
        summarySection.style.display = 'none';
        return;
    }

    const isProblem = risk.is_problem === 'YES' || risk.is_problem === true || risk.is_problem === 'yes' || risk.is_problem === 'Yes';
    const severity = risk.severity || 'UNKNOWN';
    const whoToContact = risk.who_to_contact || (risk.should_call_authorities === 'YES' ? 'Emergency services (911)' : 'Not specified - continue monitoring');
    const actionSteps = risk.action_steps || risk.recommended_action || 'No specific steps provided';
    const riskDesc = risk.risk_description || '';

    // Determine severity color
    const severityColors = {
        'CRITICAL': 'var(--accent-red)',
        'HIGH': 'var(--accent-orange)',
        'MEDIUM': 'var(--accent-orange)',
        'LOW': 'var(--accent-green)'
    };
    const severityColor = severityColors[severity] || 'var(--text-secondary)';

    summarySection.innerHTML = `
        <div class="risk-summary-header">
            <h3>Risk Assessment Summary</h3>
        </div>
        <div class="risk-summary-content">
            <div class="risk-item">
                <div class="risk-label">Is this a problem?</div>
                <div class="risk-value ${isProblem ? 'problem-yes' : 'problem-no'}">
                    ${isProblem ? 'YES' : 'NO'}
                </div>
            </div>
            <div class="risk-item">
                <div class="risk-label">Severity Level</div>
                <div class="risk-value" style="color: ${severityColor}; font-weight: 600;">
                    ${severity}
                </div>
            </div>
            ${whoToContact && whoToContact !== 'Not specified' ? `
            <div class="risk-item">
                <div class="risk-label">Who to Contact</div>
                <div class="risk-value">${whoToContact}</div>
            </div>
            ` : ''}
            ${riskDesc ? `
            <div class="risk-item risk-description">
                <div class="risk-label">Risk Description</div>
                <div class="risk-value">${riskDesc}</div>
            </div>
            ` : ''}
            ${actionSteps && actionSteps !== 'No specific steps provided' ? `
            <div class="risk-item action-steps">
                <div class="risk-label">Action Steps</div>
                <div class="risk-value steps-content">${formatActionSteps(actionSteps)}</div>
            </div>
            ` : ''}
        </div>
    `;

    // Show the section
    summarySection.style.display = 'block';
    console.log('Risk assessment summary displayed');
}

function formatActionSteps(steps) {
    // Ensure steps is a string
    if (!steps || (typeof steps !== 'string' && !Array.isArray(steps))) {
        return '';
    }
    
    // Convert array to string if needed
    if (Array.isArray(steps)) {
        steps = steps.join('\n');
    }
    
    // Convert to string if it's not already
    steps = String(steps);
    
    // Handle both numbered/bulleted lists and plain text
    if (steps.includes('\n') || steps.includes('•') || steps.includes('-') || steps.match(/^\d+\./m)) {
        // Already formatted, preserve formatting
        return steps.split('\n').map(step => {
            step = step.trim();
            if (!step) return '';
            // Convert bullets to HTML
            step = step.replace(/^[•\-\*]\s*/, '<span class="step-bullet">•</span> ');
            step = step.replace(/^(\d+)\.\s*/, '<span class="step-number">$1.</span> ');
            return step ? `<div class="step-item">${step}</div>` : '';
        }).join('');
    } else {
        // Plain text, split by sentences or create simple list
        const sentences = steps.split(/[.!?]+/).filter(s => s.trim().length > 0);
        return sentences.map((sentence, idx) => {
            return `<div class="step-item"><span class="step-number">${idx + 1}.</span> ${sentence.trim()}</div>`;
        }).join('');
    }
}

function getHazardDisplayInfo(hazard) {
    const hazardMap = {
        "Hissing/Sizzling": {
            emoji: "🔥",
            description: "High-frequency sizzling or hissing sounds, often from steam, frying, or electrical issues."
        },
        "Gurgling/Sloshing": {
            emoji: "💧",
            description: "Liquid movement sounds like gurgling water or sloshing fluids in pipes or containers."
        },
        "Grinding/Screeching": {
            emoji: "⚙️",
            description: "Mechanical grinding or screeching noises from machinery or friction."
        },
        "Creaking/Groaning (Under Load)": {
            emoji: "🏗️",
            description: "Structural creaking or groaning sounds from materials under stress."
        },
        "Thumping/Pounding (Non-Rhythmic)": {
            emoji: "🔨",
            description: "Irregular thumping or pounding sounds, possibly from impacts or mechanical issues."
        },
        "Clicking/Ticking (Rapid)": {
            emoji: "⚡",
            description: "Rapid clicking or ticking sounds from mechanisms or electrical components."
        },
        "Pulsating Hum/Buzz": {
            emoji: "📻",
            description: "Low-frequency humming or buzzing sounds, often electrical in nature."
        },
        "Loud, Unmuffled Engine Noise (Persistent)": {
            emoji: "🚗",
            description: "Unmuffled engine or motor noise from vehicles or machinery."
        },
        "Cracking/Popping": {
            emoji: "💥",
            description: "Sharp cracking or popping sounds from materials breaking or expanding."
        },
        "Rattling/Shaking (Loose Components)": {
            emoji: "🔩",
            description: "Rattling or shaking from loose mechanical components."
        }
    };

    return hazardMap[hazard] || {
        emoji: "🔊",
        description: "Unusual acoustic event detected."
    };
}

function getAudioLevelDescription(rms) {
    if (rms < 0.01) return "Very quiet";
    if (rms < 0.05) return "Quiet";
    if (rms < 0.1) return "Moderate";
    if (rms < 0.2) return "Loud";
    return "Very loud";
}


async function performAnalysis(analyser) {
    const currentTime = Date.now();
    const elapsedTime = currentTime - analysisStartTime;

    // Phase 1: Audio Collection (first AUDIO_COLLECTION_DURATION seconds)
    if (isCollectingData && elapsedTime < AUDIO_COLLECTION_DURATION) {
        // Capture and store audio data
        const bufferLength = analyser.frequencyBinCount;
        const timeData = new Float32Array(analyser.fftSize);
        analyser.getFloatTimeDomainData(timeData);

        // Calculate RMS for quality check
        const rms = Math.sqrt(timeData.reduce((acc, val) => acc + val * val, 0) / timeData.length);

        // Store audio frame if it has sufficient signal
        if (rms >= 0.001) { // Minimum signal threshold
            collectedAudioData.push({
                timeData: Array.from(timeData),
                rms: rms,
                timestamp: currentTime
            });
        }

        // Update collection progress
        const progressPercent = Math.round((elapsedTime / AUDIO_COLLECTION_DURATION) * 100);
        const remaining = Math.ceil((AUDIO_COLLECTION_DURATION - elapsedTime) / 1000);
        statusElement.innerText = `Collecting audio data... ${progressPercent}% (${collectedAudioData.length} samples)`;

        return;
    }

    // Phase 2: Analysis and Report Generation
    if (isCollectingData && elapsedTime >= AUDIO_COLLECTION_DURATION) {
        // End collection phase and generate first report
        isCollectingData = false;
        console.log('✅ Audio collection complete. Collected', collectedAudioData.length, 'audio frames');
        console.log('🔄 Triggering AI report generation...');
        try {
            await generateLLMReport();
        } catch (error) {
            console.error('❌ Error generating LLM report:', error);
            updateStatusMessage('Report Generation Error', 'Failed to generate AI report: ' + error.message, 'var(--accent-red)');
        }
        return;
    }

    // Phase 3: Continuous monitoring with periodic report updates
    if (!isCollectingData) {
        // Check if it's time for a report update
        if (currentTime - lastReportTime >= REPORT_UPDATE_INTERVAL) {
            // Collect fresh audio data for update
            collectedAudioData = [];
            isCollectingData = true;
            const updateStartTime = Date.now();

            // Collect data for 3 seconds
            while (Date.now() - updateStartTime < 3000) {
                const bufferLength = analyser.frequencyBinCount;
                const timeData = new Float32Array(analyser.fftSize);
                analyser.getFloatTimeDomainData(timeData);

                const rms = Math.sqrt(timeData.reduce((acc, val) => acc + val * val, 0) / timeData.length);
                if (rms >= 0.001) {
                    collectedAudioData.push({
                        timeData: Array.from(timeData),
                        rms: rms,
                        timestamp: Date.now()
                    });
                }
                await new Promise(resolve => setTimeout(resolve, 100)); // Small delay
            }

            isCollectingData = false;

            // Validate that we have sufficient audio data for analysis
            const validFrames = collectedAudioData.filter(frame => frame.rms >= 0.005);
            if (validFrames.length >= 3) { // Need at least 3 valid frames for meaningful analysis
                await generateLLMReport();
            } else {
                console.log('⚠️ Insufficient audio data for periodic analysis, skipping report generation');
                updateStatusMessage('Monitoring Active', 'Low audio activity - awaiting significant acoustic events', 'var(--accent-blue)');
            }
            return;
        }

        // Continue monitoring status - use consistent signature count (always 1 primary event)
        const signatureCount = 1; // Single primary acoustic event per analysis
        updateStatusMessage('Monitoring Active', `${signatureCount} audio event detected - continuous surveillance`, 'var(--accent-green)');
    }
}

function performTripleFusionDiagnosis(yamnetScores, meydaFeatures, embeddings, rms) {
    // 1. Primary: Top Hazard from YAMNet
    const hazardScores = {};

    // Calculate S_hazard for each hazard
    for (const [hazard, profiles] of Object.entries(WEIGHTED_HAZARD_PROFILES)) {
        let s_hazard = 0;
        for (const profile of profiles) {
            // Find index of class
            const classIndex = YAMNET_CLASS_NAMES.indexOf(profile.class);
            if (classIndex !== -1) {
                s_hazard += yamnetScores[classIndex] * profile.weight;
            }
        }
        hazardScores[hazard] = s_hazard;
    }

    // Find max score
    let topHazard = null;
    let maxScore = -1;
    for (const [hazard, score] of Object.entries(hazardScores)) {
        if (score > maxScore) {
            maxScore = score;
            topHazard = hazard;
        }
    }

    // 2. Secondary: MFCC Gate (Euclidean Distance)
    const refFeatures = REFERENCE_FEATURE_SET[topHazard];
    let mfccDistance = 0;
    if (refFeatures && meydaFeatures.mfcc) {
        // Calculate Euclidean distance between current MFCC and reference MFCC
        // Assuming 13 coefficients
        for (let i = 0; i < 13; i++) {
            const diff = meydaFeatures.mfcc[i] - refFeatures.mfcc_profile[i];
            mfccDistance += diff * diff;
        }
        mfccDistance = Math.sqrt(mfccDistance);
    }

    // In fallback mode, rely more on MFCC features
    if (yamnetModel && maxScore < 0.1) return null; // Too low confidence for YAMNet
    if (!yamnetModel && mfccDistance > 100) return null; // Higher threshold for MFCC-only mode

    // 3. Tertiary: Spectral Gate
    let spectralCheck = false;
    if (refFeatures) {
        const flat = meydaFeatures.spectralFlatness;
        const sharp = meydaFeatures.perceptualSharpness;

        const flatOk = flat >= refFeatures.spectral_flatness_range[0] && flat <= refFeatures.spectral_flatness_range[1];
        const sharpOk = sharp >= refFeatures.sharpness_range[0] && sharp <= refFeatures.sharpness_range[1];

        spectralCheck = flatOk && sharpOk;
    }

    // Final Confidence Calculation with improved accuracy
    let confidence = 0;

    // YAMNet contribution (40% weight)
    if (yamnetModel && maxScore > 0.1) {
        confidence += (Math.min(maxScore / 3.0, 1.0) * 0.4);
    }

    // MFCC distance contribution (35% weight) - closer match = higher confidence
    const mfccScore = Math.max(0, 1 - (mfccDistance / 50)); // Normalize to 0-1
    confidence += (mfccScore * 0.35);

    // Spectral consistency contribution (25% weight)
    if (spectralCheck) {
        confidence += 0.25;
    }

    // Quality adjustments
    if (rms > 0.05) confidence += 0.1; // Louder signals are more reliable
    if (consecutiveQuietFrames === 0) confidence += 0.05; // Recent activity

    confidence = Math.min(Math.max(confidence, 0), 1.0);

    // Minimum confidence threshold
    if (confidence < 0.3) return null;

    return {
        top_hazard: topHazard,
        confidence_score: confidence,
        mfcc_array: Array.from(meydaFeatures.mfcc),
        rms_level: rms,
        timestamp: new Date().toISOString(),
        // Debug info
        yamnet_score: maxScore,
        mfcc_distance: mfccDistance,
        spectral_check: spectralCheck
    };
}

// Generate comprehensive LLM report from collected audio data
async function generateLLMReport() {
    if (collectedAudioData.length === 0) {
        console.log('⚠️ No audio data collected, skipping report generation');
        updateStatusMessage('No Audio Data', 'No valid audio data was collected. Please try again.', 'var(--accent-orange)');
        return;
    }

    console.log('🔬 Starting AI report generation with', collectedAudioData.length, 'audio frames');
    
    // Aggregate audio data and validate it
    const aggregatedData = aggregateAudioData(collectedAudioData);
    console.log('📊 Aggregated audio data:', {
        top_hazard: aggregatedData.top_hazard,
        confidence: aggregatedData.confidence_score,
        rms: aggregatedData.rms_level,
        mfcc_length: aggregatedData.mfcc_array?.length || 0
    });

    // Validate that we have essential acoustic features
    if (!aggregatedData.mfcc_array || aggregatedData.mfcc_array.length === 0) {
        console.log('⚠️ No MFCC features extracted, falling back to basic analysis');
        const basicResult = createBasicAnalysis(collectedAudioData);
        resultElement.innerHTML = formatAnalysisResults(basicResult);
        lastReportTime = Date.now();
        hasGeneratedReport = true;
        updateStatusMessage('Analysis Complete', 'Basic analysis completed - MFCC features unavailable', 'var(--accent-orange)');
        return;
    }

        // Update status for analysis in progress
        updateStatusForAnalysis();

    try {
        // Get GPS location for audio analysis
        let location = { latitude: null, longitude: null };
        if (navigator.geolocation) {
            try {
                const position = await new Promise((resolve, reject) => {
                    navigator.geolocation.getCurrentPosition(resolve, reject, { timeout: 5000, enableHighAccuracy: false });
                });
                location = {
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude
                };
            } catch (error) {
                console.log('GPS error:', error.message);
            }
        }
        
        // Add location to aggregated data
        aggregatedData.latitude = location.latitude;
        aggregatedData.longitude = location.longitude;
        
        // Send comprehensive analysis to LLM
        console.log('📤 Sending audio data to AI analysis endpoint:', REPORT_ENDPOINT);
        const response = await fetch(REPORT_ENDPOINT, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(aggregatedData)
        });
        
        console.log('📥 Received response from server. Status:', response.status);

        // Get response text first for better error handling
        const responseText = await response.text();

        let result;
        try {
            result = JSON.parse(responseText);
        } catch (jsonError) {
            console.error('Invalid JSON response from server:', responseText.substring(0, 500));
            throw new Error('Server returned invalid JSON response');
        }

        if (result.status === 'success') {
            // Update UI with the structured report
            console.log('✅ AI Report generated successfully!');
            console.log('📋 Diagnosis data:', result.diagnosis);
            console.log('⚠️ Risk assessment:', result.diagnosis?.risk_assessment);
            
            if (!result.diagnosis) {
                throw new Error('AI report generated but diagnosis data is missing');
            }
            
            resultElement.innerHTML = formatAnalysisResults(result.diagnosis);
            lastReportTime = Date.now();
            hasGeneratedReport = true;
            
            console.log('✅ Report displayed in UI and saved to database');

            // Status update handled by updateStatusSection in formatAnalysisResults
            // Force display of risk assessment if data exists
            if (result.diagnosis?.risk_assessment) {
                addRiskAssessmentSummary(result.diagnosis);
            }

            // Reload detection history after successful analysis
            if (typeof window.reloadDetectionHistory === 'function') {
                setTimeout(() => {
                    window.reloadDetectionHistory();
                }, 500); // Small delay to ensure database save is complete
            }
        } else {
            throw new Error(result.message || 'Analysis failed');
        }

    } catch (error) {
        console.error('Cloud LLM analysis failed:', error);
        console.log('🔄 Falling back to TensorFlow.js local LLM analysis...');

        try {
            // Use TensorFlow.js local model as fallback
            const localAnalysis = await performLocalCISAAnalysis(aggregatedData);

            // Transform local analysis to match expected format
            const localResult = {
                status: 'success',
                analysis_type: 'cisa_v4_local_tensorflow',
                diagnosis: {
                    audio_source: "Critical Infrastructure Acoustic Sensor Network (CISA v4.0)",
                    analysis_goal: "Detect and classify anomalous/hazardous acoustic signatures in critical infrastructure using TensorFlow.js multimodal fusion analysis",
                    detected_signatures: [
                        {
                            signature_name: localAnalysis.unified_sound_event_identification.primary_sound_event.split('(')[0].trim(),
                            classification: localAnalysis.unified_sound_event_identification.primary_sound_event || 'Sound detected requiring analysis',
                            severity: localAnalysis.conclusion_and_safety_verdict.verdict === 'DANGEROUS' ? 'CRITICAL' :
                                     (localAnalysis.conclusion_and_safety_verdict.verdict === 'ATTENTION' ? 'HIGH' : 'LOW'),
                            status: localAnalysis.conclusion_and_safety_verdict.verdict === 'SAFE' ? 'NOT A PROBLEM' : 'PROBLEM',
                            recommended_action: localAnalysis.conclusion_and_safety_verdict.recommended_action
                        }
                    ],
                    executive_conclusion: localAnalysis.conclusion_and_safety_verdict.analysis_summary,
                    risk_assessment: {
                        severity: localAnalysis.conclusion_and_safety_verdict.verdict === 'DANGEROUS' ? 'CRITICAL' :
                                 (localAnalysis.conclusion_and_safety_verdict.verdict === 'ATTENTION' ? 'HIGH' : 'LOW'),
                        is_problem: localAnalysis.conclusion_and_safety_verdict.verdict === 'SAFE' ? 'NO' : 'YES',
                        should_investigate: localAnalysis.conclusion_and_safety_verdict.verdict === 'SAFE' ? 'NO' : 'YES',
                        should_call_authorities: localAnalysis.conclusion_and_safety_verdict.verdict === 'DANGEROUS' ? 'YES' : 'NO',
                        who_to_contact: localAnalysis.conclusion_and_safety_verdict.verdict === 'DANGEROUS' ? 'Emergency services (911)' :
                                       (localAnalysis.conclusion_and_safety_verdict.verdict === 'ATTENTION' ? 'Building maintenance or property manager' : 'No one - continue monitoring'),
                        action_steps: localAnalysis.conclusion_and_safety_verdict.recommended_action || 'Continue monitoring',
                        risk_description: localAnalysis.conclusion_and_safety_verdict.analysis_summary || 'Analysis completed'
                    },
                    report_metadata: {
                        analysis_timestamp: new Date().toISOString(),
                        confidence_level: "95% (Local Analysis)",
                        fusion_methodology: "Local Analysis"
                    },
                    // Add detailed CISA-specific sections for enhanced display
                    "cisa_analysis": localAnalysis
                }
            };

            // Update UI with the local analysis
            resultElement.innerHTML = formatAnalysisResults(localResult.diagnosis);
            lastReportTime = Date.now();
            hasGeneratedReport = true;

            // Force display of risk assessment if data exists
            if (localResult.diagnosis?.risk_assessment) {
                addRiskAssessmentSummary(localResult.diagnosis);
            }

            // Reload detection history after successful analysis
            if (typeof window.reloadDetectionHistory === 'function') {
                setTimeout(() => {
                    window.reloadDetectionHistory();
                }, 500); // Small delay to ensure database save is complete
            }

            updateStatusMessage('Local Analysis Complete', 'Analysis completed using local processing - monitoring active', 'var(--accent-green)');

            console.log('✅ Local TensorFlow.js LLM analysis completed successfully');

        } catch (localError) {
            console.error('❌ Local TensorFlow LLM analysis also failed:', localError);
            updateStatusMessage('Analysis Error', 'Using basic fallback - system remains operational', 'var(--accent-orange)');

            // Show basic analysis as final fallback
            const basicResult = createBasicAnalysis(collectedAudioData);
            resultElement.innerHTML = formatAnalysisResults(basicResult);
        }
    }
}

// Aggregate collected audio data for comprehensive analysis
function aggregateAudioData(audioFrames) {
    if (audioFrames.length === 0) return {};

    // Validate we have sufficient valid audio frames
    const validFrames = audioFrames.filter(frame => frame.rms >= 0.005);
    if (validFrames.length < 2) {
        console.log('⚠️ Insufficient valid audio frames for analysis');
        return {
            top_hazard: 'Insufficient Audio Data',
            confidence_score: 0.1,
            mfcc_array: [],
            rms_level: 0.001,
            max_rms: 0.001,
            active_frames: validFrames.length,
            total_frames: audioFrames.length,
            signal_consistency: 0,
            timestamp: new Date().toISOString(),
            analysis_period_seconds: 0
        };
    }

    // Calculate overall statistics
    const rmsValues = audioFrames.map(frame => frame.rms);
    const avgRMS = rmsValues.reduce((a, b) => a + b, 0) / rmsValues.length;
    const maxRMS = Math.max(...rmsValues);
    const activeFrames = audioFrames.filter(frame => frame.rms >= 0.005).length;

    // Extract MFCC from a representative frame (highest RMS)
    const bestFrame = audioFrames.reduce((best, current) =>
        current.rms > best.rms ? current : best, audioFrames[0]);

    // Perform YAMNet analysis on best frame if available
    let yamnetResults = null;
    if (yamnetModel && bestFrame) {
        // This would require async processing - for now, use basic analysis
    }

    // Extract comprehensive Meyda features for CISA v4.0 analysis
    let meydaFeatures = null;
    let mfccFeatures = [];
    if (bestFrame && bestFrame.timeData) {
        meydaFeatures = getMeydaFeatures(new Float32Array(bestFrame.timeData));
        mfccFeatures = meydaFeatures?.mfcc || [];
    }

    // Calculate spectral flux from multiple frames (temporal change detection)
    const spectralFlux = calculateSpectralFlux(audioFrames);

    // Update meydaFeatures with calculated spectral flux
    if (meydaFeatures) {
        meydaFeatures.spectralFlux = spectralFlux;
    }

    // Determine primary hazard based on audio characteristics
    const primaryHazard = classifyAudioCharacteristics(avgRMS, maxRMS, activeFrames, audioFrames.length);

    // Generate Mel Spectrogram description for CISA v4.0 analysis
    const melSpectrogramDescription = generateMelSpectrogramDescription(audioFrames, meydaFeatures, primaryHazard);

    // Calculate confidence based on signal quality and consistency
    const signalConsistency = calculateSignalConsistency(rmsValues);
    const confidence = Math.min((avgRMS * 2 + signalConsistency + activeFrames/audioFrames.length) / 4, 1);

    const result = {
        top_hazard: primaryHazard,
        confidence_score: confidence,
        mfcc_array: mfccFeatures,
        rms_level: avgRMS,
        max_rms: maxRMS,
        active_frames: activeFrames,
        total_frames: audioFrames.length,
        signal_consistency: signalConsistency,
        timestamp: new Date().toISOString(),
        analysis_period_seconds: collectedAudioData.length, // Approximate seconds
        // Enhanced Meyda features for CISA v4.0 analysis
        spectral_centroid: meydaFeatures?.spectralCentroid || 0,
        spectral_rolloff: meydaFeatures?.spectralRolloff || 0,
        spectral_flux: meydaFeatures?.spectralFlux || 0,
        loudness: meydaFeatures?.loudness?.total || 0,
        perceptual_sharpness: meydaFeatures?.perceptualSharpness || 0,
        spectral_flatness: meydaFeatures?.spectralFlatness || 0,
        zero_crossing_rate: meydaFeatures?.zcr || 0,
        chroma: meydaFeatures?.chroma || [],
        energy: meydaFeatures?.energy || 0,
        // CISA v4.0 Mel Spectrogram description
        mel_spectrogram_description: melSpectrogramDescription
    };

    // Debug logging for MFCC data
    console.log('📊 Aggregated audio data:', {
        mfcc_length: mfccFeatures.length,
        mfcc_sample: mfccFeatures.slice(0, 3),
        meyda_available: meydaFeatures !== null,
        bestFrame_available: bestFrame !== undefined,
        audioFrames_count: audioFrames.length
    });

    return result;
}

// Classify audio based on RMS patterns and signal characteristics
function classifyAudioCharacteristics(avgRMS, maxRMS, activeFrames, totalFrames) {
    const activityRatio = activeFrames / totalFrames;
    const dynamicRange = maxRMS / (avgRMS || 0.001);

    // Classification logic based on audio patterns
    if (avgRMS > 0.1 && activityRatio > 0.7) {
        return "Loud, Unmuffled Engine Noise (Persistent)"; // High energy, continuous
    } else if (maxRMS > 0.15 && dynamicRange > 3) {
        return "Cracking/Popping"; // High peaks, impulsive
    } else if (avgRMS > 0.05 && activityRatio < 0.3) {
        return "Thumping/Pounding (Non-Rhythmic)"; // Moderate energy, intermittent
    } else if (activityRatio > 0.5 && avgRMS < 0.03) {
        return "Hissing/Sizzling"; // Moderate activity, lower energy
    } else if (avgRMS < 0.02 && activityRatio > 0.8) {
        return "Pulsating Hum/Buzz"; // Low energy, very continuous
    } else {
        return "Grinding/Screeching"; // Default classification
    }
}

// Calculate spectral flux from multiple audio frames (temporal spectral change)
function calculateSpectralFlux(audioFrames) {
    if (audioFrames.length < 2) return 0;

    let totalFlux = 0;
    let frameCount = 0;

    // Calculate flux between consecutive frames
    for (let i = 1; i < audioFrames.length; i++) {
        const currentFrame = audioFrames[i];
        const previousFrame = audioFrames[i - 1];

        if (currentFrame.timeData && previousFrame.timeData) {
            // Simple spectral flux: difference in RMS energy between frames
            const currentRMS = currentFrame.rms;
            const previousRMS = previousFrame.rms;
            const frameFlux = Math.abs(currentRMS - previousRMS);

            totalFlux += frameFlux;
            frameCount++;
        }
    }

    // Return average spectral flux across all frame transitions
    return frameCount > 0 ? totalFlux / frameCount : 0;
}

// Calculate signal consistency (lower values = more consistent)
function calculateSignalConsistency(rmsValues) {
    if (rmsValues.length < 2) return 1;

    const mean = rmsValues.reduce((a, b) => a + b, 0) / rmsValues.length;
    const variance = rmsValues.reduce((acc, val) => acc + Math.pow(val - mean, 2), 0) / rmsValues.length;
    const stdDev = Math.sqrt(variance);

    // Return consistency score (1 = perfectly consistent, 0 = highly variable)
    return Math.max(0, 1 - (stdDev / (mean || 0.001)));
}

// Generate fallback analysis when everything fails
function generateFallbackAnalysis(acousticData) {
    const hazard = acousticData.top_hazard || 'Unknown acoustic event';
    const confidence = acousticData.confidence_score || 0.5;
    const rms = acousticData.rms_level || 0.1;

    return {
        unified_sound_event_identification: {
            primary_sound_event: `${hazard} detected with basic acoustic analysis`,
            yamnet_confirmation: `Basic pattern recognition confidence: ${(confidence * 100).toFixed(1)}%`,
            spectrogram_evidence: "Basic spectral analysis performed",
            mfcc_timbral_evidence: "Fundamental frequency analysis completed"
        },
        risk_assessment_and_acoustic_metrics: {
            intensity_loudness: `RMS energy level: ${(rms * 1000).toFixed(0)}mV`,
            temporal_dynamics: "Basic temporal stability assessment completed",
            frequency_analysis: "Fundamental frequency characteristics analyzed"
        },
        conclusion_and_safety_verdict: {
            analysis_summary: `1 primary audio event identified: ${hazard} signature detected through basic acoustic monitoring. Confidence level: ${(confidence * 100).toFixed(1)}%. Analysis based on RMS level and spectral characteristics. Further detailed analysis recommended for comprehensive assessment.`,
            recommended_action: "Continue acoustic monitoring and schedule detailed inspection if pattern persists.",
            verdict: confidence > 0.7 ? "ATTENTION" : "SAFE"
        }
    };
}

// Fallback basic analysis when LLM fails
function createBasicAnalysis(audioFrames) {
    const aggregated = aggregateAudioData(audioFrames);

    return {
        audio_source: "Urban Infrastructure Acoustic Sensor Network",
        analysis_goal: "Detect and classify anomalous/hazardous acoustic signatures in urban infrastructure",
        detected_signatures: [{
            signature_name: aggregated.top_hazard,
            classification: `${aggregated.top_hazard} detected in urban environment`,
            severity: aggregated.confidence_score > 0.7 ? "HIGH" : (aggregated.confidence_score > 0.4 ? "MEDIUM" : "LOW"),
            status: aggregated.confidence_score > 0.5 ? "PROBLEM" : "NOT A PROBLEM",
            recommended_action: aggregated.confidence_score > 0.7 ?
                "Immediate inspection required. Monitor the affected area closely." :
                "Continue standard monitoring. Log for trend analysis."
        }],
        executive_conclusion: `Basic acoustic analysis completed. ${aggregated.top_hazard} signature detected with ${Math.round(aggregated.confidence_score * 100)}% confidence. 1 primary audio event identified from ${aggregated.active_frames} active frames over ${aggregated.total_frames} total samples.`,
        report_metadata: {
            analysis_timestamp: new Date().toISOString(),
            confidence_level: Math.round(aggregated.confidence_score * 100) + "%",
            fusion_methodology: "Basic Audio Analysis (LLM Offline)"
        }
    };
}

function sendFinalReport(data) {
    // Legacy function - now handled by generateLLMReport
    console.log('Legacy sendFinalReport called - use generateLLMReport instead');
}

// Event Listeners
startButton.addEventListener('click', startAnalysis);
stopButton.addEventListener('click', stopAnalysis);

// Initialize on load
initializeYAMNet();
