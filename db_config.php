<?php
// db_config.php - Database configuration using PDO for MySQL

// Database configuration
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3307');
define('DB_NAME', getenv('DB_NAME') ?: 'hackathondb');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
// Create PDO connection
function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            // First, ensure MySQL server is accessible - connect without database
            $dsn_no_db = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
            $temp_pdo = new PDO($dsn_no_db, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $temp_pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Now connect to the database
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            $errorInfo = $e->errorInfo ?? [];
            $errorMessage = $e->getMessage();
            
            // Check if it's a "driver not found" error
            if (strpos($errorMessage, 'could not find driver') !== false || 
                strpos($errorMessage, 'driver') !== false && $e->getCode() == 0) {
                $error_msg = "PDO MySQL driver is not loaded. The extension may not be enabled in php.ini or Apache needs to be restarted. Available drivers: " . (class_exists('PDO') ? implode(', ', PDO::getAvailableDrivers()) : 'PDO class not available');
            } else {
                $error_msg = "MySQL Connection Error: " . $errorMessage . " (Code: " . $e->getCode() . ", SQLState: " . ($errorInfo[0] ?? 'N/A') . ")";
            }
            
            error_log("CRITICAL: " . $error_msg);
            throw new Exception($error_msg, $e->getCode());
        } catch (Exception $e) {
            $error_msg = "Database connection failed: " . $e->getMessage();
            error_log("CRITICAL: " . $error_msg);
            throw new Exception($error_msg);
        }
    }
    
    return $pdo;
}

