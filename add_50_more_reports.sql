-- Add 50 MORE Mock Reports Across Kosovo (Total will be 100 reports)
-- Run this in phpMyAdmin or MySQL client after running add_mock_reports.sql

USE hackathondb;

-- Insert 50 additional reports with locations across Kosovo
-- These cover more rural areas, smaller towns, and fill in gaps

INSERT INTO analysis_reports 
(timestamp, top_hazard, confidence_score, rms_level, severity, latitude, longitude, address, category, created_at) 
VALUES
-- Additional Prishtina area reports (filling in suburbs)
(NOW(), 'Drainage Problem', 0.79, 0.47, 'MEDIUM', 42.6700, 21.1720, 'Prishtina - Mati 1', 'Water & Sewage', NOW()),
(NOW(), 'Street Sign Vandalized', 0.64, 0.33, 'LOW', 42.6580, 21.1600, 'Prishtina - Mati 2', 'Public Safety & Vandalism', NOW()),
(NOW(), 'Parking Lot Potholes', 0.77, 0.45, 'MEDIUM', 42.6720, 21.1740, 'Prishtina - Velania', 'Roads & Infrastructure', NOW()),
(NOW(), 'Sewer Backup', 0.91, 0.63, 'CRITICAL', 42.6550, 21.1580, 'Prishtina - Bregu i Diellit', 'Water & Sewage', NOW()),
(NOW(), 'Street Light Pole Damaged', 0.73, 0.43, 'MEDIUM', 42.6740, 21.1760, 'Prishtina - 2 Korriku', 'Street Lighting & Electricity', NOW()),

