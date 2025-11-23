# 🔥 Hot Zone Troubleshooting Guide

## Quick Fix Steps

### Step 1: Test the Backend Endpoint
Open in your browser:
```
http://localhost/DSHackathon2025/get_heatmap_data.php
```

**Expected Result:**
```json
{
  "status": "success",
  "heatmap_data": [
    [42.6629, 21.1655, 0.75],
    [42.6640, 21.1660, 1.0]
  ],
  "stats": { ... }
}
```

**If you see an error:**
- Check XAMPP Apache and MySQL are running
- Verify database exists: `hackathondb`
- Check PHP error logs

**If `heatmap_data` is empty `[]`:**
- Your database has no reports with valid lat/lng coordinates
- Reports need: latitude != 0, longitude != 0, both NOT NULL

---

### Step 2: Use the Test Page
Open the diagnostic test page:
```
http://localhost/DSHackathon2025/test_heatmap_simple.html
```

This will:
1. ✅ Check if Leaflet is loaded
2. ✅ Check if Leaflet.heat is loaded
3. ✅ Fetch heatmap data automatically
4. ✅ Display the heatmap (if data exists)
5. ✅ Show detailed logs in the status section

**What to look for:**
- Green ✓ messages = Success
- Red ✗ messages = Problem found
- Check the status section for detailed logs

---

### Step 3: Test on Main Map Page
Open the main map:
```
http://localhost/DSHackathon2025/map.html
```

1. Open browser console (F12)
2. Click the "🔥 Hot Zone" button
3. Watch the console for messages starting with 🔥

**Console Messages to Look For:**
```
🔥 toggleHeatmap() called, current mode: false
🔥 Switching to heatmap mode: true
🔥 Activating heatmap...
🔥 loadHeatmapData() called
🔥 Loading heatmap data from: get_heatmap_data.php?
🔥 Response status: 200
✅ Heatmap data loaded: 50 points
🔥 showHeatmap() called
✅ Heatmap layer added to map successfully!
```

---

## Common Issues & Fixes

### Issue 1: "No heatmap data available"

**Cause:** Database has no reports with valid coordinates

**Fix:**
1. Check if reports exist in database
2. Verify reports have latitude and longitude values
3. Ensure coordinates are not 0, 0

**Test Query (run in phpMyAdmin):**
```sql
SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN latitude IS NOT NULL AND latitude != 0 THEN 1 ELSE 0 END) as with_coords
FROM analysis_reports;
```

**Add Test Data (if needed):**
```sql
-- Insert a test report with coordinates (Prishtina, Kosovo)
INSERT INTO analysis_reports 
(timestamp, top_hazard, confidence_score, rms_level, severity, latitude, longitude, category) 
VALUES 
(NOW(), 'Test Hazard', 0.85, 0.5, 'HIGH', 42.6629, 21.1655, 'Roads & Infrastructure');
```

---

### Issue 2: "Leaflet.heat not loaded"

**Cause:** Script failed to load from CDN

**Fix 1 - Hard Refresh:**
```
Windows: Ctrl + F5
Mac: Cmd + Shift + R
```

**Fix 2 - Check Network Tab:**
1. Open browser DevTools (F12)
2. Go to Network tab
3. Refresh page
4. Look for `leaflet-heat.js`
5. Status should be `200`

**Fix 3 - Download Locally:**
If CDN is blocked, download the file:
1. Download from: https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js
2. Save to your project folder
3. Update map.html script tag:
```html
<script src="leaflet-heat.js"></script>
```

---

### Issue 3: Heatmap shows but no colors

**Cause:** Data intensity values might be too low

**Fix - Adjust Parameters:**

Edit `map.html`, find the `showHeatmap()` function and modify:

```javascript
heatmapLayer = L.heatLayer(heatmapData, {
    radius: 50,          // Increase from 35
    blur: 30,            // Increase from 25
    maxZoom: 17,
    max: 0.5,            // Decrease from 1.0 to make colors more visible
    minOpacity: 0.7,     // Increase from 0.5
    // ... gradient stays same
});
```

---

### Issue 4: Map is blank/white

**Cause:** Base tile layer not loading

**Fix:**
1. Check internet connection (tiles load from OpenStreetMap)
2. Try alternative tile provider

Edit `map.html` and replace tile layer:

```javascript
// Replace existing tile layer with:
L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);
```

---

### Issue 5: Button click does nothing

**Cause:** JavaScript error preventing execution

**Fix:**
1. Open browser console (F12)
2. Look for red error messages
3. Check if error mentions:
   - `L is not defined` → Leaflet not loaded
   - `L.heatLayer is not defined` → Leaflet.heat not loaded
   - Other errors → Share in console for debugging

---

## Debug Mode

### Enable Verbose Logging

The updated code now includes extensive console logging. To see all logs:

1. Open browser console (F12)
2. Click "🔥 Hot Zone" button
3. Review all messages with 🔥 emoji
4. Share relevant error messages if issue persists

### Check Backend Response

In browser console, run:

```javascript
fetch('get_heatmap_data.php')
    .then(r => r.json())
    .then(d => console.table(d))
    .catch(e => console.error(e));
```

This will show the backend response in a nice table format.

---

## Success Checklist

After implementing fixes, verify:

- [ ] Backend endpoint returns JSON with data
- [ ] Test page shows green ✓ messages
- [ ] Console shows 🔥 messages when toggling
- [ ] Heatmap colors appear on map
- [ ] Legend appears in bottom-left
- [ ] Button gets red gradient when active
- [ ] Markers hide when heatmap active
- [ ] Markers return when heatmap deactivated

---

## Still Not Working?

### Get Diagnostic Report

Run this in browser console on `map.html`:

```javascript
console.log('=== DIAGNOSTIC REPORT ===');
console.log('Leaflet loaded:', typeof L !== 'undefined');
console.log('Leaflet.heat loaded:', typeof L !== 'undefined' && typeof L.heatLayer !== 'undefined');
console.log('Map initialized:', typeof map !== 'undefined' && map !== null);
console.log('Heatmap data points:', typeof heatmapData !== 'undefined' ? heatmapData.length : 'undefined');
console.log('Heatmap mode active:', isHeatmapMode);
console.log('======================');
```

Copy the output and share it.

---

## Quick Test Script

Paste this in browser console to test everything:

```javascript
(async function() {
    console.log('🔍 Running diagnostic...');
    
    // Check libraries
    console.log('✓ Leaflet:', typeof L !== 'undefined' ? 'Loaded' : '❌ NOT LOADED');
    console.log('✓ Leaflet.heat:', typeof L !== 'undefined' && typeof L.heatLayer !== 'undefined' ? 'Loaded' : '❌ NOT LOADED');
    
    // Test backend
    try {
        const res = await fetch('get_heatmap_data.php');
        const data = await res.json();
        console.log('✓ Backend response:', data.status);
        console.log('✓ Data points:', data.heatmap_data.length);
        if (data.heatmap_data.length > 0) {
            console.log('✓ Sample:', data.heatmap_data[0]);
        } else {
            console.log('⚠️ No data points (database empty or no coordinates)');
        }
    } catch(e) {
        console.log('❌ Backend error:', e.message);
    }
    
    console.log('🔍 Diagnostic complete!');
})();
```

---

## Contact Information

If you've tried all steps and it still doesn't work:

1. Run the diagnostic script above
2. Take a screenshot of the console
3. Note which step fails
4. Share the information for further help

---

**Last Updated:** November 23, 2025
**Version:** 1.1.0 (Debug Enhanced)

