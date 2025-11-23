# Crew Demo Accounts

## Quick Access

Run `create_demo_accounts.php` in your browser or via command line to create all demo accounts including crew members.

## Crew Demo Accounts Created

### 1. crew_demo (Main Demo Account)
- **Username**: `crew_demo`
- **Email**: `crew@urbanpulse.demo`
- **Password**: `crew123`
- **Role**: Crew
- **Trust Score**: 3.5 (Experienced)
- **Schedule**: Monday-Friday, 8:00 AM - 5:00 PM

### 2. alex_tech (Senior Crew Member)
- **Username**: `alex_tech`
- **Email**: `alex@urbanpulse.demo`
- **Password**: `alex123`
- **Role**: Crew
- **Trust Score**: 4.2 (Senior)
- **Schedule**: Monday-Friday, 8:00 AM - 5:00 PM

### 3. maria_field (Experienced Crew Member)
- **Username**: `maria_field`
- **Email**: `maria@urbanpulse.demo`
- **Password**: `maria123`
- **Role**: Crew
- **Trust Score**: 3.8 (Experienced)
- **Schedule**: Monday-Friday, 8:00 AM - 5:00 PM

### 4. david_repair (Mid-Level Crew Member)
- **Username**: `david_repair`
- **Email**: `david@urbanpulse.demo`
- **Password**: `david123`
- **Role**: Crew
- **Trust Score**: 2.9 (Mid-Level)
- **Schedule**: Monday-Friday, 8:00 AM - 5:00 PM

### 5. lisa_crew (New Crew Member)
- **Username**: `lisa_crew`
- **Email**: `lisa@urbanpulse.demo`
- **Password**: `lisa123`
- **Role**: Crew
- **Trust Score**: 1.5 (New)
- **Schedule**: Monday-Friday, 8:00 AM - 5:00 PM

## Schedule System

### Default Schedule
All crew members are created with a default schedule:
- **Monday - Friday**: 8:00 AM - 5:00 PM
- **Saturday - Sunday**: No schedule (unavailable)

### Managing Schedules

#### View Schedule
```
GET get_crew_availability.php?user_id=X&weekly=true
```

#### Update Schedule
```
POST manage_crew_schedule.php
{
  "user_id": 1,
  "day_of_week": 1,  // 0=Sunday, 1=Monday, ..., 6=Saturday
  "start_time": "08:00:00",
  "end_time": "17:00:00",
  "is_available": true
}
```

#### Bulk Update (Set Entire Week)
```
PUT manage_crew_schedule.php
{
  "user_id": 1,
  "schedule": [
    {"day_of_week": 1, "start_time": "08:00:00", "end_time": "17:00:00", "is_available": true},
    {"day_of_week": 2, "start_time": "08:00:00", "end_time": "17:00:00", "is_available": true},
    // ... etc
  ]
}
```

## Availability Status

When viewing crew members via `get_crew_members.php`, each member includes:

```json
{
  "id": 1,
  "username": "crew_demo",
  "is_available": true,
  "availability_status": "available",
  "availability_message": "Currently available",
  "working_hours": {
    "start": "08:00:00",
    "end": "17:00:00"
  },
  "active_assignments": 2
}
```

### Availability Statuses:
- **available**: Currently within working hours and marked available
- **off_hours**: Outside scheduled working hours
- **unavailable**: Marked as unavailable for today
- **no_schedule**: No schedule set for today

## Testing the Crew Dashboard

1. **Login as crew_demo**:
   - Go to `login.html`
   - Username: `crew_demo`
   - Password: `crew123`

2. **Access Crew Dashboard**:
   - Navigate to `crew_dashboard.html`
   - View assigned reports
   - See available high-priority reports

3. **Admin Assignment**:
   - Login as `admin` / `admin123`
   - View reports and assign to crew members
   - Check availability status before assigning

## API Endpoints for Schedule

### Get Crew Availability
```
GET get_crew_availability.php?user_id=X
```
Returns current availability status.

### Get Weekly Schedule
```
GET get_crew_availability.php?user_id=X&weekly=true
```
Returns full weekly schedule.

### Manage Schedule
```
GET/POST/PUT/DELETE manage_crew_schedule.php
```
Full CRUD operations for crew schedules.

## Example: Check if Crew Member is Available

```javascript
// Check availability before assignment
fetch('get_crew_availability.php?user_id=1')
  .then(res => res.json())
  .then(data => {
    if (data.is_available) {
      console.log('Crew member is available!');
      // Proceed with assignment
    } else {
      console.log('Crew member is not available:', data.message);
    }
  });
```

## Example: Get All Crew with Availability

```javascript
// Get all crew members with availability status
fetch('get_crew_members.php')
  .then(res => res.json())
  .then(data => {
    data.crew_members.forEach(member => {
      console.log(`${member.username}: ${member.is_available ? 'Available' : 'Not Available'}`);
      console.log(`  Status: ${member.availability_status}`);
      console.log(`  Active Assignments: ${member.active_assignments}`);
    });
  });
```

