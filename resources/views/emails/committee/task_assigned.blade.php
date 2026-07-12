@extends('emails.layouts.base')

@section('header-title','Committee Task Assigned')
@section('header-subtitle','Committee Task Board')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">A task on the <strong>{{ $task->committee?->name }}</strong> board has been assigned to you.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Task</td><td class="val"><strong>{{ $task->title }}</strong></td></tr>
    @if($task->description)
    <tr><td class="lbl">Details</td><td class="val">{{ \Illuminate\Support\Str::limit($task->description, 300) }}</td></tr>
    @endif
    <tr><td class="lbl">Committee</td><td class="val">{{ $task->committee?->name }}</td></tr>
    @if($task->due_date)
    <tr><td class="lbl">Due Date</td><td class="val">{{ $task->due_date->format('F j, Y') }}</td></tr>
    @endif
    <tr><td class="lbl">Priority</td><td class="val" style="text-transform:capitalize;">{{ $task->priority }}</td></tr>
    @if($task->period)
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $task->period->label }}</td></tr>
    @endif
</table>

<p style="margin-top:16px;font-size:14px;color:#475569;">Open the Committee module to view the board and update your task's status.</p>
@endsection
