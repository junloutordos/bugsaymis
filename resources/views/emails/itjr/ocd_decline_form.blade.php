<!DOCTYPE html>
<html>
<head>
    <title>Decline IT Job Request (OCD)</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f4f4; }
        .container { background: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: auto; }
        input, textarea, button { width: 100%; padding: 10px; margin-top: 10px; border-radius: 4px; border: 1px solid #ccc; }
        button { background: #e74c3c; color: #fff; border: none; cursor: pointer; }
    </style>
</head>
<body>
<div class="container">
    <h2>Decline IT Job Request (OCD)</h2>
    <p>You are declining IT Job Request <strong>{{ $jobRequest->itjr_no }}</strong></p>
    <p>Title: {{ $jobRequest->title }}</p>

    <form method="POST" action="{{ $postAction }}">
    @csrf
    <label for="reason">Reason for Decline:</label><br>
    <textarea name="reason" id="reason" rows="4" required></textarea><br><br>
    <button type="submit">Submit Decline</button>
</form>


</div>
</body>
</html>
