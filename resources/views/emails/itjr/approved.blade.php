<!DOCTYPE html>
<html>
<head>
    <title>IT Job Request Approved</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: auto; }
        .status { color: green; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>IT Job Request Approved</h2>
    <p>Your action has been recorded for IT Job Request <strong>{{ $jobRequest->itjr_no }}</strong>.</p>
    <p>Status: <span class="status">{{ $jobRequest->status }}</span></p>
    <p>Request Title: {{ $jobRequest->title }}</p>
    <p>Submitted by: {{ $jobRequest->user->name }}</p>
</div>
</body>
</html>
