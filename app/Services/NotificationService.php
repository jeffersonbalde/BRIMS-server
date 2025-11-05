<?php
// app/Services/NotificationService.php

namespace App\Services;

use App\Models\Notification;
use App\Models\EmailLog;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    // Notification types
    const TYPE_INCIDENT_REPORTED = 'incident_reported';
    const TYPE_INCIDENT_STATUS_CHANGED = 'incident_status_changed';
    const TYPE_REGISTRATION_APPROVED = 'registration_approved';
    const TYPE_REGISTRATION_REJECTED = 'registration_rejected';
    const TYPE_ADMIN_ALERT = 'admin_alert';


    // Add these to your existing notification types constants
const TYPE_POPULATION_DATA_ADDED = 'population_data_added';
const TYPE_POPULATION_DATA_UPDATED = 'population_data_updated';
const TYPE_INFRASTRUCTURE_STATUS_ADDED = 'infrastructure_status_added';
const TYPE_INFRASTRUCTURE_STATUS_UPDATED = 'infrastructure_status_updated';

// Add to existing notification types in NotificationService.php
const TYPE_ACCOUNT_DEACTIVATED = 'account_deactivated';
const TYPE_ACCOUNT_REACTIVATED = 'account_reactivated';
const TYPE_LOGIN_BLOCKED = 'login_blocked';

    /**
     * Create a notification for a user
     */
    public function createNotification($userId, $type, $title, $message, $data = null)
    {
        try {
            return Notification::create([
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create notification: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create notification for admins
     */
    public function notifyAdmins($type, $title, $message, $data = null)
    {
        $admins = User::where('role', 'admin')->get();
        
        foreach ($admins as $admin) {
            $this->createNotification($admin->id, $type, $title, $message, $data);
        }
        
        return $admins->count();
    }

    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId)
    {
        $notification = Notification::find($notificationId);
        if ($notification) {
            $notification->markAsRead();
            return true;
        }
        return false;
    }

    /**
     * Mark all notifications as read for user
     */
    public function markAllAsRead($userId)
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now()
            ]);
    }

/**
 * Send email notification using Blade templates
 */
public function sendEmailNotification($userId, $type, $subject, $content, $templateData = null)
{
    try {
        $user = User::find($userId);
        if (!$user) {
            throw new \Exception('User not found');
        }

        $emailSent = false;
        
        // Use Blade templates if template data is provided
        if ($templateData && view()->exists($type)) {
            Mail::send($type, $templateData, function ($message) use ($user, $subject) {
                $message->to($user->email)
                       ->subject($subject)
                       ->from(config('mail.from.address'), config('mail.from.name'));
            });
            $emailSent = true;
        } else {
            // Fallback to HTML content
            $emailSent = $this->sendEmail($user->email, $subject, $content);
        }

        // Log the email attempt
        EmailLog::create([
            'user_id' => $userId,
            'type' => $type,
            'recipient_email' => $user->email,
            'subject' => $subject,
            'content' => $content,
            'sent_successfully' => $emailSent,
            'error_message' => $emailSent ? null : 'Email service not configured',
        ]);

        return $emailSent;

    } catch (\Exception $e) {
        Log::error('Email notification failed: ' . $e->getMessage());
        
        EmailLog::create([
            'user_id' => $userId,
            'type' => $type,
            'recipient_email' => $user->email ?? 'unknown',
            'subject' => $subject,
            'content' => $content,
            'sent_successfully' => false,
            'error_message' => $e->getMessage(),
        ]);

        return false;
    }
}

    /**
     * Send incident reported notifications
     */
    public function notifyIncidentReported($incident, $reporter)
    {
        // Notify admins
        $adminCount = $this->notifyAdmins(
            self::TYPE_INCIDENT_REPORTED,
            'New Incident Reported',
            "A new incident '{$incident->title}' has been reported by {$reporter->name} from {$reporter->barangay_name}",
            ['incident_id' => $incident->id]
        );

        // Notify the reporter
        $this->createNotification(
            $reporter->id,
            self::TYPE_INCIDENT_REPORTED,
            'Incident Reported Successfully',
            "Your incident '{$incident->title}' has been reported successfully and is under review.",
            ['incident_id' => $incident->id]
        );

        // Send email to reporter
        $this->sendEmailNotification(
            $reporter->id,
            self::TYPE_INCIDENT_REPORTED,
            'Incident Reported Successfully - BRIMS',
            $this->getIncidentReportedEmailContent($incident, $reporter)
        );

        return $adminCount;
    }

    /**
     * Send incident status change notifications
     */
    public function notifyIncidentStatusChanged($incident, $oldStatus, $newStatus)
    {
        $reporter = $incident->reporter;

        $this->createNotification(
            $reporter->id,
            self::TYPE_INCIDENT_STATUS_CHANGED,
            'Incident Status Updated',
            "Your incident '{$incident->title}' status has been changed from {$oldStatus} to {$newStatus}",
            ['incident_id' => $incident->id, 'old_status' => $oldStatus, 'new_status' => $newStatus]
        );

        // Send email notification
        $this->sendEmailNotification(
            $reporter->id,
            self::TYPE_INCIDENT_STATUS_CHANGED,
            "Incident Status Updated - {$incident->title}",
            $this->getIncidentStatusChangedEmailContent($incident, $oldStatus, $newStatus)
        );
    }

    /**
     * Helper method to send email (placeholder - integrate with your email service)
     */
