# General Report Submission Feature

## Overview
Added a comprehensive general report submission form at the top of the Monitor page, allowing users to report issues/problems with text descriptions, image attachments, and audio file uploads.

## Features

### 1. **Collapsible Form**
- Toggle button to show/hide the report form
- Saves screen space when not in use
- Smooth animation on open/close

### 2. **Form Fields**

#### Required Fields:
- **Issue Title**: Brief description of the problem
- **Detailed Description**: Full explanation of the issue (textarea, 5 rows)

#### Optional Fields:
- **Severity Level**: Dropdown (Low, Medium, High, Critical)
- **Category**: Dropdown (Acoustic Issue, Visual Issue, Structural, Environmental, Other)

### 3. **File Attachments**

#### Image Upload:
- Click-to-upload area with visual feedback
- Supports: PNG, JPG, JPEG, GIF, WEBP
- Maximum: 5 images per report
- Preview uploaded files with remove option

#### Audio Upload:
- Click-to-upload area with visual feedback
- Supports: MP3, WAV, OGG, WEBM
- Maximum: 3 audio files per report
- Preview uploaded files with remove option

### 4. **File Preview**
- Shows filename with icon
- "×" button to remove individual files
- Updates in real-time as files are added/removed

### 5. **Form Actions**
- **Submit Report**: Uploads files and saves to database
- **Cancel**: Resets form to empty state
- Status indicator shows upload progress and results

### 6. **Status Feedback**
- Loading state: Blue background with "Submitting..." message
- Success state: Green background with checkmark
- Error state: Red background with error message
- Auto-hide form after successful submission

## Technical Implementation

### Frontend (index.html)

#### HTML Structure:
```html
<div class="general-report-section">
  <div class="report-header">
    - Toggle button
  </div>
  <div class="report-form-container">
    <form id="generalReportForm">
      - Text inputs
      - Textareas
      - Dropdowns
      - File upload areas
      - Submit/Cancel buttons
      - Status display
    </form>
  </div>
</div>
```

#### CSS Styling:
- Consistent with existing design system
- Hover effects on upload areas
- Focus states on inputs
- Responsive grid for form fields
- Mobile-optimized layout

#### JavaScript Functions:
- `toggleReportForm()`: Show/hide form
- `displayImagePreviews()`: Show image file list
- `displayAudioPreviews()`: Show audio file list
- `removeImage(index)`: Remove specific image
- `removeAudio(index)`: Remove specific audio file
- `handleReportSubmit()`: Submit form via AJAX
- `resetReportForm()`: Clear all form fields

### Backend (submit_report.php)

#### Features:
- Handles POST requests with file uploads
- Creates `uploads/reports/` directory automatically
- Validates file types (images and audio)
- Generates unique filenames to prevent conflicts
- Stores file paths in database as JSON
- Creates database table if not exists
- Returns JSON response with status

#### Database Schema:
```sql
CREATE TABLE general_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    severity VARCHAR(20) NOT NULL,
    category VARCHAR(50) NOT NULL,
    images JSON,
    audio_files JSON,
    status VARCHAR(20) DEFAULT 'OPEN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_severity (severity),
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
)
```

#### File Storage:
- Location: `uploads/reports/`
- Image naming: `img_[timestamp]_[uniqueid].[ext]`
- Audio naming: `audio_[timestamp]_[uniqueid].[ext]`
- JSON array stored in database for easy retrieval

#### Security:
- File type validation
- Maximum file limits enforced
- Unique filenames prevent overwrites
- Directory permissions: 0755

## User Flow

1. **Open Form**
   - Click "Show Form" button
   - Form expands with smooth animation

2. **Fill Information**
   - Enter issue title (required)
   - Describe problem in detail (required)
   - Select severity and category

3. **Attach Files**
   - Click image upload area → Select up to 5 images
   - Click audio upload area → Select up to 3 audio files
   - Preview shows all selected files
   - Remove unwanted files with × button

4. **Submit**
   - Click "Submit Report" button
   - Status shows "Submitting..."
   - Success: Green message "Report submitted successfully!"
   - Form auto-resets and closes after 2 seconds

5. **Cancel**
   - Click "Cancel" to clear form
   - All fields reset to default
   - Uploaded files cleared

## Responsive Design

### Desktop (> 768px)
- Two-column layout for severity/category
- Full-width form fields
- Horizontal action buttons

### Mobile (< 768px)
- Single-column layout
- Stacked form fields
- Vertical action buttons
- Smaller upload areas

## Error Handling

### Client-side:
- Maximum file limit validation
- Required field validation (HTML5)
- File type checking (accept attribute)
- Network error handling

### Server-side:
- POST method validation
- Required field validation
- File type validation
- Database error handling
- File upload error handling

## API Response Format

### Success:
```json
{
  "status": "success",
  "message": "Report submitted successfully",
  "report_id": 123,
  "images_uploaded": 2,
  "audio_uploaded": 1
}
```

### Error:
```json
{
  "status": "error",
  "message": "Error description"
}
```

## Files Added/Modified

### Added:
1. **submit_report.php** (NEW - 150 lines)
   - Backend handler for report submission
   - File upload processing
   - Database operations

2. **GENERAL_REPORT_FEATURE.md** (NEW - this file)
   - Complete documentation

### Modified:
1. **index.html**
   - Added report form HTML structure
   - Added CSS styling (~250 lines)
   - Added JavaScript functionality (~150 lines)
   - Added mobile responsive styles

### Created Automatically:
- **uploads/reports/** directory (when first report is submitted)

## Usage Statistics (Trackable)

The database stores:
- Number of reports by severity
- Number of reports by category
- Reports per day/week/month
- Average files attached per report
- Response/resolution time

## Future Enhancements (Optional)

- [ ] View submitted reports in History page
- [ ] Report status tracking (Open, In Progress, Resolved, Closed)
- [ ] Email notifications when report is submitted
- [ ] Admin panel to manage reports
- [ ] Image preview thumbnails
- [ ] Audio player for preview
- [ ] Geolocation tagging
- [ ] Priority assignment
- [ ] Assign to team members
- [ ] Comments/discussion thread
- [ ] Report export to PDF

---

**Created**: 2025-11-22  
**Status**: ✅ Complete and functional  
**Location**: Top of Monitor page (index.html)

