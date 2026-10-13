# SMS Notification Setup Guide

## Quick Start

This guide will help you set up SMS notifications for:
1. **Class schedule reminders** (sent 1 day before class)
2. **Grade notifications** (sent when instructor submits grades)

---

## Step 1: Get Your SMS Gateway Credentials

1. Open the **Android SMS Gateway** app on your phone
2. Find your **username** and **password** in the app settings
3. Keep this information ready - you'll need it in the next step

---

## Step 2: Configure Your Credentials

**Edit this file:** `sms/function/sms-service.php`

Find these lines (around line 34-35):

```php
$this->username = $username ?? 'YOUR_SMS_GATEWAY_USERNAME'; // <- PUT YOUR USERNAME HERE
$this->password = $password ?? 'YOUR_SMS_GATEWAY_PASSWORD'; // <- PUT YOUR PASSWORD HERE
```

**Replace with your actual credentials:**

```php
$this->username = $username ?? 'john_doe_123';        // <- Your actual username
$this->password = $password ?? 'my_password_456';     // <- Your actual password
```

**Save the file.**

---

## Step 3: Test Your Setup

Open a terminal/command prompt in your project folder and run:

```bash
php sms/test-sms.php
```

This will check if your credentials are configured correctly.

### Send a Test SMS (Optional)

1. Open `sms/test-sms.php`
2. Find the commented section at the bottom (around line 49)
3. Uncomment the code and add your phone number:

```php
$testPhoneNumber = '9171234567'; // <- Your phone number
```

4. Run the test again:

```bash
php sms/test-sms.php
```

5. Check your phone - you should receive a test SMS!

---

## Step 4: Set Up Automatic Schedule Reminders

### Option A: Windows (Easiest)

1. Right-click `sms/cron/setup-windows-task.bat`
2. Select **"Run as administrator"**
3. Follow the prompts
4. Done! The system will now send reminders daily at 6:00 PM

### Option B: Windows Manual Setup

1. Open **Task Scheduler** (search in Start menu)
2. Click **"Create Basic Task"**
3. Name: `SMS Schedule Reminder`
4. Trigger: **Daily** at **6:00 PM**
5. Action: **Start a program**
   - Program: `C:\xampp\php\php.exe` (or your PHP path)
   - Arguments: `C:\Development\Badilles\sms\cron\schedule-reminder.php`
6. Click **Finish**

### Option C: Linux/Mac

1. Open terminal
2. Type: `crontab -e`
3. Add this line:
   ```
   0 18 * * * php /path/to/your/project/sms/cron/schedule-reminder.php
   ```
4. Save and exit

---

## Step 5: Verify It's Working

### Test Schedule Reminders Manually

Run the cron script directly to see what it does:

```bash
php sms/cron/schedule-reminder.php
```

You'll see output showing:
- How many students have classes tomorrow
- Which students received SMS
- Any errors that occurred

### Test Grade Notifications

1. Log in as an instructor
2. Go to the **Grades** page
3. Select a section
4. Enter or update grades for students who have phone numbers
5. Click **"Save Grades"**
6. Those students should receive SMS notifications

---

## Important Notes

### Phone Number Format

Students must have phone numbers in one of these formats:
- `9171234567` (10 digits starting with 9)
- `09171234567` (starts with 09)
- `639171234567` (starts with 63)
- `+639171234567` (starts with +63)

The system automatically converts all formats to the correct format for sending.

### Adding Phone Numbers to Students

1. Go to **Students** page
2. Click **Edit** on a student
3. Enter their mobile number in the **Phone** field
4. Save

---

## Troubleshooting

### "SMS Gateway credentials not configured"

- You didn't update the credentials in `sms/function/sms-service.php`
- Go back to **Step 2** above

### "Failed to send SMS"

**Check these:**
1. ✓ Is your Android phone on and has signal?
2. ✓ Is the SMS Gateway app running on your phone?
3. ✓ Are your credentials correct in `sms-service.php`?
4. ✓ Does the student have a valid Philippine mobile number?

### "No SMS sent"

**For Schedule Reminders:**
- The cron runs daily and only sends SMS to students who have class **tomorrow**
- Check that the schedule field contains the day name (e.g., "Monday", "MWF", "TTh")
- Run manually to see detailed output: `php sms/cron/schedule-reminder.php`

**For Grade Notifications:**
- Only students with phone numbers receive SMS
- SMS is only sent when you save grades with actual scores entered
- Check the flash message after saving - it will tell you how many SMS were sent

### Windows Task Not Running

1. Open **Task Scheduler**
2. Find **"SMS Schedule Reminder"** in the list
3. Right-click and choose **"Run"** to test it
4. Check the **"History"** tab for errors
5. Make sure the task is **Enabled**

---

## What Happens Automatically

Once set up:

1. **Every day at 6:00 PM**, the system:
   - Checks tomorrow's schedule
   - Finds students with classes tomorrow
   - Sends SMS reminders to those students

2. **When instructors save grades**, the system:
   - Calculates final grades and scores
   - Sends SMS to each student who received a grade
   - Shows confirmation message with count of SMS sent

---

## Need Help?

Check the detailed documentation: `sms/README.md`

Look at the example messages the system sends:

**Schedule Reminder:**
```
Hi John Doe! Reminder: You have CS101 - Programming class tomorrow.
Schedule: MWF 9:00-10:00 AM
Room: Room 301
Instructor: Jane Smith
```

**Grade Notification:**
```
Hi John Doe! Your grade for CS101 - Programming has been posted.
Grade: A
Score: 92.5%
Check your portal for details.
```

---

## Summary Checklist

- [ ] Got SMS Gateway app credentials
- [ ] Updated credentials in `sms/function/sms-service.php`
- [ ] Tested with `php sms/test-sms.php`
- [ ] Set up Windows Task Scheduler (or cron for Linux/Mac)
- [ ] Students have phone numbers in the system
- [ ] SMS Gateway app is running on your phone

**That's it! Your SMS notification system is ready to go!** 🎉
