<p>Dear {{ $adviserName }},</p>

<p>This is to inform you that your advisee has requested a guidance consultation.</p>

<ul>
    <li><strong>Student:</strong> {{ $studentName }}</li>
    <li><strong>Reason / Concern:</strong> {{ $concern }}</li>
    <li><strong>Requested Date/Time:</strong> {{ $requested ?? 'Not specified' }}</li>
    <li><strong>Assigned Guidance Personnel:</strong> {{ $assignedPersonnel ?? 'Not assigned' }}</li>
</ul>

<p>Please coordinate with the student as needed.</p>

<p>Regards,<br/>Guidance Office</p>
