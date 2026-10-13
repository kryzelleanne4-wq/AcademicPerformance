# SMS Notification System - Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    SMS NOTIFICATION SYSTEM                      │
└─────────────────────────────────────────────────────────────────┘

                     ┌──────────────────┐
                     │   Android Phone  │
                     │  (SMS Gateway)   │
                     └────────┬─────────┘
                              │
                              │ HTTP API
                              │
                     ┌────────▼─────────┐
                     │  SMS Service     │
                     │  (sms-service.php)│
                     └────────┬─────────┘
                              │
                ┌─────────────┴─────────────┐
                │                           │
       ┌────────▼─────────┐        ┌───────▼────────┐
       │  Schedule         │        │  Grade         │
       │  Reminder Cron    │        │  Notification  │
       │  (daily at 6 PM)  │        │  (on save)     │
       └────────┬──────────┘        └───────┬────────┘
                │                           │
                │                           │
                └─────────────┬─────────────┘
                              │
                     ┌────────▼─────────┐
                     │   Database       │
                     │   (SQLite)       │
                     │                  │
                     │ - students       │
                     │ - enrollments    │
                     │ - course_sections│
                     │ - grades         │
                     │ - sms_notifications│
                     └──────────────────┘
```

## Component Details

### 1. SMS Service Layer (`sms/function/sms-service.php`)

**Responsibilities:**
- Encapsulates SMS Gateway API communication
- Formats Philippine phone numbers
- Provides helper methods for different notification types
- Handles errors and retries

**Key Methods:**
```php
- sendSMS($phone, $message)           // Send single SMS
- sendBulkSMS($recipients, $message)  // Send to multiple recipients
- sendScheduleReminder(...)           // Schedule-specific message
- sendGradeNotification(...)          // Grade-specific message
- formatPhoneNumber($phone)           // Normalize phone format
```

**Configuration:**
- Username and password for SMS Gateway APK
- Located at lines 34-35

---

### 2. Schedule Reminder System

#### Cron Job (`sms/cron/schedule-reminder.php`)

**Trigger:** Daily at 6:00 PM (configurable)

**Process Flow:**
```
1. Calculate tomorrow's date
2. Query active enrollments
3. For each enrollment:
   ├─ Parse schedule string
   ├─ Check if schedule contains tomorrow's day
   ├─ If yes: Get student phone number
   └─ Send reminder SMS
4. Log notifications to database
5. Output summary statistics
```

**Schedule Parsing:**
- Detects full day names (Monday, Tuesday, etc.)
- Detects abbreviations (M, T, W, Th, F, S)
- Handles common formats: "MWF", "TTh", "Monday/Wednesday"

**Windows Setup:**
```
Task Scheduler → Daily Task
Program: php.exe
Arguments: C:\Development\Badilles\sms\cron\schedule-reminder.php
Time: 6:00 PM
```

**Linux/Mac Setup:**
```bash
crontab -e
0 18 * * * php /path/to/sms/cron/schedule-reminder.php
```

---

### 3. Grade Notification System

#### Integration (`pages/grades.php`)

**Trigger:** When instructor saves score sheet

**Process Flow:**
```
1. Instructor submits grade form
2. System saves grades to database
3. Database transaction commits
4. For each student with new grade:
   ├─ Check if student has phone number
   ├─ Get subject and grade information
   ├─ Send grade notification SMS
   └─ Log notification
5. Display summary (X grades saved, Y SMS sent)
```

**Integration Point:**
- After successful grade save (around line 120)
- Before redirect back to grades page
- Errors don't prevent grade saving

---

### 4. Admin Dashboard (`pages/sms-notifications.php`)

**Features:**

**Statistics Cards:**
- Total notifications sent
- Sent today
- Schedule reminders count
- Grade notifications count

**Test Panel:**
- Send test SMS to any number
- Verify system is working
- Debug connectivity issues

**Actions:**
- Manually trigger schedule reminder
- View setup instructions
- Clear notification logs

**Recent Notifications Table:**
- Last 50 notifications
- Student name and ID
- Notification type
- Timestamp

---

### 5. Database Schema

#### `sms_notifications` Table

```sql
CREATE TABLE sms_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    notification_type TEXT NOT NULL,  -- 'schedule_reminder' or 'grade_posted'
    reference_id INTEGER,              -- enrollment_id or grade_id
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

**Purpose:**
- Track all sent notifications
- Prevent duplicate sends
- Audit trail
- Statistics and reporting

---

## Data Flow Diagrams

### Schedule Reminder Flow

```
┌──────────────┐
│  Cron Job    │  (6:00 PM Daily)
└──────┬───────┘
       │
       ▼
┌──────────────────────────────────────┐
│ Query Database                       │
│ - Get active enrollments             │
│ - Filter by current term             │
│ - Include student phone numbers      │
└──────────────┬───────────────────────┘
               │
               ▼
       ┌───────────────┐
       │ For Each      │
       │ Enrollment    │
       └───────┬───────┘
               │
               ▼
       ┌───────────────────┐
       │ Parse Schedule    │
       │ Check Day Match   │
       └───────┬───────────┘
               │
        ┌──────┴──────┐
        │             │
    ┌───▼────┐   ┌───▼────┐
    │ Match  │   │ No     │
    │ Found  │   │ Match  │
    └───┬────┘   └───┬────┘
        │            │
        ▼            ▼
   ┌────────┐   ┌────────┐
   │ Send   │   │ Skip   │
   │ SMS    │   │        │
   └───┬────┘   └────────┘
       │
       ▼
   ┌────────────┐
   │ Log to DB  │
   └────────────┘
```

