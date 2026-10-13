# SMS Notification System

This system sends automated SMS notifications to students using Android SMS Gateway APK on your phone.

## Features

1. **Schedule Reminders** - Students receive SMS notifications 1 day before their scheduled classes
2. **Grade Notifications** - Students receive SMS when instructors submit their grades

## Setup Instructions

### Step 1: Install Android SMS Gateway APK

1. Install the SMS Gateway app on your Android phone
2. Open the app and get your **username** and **password** credentials
3. Make sure the app is running and your phone has signal

### Step 2: Configure SMS Credentials

Open `sms/function/sms-service.php` and find these lines (around line 34-35):

```php
$this->username = $username ?? 'YOUR_SMS_GATEWAY_USERNAME'; // <- PUT YOUR USERNAME HERE
$this->password = $password ?? 'YOUR_SMS_GATEWAY_PASSWORD'; // <- PUT YOUR PASSWORD HERE
```

Replace `YOUR_SMS_GATEWAY_USERNAME` and `YOUR_SMS_GATEWAY_PASSWORD` with your actual credentials from the SMS Gateway app.

**Example:**
```php
$this->username = $username ?? 'john_doe_123';
$this->password = $password ?? 'my_secure_password_456';
```

### Step 3: Set Up Schedule Reminder Cron Job

The schedule reminder needs to run automatically every day. Choose your operating system:

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
