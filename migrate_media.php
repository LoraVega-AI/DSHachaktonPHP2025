<?php
// migrate_media.php - Migrate media from JSON columns to media_files table

require_once __DIR__ . '/db_config.php';

/**
 * Migrate media files from general_reports JSON columns to media_files table
 */
function migrateGeneralReportsMedia() {
    try {
        $pdo = getDBConnection();
        
        // Get all reports with media
        $stmt = $pdo->query("SELECT id, images, audio_files FROM general_reports WHERE images IS NOT NULL OR audio_files IS NOT NULL");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migratedImages = 0;
        $migratedAudio = 0;
        
        foreach ($reports as $report) {
            $reportId = $report['id'];
            
            // Migrate images
            if (!empty($report['images'])) {
                $images = json_decode($report['images'], true);
                if (is_array($images)) {
                    foreach ($images as $imagePath) {
                        // Check if already migrated
                        $check = $pdo->prepare("SELECT id FROM media_files WHERE report_id = ? AND report_type = 'general' AND file_path = ?");
                        $check->execute([$reportId, $imagePath]);
                        
                        if ($check->rowCount() === 0) {
                            $insert = $pdo->prepare("INSERT INTO media_files (report_id, report_type, file_path, file_type) VALUES (?, 'general', ?, 'image')");
                            $insert->execute([$reportId, $imagePath]);
                            $migratedImages++;
                        }
                    }
                }
            }
            
            // Migrate audio files
            if (!empty($report['audio_files'])) {
                $audioFiles = json_decode($report['audio_files'], true);
                if (is_array($audioFiles)) {
                    foreach ($audioFiles as $audioPath) {
                        // Check if already migrated
                        $check = $pdo->prepare("SELECT id FROM media_files WHERE report_id = ? AND report_type = 'general' AND file_path = ?");
                        $check->execute([$reportId, $audioPath]);
                        
                        if ($check->rowCount() === 0) {
                            $insert = $pdo->prepare("INSERT INTO media_files (report_id, report_type, file_path, file_type) VALUES (?, 'general', ?, 'audio')");
                            $insert->execute([$reportId, $audioPath]);
                            $migratedAudio++;
                        }
                    }
                }
            }
        }
        
        echo "✅ Migrated $migratedImages images and $migratedAudio audio files from general_reports\n";
        error_log("✅ Media migration completed: $migratedImages images, $migratedAudio audio");
        
        return true;
    } catch (PDOException $e) {
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        error_log("❌ Media migration failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get media files for a specific report
 * 
 * @param int $reportId Report ID
 * @param string $reportType 'analysis' or 'general'
 * @param string $fileType Optional: 'image' or 'audio' to filter
 * @return array Array of media file paths
 */
function getReportMedia($reportId, $reportType = 'general', $fileType = null) {
    try {
        $pdo = getDBConnection();
        
        $sql = "SELECT file_path, file_type, uploaded_at FROM media_files 
                WHERE report_id = :report_id AND report_type = :report_type";
        
        if ($fileType) {
            $sql .= " AND file_type = :file_type";
        }
        
        $sql .= " ORDER BY uploaded_at ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':report_id', $reportId, PDO::PARAM_INT);
        $stmt->bindValue(':report_type', $reportType, PDO::PARAM_STR);
        
        if ($fileType) {
            $stmt->bindValue(':file_type', $fileType, PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("❌ Failed to fetch report media: " . $e->getMessage());
        return [];
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli' || (isset($_GET['run']) && $_GET['run'] === 'migrate')) {
    echo "Starting media migration...\n";
    migrateGeneralReportsMedia();
    echo "Migration complete!\n";
}
?>

