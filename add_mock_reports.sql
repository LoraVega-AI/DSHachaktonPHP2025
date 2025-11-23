-- Add 50 Mock Reports Across Kosovo for Hot Zone Visualization
-- Run this in phpMyAdmin or MySQL client

USE hackathondb;

-- Insert 50 reports with locations across Kosovo
-- Coordinates cover Prishtina, Prizren, Peja, Gjakova, Gjilan, Ferizaj, Mitrovica, etc.

INSERT INTO analysis_reports 
(timestamp, top_hazard, confidence_score, rms_level, severity, latitude, longitude, address, category, created_at) 
VALUES
-- Prishtina area (capital - most reports)
(NOW(), 'Pothole on Main Street', 0.92, 0.65, 'CRITICAL', 42.6629, 21.1655, 'Prishtina Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Broken Streetlight', 0.78, 0.45, 'HIGH', 42.6650, 21.1670, 'Mother Teresa Boulevard', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Garbage Overflow', 0.85, 0.55, 'HIGH', 42.6640, 21.1660, 'Bill Clinton Boulevard', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Water Leak', 0.88, 0.60, 'CRITICAL', 42.6620, 21.1645, 'Aktash', 'Water & Sewage', NOW()),
(NOW(), 'Traffic Sign Missing', 0.65, 0.35, 'MEDIUM', 42.6680, 21.1690, 'Ulpiana', 'Traffic & Parking', NOW()),
(NOW(), 'Damaged Sidewalk', 0.72, 0.42, 'MEDIUM', 42.6660, 21.1680, 'Dardania', 'Roads & Infrastructure', NOW()),
(NOW(), 'Park Bench Broken', 0.55, 0.28, 'LOW', 42.6635, 21.1650, 'City Park', 'Parks & Green Spaces', NOW()),
(NOW(), 'Graffiti on Wall', 0.60, 0.30, 'LOW', 42.6645, 21.1665, 'Sunny Hill', 'Public Safety & Vandalism', NOW()),
(NOW(), 'Air Pollution', 0.82, 0.50, 'HIGH', 42.6625, 21.1640, 'Industrial Zone', 'Environment & Pollution', NOW()),
(NOW(), 'Road Crack', 0.70, 0.40, 'MEDIUM', 42.6690, 21.1700, 'Arberia', 'Roads & Infrastructure', NOW()),
(NOW(), 'Street Light Flickering', 0.68, 0.38, 'MEDIUM', 42.6615, 21.1635, 'Kalabria', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Illegal Dumping', 0.90, 0.62, 'CRITICAL', 42.6605, 21.1625, 'Dragodan', 'Sanitation & Waste Management', NOW()),

-- Prizren (southern Kosovo)
(NOW(), 'Bridge Damage', 0.95, 0.70, 'CRITICAL', 42.2139, 20.7397, 'Prizren Bridge', 'Roads & Infrastructure', NOW()),
(NOW(), 'Historic Building Damage', 0.88, 0.58, 'HIGH', 42.2145, 20.7405, 'Old Town Prizren', 'Public Safety & Vandalism', NOW()),
(NOW(), 'Street Flooding', 0.83, 0.52, 'HIGH', 42.2130, 20.7390, 'Prizren Center', 'Water & Sewage', NOW()),
(NOW(), 'Trash Accumulation', 0.75, 0.46, 'MEDIUM', 42.2150, 20.7410, 'Marash', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Parking Issue', 0.62, 0.32, 'LOW', 42.2135, 20.7395, 'Shadervan', 'Traffic & Parking', NOW()),

-- Peja (western Kosovo)
(NOW(), 'Mountain Road Damage', 0.92, 0.64, 'CRITICAL', 42.6594, 20.2889, 'Peja to Rugova', 'Roads & Infrastructure', NOW()),
(NOW(), 'Power Outage', 0.87, 0.57, 'HIGH', 42.6600, 20.2895, 'Peja Center', 'Street Lighting & Electricity', NOW()),
(NOW(), 'River Pollution', 0.80, 0.48, 'HIGH', 42.6585, 20.2880, 'Bistrica River', 'Environment & Pollution', NOW()),
(NOW(), 'Sidewalk Obstruction', 0.68, 0.36, 'MEDIUM', 42.6590, 20.2885, 'Peja Bazaar', 'Roads & Infrastructure', NOW()),

-- Gjakova (southwestern Kosovo)
(NOW(), 'Road Sinkhole', 0.94, 0.68, 'CRITICAL', 42.3803, 20.4289, 'Gjakova Main Road', 'Roads & Infrastructure', NOW()),
(NOW(), 'Street Market Issue', 0.72, 0.42, 'MEDIUM', 42.3810, 20.4295, 'Old Bazaar', 'Traffic & Parking', NOW()),
(NOW(), 'Water Main Break', 0.89, 0.60, 'CRITICAL', 42.3795, 20.4280, 'Gjakova Suburb', 'Water & Sewage', NOW()),
(NOW(), 'Abandoned Vehicle', 0.65, 0.34, 'MEDIUM', 42.3800, 20.4285, 'Gjakova Industrial', 'Traffic & Parking', NOW()),

-- Gjilan (eastern Kosovo)
(NOW(), 'Pothole Cluster', 0.86, 0.54, 'HIGH', 42.4636, 21.4694, 'Gjilan Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Park Maintenance', 0.58, 0.28, 'LOW', 42.4640, 21.4700, 'City Park Gjilan', 'Parks & Green Spaces', NOW()),
(NOW(), 'Street Light Out', 0.75, 0.44, 'MEDIUM', 42.4630, 21.4690, 'Gjilan East', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Waste Container Full', 0.70, 0.40, 'MEDIUM', 42.4645, 21.4705, 'Gjilan West', 'Sanitation & Waste Management', NOW()),

-- Ferizaj (central Kosovo)
(NOW(), 'Train Station Damage', 0.88, 0.56, 'HIGH', 42.3700, 21.1483, 'Ferizaj Station', 'Public Transport & Facilities', NOW()),
(NOW(), 'Road Surface Damage', 0.82, 0.50, 'HIGH', 42.3705, 21.1490, 'Ferizaj Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Stray Dogs', 0.68, 0.38, 'MEDIUM', 42.3695, 21.1480, 'Ferizaj Suburbs', 'Animal Control', NOW()),
(NOW(), 'Noise Pollution', 0.72, 0.42, 'MEDIUM', 42.3710, 21.1495, 'Ferizaj Industrial', 'Environment & Pollution', NOW()),

-- Mitrovica (northern Kosovo)
(NOW(), 'Bridge Safety Issue', 0.96, 0.72, 'CRITICAL', 42.8914, 20.8661, 'Mitrovica Bridge', 'Roads & Infrastructure', NOW()),
(NOW(), 'River Contamination', 0.85, 0.54, 'HIGH', 42.8920, 20.8670, 'Ibar River', 'Environment & Pollution', NOW()),
(NOW(), 'Electrical Hazard', 0.91, 0.62, 'CRITICAL', 42.8910, 20.8655, 'North Mitrovica', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Building Collapse Risk', 0.93, 0.66, 'CRITICAL', 42.8925, 20.8675, 'Old Town Mitrovica', 'Public Safety & Vandalism', NOW()),

-- Additional locations across Kosovo
(NOW(), 'Rural Road Damage', 0.78, 0.46, 'MEDIUM', 42.5500, 21.0000, 'Route to Lipjan', 'Roads & Infrastructure', NOW()),
(NOW(), 'Forest Fire Risk', 0.84, 0.52, 'HIGH', 42.7500, 20.9000, 'Drenas Area', 'Environment & Pollution', NOW()),
(NOW(), 'School Zone Safety', 0.73, 0.43, 'MEDIUM', 42.4000, 21.3000, 'Kamenica', 'Traffic & Parking', NOW()),
(NOW(), 'Hospital Access Road', 0.87, 0.56, 'HIGH', 42.3200, 20.6500, 'Suhareka', 'Roads & Infrastructure', NOW()),
(NOW(), 'Bus Stop Damage', 0.66, 0.36, 'MEDIUM', 42.5800, 20.8500, 'Klina', 'Public Transport & Facilities', NOW()),
(NOW(), 'Playground Safety', 0.62, 0.32, 'LOW', 42.4500, 20.9500, 'Malisheva', 'Parks & Green Spaces', NOW()),
(NOW(), 'Sidewalk Flooding', 0.76, 0.46, 'MEDIUM', 42.7200, 21.3500, 'Vushtrri', 'Water & Sewage', NOW()),
(NOW(), 'Traffic Light Malfunction', 0.81, 0.50, 'HIGH', 42.6300, 20.7000, 'Istog', 'Traffic & Parking', NOW()),
(NOW(), 'Cemetery Maintenance', 0.55, 0.26, 'LOW', 42.3500, 20.5000, 'Rahovec', 'Parks & Green Spaces', NOW()),
(NOW(), 'Market Sanitation', 0.74, 0.44, 'MEDIUM', 42.8500, 20.6000, 'Skenderaj', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Pipeline Leak', 0.90, 0.60, 'CRITICAL', 42.6000, 21.4000, 'Novo Brdo', 'Water & Sewage', NOW()),
(NOW(), 'Landslide Risk', 0.94, 0.68, 'CRITICAL', 42.7800, 20.5500, 'Decani', 'Environment & Pollution', NOW()),
(NOW(), 'Street Vendor Issue', 0.63, 0.33, 'LOW', 42.2500, 20.8000, 'Dragash', 'Traffic & Parking', NOW()),
(NOW(), 'Animal Shelter Overflow', 0.71, 0.41, 'MEDIUM', 42.5200, 21.2500, 'Gracanica', 'Animal Control', NOW()),
(NOW(), 'Sports Field Damage', 0.58, 0.28, 'LOW', 42.6800, 21.0500, 'Obiliq', 'Parks & Green Spaces', NOW());

-- Show confirmation
SELECT 'Mock reports added successfully!' AS status, 
       COUNT(*) AS total_reports,
       COUNT(CASE WHEN severity='CRITICAL' THEN 1 END) AS critical,
       COUNT(CASE WHEN severity='HIGH' THEN 1 END) AS high,
       COUNT(CASE WHEN severity='MEDIUM' THEN 1 END) AS medium,
       COUNT(CASE WHEN severity='LOW' THEN 1 END) AS low
FROM analysis_reports 
WHERE DATE(created_at) = CURDATE();

