@extends('emails.layouts.base')

@section('header-title', $headerTitle)
@section('header-subtitle', 'Atlas — Research Advisory')

@section('content')
<p class="greeting">Hello <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">{{ $lead }}</p>

@if(!empty($details))
<table class="details" role="presentation">
    @foreach($details as [$label, $value])
    <tr><td class="lbl">{{ $label }}</td><td class="val">{{ $value }}</td></tr>
    @endforeach
</table>
@endif

@if($actionUrl)
<p><a href="{{ $actionUrl }}" class="btn btn-primary">{{ $actionLabel ?? 'View' }}</a></p>
@endif
@endsection
