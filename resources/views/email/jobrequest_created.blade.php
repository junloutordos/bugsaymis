<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New IT Job Request</title>
</head>
<body>
    <h2>New IT Job Request Submitted</h2>

    <p><strong>ITJR No:</strong> {{ $jobRequest->itjr_no }}</p>
    <p><strong>Title:</strong> {{ $jobRequest->title }}</p>
    <p><strong>Category:</strong> {{ $jobRequest->category }}</p>
    <p><strong>Description:</strong> {{ $jobRequest->description }}</p>
    <p><strong>Status:</strong> {{ $jobRequest->status }}</p>

    <p>Please log in to the system to review and take action.</p>
</body>
</html>
