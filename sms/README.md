# SMS Notification System

This system sends automated SMS notifications to students using Android SMS Gateway APK on your phone.

## Features

1. **Schedule Reminders** - Students receive an SMS the day before a scheduled class
2. **Grade Notifications** - Students receive an SMS when their instructor submits their grades
3. **Notifications page** - Students can read every message in the portal (Student menu -> Notifications)
4. **SMS delivery log** - Admins can see exactly what was sent, skipped or failed, and why
   (Admin menu -> SMS Notifications, which also has a test-message button)

## Setup Instructions

### Step 1: Install Android SMS Gateway APK

1. Install the SMS Gateway app on your Android phone
2. Open the app and get your **username** and **password** credentials
3. Make sure the app is running and your phone has signal

### Step 2: Configure SMS Credentials

The username and password live in `sms/function/sms-service.php`, on the two lines
marked with the big comment block near the top of the constructor:

```php
$this->username = $username ?? 'YOUR_APK_USERNAME';   // <-- YOUR APK USERNAME
$this->password = $password ?? 'YOUR_APK_PASSWORD';   // <-- YOUR APK PASSWORD
```

These are **already filled in for this project**, so there is nothing to do unless
the SMS Gateway account is changed: replace the text inside the quotes with the
**username** and **password** shown in your SMS Gateway APK (login screen, or
menu -> Settings -> Account).
There is **no .env file** - these two lines are the only credentials needed.

Just below them is the gateway address, which is already set to the cloud server
(`https://api.sms-gate.app/3rdparty/v1`). If you run the APK in **Local** mode,
change it to the phone's Wi-Fi address, e.g. `http://192.168.1.10:8080/api/3rdparty/v1`.

### Step 3: Schedule Reminders (optional cron job)

The daily "class tomorrow" reminder runs **automatically** - the dashboard starts it
once per day the first time somebody opens the system, so no cron entry is required.
It is safe to run it more often: a student is never texted twice for the same class
on the same day.

You can also trigger it by hand:

* Admin menu -> **SMS Notifications** -> *Send reminders for tomorrow*
* `php sms/cron/schedule-reminder.php`

If you want a fixed daily time instead, set up a scheduled task with one of the
options below.

#### Windows (Task Scheduler)

1. Open **Task Scheduler** (search in Start menu)
2. Click **Create Basic Task**
3. Name: "SMS Schedule Reminder"
4. Trigger: **Daily** at **6:00 PM** (or your preferred time)
5. Action: **Start a program**
6. Program/script: Browse to your `php.exe` location (e.g., `C:\xampp\php\php.exe`)
7. Add arguments: `C:\Development\Badilles\sms\cron\schedule-reminder.php`
8. Click **Finish**

#### Linux/Mac (Crontab)

1. Open terminal
2. Type: `crontab -e`
3. Add this line:
   ```
   0 18 * * * php /path/to/your/project/sms/cron/schedule-reminder.php
   ```
   (This runs daily at 6:00 PM)
4. Save and exit

### Step 4: Test the System

#### Test Schedule Reminders

Run the cron script manually to test:

```bash
cd sms/cron
php schedule-reminder.php
```

You should see output showing which students were notified.

#### Test Grade Notifications

1. Log in as an instructor
2. Go to **Grades** page
3. Select a section
4. Enter or update grades for students
5. Click **Save Grades**
6. Students with phone numbers should receive SMS notifications

## Phone Number Format

The system accepts Philippine mobile numbers in these formats:
- `9XXXXXXXXX` (10 digits starting with 9)
- `09XXXXXXXXX` (11 digits starting with 09)
- `639XXXXXXXXX` (12 digits starting with 63)
- `+639XXXXXXXXX` (13 characters with +63)

All formats are automatically converted to `+639XXXXXXXXX` for sending.

## Notification Tracking

The system creates an `sms_notifications` table in your database to track sent messages:

```sql
CREATE TABLE sms_notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    notification_type TEXT NOT NULL,  -- 'schedule_reminder' or 'grade_posted'
    reference_id INTEGER,              -- enrollment_id or grade_id
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

## Troubleshooting

### SMS not sending?

1. **Check credentials**: Make sure you've replaced the placeholder credentials in `sms-service.php`
2. **Check phone signal**: Ensure your Android phone has good cellular signal
3. **Check SMS Gateway app**: Make sure the app is running on your phone
4. **Check phone numbers**: Verify students have valid Philippine mobile numbers in the system
5. **Check logs**: Look at PHP error logs for detailed error messages

### Students not receiving notifications?

1. **Verify phone numbers**: Go to **Students** page and check if phone numbers are entered
2. **Check schedule format**: Schedule reminder checks if the schedule contains the day name (Monday, Tuesday, etc.)
3. **Run manually**: Test the cron script manually to see detailed output

### Cron job not running?

**Windows:**
- Open Task Scheduler
- Check if the task exists and is enabled
- Right-click the task and choose "Run" to test
- Check "History" tab for error messages

**Linux/Mac:**
- Check crontab: `crontab -l`
- Check cron logs: `grep CRON /var/log/syslog`

## File Structure

```
sms/
├── function/
│   ├── sms.php              # Original SMS sender (POST endpoint)
│   └── sms-service.php      # NEW: Reusable SMS service class
├── cron/
│   └── schedule-reminder.php # NEW: Daily cron job for schedule reminders
├── vendor/                   # Composer dependencies
├── composer.json
└── README.md                # This file
```

## SMS Message Examples

### Schedule Reminder
```
Hi John Doe! Reminder: You have CS101 - Programming class tomorrow.
Schedule: MWF 9:00-10:00 AM
Room: Room 301
Instructor: Jane Smith
```

### Grade Notification
```
Hi John Doe! Your grade for CS101 - Programming has been posted.
Grade: A
Score: 92.5%
Check your portal for details.
```

## Support

If you encounter any issues:

1. Check the PHP error logs
2. Verify your SMS Gateway app credentials
3. Test the SMS service manually using the test script
4. Check that students have valid phone numbers in the database

## Notes

- SMS notifications are only sent to students with valid phone numbers
- The system automatically skips students without phone numbers
- Failed SMS sends are logged but don't stop the grade saving process
- Each SMS has a small delay (0.5 seconds) to avoid overwhelming the gateway
- Schedule reminders check for classes happening the next day based on the schedule field