// Initialize database and create table if it doesn't exist
function initializeDatabase() {
    try {
        // First, connect without database to create it if needed
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if it doesn't exist (use backticks for database name)
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        error_log("✅ Database '" . DB_NAME . "' checked/created successfully");
        
        // Now connect to the database directly (don't use getDBConnection to avoid recursion)
        $dsn_db = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        $pdo_db = new PDO($dsn_db, DB_USER, DB_PASS, $options);
        
        // Create table for analysis reports
        $sql = "CREATE TABLE IF NOT EXISTS analysis_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            top_hazard VARCHAR(255) NOT NULL,
            confidence_score DECIMAL(5,3) NOT NULL,
            rms_level DECIMAL(10,6) NOT NULL,
            spectral_centroid DECIMAL(10,2) DEFAULT NULL,
            frequency VARCHAR(50) DEFAULT NULL,
            signature_name TEXT,
            classification TEXT,
            executive_conclusion TEXT,
            severity VARCHAR(20) DEFAULT NULL,
            is_problem VARCHAR(10) DEFAULT NULL,
            verdict VARCHAR(20) DEFAULT NULL,
            risk_description TEXT,
            action_steps TEXT,
            who_to_contact TEXT,
            category VARCHAR(100) DEFAULT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            full_report_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_severity (severity),
            INDEX idx_category (category),
            INDEX idx_location (latitude, longitude),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo_db->exec($sql);
        
        // Add category column if it doesn't exist (for existing tables)
        try {
            $checkColumn = $pdo_db->query("SHOW COLUMNS FROM analysis_reports LIKE 'category'");
            if ($checkColumn->rowCount() === 0) {
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER who_to_contact");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD INDEX idx_category (category)");
                error_log("✅ Added category column to analysis_reports table");
            }
        } catch (PDOException $e) {
            // Column might already exist, ignore
            error_log("Note: Category column check: " . $e->getMessage());
        }
        
        // Add status column if it doesn't exist (for existing tables)
        try {
            $checkStatus = $pdo_db->query("SHOW COLUMNS FROM analysis_reports LIKE 'status'");
            if ($checkStatus->rowCount() === 0) {
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN status VARCHAR(20) DEFAULT 'pending' AFTER category");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD INDEX idx_status (status)");
                error_log("✅ Added status column to analysis_reports table");
            }
        } catch (PDOException $e) {
            error_log("Note: Status column check: " . $e->getMessage());
        }
        
        // Add verification_photo column if it doesn't exist
        try {
            $checkVerification = $pdo_db->query("SHOW COLUMNS FROM analysis_reports LIKE 'verification_photo'");
            if ($checkVerification->rowCount() === 0) {
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN verification_photo VARCHAR(255) DEFAULT NULL AFTER status");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER verification_photo");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN verified_by VARCHAR(100) DEFAULT NULL AFTER verified_at");
                error_log("✅ Added verification columns to analysis_reports table");
            }
        } catch (PDOException $e) {
            error_log("Note: Verification columns check: " . $e->getMessage());
        }
        
        // Add location columns if they don't exist (for existing tables)
        try {
            $checkLat = $pdo_db->query("SHOW COLUMNS FROM analysis_reports LIKE 'latitude'");
            if ($checkLat->rowCount() === 0) {
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN latitude DECIMAL(10, 8) DEFAULT NULL AFTER category");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN longitude DECIMAL(11, 8) DEFAULT NULL AFTER latitude");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN address TEXT DEFAULT NULL AFTER longitude");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD INDEX idx_location (latitude, longitude)");
                error_log("✅ Added location columns to analysis_reports table");
            }
        } catch (PDOException $e) {
            // Columns might already exist, ignore
            error_log("Note: Location columns check: " . $e->getMessage());
        }
        
        // Add user_id and is_anonymous columns if they don't exist (for user roles system)
        try {
            $checkUserId = $pdo_db->query("SHOW COLUMNS FROM analysis_reports LIKE 'user_id'");
            if ($checkUserId->rowCount() === 0) {
                // First, ensure users table exists (needed for foreign key)
                $usersTableCheck = $pdo_db->query("SHOW TABLES LIKE 'users'");
                if ($usersTableCheck->rowCount() === 0) {
                    // Create users table first
                    $createUsersSql = "CREATE TABLE IF NOT EXISTS users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        username VARCHAR(100) NOT NULL UNIQUE,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        password_hash VARCHAR(255) NOT NULL,
                        role ENUM('guest', 'user', 'admin') NOT NULL DEFAULT 'user',
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        INDEX idx_username (username),
                        INDEX idx_email (email),
                        INDEX idx_role (role)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $pdo_db->exec($createUsersSql);
                    error_log("✅ Created users table for foreign key reference");
                }
                
                // Add columns
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN user_id INT DEFAULT NULL AFTER id");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
                $pdo_db->exec("ALTER TABLE analysis_reports ADD INDEX idx_user_id (user_id)");
                
                // Try to add foreign key constraint (may fail if users table still doesn't exist, but that's ok)
                try {
                    $pdo_db->exec("ALTER TABLE analysis_reports ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
                } catch (PDOException $fkError) {
                    // Foreign key might fail, but columns are added - that's ok
                    error_log("Note: Foreign key constraint not added (users table may not exist yet): " . $fkError->getMessage());
                }
                
                error_log("✅ Added user_id and is_anonymous columns to analysis_reports table");
            }
        } catch (PDOException $e) {
            // Columns might already exist, ignore
            error_log("Note: User ID columns check: " . $e->getMessage());
        }
        
        error_log("✅ Table 'analysis_reports' checked/created successfully");
        
        return true;
    } catch (PDOException $e) {
        // Don't log driver errors here - let getDBConnection handle them
        if (strpos($e->getMessage(), 'could not find driver') === false) {
            error_log("❌ Database initialization failed: " . $e->getMessage());
            error_log("Error Code: " . $e->getCode());
            error_log("SQL State: " . ($e->errorInfo[0] ?? 'N/A'));
        }
        return false;
    } catch (Exception $e) {
        // Don't log driver errors here
        if (strpos($e->getMessage(), 'driver') === false) {
            error_log("❌ Database initialization failed: " . $e->getMessage());
        }
        return false;
    }
}

