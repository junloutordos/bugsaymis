<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>@include('alp.forms._styles')</style>
</head>
<body>
<div class="cover">
    <h1 style="font-size:18pt">ALTERNATIVE LEARNING PROGRAM</h1>
    <h2>{{ $cycle->cycle_type === 'reaccreditation' ? 'REACCREDITATION' : 'ACCREDITATION' }} PACKAGE</h2>
    <p>{{ $cycle->program->name }}</p>
    <p>S.Y. {{ $cycle->schoolYear->name }}</p>
    <p>Status: {{ str($cycle->status)->replace('_', ' ')->title() }}</p>
</div>
@foreach($sections as $section)
    <div class="page-break"></div>
    {!! $section['html'] !!}
    @include('alp.forms._footer', ['formCode' => $section['formCode']])
@endforeach
</body>
</html>
