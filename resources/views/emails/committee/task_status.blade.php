@extends('emails.layouts.base')

@section('header-title', $task->status === 'done' ? 'Committee Task Done' : 'Committee Task Stuck')
@section('header-subtitle','Committee Task Board')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">
    <strong>{{ $actor->name }}</strong> marked a task as
    <strong>{{ $task->status === 'done' ? 'Done' : 'Stuck' }}</strong>
    on the <strong>{{ $task->committee?->name }}</strong> board.
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Task</td><td class="val"><strong>{{ $task->title }}</strong></td></tr>
    <tr><td class="lbl">Committee</td><td class="val">{{ $task->committee?->name }}</td></tr>
    @if($task->due_date)
    <tr><td class="lbl">Due Date</td><td class="val">{{ $task->due_date->format('F j, Y') }}</td></tr>
    @endif
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ $task->status === 'done' ? 'badge-green' : 'badge-red' }}">{{ $task->status === 'done' ? 'Done' : 'Stuck' }}</span></td></tr>
</table>

@if($task->status === 'stuck')
<p style="margin-top:16px;font-size:14px;color:#475569;">The assignee may need help to move forward — check the task's updates for details.</p>
@endif
@endsection
