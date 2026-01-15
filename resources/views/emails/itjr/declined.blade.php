<!DOCTYPE html>
<html>
<head>
    <title>IT Job Request Declined</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: auto; }
        .status { color: red; font-weight: bold; }
    </style>
</head>
<body>
<div class="container">
    <h2>IT Job Request Declined</h2>
    <p>The IT Job Request <strong>{{ $jobRequest->itjr_no }}</strong> has been declined.</p>
    <p>Status: <span class="status">{{ $jobRequest->status }}</span></p>
    <p>Reason: {{ $reason }}</p>
</div>
</body>
</html>
