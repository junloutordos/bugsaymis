<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('alp.forms._styles')</style>
</head>
<body>
@yield('content')
<div class="form-footer">{{ $formCode ?? '' }} | Generated {{ now()->format('m/d/Y') }} | Controlled when verified in Atlas</div>
</body>
</html>
