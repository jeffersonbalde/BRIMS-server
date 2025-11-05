<!DOCTYPE html>
<html>
<head>
    <title>Infrastructure Status Updated</title>
</head>
<body>
    <h2>Infrastructure Status Updated</h2>
    
    <p>Hello {{ $admin->name }},</p>
    
    <p>Infrastructure status has been updated for the following incident:</p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <strong>Incident Details:</strong><br>
        Title: {{ $incident->title }}<br>
        Type: {{ $incident->incident_type }}<br>
        Location: {{ $incident->location }}<br>
        Severity: {{ $incident->severity }}<br>
        Reported by: {{ $reporter->name }} ({{ $reporter->barangay }})<br>
    </div>

    <p>The barangay has provided updated information about roads, power supply, and communication lines status.</p>

    <p>
        <a href="{{ url('/admin/incidents/' . $incident->id) }}" 
           style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            View Incident Details
        </a>
    </p>

    <p>Best regards,<br>Emergency Incident Reporting System</p>
</body>
</html>