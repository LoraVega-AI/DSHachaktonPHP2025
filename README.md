Urban Pulse - CityCare Theme under PHP MYSQL

This project is a reporting website for all types of categories (Roads & Infrastructure, Animal Control, traffic & parking, etc.). Besides the advanced reporting feautures, which you can give a general report with title, description and location, with either an audio or image attached to the report.
You have the image analysis and audio analysis reporting =>

Audio analysis works with either uploading a audio file by clicking the upload file button, uploading a audio file which gets 64base encoded, goes through an audio pipeline and ends with a report by the LLM. Or you can click start analysis which gets the live audio feed from your device, and goes through the same 64base encoding and pipeline as the uploaded audio file!
You can click make a report, then the LLM will automatally create the report with your current GPS location (Its functional, but can be slow. A few seconds to 15s.), or you can input yourself.

Image analysis, you can either upload an Image by clicking the upload file button, the LLM analyzes it and gives a report. Or you can click start camera analysis which for 10 seconds picks up frames, sends them to the LLM which analyzes it then gives a report.
You can click make a report, then the LLM will automatally create the report with your current GPS location (Its functional, but can be slow. A few seconds to 15s.), or you can input yourself.

The final report incldues A title, description, final conclusion, risk assesment (severity, is it a problem, should be investigated), and steps you should take. For the image and audio analysis.

the map shows citizen reports as color-coded pins (red for critical, orange for high, etc.) with popups showing details, photos, and trust scores, plus filters, a "hot zone" heatmap, stats panel, and timeline views for tracking report status.
  These features are for registered users and guests.

But Registered users have 2 things which guests dont. Detection history, which has all their personal reports saved in the MYSQL Database. and Rankings, showing the rankings of critical, recent or pending reports.

To login as registered user. Username = User1, Password = user123 (or use the demo accounts section in the login page to automatically login)

Registered users also have a settings and profile tab.

Admins in UrbanPulse can oversee everything - they monitor system stats, manage crew members and user accounts, review all detection history, mark reports as solved or verified with photos, and access the map with full control over report statuses. They handle crew management by creating accounts, assigning tasks, and tracking member performance, plus they get detailed analytics about user engagement and report trends. Basically, they're the full system operators with complete access to manage the entire urban monitoring platform. 

To login as admin. Username = admin, password = admin123  (or use the demo accounts section in the login page to automatically login)

For the crew portal, which acts as a company portal which is contracted to deal with the reports. 

**Crew managers** use the dashboard to oversee their team - they view crew stats, assign high-priority reports to members, and track who's available for work. **Individual crew members** have their own dashboard showing only their assigned tasks, where they can mark jobs as solved and filter between active work and completed history. Basically, managers handle the big picture while workers focus on getting their tasks done.

For crew manager to login as Username = crew_demo /  password = crew123
for crew memebr. Username = alex_tech, password = alex123





Technologies used =

Core Web Technologies
HTML5, CSS3, JavaScript
PHP, MySQL
Mapping & UI
Leaflet.js (maps)
Font Awesome (icons)
Google Fonts
AI/ML Technologies
TensorFlow.js (browser ML)
YAMNet (audio classification)
Meyda.js (audio features)
Groq API (LLM analysis)
Audio Processing
Web Audio API
MFCC, FFT, RMS analysis
Infrastructure
XAMPP (local dev)
CDN delivery (jsDelivr, UNPKG)
Session authentication

 
set up =
 create a .env file and copy and paste this into it,   

GROQ_API_KEY=API_KEY         (create ur own groq api key, its free as im unable to share mine :>)

define('DB_HOST', 'localhost');
define('DB_PORT', '3307'); 
define('DB_NAME', 'hackathondb');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP MySQL password (empty)
 