private function sendEmail($to, $subject, $content)
{
    try {
        // Method 1: Using Mail facade with html
        Mail::html($content, function ($message) use ($to, $subject) {
            $message->to($to)
                   ->subject($subject)
                   ->from(config('mail.from.address'), config('mail.from.name'));
        });
        
        Log::info("Email sent successfully to: {$to}");
        return true;
        
    } catch (\Exception $e) {
        Log::error('Email sending failed: ' . $e->getMessage());
        
        // For development, you can also log the email content
        Log::info("Email that failed to send - To: {$to}, Subject: {$subject}");
        
        return false;
    }
}

    /**
     * Email content templates
     */
    private function getIncidentReportedEmailContent($incident, $reporter)
    {
        return "
        <h2>Incident Reported Successfully</h2>
        <p>Dear {$reporter->name},</p>
        <p>Your incident has been reported successfully and is now under review by municipal administrators.</p>
        
        <h3>Incident Details:</h3>
        <ul>
            <li><strong>Title:</strong> {$incident->title}</li>
            <li><strong>Type:</strong> {$incident->incident_type}</li>
            <li><strong>Location:</strong> {$incident->location}</li>
            <li><strong>Severity:</strong> {$incident->severity}</li>
            <li><strong>Date & Time:</strong> {$incident->incident_date}</li>
        </ul>
        
        <p><strong>Next Steps:</strong></p>
        <ul>
            <li>Municipal administrators will review your incident report</li>
            <li>You will receive notifications about status updates</li>
            <li>You can edit or delete this incident within 1 hour of reporting</li>
        </ul>
        
        <p>Thank you for using BRIMS to keep our community safe.</p>
        
        <p><em>This is an automated message. Please do not reply to this email.</em></p>
        ";
    }

    private function getIncidentStatusChangedEmailContent($incident, $oldStatus, $newStatus)
    {
        return "
        <h2>Incident Status Updated</h2>
        <p>Dear {$incident->reporter->name},</p>
        <p>The status of your incident report has been updated.</p>
        
        <h3>Incident Details:</h3>
        <ul>
            <li><strong>Title:</strong> {$incident->title}</li>
            <li><strong>Previous Status:</strong> {$oldStatus}</li>
            <li><strong>Current Status:</strong> {$newStatus}</li>
            <li><strong>Location:</strong> {$incident->location}</li>
        </ul>
        
        <p><strong>What this means:</strong></p>
        " . $this->getStatusExplanation($newStatus) . "
        
        <p>You can view the latest updates in your BRIMS dashboard.</p>
        
        <p>Thank you for your cooperation.</p>
        
        <p><em>This is an automated message. Please do not reply to this email.</em></p>
        ";
    }

    private function getStatusExplanation($status)
    {
        $explanations = [
            'Reported' => 'The incident has been reported and is awaiting review by municipal administrators.',
            'Investigating' => 'Municipal authorities are currently investigating the incident and taking appropriate actions.',
            'Resolved' => 'The incident has been successfully resolved and no further action is required.'
        ];

        return $explanations[$status] ?? 'The incident status has been updated.';
    }

    // Add this method to your NotificationService.php
