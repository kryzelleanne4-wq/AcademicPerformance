<?php
/**
 * SMS Testing Script
 * 
 * Use this script to test your SMS Gateway configuration
 * 
 * Usage:
 * php sms/test-sms.php
 */

require_once __DIR__ . '/function/sms-service.php';

echo "=== SMS Gateway Test Script ===\n\n";

// Check if credentials are configured
$smsService = getSMSService();

echo "1. Checking SMS Gateway configuration...\n";

// Test phone number formatting
echo "\n2. Testing phone number formatting...\n";
$testNumbers = [
    '9171234567',
    '09171234567',
    '639171234567',
    '+639171234567'
];

$reflection = new ReflectionClass($smsService);
$method = $reflection->getMethod('formatPhoneNumber');
$method->setAccessible(true);

foreach ($testNumbers as $num) {
    $formatted = $method->invoke($smsService, $num);
    echo "  {$num} -> {$formatted}\n";
}

echo "\n3. Ready to send test SMS\n";
echo "   To send a test message, uncomment the test code below and add your phone number.\n\n";

// ============================================
// UNCOMMENT THE LINES BELOW TO SEND TEST SMS
// ============================================
/*
$testPhoneNumber = '9171234567'; // <- PUT YOUR PHONE NUMBER HERE (format: 9XXXXXXXXX)
$testMessage = 'Test message from School Management System. If you receive this, SMS notifications are working!';

echo "Sending test SMS to {$testPhoneNumber}...\n";
$result = $smsService->sendSMS($testPhoneNumber, $testMessage);

if ($result['success']) {
    echo "✓ SUCCESS! SMS sent successfully.\n";
    echo "  Message ID: {$result['message_id']}\n";
    echo "  Check your phone for the test message.\n";
} else {
    echo "✗ FAILED: {$result['message']}\n";
    echo "  Please check:\n";
    echo "  1. Your SMS Gateway app credentials in sms/function/sms-service.php\n";
    echo "  2. Your Android phone has signal and the SMS Gateway app is running\n";
    echo "  3. The phone number format is correct (Philippine mobile number)\n";
}
*/

echo "\n=== Test Schedule Notification ===\n";
echo "To test schedule reminders:\n";
echo "  php sms/cron/schedule-reminder.php\n\n";

echo "=== Test Grade Notification ===\n";
echo "To test grade notifications:\n";
echo "  1. Log in as an instructor\n";
echo "  2. Go to the Grades page\n";
echo "  3. Select a section and enter grades\n";
echo "  4. Students with phone numbers will receive SMS\n\n";

echo "=== Configuration Check ===\n";

// Check if credentials look configured
$testService = new SMSService();
$reflectionProp = new ReflectionClass($testService);
$usernameProp = $reflectionProp->getProperty('username');
$usernameProp->setAccessible(true);
$username = $usernameProp->getValue($testService);

if ($username === 'YOUR_SMS_GATEWAY_USERNAME') {
    echo "⚠ WARNING: SMS Gateway credentials not configured!\n";
    echo "  Please edit sms/function/sms-service.php and add your credentials.\n";
    echo "  Look for lines 34-35 and replace the placeholder values.\n";
} else {
    echo "✓ SMS Gateway credentials appear to be configured.\n";
    echo "  Username: {$username}\n";
}

echo "\n===============================\n";
