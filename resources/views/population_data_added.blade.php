<!DOCTYPE html>
<html>
<head>
    <title>Population Data Added</title>
</head>
<body>
    <h2>Population Data Added to Incident</h2>
    
    <p>Hello {{ $admin->name }},</p>
    
    <p>Population data has been added to the following incident:</p>
    
    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
        <strong>Incident Details:</strong><br>
        Title: {{ $incident->title }}<br>
        Type: {{ $incident->incident_type }}<br>
        Location: {{ $incident->location }}<br>
        Severity: {{ $incident->severity }}<br>
        Reported by: {{ $reporter->name }} ({{ $reporter->barangay }})<br>
        Reported on: {{ $incident->created_at->format('M j, Y g:i A') }}
    </div>

    <p>The barangay has provided detailed population data including demographic information and special groups affected.</p>

    <p>
        <a href="{{ url('/admin/incidents/' . $incident->id) }}" 
           style="background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
            View Incident Details
        </a>
    </p>

    <p>Best regards,<br>Emergency Incident Reporting System</p>
</body>
</html>