public function notifyIncidentArchived($incident, $archiveReason)
{
    $reporter = $incident->reporter;

    $this->createNotification(
        $reporter->id,
        self::TYPE_INCIDENT_STATUS_CHANGED,
        'Incident Archived',
        "Your incident '{$incident->title}' has been archived by administrators. Reason: {$archiveReason}",
        [
            'incident_id' => $incident->id, 
            'old_status' => $incident->status, 
            'new_status' => 'Archived',
            'archive_reason' => $archiveReason
        ]
    );

    // Send email notification
    $this->sendEmailNotification(
        $reporter->id,
        self::TYPE_INCIDENT_STATUS_CHANGED,
        "Incident Archived - {$incident->title}",
        $this->getIncidentArchivedEmailContent($incident, $archiveReason)
    );
}

private function getIncidentArchivedEmailContent($incident, $archiveReason)
{
    return "
    <h2>Incident Archived</h2>
    <p>Dear {$incident->reporter->name},</p>
    <p>Your incident report has been archived by municipal administrators.</p>
    
    <h3>Incident Details:</h3>
    <ul>
        <li><strong>Title:</strong> {$incident->title}</li>
        <li><strong>Type:</strong> {$incident->incident_type}</li>
        <li><strong>Location:</strong> {$incident->location}</li>
        <li><strong>Archive Reason:</strong> {$archiveReason}</li>
    </ul>
    
    <p><strong>What this means:</strong></p>
    <p>This incident has been moved to archived status for the following reasons:</p>
    <ul>
        <li>Data preservation for audit purposes</li>
        <li>Removal from active incident lists</li>
        <li>Historical record keeping</li>
    </ul>
    
    <p>The incident data is preserved in the system but will no longer appear in your active incident lists.</p>
    
    <p>If you believe this incident should not have been archived, please contact municipal administrators.</p>
    
    <p>Thank you for your understanding.</p>
    
    <p><em>This is an automated message. Please do not reply to this email.</em></p>
    ";
}


// Add to NotificationService.php
public function notifyIncidentUnarchived($incident, $newStatus, $unarchiveReason)
{
    $reporter = $incident->reporter;

    $this->createNotification(
        $reporter->id,
        self::TYPE_INCIDENT_STATUS_CHANGED,
        'Incident Restored',
        "Your incident '{$incident->title}' has been restored from archive and set to {$newStatus} status.",
        [
            'incident_id' => $incident->id, 
            'old_status' => 'Archived', 
            'new_status' => $newStatus,
            'unarchive_reason' => $unarchiveReason
        ]
    );

    // Send email notification
    $this->sendEmailNotification(
        $reporter->id,
        self::TYPE_INCIDENT_STATUS_CHANGED,
        "Incident Restored - {$incident->title}",
        $this->getIncidentUnarchivedEmailContent($incident, $newStatus, $unarchiveReason)
    );
}

