<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
@include('class-record.partials.wat-pdf-styles')
  </style>
</head>
<body>

<div class="page-body">

  @include('class-record.partials.wat-pdf-heading')

  <table class="wat-table">
    <thead>
@include('class-record.partials.wat-pdf-thead-row')
    </thead>
    <tbody>
      @foreach($wat['days'] as $day)
        @foreach($day['chunks'] as $chunkIdx => $chunk)
          @if($chunk['items']->isEmpty())
            <tr class="wat-chunk-start">
              <td width="10%" class="wat-day">{{ $chunk['label'] }}</td>
              <td colspan="7" class="wat-empty">No assessments scheduled</td>
            </tr>
          @else
            @foreach($chunk['items'] as $idx => $item)
              <tr @if($idx === 0 && $chunkIdx === 0) class="wat-chunk-start" @endif>
                @if($idx === 0)
                  <td width="10%" class="wat-day" rowspan="{{ count($chunk['items']) }}">{{ $chunk['label'] }}</td>
                @endif
@include('class-record.partials.wat-pdf-item-cells')
              </tr>
            @endforeach
          @endif
        @endforeach
      @endforeach
    </tbody>
  </table>

  <table class="wat-signatories">
    <tr>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Consolidated by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $coordinatorName ?? '' }}</b></div>
        <div class="wat-signatory-position">Homeroom Coordinator</div>
      </td>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Reviewed by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $acidaaName ?? '' }}</b></div>
        <div class="wat-signatory-position">Assistant CID Chief for Academic Affairs</div>
      </td>
      <td class="wat-signatory">
        <div class="wat-signatory-caption">Approved by:</div>
        <div class="wat-signature-line">&nbsp;</div>
        <div class="wat-signatory-name"><b>{{ $cidChiefName ?? '' }}</b></div>
        <div class="wat-signatory-position">CID Chief</div>
      </td>
    </tr>
  </table>

  @if($wat['review'])
    <p class="wat-reviewed">
      Reviewed by {{ $wat['review']->reviewedBy?->name }} on
      {{ \Carbon\Carbon::parse($wat['review']->reviewed_at)->format('F j, Y') }}
      @if($wat['review']->remarks) — &ldquo;{{ $wat['review']->remarks }}&rdquo; @endif
    </p>
  @endif

</div>
</body>
</html>
