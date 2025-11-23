<?php
// tests/validate_database_schema.php - Comprehensive database schema validator

echo "<!DOCTYPE html><html><head><title>Database Schema Validator</title>";
echo "<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
.container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
h2 { color: #555; margin-top: 30px; }
h3 { color: #666; margin-top: 20px; }
.test-result { margin: 10px 0; padding: 12px; border-radius: 5px; }
.success { background: #d4edda; border-left: 4px solid #28a745; }
.error { background: #f8d7da; border-left: 4px solid #dc3545; }
.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
.info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
.status { font-weight: bold; }
.details { margin-top: 5px; font-size: 14px; color: #666; }
table { width: 100%; border-collapse: collapse; margin: 15px 0; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #f8f9fa; font-weight: bold; }
tr:hover { background: #f8f9fa; }
.check-mark { color: #28a745; font-weight: bold; }
.x-mark { color: #dc3545; font-weight: bold; }
pre { background: #f4f4f4; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px; }
.summary { background: #e7f3ff; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #2196F3; }
</style></head><body><div class='container'>";

echo "<h1>🔍 Database Schema Validator</h1>";
echo "<p>Validating all tables, columns, indexes, and constraints for UrbanPulse application.</p>";

require_once __DIR__ . '/../db_config.php';

$results = [
    'tables' => [],
    'overall_status' => 'success',
    'issues_found' => []
];

try {
    $pdo = getDBConnection();
    echo "<div class='test-result success'><div class='status'>✅ Database connection established</div></div>";
} catch (Exception $e) {
    echo "<div class='test-result error'><div class='status'>❌ CRITICAL: Cannot connect to database</div>";
    echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
    echo "</div></body></html>";
    exit;
}

// Define expected schemas
$expectedSchemas = [
    'users' => [
        'columns' => [
            'id' => ['type' => 'int', 'required' => true, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'username' => ['type' => 'varchar', 'required' => true],
            'email' => ['type' => 'varchar', 'required' => true],
            'password_hash' => ['type' => 'varchar', 'required' => true],
            'role' => ['type' => 'enum', 'required' => true],
            'trust_score' => ['type' => 'int', 'required' => false],
            'profile_img' => ['type' => 'varchar', 'required' => false],
            'bio' => ['type' => 'text', 'required' => false],
            'created_at' => ['type' => 'timestamp', 'required' => false]
        ],
        'indexes' => ['idx_username', 'idx_email', 'idx_role', 'idx_trust_score'],
        'required' => true
    ],
    'analysis_reports' => [
        'columns' => [
            'id' => ['type' => 'int', 'required' => true, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'user_id' => ['type' => 'int', 'required' => false],
            'is_anonymous' => ['type' => 'tinyint', 'required' => false],
            'timestamp' => ['type' => 'datetime', 'required' => true],
            'top_hazard' => ['type' => 'varchar', 'required' => true],
            'confidence_score' => ['type' => 'decimal', 'required' => true],
            'rms_level' => ['type' => 'decimal', 'required' => true],
            'spectral_centroid' => ['type' => 'decimal', 'required' => false],
            'frequency' => ['type' => 'varchar', 'required' => false],
            'signature_name' => ['type' => 'text', 'required' => false],
            'classification' => ['type' => 'text', 'required' => false],
            'executive_conclusion' => ['type' => 'text', 'required' => false],
            'severity' => ['type' => 'varchar', 'required' => false],
            'is_problem' => ['type' => 'varchar', 'required' => false],
            'verdict' => ['type' => 'varchar', 'required' => false],
            'risk_description' => ['type' => 'text', 'required' => false],
            'action_steps' => ['type' => 'text', 'required' => false],
            'who_to_contact' => ['type' => 'text', 'required' => false],
            'category' => ['type' => 'varchar', 'required' => false],
            'status' => ['type' => 'varchar', 'required' => false],
            'latitude' => ['type' => 'decimal', 'required' => false],
            'longitude' => ['type' => 'decimal', 'required' => false],
            'address' => ['type' => 'text', 'required' => false],
            'verification_photo' => ['type' => 'varchar', 'required' => false],
            'verified_at' => ['type' => 'timestamp', 'required' => false],
            'verified_by' => ['type' => 'varchar', 'required' => false],
            'full_report_data' => ['type' => 'json', 'required' => false],
            'created_at' => ['type' => 'timestamp', 'required' => false]
        ],
        'indexes' => ['idx_timestamp', 'idx_severity', 'idx_category', 'idx_location', 'idx_created_at', 'idx_user_id', 'idx_status'],
        'required' => true
    ],
    'general_reports' => [
        'columns' => [
            'id' => ['type' => 'int', 'required' => true, 'key' => 'PRI', 'extra' => 'auto_increment'],
            'user_id' => ['type' => 'int', 'required' => false],
            'is_anonymous' => ['type' => 'tinyint', 'required' => false],
            'title' => ['type' => 'varchar', 'required' => true],
            'description' => ['type' => 'text', 'required' => true],
            'severity' => ['type' => 'varchar', 'required' => true],
            'category' => ['type' => 'varchar', 'required' => true],
            'latitude' => ['type' => 'decimal', 'required' => false],
            'longitude' => ['type' => 'decimal', 'required' => false],
            'address' => ['type' => 'text', 'required' => false],
            'images' => ['type' => 'json', 'required' => false],
            'audio_files' => ['type' => 'json', 'required' => false],
            'status' => ['type' => 'varchar', 'required' => false],
            'verification_photo' => ['type' => 'varchar', 'required' => false],
            'verified_at' => ['type' => 'timestamp', 'required' => false],
            'verified_by' => ['type' => 'varchar', 'required' => false],
            'created_at' => ['type' => 'timestamp', 'required' => false],
            'updated_at' => ['type' => 'timestamp', 'required' => false]
        ],
        'indexes' => ['idx_severity', 'idx_category', 'idx_status', 'idx_created_at', 'idx_location', 'idx_user_id'],
        'required' => true
    ]
];

// Check each table
foreach ($expectedSchemas as $tableName => $schema) {
    echo "<h2>Table: {$tableName}</h2>";
    
    $tableResult = [
        'exists' => false,
        'columns' => [],
        'indexes' => [],
        'missing_columns' => [],
        'missing_indexes' => [],
        'extra_columns' => []
    ];
    
    // Check if table exists
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            echo "<div class='test-result success'><div class='status'>✅ Table exists</div></div>";
            $tableResult['exists'] = true;
        } else {
            echo "<div class='test-result error'><div class='status'>❌ Table does NOT exist</div>";
            echo "<div class='details'>This table needs to be created</div></div>";
            $results['issues_found'][] = "Table '{$tableName}' is missing";
            $results['overall_status'] = 'error';
            $tableResult['exists'] = false;
            $results['tables'][$tableName] = $tableResult;
            continue;
        }
    } catch (PDOException $e) {
        echo "<div class='test-result error'><div class='status'>❌ Error checking table</div>";
        echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
        $results['issues_found'][] = "Error checking table '{$tableName}': " . $e->getMessage();
        $results['overall_status'] = 'error';
        continue;
    }
    
    // Check columns
    echo "<h3>Columns</h3>";
    try {
        $stmt = $pdo->query("SHOW FULL COLUMNS FROM {$tableName}");
        $actualColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table>";
        echo "<tr><th>Column Name</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th><th>Status</th></tr>";
        
        $actualColumnNames = [];
        foreach ($actualColumns as $col) {
            $actualColumnNames[] = $col['Field'];
            $tableResult['columns'][$col['Field']] = $col;
            
            $expectedCol = $schema['columns'][$col['Field']] ?? null;
            $status = '';
            
            if ($expectedCol) {
                // Column is expected
                $typeMatch = stripos($col['Type'], $expectedCol['type']) !== false;
                if ($typeMatch) {
                    $status = "<span class='check-mark'>✅ Expected</span>";
                } else {
                    $status = "<span class='x-mark'>⚠️ Type mismatch</span>";
                    $results['issues_found'][] = "Column '{$tableName}.{$col['Field']}' type mismatch";
                }
            } else {
                // Extra column not in schema
                $status = "<span style='color: #ffc107;'>➕ Extra</span>";
                $tableResult['extra_columns'][] = $col['Field'];
            }
            
            echo "<tr>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>{$col['Key']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "<td>{$col['Extra']}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for missing columns
        $missingColumns = array_diff(array_keys($schema['columns']), $actualColumnNames);
        if (!empty($missingColumns)) {
            echo "<div class='test-result error'>";
            echo "<div class='status'>❌ Missing columns: " . implode(', ', $missingColumns) . "</div>";
            echo "</div>";
            $tableResult['missing_columns'] = $missingColumns;
            foreach ($missingColumns as $col) {
                $results['issues_found'][] = "Column '{$tableName}.{$col}' is missing";
            }
            $results['overall_status'] = 'error';
        } else {
            echo "<div class='test-result success'><div class='status'>✅ All required columns present</div></div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='test-result error'><div class='status'>❌ Error checking columns</div>";
        echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
        $results['issues_found'][] = "Error checking columns for '{$tableName}': " . $e->getMessage();
    }
    
    // Check indexes
    echo "<h3>Indexes</h3>";
    try {
        $stmt = $pdo->query("SHOW INDEX FROM {$tableName}");
        $actualIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Group by key name
        $indexGroups = [];
        foreach ($actualIndexes as $idx) {
            $indexGroups[$idx['Key_name']][] = $idx;
        }
        
        echo "<table>";
        echo "<tr><th>Index Name</th><th>Column(s)</th><th>Unique</th><th>Status</th></tr>";
        
        $actualIndexNames = array_keys($indexGroups);
        foreach ($indexGroups as $keyName => $indexData) {
            $columns = array_map(function($idx) { return $idx['Column_name']; }, $indexData);
            $unique = $indexData[0]['Non_unique'] == 0 ? 'Yes' : 'No';
            $tableResult['indexes'][] = $keyName;
            
            $expectedIndex = in_array($keyName, $schema['indexes']) || $keyName === 'PRIMARY';
            $status = $expectedIndex ? "<span class='check-mark'>✅ Expected</span>" : "<span style='color: #ffc107;'>➕ Extra</span>";
            
            echo "<tr>";
            echo "<td>{$keyName}</td>";
            echo "<td>" . implode(', ', $columns) . "</td>";
            echo "<td>{$unique}</td>";
            echo "<td>{$status}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Check for missing indexes
        $missingIndexes = array_diff($schema['indexes'], $actualIndexNames);
        if (!empty($missingIndexes)) {
            echo "<div class='test-result warning'>";
            echo "<div class='status'>⚠️ Missing indexes: " . implode(', ', $missingIndexes) . "</div>";
            echo "<div class='details'>These indexes should be added for optimal performance</div>";
            echo "</div>";
            $tableResult['missing_indexes'] = $missingIndexes;
            foreach ($missingIndexes as $idx) {
                $results['issues_found'][] = "Index '{$idx}' is missing from table '{$tableName}'";
            }
        } else {
            echo "<div class='test-result success'><div class='status'>✅ All expected indexes present</div></div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='test-result error'><div class='status'>❌ Error checking indexes</div>";
        echo "<div class='details'>Error: " . htmlspecialchars($e->getMessage()) . "</div></div>";
        $results['issues_found'][] = "Error checking indexes for '{$tableName}': " . $e->getMessage();
    }
    
    // Check foreign keys
    echo "<h3>Foreign Keys</h3>";
    try {
        $stmt = $pdo->query("
            SELECT 
                CONSTRAINT_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = 'hackathondb'
            AND TABLE_NAME = '{$tableName}'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($foreignKeys)) {
            echo "<table>";
            echo "<tr><th>Constraint</th><th>Column</th><th>References</th></tr>";
            foreach ($foreignKeys as $fk) {
                echo "<tr>";
                echo "<td>{$fk['CONSTRAINT_NAME']}</td>";
                echo "<td>{$fk['COLUMN_NAME']}</td>";
                echo "<td>{$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<div class='test-result info'><div class='details'>No foreign keys defined (optional)</div></div>";
        }
        
    } catch (PDOException $e) {
        echo "<div class='test-result warning'><div class='details'>Could not check foreign keys</div></div>";
    }
    
    $results['tables'][$tableName] = $tableResult;
}

// Summary
echo "<h2>📊 Validation Summary</h2>";
echo "<div class='summary'>";

$totalTables = count($expectedSchemas);
$existingTables = 0;
$totalIssues = count($results['issues_found']);

foreach ($results['tables'] as $table => $data) {
    if ($data['exists']) $existingTables++;
}

echo "<div><strong>Tables:</strong> {$existingTables} / {$totalTables} exist</div>";
echo "<div><strong>Issues Found:</strong> {$totalIssues}</div>";
echo "<div style='margin-top: 10px;'><strong>Overall Status:</strong> ";

if ($results['overall_status'] === 'success' && $totalIssues === 0) {
    echo "<span style='color: #28a745; font-weight: bold;'>✅ ALL SCHEMAS VALID</span>";
} else {
    echo "<span style='color: #dc3545; font-weight: bold;'>❌ SCHEMA ISSUES DETECTED</span>";
}

echo "</div>";
echo "</div>";

// Issues List
if ($totalIssues > 0) {
    echo "<h2>⚠️ Issues to Fix</h2>";
    echo "<div class='test-result warning'>";
    echo "<ol>";
    foreach ($results['issues_found'] as $issue) {
        echo "<li>" . htmlspecialchars($issue) . "</li>";
    }
    echo "</ol>";
    echo "</div>";
}

// JSON Results
echo "<h2>📄 JSON Results</h2>";
echo "<details><summary>Click to expand</summary>";
echo "<pre>" . json_encode($results, JSON_PRETTY_PRINT) . "</pre>";
echo "</details>";

echo "</div></body></html>";
?>

