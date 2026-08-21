<td width="9%" class="wat-center">{{ $item['time_label'] ?? '—' }}</td>
<td width="13%">{{ $item['subject_name'] }}</td>
<td>
  <span class="wat-title">{{ $item['title'] }}</span>
</td>
<td width="14%">{{ $item['type_label'] }}</td>
<td width="8%">{{ $item['is_graded'] ? 'Graded' : 'Non-graded' }}</td>
<td width="12%">{{ $item['teacher_name'] }}</td>
<td width="9%" class="wat-center">{{ $item['compliance'] !== null ? $item['compliance'].'%' : '—' }}</td>
