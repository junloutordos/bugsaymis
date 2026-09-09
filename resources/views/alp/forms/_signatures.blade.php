{{--
    Shared signature grid.
    $rows = array of rows; each row = array of 1 or 2 cells.
    Each cell = ['action' => 'Prepared by:', 'name' => ?string, 'role' => 'ALP Adviser', 'date' => null|string|true]
    A cell's 'date' as true prints a blank "Date:" line; a string prints that formatted date.
--}}
<table class="sig-table" style="margin-top:26px">
    @foreach(($rows ?? []) as $row)
    <tr>
        @foreach($row as $cell)
        <td style="width: {{ (int) floor(100 / count($row)) }}%">
            <div class="sig-action">{{ $cell['action'] }}</div>
            <div class="sig-line">&nbsp;</div>
            @if(!empty($cell['name']))<div class="sig-name">{{ $cell['name'] }}</div>@endif
            <div class="sig-role">{{ $cell['role'] }}</div>
            @if(!empty($cell['date']))
                <div class="sig-date">Date: {{ $cell['date'] === true ? '' : $cell['date'] }}</div>
            @endif
        </td>
        @endforeach
    </tr>
    @endforeach
</table>