private function getIncidentUnarchivedEmailContent($incident, $newStatus, $unarchiveReason)
{
    return "
    <h2>Incident Restored</h2>
    <p>Dear {$incident->reporter->name},</p>
    <p>Your incident report has been restored from archive by municipal administrators.</p>
    
    <h3>Incident Details:</h3>
    <ul>
        <li><strong>Title:</strong> {$incident->title}</li>
        <li><strong>Type:</strong> {$incident->incident_type}</li>
        <li><strong>Location:</strong> {$incident->location}</li>
        <li><strong>New Status:</strong> {$newStatus}</li>
        <li><strong>Restoration Reason:</strong> {$unarchiveReason}</li>
    </ul>
    
    <p><strong>What this means:</strong></p>
    <p>This incident is now active again and will appear in your incident lists with status: <strong>{$newStatus}</strong></p>
    
    <p>You can now view and track this incident in your BRIMS dashboard.</p>
    
    <p>Thank you for your understanding.</p>
    
    <p><em>This is an automated message. Please do not reply to this email.</em></p>
    ";
}

/**
 * Notify admin when population data is added by barangay
 */
public function notifyPopulationDataAdded(Incident $incident, User $reporter)
{
    try {
        $admins = User::where('role', 'admin')->get();
        $notificationCount = 0;

        foreach ($admins as $admin) {
            // Create in-app notification
            Notification::create([
                'user_id' => $admin->id,
                'type' => self::TYPE_POPULATION_DATA_ADDED,
                'title' => 'New Population Data Added',
                'message' => "Barangay {$reporter->barangay_name} has added population data for incident: {$incident->title}",
                'data' => [
                    'incident_id' => $incident->id,
                    'incident_title' => $incident->title,
                    'reporter_name' => $reporter->name,
                    'barangay_name' => $reporter->barangay_name,
                    'action' => 'population_data_added'
                ],
                'is_read' => false
            ]);

            // Send email with Blade template
            $templateData = [
                'incident' => $incident,
                'reporter' => $reporter,
                'admin' => $admin,
                'populationData' => $incident->populationData
            ];

            $this->sendEmailNotification(
                $admin->id,
                'population_data_added',
                "New Population Data Added - {$incident->title}",
                "Barangay {$reporter->barangay_name} has added population data for incident: {$incident->title}",
                $templateData
            );

            $notificationCount++;
        }

        Log::info("Population data added notifications sent", [
            'incident_id' => $incident->id,
            'notifications_sent' => $notificationCount
        ]);

        return $notificationCount;
    } catch (\Exception $e) {
        Log::error('Error sending population data added notifications: ' . $e->getMessage());
        return 0;
    }
}

    /**
     * Notify admins when infrastructure status is added/updated
     */
/**
 * Notify admin when infrastructure status is added by barangay
 */
public function notifyInfrastructureStatusAdded(Incident $incident, User $reporter)
{
    try {
        $admins = User::where('role', 'admin')->get();
        $notificationCount = 0;

        foreach ($admins as $admin) {
            // Create in-app notification
            Notification::create([
                'user_id' => $admin->id,
                'type' => self::TYPE_INFRASTRUCTURE_STATUS_ADDED,
                'title' => 'New Infrastructure Status Added',
                'message' => "Barangay {$reporter->barangay_name} has added infrastructure status for incident: {$incident->title}",
                'data' => [
                    'incident_id' => $incident->id,
                    'incident_title' => $incident->title,
                    'reporter_name' => $reporter->name,
                    'barangay_name' => $reporter->barangay_name,
                    'action' => 'infrastructure_status_added'
                ],
                'is_read' => false
            ]);

            // Send email with Blade template
            $templateData = [
                'incident' => $incident,
                'reporter' => $reporter,
                'admin' => $admin,
                'infrastructureStatus' => $incident->infrastructureStatus
            ];

            $this->sendEmailNotification(
                $admin->id,
                'infrastructure_status_added',
                "New Infrastructure Status Added - {$incident->title}",
                "Barangay {$reporter->barangay_name} has added infrastructure status for incident: {$incident->title}",
                $templateData
            );

            $notificationCount++;
        }

        Log::info("Infrastructure status added notifications sent", [
            'incident_id' => $incident->id,
            'notifications_sent' => $notificationCount
        ]);

        return $notificationCount;
    } catch (\Exception $e) {
        Log::error('Error sending infrastructure status added notifications: ' . $e->getMessage());
        return 0;
    }
}


