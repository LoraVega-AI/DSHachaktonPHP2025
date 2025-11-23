# Map Feature with GPS Tracking

## Overview
Replaced Analytics tab with an interactive Map page that displays all submitted reports with location data on OpenStreetMap, with GPS tracking and manual location input capabilities.

## Features Implemented

### 1. **Navigation Update**
- Replaced "Analytics" with "Map" in sidebar (📍 icon)
- Links to new `map.html` page
- Updated in both `index.html` and `history.html`

### 2. **Location Tracking in Report Form**

#### GPS Auto-Detection:
- **"Use Current Location" button** - One-click GPS tracking
- Automatically captures latitude/longitude
- Shows location status (getting location, success, error)
- Reverse geocoding to get readable address
- Permission-based (asks user for location access)

#### Manual Location Input:
- Address input field for manual entry
- Useful when GPS is unavailable
- Stores text address in database

#### Location Display:
- Shows captured coordinates: "Location set: lat, lng"
- Green status indicator when location is set
- Error messages for permission denied/unavailable

### 3. **Database Schema Update**

New fields added to `general_reports` table:
```sql
latitude DECIMAL(10, 8) DEFAULT NULL
longitude DECIMAL(11, 8) DEFAULT NULL
address TEXT DEFAULT NULL
INDEX idx_location (latitude, longitude)
```

### 4. **Interactive Map Page (map.html)**

#### OpenStreetMap Integration:
- Uses **Leaflet.js** library (free, open-source)
- High-quality OpenStreetMap tiles
- Smooth pan and zoom
- Mobile-responsive

#### Map Features:
- **Color-Coded Markers** by severity:
  - 🔴 Red: Critical
  - 🟠 Orange: High
  - 🟡 Yellow: Medium
  - 🟢 Green: Low

- **Interactive Popups** showing:
  - Report title
  - Time ago
  - Category
  - Severity badge
  - Description
  - Address (if available)

- **Auto-Center**:
  - First loads user's current location
  - Falls back to San Francisco if GPS unavailable
  - Auto-zooms to fit all markers

#### Filter System:
- **Severity Filter**: Critical, High, Medium, Low
- **Category Filter**: Acoustic, Visual, Structural, Environmental, Other
- Real-time map updates on filter change

#### Statistics Panel:
- Total reports on map
- Count by severity (Critical, High, Medium, Low)
- Color-coded numbers
- Updates with filters

### 5. **Backend API (get_map_reports.php)**

#### Features:
- Fetches reports with location data (lat/lng not null)
- Supports severity and category filtering
- Returns formatted JSON with coordinates
- Includes time ago calculation
- Limit: 1000 reports (max 5000)

#### Response Format:
```json
{
  "status": "success",
  "reports": [
    {
      "id": 1,
      "title": "Loud noise complaint",
      "description": "...",
      "severity": "HIGH",
      "category": "Acoustic",
      "latitude": 37.7749,
      "longitude": -122.4194,
      "address": "123 Main St",
      "status": "OPEN",
      "created_at": "2025-11-22 12:00:00",
      "timeAgo": "2 hours ago"
    }
  ],
  "total": 1,
  "limit": 1000
}
```

## User Flow

### Submitting Report with Location:

1. **Open Report Form** on Monitor page
2. **Fill in details** (title, description, etc.)
3. **Click "Use Current Location"**:
   - Browser asks for permission
   - GPS captures coordinates
   - Shows: "Location set: 37.7749, -122.4194"
   - Auto-fills address field
4. **OR enter address manually**
5. **Submit report**
6. Report saved with location data

### Viewing Reports on Map:

1. **Click "Map" in sidebar**
2. Map loads with user's location
3. **View all reports** as colored markers
4. **Click marker** to see popup with details
5. **Filter reports**:
   - Select severity dropdown
   - Select category dropdown
   - Map updates automatically
6. **View statistics** in corner panel

## Technical Implementation

### Frontend (index.html)

#### HTML:
- Location button with GPS icon
- Hidden lat/lng input fields
- Manual address input
- Location status indicator

#### JavaScript:
```javascript
getCurrentLocation() - Capture GPS coordinates
- navigator.geolocation.getCurrentPosition()
- Updates hidden fields
- Reverse geocoding via OpenStreetMap API
- Error handling for permissions
```

### Backend (submit_report.php)
- Accepts latitude, longitude, address fields
- Stores in database with proper decimal precision
- Creates table with location index

### Map Page (map.html)

#### Leaflet.js Setup:
```javascript
L.map('map').setView([lat, lng], zoom)
L.tileLayer('https://{s}.tile.openstreetmap.org/...')
L.marker([lat, lng]).addTo(map)
```

#### Custom Markers:
- Circular colored dots
- White border for visibility
- Shadow for depth
- Custom icon based on severity

#### Popups:
- Styled to match dark theme
- Show all relevant report info
- Scrollable description
- Click marker to open

## Security & Privacy

### Location Data:
- ✅ Permission-based (browser asks user)
- ✅ Optional (can submit without location)
- ✅ No continuous tracking (one-time capture)
- ✅ User can manually edit address

### Data Storage:
- Coordinates stored as DECIMAL(10,8) and DECIMAL(11,8)
- Indexed for fast queries
- Can be null (optional)

## Responsive Design

### Desktop:
- Full map viewport
- Sidebar navigation
- Stats panel in corner
- Dual filter dropdowns

### Mobile:
- Sidebar collapses
- Full-width map
- Smaller stats panel
- Stacked filters

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers
- Requires: Geolocation API, Leaflet.js

## OpenStreetMap Attribution

Uses OpenStreetMap tiles (free, open-source)
- No API key required
- Community-driven map data
- Attribution included in map

## Files Modified/Created

### Modified:
1. **index.html**
   - Added location fields to report form
   - Added GPS tracking JavaScript
   - Updated navigation (Analytics → Map)

2. **history.html**
   - Updated navigation (Analytics → Map)

3. **submit_report.php**
   - Added latitude, longitude, address fields
   - Updated database schema

### Created:
1. **map.html** (NEW - 650 lines)
   - Interactive OpenStreetMap display
   - Leaflet.js integration
   - Custom markers and popups

2. **get_map_reports.php** (NEW - 120 lines)
   - API endpoint for map data
   - Filtering support
   - JSON response

3. **MAP_FEATURE.md** (NEW - this file)
   - Complete documentation

## Future Enhancements (Optional)

- [ ] Clustering for many markers (Leaflet.markercluster)
- [ ] Heatmap view option
- [ ] Draw polygon to filter area
- [ ] Export map data to GeoJSON
- [ ] Street view integration
- [ ] Routing/directions to report location
- [ ] Search by address/coordinates
- [ ] Save favorite locations
- [ ] Report density analysis
- [ ] Time-based animation of reports

---

**Created**: 2025-11-22  
**Status**: ✅ Complete and functional  
**Pages**: Map (map.html), updated Monitor & History navigation