// Initialize users table for authentication
function initializeUsersTable() {
    try {
        $pdo = getDBConnection();
        
        // Create users table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            role ENUM('guest', 'user', 'admin', 'crew') NOT NULL DEFAULT 'user',
            trust_score DECIMAL(5,2) DEFAULT 1.0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_role (role),
            INDEX idx_trust_score (trust_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'users' checked/created successfully");
        
        // Add trust_score column if it doesn't exist (for existing tables)
        try {
            $checkTrustScore = $pdo->query("SHOW COLUMNS FROM users LIKE 'trust_score'");
            if ($checkTrustScore->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN trust_score DECIMAL(5,2) DEFAULT 1.0 AFTER role");
                $pdo->exec("ALTER TABLE users ADD INDEX idx_trust_score (trust_score)");
                error_log("✅ Added trust_score column to users table");
            } else {
                // Update existing trust_score from INT to DECIMAL if needed
                $col = $checkTrustScore->fetch(PDO::FETCH_ASSOC);
                if (strpos(strtolower($col['Type']), 'decimal') === false) {
                    $pdo->exec("ALTER TABLE users MODIFY COLUMN trust_score DECIMAL(5,2) DEFAULT 1.0");
                    error_log("✅ Updated trust_score column to DECIMAL(5,2)");
                }
            }
        } catch (PDOException $e) {
            error_log("Note: Trust score column check: " . $e->getMessage());
        }
        
        // Update role ENUM to include 'crew' if not already present
        try {
            $checkRole = $pdo->query("SHOW COLUMNS FROM users WHERE Field = 'role'");
            $roleCol = $checkRole->fetch(PDO::FETCH_ASSOC);
            if ($roleCol && strpos($roleCol['Type'], 'crew') === false) {
                $pdo->exec("ALTER TABLE users MODIFY COLUMN role ENUM('guest', 'user', 'admin', 'crew') NOT NULL DEFAULT 'user'");
                error_log("✅ Updated role ENUM to include 'crew'");
            }
        } catch (PDOException $e) {
            error_log("Note: Role ENUM update: " . $e->getMessage());
        }
        
        // Add profile_img column if it doesn't exist (for user profiles)
        try {
            $checkProfileImg = $pdo->query("SHOW COLUMNS FROM users LIKE 'profile_img'");
            if ($checkProfileImg->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN profile_img VARCHAR(255) DEFAULT NULL AFTER trust_score");
                error_log("✅ Added profile_img column to users table");
            }
        } catch (PDOException $e) {
            error_log("Note: Profile image column check: " . $e->getMessage());
        }
        
        // Add bio column if it doesn't exist (for user profiles)
        try {
            $checkBio = $pdo->query("SHOW COLUMNS FROM users LIKE 'bio'");
            if ($checkBio->rowCount() === 0) {
                $pdo->exec("ALTER TABLE users ADD COLUMN bio TEXT DEFAULT NULL AFTER profile_img");
                error_log("✅ Added bio column to users table");
            }
        } catch (PDOException $e) {
            error_log("Note: Bio column check: " . $e->getMessage());
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Users table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize general_reports table
function initializeGeneralReportsTable() {
    try {
        $pdo = getDBConnection();
        
        // Create general_reports table if it doesn't exist
        $sql = "CREATE TABLE IF NOT EXISTS general_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT DEFAULT NULL,
            is_anonymous BOOLEAN DEFAULT FALSE,
            title VARCHAR(255) NOT NULL,
            description TEXT NOT NULL,
            severity VARCHAR(20) NOT NULL,
            category VARCHAR(50) NOT NULL,
            latitude DECIMAL(10, 8) DEFAULT NULL,
            longitude DECIMAL(11, 8) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            images JSON,
            audio_files JSON,
            status VARCHAR(20) DEFAULT 'pending',
            verification_photo VARCHAR(255) DEFAULT NULL,
            verified_at TIMESTAMP NULL DEFAULT NULL,
            verified_by VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_severity (severity),
            INDEX idx_category (category),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_location (latitude, longitude),
            INDEX idx_user_id (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'general_reports' checked/created successfully");
        
        // Add user_id and is_anonymous columns if they don't exist (for existing tables)
        try {
            $checkUserId = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'user_id'");
            if ($checkUserId->rowCount() === 0) {
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN user_id INT DEFAULT NULL AFTER id");
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE AFTER user_id");
                $pdo->exec("ALTER TABLE general_reports ADD INDEX idx_user_id (user_id)");
                
                // Try to add foreign key constraint
                try {
                    $pdo->exec("ALTER TABLE general_reports ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
                } catch (PDOException $fkError) {
                    error_log("Note: Foreign key constraint not added: " . $fkError->getMessage());
                }
                
                error_log("✅ Added user_id and is_anonymous columns to general_reports table");
            }
        } catch (PDOException $e) {
            error_log("Note: User ID columns check: " . $e->getMessage());
        }
        
        // Add verification columns if they don't exist
        try {
            $checkVerification = $pdo->query("SHOW COLUMNS FROM general_reports LIKE 'verification_photo'");
            if ($checkVerification->rowCount() === 0) {
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN verification_photo VARCHAR(255) DEFAULT NULL AFTER status");
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN verified_at TIMESTAMP NULL DEFAULT NULL AFTER verification_photo");
                $pdo->exec("ALTER TABLE general_reports ADD COLUMN verified_by VARCHAR(100) DEFAULT NULL AFTER verified_at");
                error_log("✅ Added verification columns to general_reports table");
            }
        } catch (PDOException $e) {
            error_log("Note: Verification columns check: " . $e->getMessage());
        }
        
        // Ensure status column default is 'pending' (not 'OPEN')
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM general_reports WHERE Field = 'status'");
            $statusCol = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($statusCol && $statusCol['Default'] !== 'pending') {
                $pdo->exec("ALTER TABLE general_reports MODIFY COLUMN status VARCHAR(20) DEFAULT 'pending'");
                error_log("✅ Updated status column default to 'pending' in general_reports table");
            }
        } catch (PDOException $e) {
            error_log("Note: Status column update: " . $e->getMessage());
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ General reports table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize media_files table for separate media storage
function initializeMediaFilesTable() {
    try {
        $pdo = getDBConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS media_files (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            report_type ENUM('analysis', 'general') NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_type ENUM('image', 'audio') NOT NULL,
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report (report_id, report_type),
            INDEX idx_type (file_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'media_files' checked/created successfully");
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Media files table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize audit_log table for tracking changes
function initializeAuditLogTable() {
    try {
        $pdo = getDBConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_id INT NOT NULL,
            report_type ENUM('analysis', 'general') NOT NULL,
            user_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report (report_id, report_type),
            INDEX idx_user (user_id),
            INDEX idx_action (action_type),
            INDEX idx_timestamp (timestamp),
            FOREIGN KEY (user_id) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'audit_log' checked/created successfully");
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Audit log table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize user_watch_zones table for proximity alerts
function initializeWatchZonesTable() {
    try {
        $pdo = getDBConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS user_watch_zones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            radius_meters INT NOT NULL DEFAULT 1000,
            alert_frequency ENUM('realtime', 'daily', 'weekly') DEFAULT 'realtime',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'user_watch_zones' checked/created successfully");
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Watch zones table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize crew_schedule table for crew availability
function initializeCrewScheduleTable() {
    try {
        $pdo = getDBConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS crew_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            day_of_week INT NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            is_available BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_user_day (user_id, day_of_week),
            UNIQUE KEY unique_user_day (user_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'crew_schedule' checked/created successfully");
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Crew schedule table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Initialize compliance_rules table for regulatory compliance
function initializeComplianceRulesTable() {
    try {
        $pdo = getDBConnection();
        
        $sql = "CREATE TABLE IF NOT EXISTS compliance_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(50) NOT NULL,
            max_response_time_hours INT NOT NULL,
            severity_threshold VARCHAR(20),
            description TEXT,
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $pdo->exec($sql);
        error_log("✅ Table 'compliance_rules' checked/created successfully");
        
        // Seed default compliance rules if table is empty
        $count = $pdo->query("SELECT COUNT(*) FROM compliance_rules")->fetchColumn();
        if ($count == 0) {
            $rules = [
                ['Water & Sewage', 4, 'CRITICAL', 'Emergency water/sewage issues require immediate response'],
                ['Roads & Infrastructure', 24, 'HIGH', 'Major road issues must be addressed within 24 hours'],
                ['Street Lighting & Electricity', 48, 'MEDIUM', 'Electrical issues require 2-day response'],
                ['Public Safety & Vandalism', 12, 'HIGH', 'Safety issues require urgent attention'],
                ['Sanitation & Waste Management', 48, 'MEDIUM', 'Waste issues require 2-day response']
            ];
            
            $stmt = $pdo->prepare("INSERT INTO compliance_rules (category, max_response_time_hours, severity_threshold, description) VALUES (?, ?, ?, ?)");
            foreach ($rules as $rule) {
                $stmt->execute($rule);
            }
            error_log("✅ Seeded default compliance rules");
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Compliance rules table initialization failed: " . $e->getMessage());
        return false;
    }
}

// Add assignment and status fields to report tables
function addReportEnhancementFields() {
    try {
        $pdo = getDBConnection();
        
        // Update analysis_reports table
        $tables = ['analysis_reports', 'general_reports'];
        
        foreach ($tables as $table) {
            // Add assigned_to_user_id
            try {
                $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'assigned_to_user_id'");
                if ($check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN assigned_to_user_id INT DEFAULT NULL AFTER status");
                    $pdo->exec("ALTER TABLE $table ADD INDEX idx_assigned_to (assigned_to_user_id)");
                    $pdo->exec("ALTER TABLE $table ADD FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL");
                    error_log("✅ Added assigned_to_user_id to $table");
                }
            } catch (PDOException $e) {
                error_log("Note: assigned_to_user_id check for $table: " . $e->getMessage());
            }
            
            // Add eta_solved
            try {
                $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'eta_solved'");
                if ($check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN eta_solved DATETIME DEFAULT NULL AFTER assigned_to_user_id");
                    error_log("✅ Added eta_solved to $table");
                }
            } catch (PDOException $e) {
                error_log("Note: eta_solved check for $table: " . $e->getMessage());
            }
            
            // Add is_triangulated for analysis_reports
            if ($table === 'analysis_reports') {
                try {
                    $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'is_triangulated'");
                    if ($check->rowCount() === 0) {
                        $pdo->exec("ALTER TABLE $table ADD COLUMN is_triangulated BOOLEAN DEFAULT FALSE AFTER status");
                        $pdo->exec("ALTER TABLE $table ADD COLUMN cluster_id INT DEFAULT NULL AFTER is_triangulated");
                        error_log("✅ Added triangulation fields to $table");
                    }
                } catch (PDOException $e) {
                    error_log("Note: triangulation fields check: " . $e->getMessage());
                }
            }
            
            // Add weather_data column
            try {
                $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'weather_data'");
                if ($check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN weather_data JSON DEFAULT NULL");
                    error_log("✅ Added weather_data to $table");
                }
            } catch (PDOException $e) {
                error_log("Note: weather_data check: " . $e->getMessage());
            }
            
            // Add compliance_status column
            try {
                $check = $pdo->query("SHOW COLUMNS FROM $table LIKE 'compliance_status'");
                if ($check->rowCount() === 0) {
                    $pdo->exec("ALTER TABLE $table ADD COLUMN compliance_status ENUM('compliant', 'at_risk', 'overdue') DEFAULT 'compliant'");
                    error_log("✅ Added compliance_status to $table");
                }
            } catch (PDOException $e) {
                error_log("Note: compliance_status check: " . $e->getMessage());
            }
            
            // Add composite index for crew queries
            try {
                $pdo->exec("ALTER TABLE $table ADD INDEX idx_status_assigned (status, assigned_to_user_id)");
                error_log("✅ Added composite index for crew queries to $table");
            } catch (PDOException $e) {
                // Index might already exist
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("❌ Adding report enhancement fields failed: " . $e->getMessage());
        return false;
    }
}

// Call initialization on include (suppress errors to prevent breaking if DB not ready)
// Initialize users table first to ensure it exists before adding foreign keys
@initializeUsersTable();
@initializeDatabase();
@initializeGeneralReportsTable();
@initializeMediaFilesTable();
@initializeAuditLogTable();
@initializeWatchZonesTable();
@initializeComplianceRulesTable();
@initializeCrewScheduleTable();
@addReportEnhancementFields();
?>

