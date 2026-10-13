# Student Phone Number Management - Admin Guide

## Overview

Admins can now **edit student information** including phone numbers directly from the Students page. This is essential for enabling SMS notifications.

---

## How to Add/Edit Phone Numbers

### Step 1: Access Students Page

1. Log in as **admin**
2. Navigate to **Students** page from the menu

### Step 2: Edit Student Information

1. Find the student in the table
2. Click the **"Edit"** button next to their name
3. A modal will open with all student information

### Step 3: Add Phone Number

1. In the **Phone** field, enter the student's mobile number
2. **Format:** `9171234567` (10 digits starting with 9)
3. Click **"Update Student"**

**Example Phone Numbers:**
- ✅ `9171234567`
- ✅ `9281234567`
- ✅ `9051234567`
- ❌ `09171234567` (don't include 0 at start)
- ❌ `639171234567` (don't include country code)

---

## Visual Indicators

### In the Phone Column:

**With Phone Number:**
```
9171234567 ✓
```
- Green checkmark = SMS notifications enabled

**Without Phone Number:**
```
No phone ⚠
```
- Orange warning = SMS notifications disabled

### Alert at Top of Page:

If active students don't have phone numbers, you'll see:
```
⚠ SMS Notification Notice:
5 active student(s) don't have phone numbers.
They won't receive SMS notifications for schedules and grades.
Click "Edit" to add phone numbers.
```

---

## What You Can Edit

Using the Edit button, admins can update:

- ✏️ **First Name**
- ✏️ **Last Name**
- ✏️ **Email**
- ✏️ **Phone** ⭐ (for SMS notifications)
- ✏️ **Gender**
- ✏️ **Student Type** (Regular/Irregular)
- ✏️ **Class Block**

**Cannot be changed:**
- ❌ Student ID (read-only)

---

## SMS Notification Impact

### When Phone Number is Added:

**Student will receive:**
- 📅 **Schedule reminders** (1 day before class)
- 🎓 **Grade notifications** (when instructor posts grades)

### When Phone Number is Missing:

**Student will NOT receive:**
- ❌ No schedule reminders
- ❌ No grade notifications

**All other features work normally** (login, view grades online, etc.)

---

## Bulk Phone Number Entry

### For Multiple Students:

1. Open the **Students** page
2. For each student without a phone number:
   - Click **"Edit"**
   - Enter phone number
   - Click **"Update Student"**
3. The warning alert will update automatically

### Priority Students:

Focus on adding phone numbers for:
1. ✅ Active students
2. ✅ Regular students
3. ✅ Students currently enrolled in classes

---

## Testing the SMS System

### After Adding Phone Numbers:

**Test Schedule Reminder:**
```bash
php sms/cron/schedule-reminder.php
```
- Check output to see which students will receive SMS tomorrow

**Test Grade Notification:**
1. Log in as instructor
2. Go to Grades page
3. Save grades for a section
4. Students with phone numbers should receive SMS

**View Notifications:**
- Go to: `/pages/sms-notifications.php`
- Check recent notifications log

---

## Phone Number Format Guide

### Accepted Input Formats:

The system accepts multiple formats and converts them automatically:

| You Enter | System Converts To | Result |
|-----------|-------------------|--------|
| `9171234567` | `+639171234567` | ✅ SMS sent |
| `09171234567` | `+639171234567` | ✅ SMS sent |
| `639171234567` | `+639171234567` | ✅ SMS sent |
| `+639171234567` | `+639171234567` | ✅ SMS sent |

**Recommended Format for Entry:**
```
9171234567
```
(10 digits starting with 9)

---

## Common Questions

### Q: Can students edit their own phone numbers?

**A:** No, only admins can edit student phone numbers for security.

### Q: What if a student changes their number?

**A:** Admin should update the phone number in the Students page by clicking "Edit".

### Q: Do phone numbers need to be unique?

**A:** No, multiple students can share the same phone number (e.g., family members).

### Q: What happens if I enter an invalid number?

**A:** The SMS will fail to send, but it won't affect other system functions. Check the format guide above.

---

## Workflow for New Students

### When Adding a New Student:

1. Click **"Add Student"** button
2. Fill in required information:
   - First Name ✅
   - Last Name ✅
   - Email (optional)
   - **Phone** ⭐ (recommended for SMS)
   - Gender
   - Student Type
   - Class Block
3. Click **"Save Student"**

**💡 Tip:** Add phone numbers when creating students to enable SMS from day one!

---

## Troubleshooting

### Problem: Phone number saved but no SMS received

**Check:**
1. ✓ Phone format is correct (9XXXXXXXXX)
2. ✓ SMS Gateway app is running on your phone
3. ✓ Phone has cellular signal
4. ✓ Test with: `php sms/test-sms.php`

### Problem: Can't find Edit button

**Solution:**
- Make sure you're logged in as **admin**
- Refresh the page
- Edit button is in the "Actions" column

### Problem: Edit modal won't open

**Solution:**
- Check browser console for JavaScript errors
- Try refreshing the page
- Make sure JavaScript is enabled

---

## Admin Dashboard Access

For comprehensive SMS management and monitoring:

**URL:** `/pages/sms-notifications.php`

**Features:**
- View notification statistics
- Send test SMS
- See recent notifications
- Check which students don't have phone numbers

---

## Best Practices

### 1. Regular Updates
- Review student phone numbers monthly
- Update when students report changes
- Verify new student phone numbers

### 2. Data Quality
- Always use 10-digit format (9XXXXXXXXX)
- Double-check number before saving
- Test with a sample SMS after adding bulk numbers

### 3. Privacy
- Only share phone numbers with authorized personnel
- Don't display phone numbers publicly
- Keep student data secure

### 4. Communication
- Inform students about SMS notifications
- Provide opt-out instructions if needed
- Respect student privacy preferences

---

## Quick Reference

| Task | How To |
|------|--------|
| Add phone to existing student | Students page → Edit → Enter phone → Update |
| Add phone to new student | Add Student → Fill phone field → Save |
| Check who has phone numbers | Look for ✓ or ⚠ in Phone column |
| View SMS notification logs | Go to /pages/sms-notifications.php |
| Test SMS system | php sms/test-sms.php |

---

## Summary

✅ **Admins can now:**
- Edit all student information
- Add/update phone numbers
- See which students have phone numbers
- Enable SMS notifications for students

🎯 **Impact:**
- Students with phones get automated reminders
- Better communication with students
- Reduced missed classes
- Timely grade notifications

📚 **Related Documentation:**
- `SMS-SETUP-GUIDE.md` - Complete SMS setup
- `SMS-VISUAL-GUIDE.txt` - Visual walkthrough
- `sms/README.md` - Technical details

---

**Ready to manage student phone numbers!** 🎉