/**
 * Notify reporter when admin updates population data
 */
public function notifyPopulationDataUpdatedByAdmin(Incident $incident, User $admin)
{
    try {
        // Create in-app notification
        Notification::create([
            'user_id' => $incident->reported_by,
            'type' => self::TYPE_POPULATION_DATA_UPDATED,
            'title' => 'Population Data Updated by Admin',
            'message' => "Administrator {$admin->name} has updated the population data for your incident: {$incident->title}",
            'data' => [
                'incident_id' => $incident->id,
                'incident_title' => $incident->title,
                'admin_name' => $admin->name,
                'action' => 'population_data_updated'
            ],
            'is_read' => false
        ]);

        // Send email with Blade template
        $templateData = [
            'incident' => $incident,
            'admin' => $admin,
            'reporter' => $incident->reporter,
            'populationData' => $incident->populationData
        ];

        $this->sendEmailNotification(
            $incident->reported_by,
            'population_data_updated',
            "Population Data Updated - {$incident->title}",
            "Administrator {$admin->name} has updated the population data for your incident: {$incident->title}",
            $templateData
        );

        Log::info("Population data updated notification sent to reporter", [
            'incident_id' => $incident->id,
            'reporter_id' => $incident->reported_by
        ]);

        return 1;
    } catch (\Exception $e) {
        Log::error('Error sending population data updated notification: ' . $e->getMessage());
        return 0;
    }
}


/**
 * Notify reporter when admin updates infrastructure status
 */
public function notifyInfrastructureStatusUpdatedByAdmin(Incident $incident, User $admin)
{
    try {
        // Create in-app notification
        Notification::create([
            'user_id' => $incident->reported_by,
            'type' => self::TYPE_INFRASTRUCTURE_STATUS_UPDATED,
            'title' => 'Infrastructure Status Updated by Admin',
            'message' => "Administrator {$admin->name} has updated the infrastructure status for your incident: {$incident->title}",
            'data' => [
                'incident_id' => $incident->id,
                'incident_title' => $incident->title,
                'admin_name' => $admin->name,
                'action' => 'infrastructure_status_updated'
            ],
            'is_read' => false
        ]);

        // Send email with Blade template
        $templateData = [
            'incident' => $incident,
            'admin' => $admin,
            'reporter' => $incident->reporter,
            'infrastructureStatus' => $incident->infrastructureStatus
        ];

        $this->sendEmailNotification(
            $incident->reported_by,
            'infrastructure_status_updated',
            "Infrastructure Status Updated - {$incident->title}",
            "Administrator {$admin->name} has updated the infrastructure status for your incident: {$incident->title}",
            $templateData
        );

        Log::info("Infrastructure status updated notification sent to reporter", [
            'incident_id' => $incident->id,
            'reporter_id' => $incident->reported_by
        ]);

        return 1;
    } catch (\Exception $e) {
        Log::error('Error sending infrastructure status updated notification: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Email content for population data added
 */
private function getPopulationDataAddedEmailContent($incident, $reporter, $admin)
{
    return "
    <h2>New Population Data Added</h2>
    <p>Hello {$admin->name},</p>
    
    <p>Barangay {$reporter->barangay_name} has added population data for the following incident:</p>
    
    <div style='background: #f8f9fa; padding: 15px; margin: 15px 0;'>
        <h3>Incident Details:</h3>
        <p><strong>Title:</strong> {$incident->title}</p>
        <p><strong>Type:</strong> {$incident->incident_type}</p>
        <p><strong>Location:</strong> {$incident->location}</p>
        <p><strong>Date:</strong> {$incident->incident_date}</p>
    </div>

    <p>Please review the population data in the BRMIS admin dashboard.</p>
    
    <p><em>This is an automated message from BRMIS.</em></p>
    ";
}
}