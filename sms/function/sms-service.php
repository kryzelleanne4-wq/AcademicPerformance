<?php
/**
 * SMS Notification Service
 * Reusable service for sending SMS notifications to students
 * Uses Android SMS Gateway APK
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AndroidSmsGateway\Client;
use AndroidSmsGateway\Domain\Message;

class SMSService {
    private $username;
    private $password;
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
                    'message' => 'Invalid Philippine mobile number format. Must be 9XXXXXXXXX (10 digits starting with 9)'
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
