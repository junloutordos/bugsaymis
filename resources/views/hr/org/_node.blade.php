<div class="node">
  <div class="node-row depth-{{ min($depth, 5) }}">
    <span class="badge badge-{{ $node['type'] }}">{{ $node['type'] }}</span>
    <span class="node-name">{{ $node['name'] }}</span>
    <span class="node-code">{{ $node['code'] }}</span>
    @if (!empty($node['current_head'][0]['user']['name']))
      <span class="node-head">{{ $node['current_head'][0]['user']['name'] }}</span>
    @endif
  </div>

  @if (!empty($node['children']))
    <div class="children">
      @foreach ($node['children'] as $child)
        @include('hr.org._node', ['node' => $child, 'depth' => $depth + 1])
      @endforeach
    </div>
  @endif
</div>
