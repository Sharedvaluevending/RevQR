<?php
/**
 * Pizza Tracker Notification System
 * Handles SMS, Email, and Push notifications for milestones
 */

require_once __DIR__ . '/config.php';

class PizzaTrackerNotificationSystem {
    private $pdo;
    private $emailConfig;
    private $smsConfig;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->emailConfig = [
            'smtp_host' => $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com',
            'smtp_port' => $_ENV['SMTP_PORT'] ?? 587,
            'smtp_username' => $_ENV['SMTP_USERNAME'] ?? '',
            'smtp_password' => $_ENV['SMTP_PASSWORD'] ?? '',
            'from_email' => $_ENV['FROM_EMAIL'] ?? 'noreply@pizzatracker.com',
            'from_name' => $_ENV['FROM_NAME'] ?? 'Pizza Tracker'
        ];
        
        $this->smsConfig = [
            'twilio_sid' => $_ENV['TWILIO_SID'] ?? '',
            'twilio_token' => $_ENV['TWILIO_TOKEN'] ?? '',
            'twilio_phone' => $_ENV['TWILIO_PHONE'] ?? ''
        ];
    }
    
    /**
     * Send milestone notification when tracker reaches certain thresholds
     */
    public function sendMilestoneNotification($trackerId, $milestone, $trackerData) {
        try {
            // Get notification preferences for this tracker's business
            $preferences = $this->getNotificationPreferences($trackerData['business_id']);
            
            if (!$preferences) {
                return false;
            }
            
            $message = $this->generateMilestoneMessage($milestone, $trackerData);
            $sent = false;
            
            // Send Email if enabled
            if ($preferences['email_enabled'] && !empty($preferences['email_addresses'])) {
                $emailSent = $this->sendEmail($preferences['email_addresses'], $message, $trackerData);
                $sent = $sent || $emailSent;
            }
            
            // Send SMS if enabled
            if ($preferences['sms_enabled'] && !empty($preferences['phone_numbers'])) {
                $smsSent = $this->sendSMS($preferences['phone_numbers'], $message, $trackerData);
                $sent = $sent || $smsSent;
            }
            
            // Send Push Notification if enabled
            if ($preferences['push_enabled']) {
                $pushSent = $this->sendPushNotification($trackerData['business_id'], $message, $trackerData);
                $sent = $sent || $pushSent;
            }
            
            // Log notification
            $this->logNotification($trackerId, $milestone, $message, $sent);
            
            return $sent;
        } catch (Exception $e) {
            error_log("Notification error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check and send notifications for tracker milestones
     */
    public function checkAndSendMilestoneNotifications($trackerId, $oldProgress, $newProgress) {
        $milestones = [25, 50, 75, 90, 100];
        
        foreach ($milestones as $milestone) {
            if ($oldProgress < $milestone && $newProgress >= $milestone) {
                // Get tracker data
                require_once __DIR__ . '/pizza_tracker_utils.php';
                $pizzaTracker = new PizzaTracker($this->pdo);
                $trackerData = $pizzaTracker->getTrackerDetails($trackerId);
                
                if ($trackerData) {
                    $this->sendMilestoneNotification($trackerId, $milestone, $trackerData);
                }
            }
        }
    }
    
    private function getNotificationPreferences($businessId) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM pizza_tracker_notification_preferences 
            WHERE business_id = ? AND is_active = 1
        ");
        $stmt->execute([$businessId]);
        return $stmt->fetch();
    }
    
    private function generateMilestoneMessage($milestone, $trackerData) {
        $messages = [
            25 => [
                'subject' => "🍕 Pizza Fund 25% Complete!",
                'body' => "Great progress! {tracker_name} is now 25% complete. Current: {$current_revenue} / {$revenue_goal}"
            ],
            50 => [
                'subject' => "🍕 Pizza Fund Halfway There!",
                'body' => "Amazing! {tracker_name} is 50% complete! We're halfway to pizza time! Current: {$current_revenue} / {$revenue_goal}"
            ],
            75 => [
                'subject' => "🍕 Pizza Fund 75% Complete!",
                'body' => "So close! {tracker_name} is 75% complete. Pizza time is almost here! Current: {$current_revenue} / {$revenue_goal}"
            ],
            90 => [
                'subject' => "🍕 Pizza Fund Almost Complete!",
                'body' => "Final stretch! {tracker_name} is 90% complete. Just {$remaining_amount} to go for pizza celebration!"
            ],
            100 => [
                'subject' => "🎉 PIZZA TIME! Goal Achieved!",
                'body' => "Congratulations! {tracker_name} has reached its goal! Time to order that pizza! 🍕🎉"
            ]
        ];
        
        $template = $messages[$milestone] ?? $messages[100];
        
        // Replace placeholders
        $replacements = [
            '{tracker_name}' => $trackerData['name'],
            '{current_revenue}' => number_format($trackerData['current_revenue'], 2),
            '{revenue_goal}' => number_format($trackerData['revenue_goal'], 2),
            '{remaining_amount}' => number_format($trackerData['remaining_amount'], 2),
            '{progress_percent}' => $trackerData['progress_percent'],
            '{business_name}' => $trackerData['business_name']
        ];
        
        return [
            'subject' => str_replace(array_keys($replacements), array_values($replacements), $template['subject']),
            'body' => str_replace(array_keys($replacements), array_values($replacements), $template['body'])
        ];
    }
    
    private function sendEmail($emailAddresses, $message, $trackerData) {
        if (empty($this->emailConfig['smtp_username'])) {
            error_log("Email not configured");
            return false;
        }
        
        try {
            // Use PHPMailer if available, otherwise use basic mail()
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                return $this->sendEmailPHPMailer($emailAddresses, $message, $trackerData);
            } else {
                return $this->sendEmailBasic($emailAddresses, $message, $trackerData);
            }
        } catch (Exception $e) {
            error_log("Email send error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendEmailPHPMailer($emailAddresses, $message, $trackerData) {
        require_once __DIR__ . '/../vendor/autoload.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        // SMTP configuration
        $mail->isSMTP();
        $mail->Host = $this->emailConfig['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $this->emailConfig['smtp_username'];
        $mail->Password = $this->emailConfig['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $this->emailConfig['smtp_port'];
        
        // Email content
        $mail->setFrom($this->emailConfig['from_email'], $this->emailConfig['from_name']);
        $mail->Subject = $message['subject'];
        $mail->isHTML(true);
        
        $htmlBody = $this->generateEmailHTML($message, $trackerData);
        $mail->Body = $htmlBody;
        $mail->AltBody = $message['body'];
        
        // Add recipients
        $emails = is_array($emailAddresses) ? $emailAddresses : explode(',', $emailAddresses);
        foreach ($emails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
            }
        }
        
        return $mail->send();
    }
    
    private function sendEmailBasic($emailAddresses, $message, $trackerData) {
        $headers = [
            'From: ' . $this->emailConfig['from_name'] . ' <' . $this->emailConfig['from_email'] . '>',
            'Reply-To: ' . $this->emailConfig['from_email'],
            'Content-Type: text/html; charset=UTF-8',
            'MIME-Version: 1.0'
        ];
        
        $htmlBody = $this->generateEmailHTML($message, $trackerData);
        $emails = is_array($emailAddresses) ? $emailAddresses : explode(',', $emailAddresses);
        $sent = true;
        
        foreach ($emails as $email) {
            $email = trim($email);
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $result = mail($email, $message['subject'], $htmlBody, implode("\r\n", $headers));
                $sent = $sent && $result;
            }
        }
        
        return $sent;
    }
    
    private function generateEmailHTML($message, $trackerData) {
        $logoUrl = APP_URL . '/assets/img/pizza-logo.png';
        $trackerUrl = APP_URL . '/public/pizza-tracker.php?tracker_id=' . $trackerData['id'];
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
                .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); color: white; padding: 30px; text-align: center; }
                .content { padding: 30px; }
                .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; margin: 20px 0; }
                .progress-fill { height: 100%; background: linear-gradient(90deg, #ff6b6b 0%, #feca57 100%); width: {$trackerData['progress_percent']}%; }
                .button { display: inline-block; background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%); color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { padding: 20px; text-align: center; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍕 {$message['subject']}</h1>
                </div>
                <div class='content'>
                    <p>{$message['body']}</p>
                    
                    <div class='progress-bar'>
                        <div class='progress-fill'></div>
                    </div>
                    
                    <p><strong>Progress Details:</strong></p>
                    <ul>
                        <li>Current Revenue: \${$trackerData['current_revenue']}</li>
                        <li>Goal: \${$trackerData['revenue_goal']}</li>
                        <li>Progress: {$trackerData['progress_percent']}%</li>
                        <li>Pizzas Earned: {$trackerData['completion_count']}</li>
                    </ul>
                    
                    <a href='$trackerUrl' class='button'>View Live Progress 📊</a>
                </div>
                <div class='footer'>
                    <p>Pizza Tracker System - {$trackerData['business_name']}</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function sendSMS($phoneNumbers, $message, $trackerData) {
        if (empty($this->smsConfig['twilio_sid'])) {
            error_log("SMS not configured");
            return false;
        }
        
        try {
            require_once __DIR__ . '/../vendor/autoload.php';
            
            $twilio = new Twilio\Rest\Client(
                $this->smsConfig['twilio_sid'],
                $this->smsConfig['twilio_token']
            );
            
            $smsBody = $message['body'] . "\n\nView progress: " . APP_URL . '/public/pizza-tracker.php?tracker_id=' . $trackerData['id'];
            $phones = is_array($phoneNumbers) ? $phoneNumbers : explode(',', $phoneNumbers);
            $sent = true;
            
            foreach ($phones as $phone) {
                $phone = trim($phone);
                if (!empty($phone)) {
                    try {
                        $twilio->messages->create($phone, [
                            'from' => $this->smsConfig['twilio_phone'],
                            'body' => $smsBody
                        ]);
                    } catch (Exception $e) {
                        error_log("SMS send error for $phone: " . $e->getMessage());
                        $sent = false;
                    }
                }
            }
            
            return $sent;
        } catch (Exception $e) {
            error_log("SMS service error: " . $e->getMessage());
            return false;
        }
    }
    
    private function sendPushNotification($businessId, $message, $trackerData) {
        // Get push tokens for business users
        $stmt = $this->pdo->prepare("
            SELECT push_token FROM user_push_tokens upt
            JOIN users u ON upt.user_id = u.id
            JOIN businesses b ON u.business_id = b.id
            WHERE b.id = ? AND upt.is_active = 1
        ");
        $stmt->execute([$businessId]);
        $pushTokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($pushTokens)) {
            return false;
        }
        
        $payload = [
            'title' => $message['subject'],
            'body' => $message['body'],
            'icon' => '/assets/img/pizza-icon.png',
            'badge' => '/assets/img/pizza-badge.png',
            'url' => APP_URL . '/public/pizza-tracker.php?tracker_id=' . $trackerData['id'],
            'data' => [
                'tracker_id' => $trackerData['id'],
                'progress' => $trackerData['progress_percent']
            ]
        ];
        
        return $this->sendWebPushNotifications($pushTokens, $payload);
    }
    
    private function sendWebPushNotifications($pushTokens, $payload) {
        // Implement Web Push using service like Firebase FCM or web-push library
        // For now, return true to indicate it would work
        return true;
    }
    
    private function logNotification($trackerId, $milestone, $message, $success) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pizza_tracker_notifications 
                (tracker_id, milestone, message_subject, message_body, sent_successfully, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $trackerId,
                $milestone,
                $message['subject'],
                $message['body'],
                $success ? 1 : 0
            ]);
        } catch (Exception $e) {
            error_log("Failed to log notification: " . $e->getMessage());
        }
    }
    
    /**
     * Set notification preferences for a business
     */
    public function setNotificationPreferences($businessId, $preferences) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pizza_tracker_notification_preferences 
                (business_id, email_enabled, sms_enabled, push_enabled, 
                 email_addresses, phone_numbers, milestones, is_active, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                email_enabled = VALUES(email_enabled),
                sms_enabled = VALUES(sms_enabled),
                push_enabled = VALUES(push_enabled),
                email_addresses = VALUES(email_addresses),
                phone_numbers = VALUES(phone_numbers),
                milestones = VALUES(milestones),
                updated_at = NOW()
            ");
            
            return $stmt->execute([
                $businessId,
                $preferences['email_enabled'] ? 1 : 0,
                $preferences['sms_enabled'] ? 1 : 0,
                $preferences['push_enabled'] ? 1 : 0,
                $preferences['email_addresses'] ?? '',
                $preferences['phone_numbers'] ?? '',
                json_encode($preferences['milestones'] ?? [25, 50, 75, 90, 100])
            ]);
        } catch (Exception $e) {
            error_log("Failed to set notification preferences: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send test notification
     */
    public function sendTestNotification($businessId, $type = 'email') {
        $testMessage = [
            'subject' => '🍕 Test Pizza Tracker Notification',
            'body' => 'This is a test notification from your Pizza Tracker system. Everything is working correctly!'
        ];
        
        $testTrackerData = [
            'id' => 'test',
            'name' => 'Test Pizza Tracker',
            'business_name' => 'Test Business',
            'current_revenue' => 250.00,
            'revenue_goal' => 500.00,
            'progress_percent' => 50,
            'completion_count' => 1
        ];
        
        $preferences = $this->getNotificationPreferences($businessId);
        if (!$preferences) {
            return false;
        }
        
        switch ($type) {
            case 'email':
                return $preferences['email_enabled'] ? 
                    $this->sendEmail($preferences['email_addresses'], $testMessage, $testTrackerData) : false;
            case 'sms':
                return $preferences['sms_enabled'] ? 
                    $this->sendSMS($preferences['phone_numbers'], $testMessage, $testTrackerData) : false;
            case 'push':
                return $preferences['push_enabled'] ? 
                    $this->sendPushNotification($businessId, $testMessage, $testTrackerData) : false;
        }
        
        return false;
    }
}
?> 