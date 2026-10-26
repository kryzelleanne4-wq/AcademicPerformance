<?php
/**
 * SMS Notification Service
 * Reusable service for sending SMS notifications to students.
 *
 * Messages are delivered through the Android SMS Gateway APK that runs on your
 * own phone. The phone must be switched on, online, and signed in to the same
 * SMS Gateway account as the username/password below.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;

class SMSService {
    private $username;
    private $password;
    private $baseUrl;
    private $client;

    /**
     * Initialize SMS Service
     *
     * @param string|null $username Your SMS Gateway APK username
     * @param string|null $password Your SMS Gateway APK password
     * @param string|null $baseUrl  Override the gateway API address
     */
    public function __construct($username = null, $password = null, $baseUrl = null) {
        // ==================================================================
        //   >>> PUT YOUR SMS GATEWAY APK USERNAME AND PASSWORD HERE <<<
        // ------------------------------------------------------------------
        //  1. Open the "SMS Gateway" APK on your phone.
        //  2. Find "Username" and "Password":
        //       - the app's login screen shows them, or
        //       - app menu -> Settings -> Account, or
        //       - https://sms-gate.app -> Devices (after signing in).
        //  3. Paste them inside the quotes on the two lines below.
        //
        //  These are the ONLY two values the system needs. There is no .env
        //  file - just edit this file and save it.
        //  The phone has to stay online and signed in for texts to go out.
        // ==================================================================
        $this->username = $username ?? 'GTDE5A';            // <-- YOUR APK USERNAME
        $this->password = $password ?? 'd_94hhwzcwngkm';    // <-- YOUR APK PASSWORD

        // Gateway address.
        //   Cloud server (default) ... https://api.sms-gate.app/3rdparty/v1
        //   APK in "Local" mode ..... http://192.168.1.10:8080/api/3rdparty/v1
        //                              (use the phone's Wi-Fi IP address)
        $this->baseUrl = $baseUrl ?? 'https://api.sms-gate.app/3rdparty/v1';

        if ($this->username === '' || $this->password === '' || $this->username === 'YOUR_SMS_GATEWAY_USERNAME') {
            error_log('SMS WARNING: gateway credentials are empty. Edit sms/function/sms-service.php and add the username/password from your SMS Gateway APK.');
        }

        // Keep a page from hanging forever when the phone is offline.
        $httpClient = null;
        try {
            if (class_exists('Symfony\\Component\\HttpClient\\HttpClient')) {
                $httpClient = new \Symfony\Component\HttpClient\Psr18Client(
                    \Symfony\Component\HttpClient\HttpClient::create(['timeout' => 20])
                );
            }
        } catch (Throwable $e) {
            $httpClient = null; // fall back to auto-discovery
        }

        $this->client = new Client($this->username, $this->password, $this->baseUrl, $httpClient);
    }

    /**
     * Send SMS to a Philippine mobile number
     *
     * @param string $phoneNumber Philippine mobile number (09171234567, 9171234567 or +639171234567)
     * @param string $message SMS message content
     * @return array Result with 'success' boolean and 'message' string
     */
    public function sendSMS($phoneNumber, $message) {
        try {
            // Format phone number to +639XXXXXXXXX
            $formattedNumber = $this->formatPhoneNumber($phoneNumber);

            if (!$formattedNumber) {
                return [
                    'success' => false,
                    'message' => 'Invalid Philippine mobile number format. Use 09171234567 or 9171234567.'
                ];
            }

            // Create and send message
            $smsMessage = new Message($message, [$formattedNumber]);
            $messageState = $this->client->Send($smsMessage);

            // Log success
            error_log("SMS sent successfully. ID: " . $messageState->ID() . ", Number: " . $formattedNumber);

            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $messageState->ID()
            ];

        } catch (Throwable $e) {
            $error = $e->getMessage();
            if ($error === '') {
                $error = 'unknown error';
            }
            error_log("SMS sending failed: " . $error);
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $error
            ];
        }
    }

    /**
     * Send SMS to multiple recipients
     *
     * @param array $recipients Array of phone numbers
     * @param string $message SMS message content
     * @return array Results with count of successful and failed sends
     */
    public function sendBulkSMS($recipients, $message) {
        $results = [
            'total' => count($recipients),
            'successful' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($recipients as $phoneNumber) {
            $result = $this->sendSMS($phoneNumber, $message);

            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'phone' => $phoneNumber,
                'status' => $result['success'] ? 'sent' : 'failed',
                'message' => $result['message']
            ];

            // Small delay to avoid overwhelming the gateway
            usleep(300000); // 0.3 second delay between messages
        }

        return $results;
    }

    /**
     * Format phone number to +639XXXXXXXXX format
     *
     * @param string $phoneNumber Raw phone number input
     * @return string|false Formatted number or false if invalid
     */
    public function formatPhoneNumber($phoneNumber) {
        // Remove spaces, dashes, and other non-numeric characters except +
        $cleaned = preg_replace('/[^\d+]/', '', (string) $phoneNumber);

        // Handle different input formats
        if (preg_match('/^\+639\d{9}$/', $cleaned)) {
            // Already in correct format: +639XXXXXXXXX
            return $cleaned;
        } elseif (preg_match('/^639\d{9}$/', $cleaned)) {
            // Format: 639XXXXXXXXX (missing +)
            return '+' . $cleaned;
        } elseif (preg_match('/^09\d{9}$/', $cleaned)) {
            // Format: 09XXXXXXXXX (Philippine domestic format)
            return '+63' . substr($cleaned, 1);
        } elseif (preg_match('/^9\d{9}$/', $cleaned)) {
            // Format: 9XXXXXXXXX (no prefix)
            return '+63' . $cleaned;
        }

        // Invalid format
        return false;
    }

    /**
     * Send class schedule reminder to student
     *
     * @param string $phoneNumber Student phone number
     * @param string $studentName Student's full name
     * @param string $subjectName Name of the subject
     * @param string $schedule Class schedule details
     * @param string $room Room location
     * @param string $instructorName Instructor's name
     * @return array Send result
     */
    public function sendScheduleReminder($phoneNumber, $studentName, $subjectName, $schedule, $room, $instructorName) {
        $message = "Hi {$studentName}! Reminder: You have {$subjectName} class tomorrow.\n";
        $message .= "Schedule: {$schedule}\n";
        $message .= "Room: {$room}\n";
        $message .= "Instructor: {$instructorName}";

        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send grade notification to student
     *
     * @param string $phoneNumber Student phone number
     * @param string $studentName Student's full name
     * @param string $subjectName Name of the subject
     * @param string $grade Letter grade received
     * @param float $score Numeric score
     * @return array Send result
     */
    public function sendGradeNotification($phoneNumber, $studentName, $subjectName, $grade, $score) {
        $message = "Hi {$studentName}! Your grade for {$subjectName} has been posted.\n";
        $message .= "Grade: {$grade}\n";
        $message .= "Score: {$score}%\n";
        $message .= "Check your portal for details.";

        return $this->sendSMS($phoneNumber, $message);
    }
}

/**
 * Get SMS Service instance with configured credentials
 *
 * @return SMSService
 */
function getSMSService() {
    // ============================================
    // OPTION: Override credentials here if you prefer
    // ============================================
    // return new SMSService('your_username', 'your_password');

    return new SMSService();
}
