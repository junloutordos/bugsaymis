{{--
    Shared centered header block for every official ALP QMS form.
    Params: $title (string), $periodLine (string), $extraLines (array<string>, optional)
--}}
<div class="form-header">
    <div class="system">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
    <div class="campus">CAMPUS: CARAGA REGION CAMPUS</div>
    <div class="program-line">ALTERNATIVE LEARNING PROGRAM (ALP)</div>
    <h1>{{ $title }}</h1>
    @if(!empty($periodLine))<div class="period-line">{{ $periodLine }}</div>@endif
    @foreach($extraLines ?? [] as $line)<div class="extra-line">{{ $line }}</div>@endforeach
</div>