### Grade Notification Flow

```
┌──────────────────┐
│ Instructor Saves │
│ Grade Sheet      │
└────────┬─────────┘
         │
         ▼
┌────────────────────┐
│ Save to Database   │
│ - grades table     │
│ - grade_scores     │
└────────┬───────────┘
         │
         ▼
┌──────────────────────────┐
│ Transaction Committed    │
└────────┬─────────────────┘
         │
         ▼
 ┌───────────────────┐
 │ For Each Student  │
 │ With New Grade    │
 └────────┬──────────┘
          │
   ┌──────┴──────┐
   │             │
┌──▼─────────┐ ┌─▼──────────┐
│ Has Phone  │ │ No Phone   │
└──┬─────────┘ └─┬──────────┘
   │             │
   ▼             ▼
┌──────────┐  ┌──────────┐
│ Send SMS │  │ Skip     │
└──┬───────┘  └──────────┘
   │
   ▼
┌──────────┐
│ Log to DB│
└──────────┘
   │
   ▼
┌─────────────────┐
│ Show Flash Msg  │
│ "X SMS sent"    │
└─────────────────┘
```

---

## Security Considerations

### Credentials Storage
- **Current:** Hardcoded in PHP file
- **Location:** `sms/function/sms-service.php`
- **Protection:** File permissions (not web-accessible)
- **Note:** No .env used per user requirement

### Phone Number Privacy
- Phone numbers stored in database
- Not exposed in URLs or client-side code
- Only accessible to admin/instructor roles

### SMS Gateway Authentication
- Username/password authentication
- Credentials sent via HTTPS to SMS Gateway API
- API provided by Android SMS Gateway library

---

## Error Handling

### SMS Send Failures
- Caught and logged
- Don't block grade saving process
- Error message stored in logs
- Admin can view failed attempts

### Missing Phone Numbers
- Students without phones automatically skipped
- No error thrown
- Warning displayed in admin dashboard

### Schedule Parsing Failures
- Unknown schedule formats skipped
- Logged for review
- Manual trigger available for edge cases

---

## Performance Considerations

### Bulk Sending
- 0.5 second delay between messages
- Prevents gateway overload
- Configurable in code

### Database Queries
- Optimized for active enrollments only
- Indexes on frequently queried columns
- Minimal database load

### Grade Save Performance
- SMS sending happens after commit
- Async operation (doesn't block user)
- Failed SMS doesn't rollback grades

---

## Maintenance & Monitoring

### Daily Checks
- Verify cron/task is running
- Check phone has signal
- Review notification logs

### Weekly Checks
- Review failed notifications
- Verify student phone numbers
- Clean old logs if needed

### Monthly Checks
- Verify SMS Gateway credentials still valid
- Check phone storage/battery
- Review and update schedule parsing rules

---

## Troubleshooting Guide

### No SMS Received

**Check:**
1. SMS Gateway app running? → Restart app
2. Phone has signal? → Check connectivity
3. Credentials correct? → Update sms-service.php
4. Phone number valid? → Check format (9XXXXXXXXX)

### Schedule Reminders Not Sent

**Check:**
1. Cron/task active? → Task Scheduler / crontab -l
2. Students have classes tomorrow? → Run manually to test
3. Schedule format recognized? → Check schedule parsing logic
4. Students have phone numbers? → Review student records

### Grade Notifications Not Sent

**Check:**
1. Integration code present? → Review grades.php
2. Students have phone numbers? → Check student profiles
3. SMS service loaded? → Check require_once path
4. PHP errors? → Review error logs

---

## Future Enhancements

### Possible Additions
- [ ] SMS delivery status tracking
- [ ] Retry failed messages
- [ ] Custom message templates
- [ ] Parent/guardian notifications
- [ ] Absence alerts
- [ ] Payment reminders
- [ ] Event announcements

### Infrastructure Improvements
- [ ] Move credentials to config file
- [ ] Add SMS sending queue
- [ ] Implement rate limiting
- [ ] Add notification preferences per student
- [ ] Multi-language support

---

## API Documentation

### SMS Gateway PHP Client

**Library:** `capcom6/android-sms-gateway`

**Installation:**
```bash
composer require capcom6/android-sms-gateway
```

**Basic Usage:**
```php
use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;

$client = new Client($username, $password);
$message = new Message($text, [$phoneNumber]);
$state = $client->Send($message);
```

**Phone Format:** +639XXXXXXXXX (E.164 format)

---

## Conclusion

This architecture provides:
- ✅ Automated schedule reminders
- ✅ Real-time grade notifications
- ✅ Admin monitoring and testing
- ✅ Error handling and logging
- ✅ Easy maintenance and troubleshooting

The system is production-ready and requires minimal ongoing maintenance beyond keeping the SMS Gateway app running on the Android phone.
