<?php
/**
 * SMS Notification Service
<<<<<<< HEAD
 * Reusable service for sending SMS notifications to students.
 *
 * Messages are delivered through the Android SMS Gateway APK that runs on your
 * own phone. The phone must be switched on, online, and signed in to the same
 * SMS Gateway account as the username/password below.
=======
 * Reusable service for sending SMS notifications to students
 * Uses Android SMS Gateway APK
>>>>>>> main
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;

class SMSService {
    private $username;
    private $password;
<<<<<<< HEAD
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
        $this->username = $username ?? 'HSJV8P';          // <-- YOUR APK USERNAME
        $this->password = $password ?? 'jd4f4djwhdvtnd';  // <-- YOUR APK PASSWORD

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
=======
    private $client;
    
    /**
     * Initialize SMS Service
     * 
     * IMPORTANT: Replace these credentials with your Android SMS Gateway APK credentials
     * You can get these from your SMS Gateway app on your phone
     * 
     * @param string $username Your SMS Gateway APK username
     * @param string $password Your SMS Gateway APK password
     */
    public function __construct($username = null, $password = null) {
        // ============================================
        // TODO: PUT YOUR SMS GATEWAY APK CREDENTIALS HERE
        // ============================================
        // Option 1: Pass credentials when creating the service
        // Option 2: Set default credentials here (uncomment and edit below)
        
        $this->username = $username ?? 'YOUR_SMS_GATEWAY_USERNAME'; // <- PUT YOUR USERNAME HERE
        $this->password = $password ?? 'YOUR_SMS_GATEWAY_PASSWORD'; // <- PUT YOUR PASSWORD HERE
        
        // ============================================
        
        if ($this->username === 'YOUR_SMS_GATEWAY_USERNAME' || $this->password === 'YOUR_SMS_GATEWAY_PASSWORD') {
            error_log('WARNING: SMS Gateway credentials not configured. Please update sms/function/sms-service.php');
        }
        
        $this->client = new Client($this->username, $this->password);
    }
    
    /**
     * Send SMS to a Philippine mobile number
     * 
     * @param string $phoneNumber Philippine mobile number (format: 9XXXXXXXXX or +639XXXXXXXXX)
>>>>>>> main
     * @param string $message SMS message content
     * @return array Result with 'success' boolean and 'message' string
     */
    public function sendSMS($phoneNumber, $message) {
        try {
            // Format phone number to +639XXXXXXXXX
            $formattedNumber = $this->formatPhoneNumber($phoneNumber);
<<<<<<< HEAD

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

=======
            
            if (!$formattedNumber) {
                return [
                    'success' => false,
                    'message' => 'Invalid Philippine mobile number format. Must be 9XXXXXXXXX (10 digits starting with 9)'
                ];
            }
            
            // Create and send message
            $smsMessage = new Message($message, [$formattedNumber]);
            $messageState = $this->client->Send($smsMessage);
            
            // Log success
            error_log("SMS sent successfully. ID: " . $messageState->ID() . ", Number: " . $formattedNumber);
            
>>>>>>> main
            return [
                'success' => true,
                'message' => 'SMS sent successfully',
                'message_id' => $messageState->ID()
            ];
<<<<<<< HEAD

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
=======
            
        } catch (Exception $e) {
            error_log("SMS sending failed: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send SMS to multiple recipients
     * 
>>>>>>> main
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
<<<<<<< HEAD

        foreach ($recipients as $phoneNumber) {
            $result = $this->sendSMS($phoneNumber, $message);

=======
        
        foreach ($recipients as $phoneNumber) {
            $result = $this->sendSMS($phoneNumber, $message);
            
>>>>>>> main
            if ($result['success']) {
                $results['successful']++;
            } else {
                $results['failed']++;
            }
<<<<<<< HEAD

=======
            
>>>>>>> main
            $results['details'][] = [
                'phone' => $phoneNumber,
                'status' => $result['success'] ? 'sent' : 'failed',
                'message' => $result['message']
            ];
<<<<<<< HEAD

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

=======
            
            // Small delay to avoid overwhelming the gateway
            usleep(500000); // 0.5 second delay between messages
        }
        
        return $results;
    }
    
    /**
     * Format phone number to +639XXXXXXXXX format
     * 
     * @param string $phoneNumber Raw phone number input
     * @return string|false Formatted number or false if invalid
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove spaces, dashes, and other non-numeric characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phoneNumber);
        
>>>>>>> main
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
<<<<<<< HEAD

        // Invalid format
        return false;
    }

    /**
     * Send class schedule reminder to student
     *
=======
        
        // Invalid format
        return false;
    }
    
    /**
     * Send class schedule reminder to student
     * 
>>>>>>> main
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
<<<<<<< HEAD

        return $this->sendSMS($phoneNumber, $message);
    }

    /**
     * Send grade notification to student
     *
=======
        
        return $this->sendSMS($phoneNumber, $message);
    }
    
    /**
     * Send grade notification to student
     * 
>>>>>>> main
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
<<<<<<< HEAD

=======
        
>>>>>>> main
        return $this->sendSMS($phoneNumber, $message);
    }
}

/**
 * Get SMS Service instance with configured credentials
<<<<<<< HEAD
 *
=======
 * 
>>>>>>> main
 * @return SMSService
 */
function getSMSService() {
    // ============================================
    // OPTION: Override credentials here if you prefer
    // ============================================
    // return new SMSService('your_username', 'your_password');
<<<<<<< HEAD

=======
    
>>>>>>> main
    return new SMSService();
}