-- Lipjan area (south of Prishtina)
(NOW(), 'Road Surface Deterioration', 0.82, 0.50, 'HIGH', 42.5236, 21.1258, 'Lipjan Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Public Park Litter', 0.66, 0.36, 'MEDIUM', 42.5250, 21.1270, 'Lipjan Park', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Traffic Sign Missing', 0.69, 0.39, 'MEDIUM', 42.5220, 21.1240, 'Lipjan - Main Road', 'Traffic & Parking', NOW()),
(NOW(), 'Water Quality Concern', 0.85, 0.54, 'HIGH', 42.5240, 21.1260, 'Lipjan - Residential', 'Water & Sewage', NOW()),

-- Podujeva area (northeast of Prishtina)
(NOW(), 'Rural Road Maintenance', 0.75, 0.44, 'MEDIUM', 42.9100, 21.1930, 'Podujeva Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Street Lighting Needed', 0.71, 0.41, 'MEDIUM', 42.9110, 21.1940, 'Podujeva - New Area', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Waste Collection Issue', 0.78, 0.46, 'MEDIUM', 42.9090, 21.1920, 'Podujeva - Suburbs', 'Sanitation & Waste Management', NOW()),

-- Vushtrri area (northwest)
(NOW(), 'Bridge Inspection Needed', 0.88, 0.56, 'HIGH', 42.8231, 20.9681, 'Vushtrri Bridge', 'Roads & Infrastructure', NOW()),
(NOW(), 'Market Area Cleanup', 0.72, 0.42, 'MEDIUM', 42.8240, 20.9690, 'Vushtrri Bazaar', 'Sanitation & Waste Management', NOW()),
(NOW(), 'School Zone Safety', 0.76, 0.45, 'MEDIUM', 42.8220, 20.9670, 'Vushtrri School Area', 'Traffic & Parking', NOW()),

-- Skenderaj area (northwest)
(NOW(), 'Rural Access Road', 0.74, 0.44, 'MEDIUM', 42.7481, 20.7894, 'Skenderaj - Rural', 'Roads & Infrastructure', NOW()),
(NOW(), 'Public Well Contamination', 0.87, 0.55, 'HIGH', 42.7490, 20.7900, 'Skenderaj Village', 'Water & Sewage', NOW()),
(NOW(), 'Animal Waste Issue', 0.68, 0.38, 'MEDIUM', 42.7470, 20.7880, 'Skenderaj - Farm Area', 'Animal Control', NOW()),

-- Drenas area (central)
(NOW(), 'Industrial Zone Pollution', 0.89, 0.59, 'CRITICAL', 42.6250, 20.8930, 'Drenas Industrial', 'Environment & Pollution', NOW()),
(NOW(), 'Road Widening Needed', 0.81, 0.49, 'HIGH', 42.6260, 20.8940, 'Drenas Main Road', 'Roads & Infrastructure', NOW()),
(NOW(), 'Public Transport Stop', 0.70, 0.40, 'MEDIUM', 42.6240, 20.8920, 'Drenas Bus Station', 'Public Transport & Facilities', NOW()),

-- Malisheva area (central-west)
(NOW(), 'Sidewalk Repair Needed', 0.73, 0.43, 'MEDIUM', 42.4828, 20.7458, 'Malisheva Center', 'Roads & Infrastructure', NOW()),
(NOW(), 'Street Drainage', 0.80, 0.48, 'HIGH', 42.4830, 20.7460, 'Malisheva - Flood Area', 'Water & Sewage', NOW()),
(NOW(), 'Parking Enforcement', 0.65, 0.35, 'LOW', 42.4820, 20.7450, 'Malisheva Market', 'Traffic & Parking', NOW()),

-- Rahovec area (southwest)
(NOW(), 'Vineyard Road Access', 0.72, 0.42, 'MEDIUM', 42.3994, 20.6544, 'Rahovec - Wine Region', 'Roads & Infrastructure', NOW()),
(NOW(), 'Agricultural Waste', 0.75, 0.44, 'MEDIUM', 42.4000, 20.6550, 'Rahovec - Farm Area', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Rural Lighting', 0.67, 0.37, 'MEDIUM', 42.3980, 20.6530, 'Rahovec - Village', 'Street Lighting & Electricity', NOW()),

-- Suhareka area (south)
(NOW(), 'Hospital Access Road', 0.90, 0.60, 'CRITICAL', 42.3800, 20.8217, 'Suhareka Hospital', 'Roads & Infrastructure', NOW()),
(NOW(), 'Medical Waste Disposal', 0.86, 0.54, 'HIGH', 42.3810, 20.8220, 'Suhareka Medical Center', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Emergency Services Route', 0.88, 0.56, 'HIGH', 42.3790, 20.8210, 'Suhareka - Emergency', 'Traffic & Parking', NOW()),

-- Kamenica area (east)
(NOW(), 'Border Crossing Road', 0.84, 0.52, 'HIGH', 42.5781, 21.5800, 'Kamenica Border', 'Roads & Infrastructure', NOW()),
(NOW(), 'Customs Area Maintenance', 0.77, 0.45, 'MEDIUM', 42.5790, 21.5810, 'Kamenica Customs', 'Public Transport & Facilities', NOW()),
(NOW(), 'Cross-Border Pollution', 0.83, 0.51, 'HIGH', 42.5770, 21.5790, 'Kamenica - Border Zone', 'Environment & Pollution', NOW()),

-- Dragash area (southwest, mountainous)
(NOW(), 'Mountain Road Safety', 0.92, 0.64, 'CRITICAL', 42.0625, 20.6533, 'Dragash - Mountain Pass', 'Roads & Infrastructure', NOW()),
(NOW(), 'Avalanche Risk Area', 0.94, 0.66, 'CRITICAL', 42.0630, 20.6540, 'Dragash - High Altitude', 'Environment & Pollution', NOW()),
(NOW(), 'Winter Road Maintenance', 0.87, 0.55, 'HIGH', 42.0620, 20.6530, 'Dragash - Winter Route', 'Roads & Infrastructure', NOW()),

-- Istog area (west)
(NOW(), 'Thermal Spring Access', 0.76, 0.45, 'MEDIUM', 42.7800, 20.4878, 'Istog - Thermal Area', 'Roads & Infrastructure', NOW()),
(NOW(), 'Tourist Area Cleanup', 0.74, 0.44, 'MEDIUM', 42.7810, 20.4880, 'Istog - Tourist Zone', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Parking for Visitors', 0.69, 0.39, 'MEDIUM', 42.7790, 20.4870, 'Istog - Park Area', 'Traffic & Parking', NOW()),

-- Klina area (west)
(NOW(), 'River Access Road', 0.78, 0.46, 'MEDIUM', 42.6200, 20.5778, 'Klina - River Area', 'Roads & Infrastructure', NOW()),
(NOW(), 'Flood Prevention Needed', 0.85, 0.53, 'HIGH', 42.6210, 20.5780, 'Klina - Flood Zone', 'Water & Sewage', NOW()),
(NOW(), 'Recreational Area Safety', 0.71, 0.41, 'MEDIUM', 42.6190, 20.5770, 'Klina - Recreation', 'Parks & Green Spaces', NOW()),

-- Novo Brdo area (east)
(NOW(), 'Mining Road Access', 0.86, 0.54, 'HIGH', 42.6150, 21.4167, 'Novo Brdo - Mine Area', 'Roads & Infrastructure', NOW()),
(NOW(), 'Mining Waste Management', 0.91, 0.61, 'CRITICAL', 42.6160, 21.4170, 'Novo Brdo - Mine Site', 'Environment & Pollution', NOW()),
(NOW(), 'Worker Safety Route', 0.88, 0.56, 'HIGH', 42.6140, 21.4160, 'Novo Brdo - Industrial', 'Public Safety & Vandalism', NOW()),

-- Decani area (west)
(NOW(), 'Monastery Access Road', 0.82, 0.50, 'HIGH', 42.5400, 20.2889, 'Decani - Monastery', 'Roads & Infrastructure', NOW()),
(NOW(), 'Cultural Site Protection', 0.79, 0.47, 'MEDIUM', 42.5410, 20.2890, 'Decani - Heritage Site', 'Public Safety & Vandalism', NOW()),
(NOW(), 'Tourist Parking', 0.72, 0.42, 'MEDIUM', 42.5390, 20.2880, 'Decani - Visitor Area', 'Traffic & Parking', NOW()),

-- Gracanica area (east of Prishtina)
(NOW(), 'Monastery Road', 0.80, 0.48, 'HIGH', 42.6011, 21.1944, 'Gracanica - Monastery Road', 'Roads & Infrastructure', NOW()),
(NOW(), 'Cultural Heritage Maintenance', 0.77, 0.45, 'MEDIUM', 42.6020, 21.1950, 'Gracanica - Heritage', 'Public Safety & Vandalism', NOW()),
(NOW(), 'Residential Area Lighting', 0.73, 0.43, 'MEDIUM', 42.6000, 21.1940, 'Gracanica - Residential', 'Street Lighting & Electricity', NOW()),

-- Obiliq area (north of Prishtina)
(NOW(), 'Power Plant Access', 0.89, 0.57, 'CRITICAL', 42.6867, 21.0783, 'Obiliq - Power Plant', 'Roads & Infrastructure', NOW()),
(NOW(), 'Industrial Pollution', 0.93, 0.65, 'CRITICAL', 42.6870, 21.0790, 'Obiliq - Industrial Zone', 'Environment & Pollution', NOW()),
(NOW(), 'Worker Commute Route', 0.81, 0.49, 'HIGH', 42.6860, 21.0780, 'Obiliq - Commute Route', 'Traffic & Parking', NOW()),

-- Additional scattered rural locations
(NOW(), 'Rural Water Supply', 0.76, 0.45, 'MEDIUM', 42.4500, 20.8000, 'Rural - Central Kosovo', 'Water & Sewage', NOW()),
(NOW(), 'Farm Road Access', 0.70, 0.40, 'MEDIUM', 42.5000, 21.2000, 'Rural - Eastern Kosovo', 'Roads & Infrastructure', NOW()),
(NOW(), 'Village Lighting', 0.68, 0.38, 'MEDIUM', 42.3500, 20.6000, 'Rural - Western Kosovo', 'Street Lighting & Electricity', NOW()),
(NOW(), 'Rural Waste Collection', 0.74, 0.44, 'MEDIUM', 42.6000, 20.4000, 'Rural - Northern Kosovo', 'Sanitation & Waste Management', NOW()),
(NOW(), 'Agricultural Road', 0.72, 0.42, 'MEDIUM', 42.4000, 21.3000, 'Rural - Southern Kosovo', 'Roads & Infrastructure', NOW());

-- Show confirmation
SELECT '50 additional reports added successfully!' AS status, 
       COUNT(*) AS total_reports,
       COUNT(CASE WHEN severity='CRITICAL' THEN 1 END) AS critical,
       COUNT(CASE WHEN severity='HIGH' THEN 1 END) AS high,
       COUNT(CASE WHEN severity='MEDIUM' THEN 1 END) AS medium,
       COUNT(CASE WHEN severity='LOW' THEN 1 END) AS low
FROM analysis_reports 
WHERE latitude IS NOT NULL 
AND longitude IS NOT NULL
AND latitude != 0 
AND longitude != 0;

