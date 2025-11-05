<!DOCTYPE html>
<html>
<head>
    <title>Infrastructure Status Updated</title>
</head>
<body>
    <h2>Infrastructure Status Updated by Administrator</h2>
    
    <p>Hello {{ $reporter->name }},</p>
    
    <p>An administrator has updated the infrastructure status for your incident report:</p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <strong>Incident Details:</strong><br>
        Title: {{ $incident->title }}<br>
        Type: {{ $incident->incident_type }}<br>
        Location: {{ $incident->location }}<br>
        Updated by: {{ $admin->name }}<br>
        Updated on: {{ now()->format('M j, Y g:i A') }}
    </div>

    <p>You can view the updated details in the incident management system.</p>

    <p>Best regards,<br>Emergency Incident Reporting System</p>
</body>
</html>