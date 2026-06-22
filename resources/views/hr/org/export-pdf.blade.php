<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1e293b; }

  .header { text-align: center; margin-bottom: 18pt; border-bottom: 2pt solid #4f46e5; padding-bottom: 10pt; }
  .header h1 { font-size: 14pt; font-weight: bold; color: #1e1b4b; letter-spacing: 0.5pt; }
  .header p  { font-size: 8pt; color: #64748b; margin-top: 3pt; }

  /* Tree */
  .tree { margin-top: 6pt; }
  .node { margin-bottom: 2pt; }
  .node-row { display: flex; align-items: baseline; gap: 6pt; padding: 3pt 4pt; border-radius: 3pt; }
  .node-row.depth-0 { background: #eef2ff; margin-bottom: 4pt; }
  .node-row.depth-1 { padding-left: 14pt; }
  .node-row.depth-2 { padding-left: 26pt; }
  .node-row.depth-3 { padding-left: 38pt; }
  .node-row.depth-4 { padding-left: 50pt; }
  .node-row.depth-5 { padding-left: 62pt; }

  .badge {
    font-size: 6.5pt; font-weight: bold; text-transform: uppercase;
    padding: 1pt 4pt; border-radius: 2pt; white-space: nowrap;
  }
  .badge-institution { background: #ede9fe; color: #5b21b6; }
  .badge-division    { background: #dbeafe; color: #1d4ed8; }
  .badge-department  { background: #cffafe; color: #0e7490; }
  .badge-section     { background: #d1fae5; color: #065f46; }
  .badge-unit        { background: #d1fae5; color: #065f46; }
  .badge-office      { background: #fef3c7; color: #92400e; }
  .badge-committee   { background: #fce7f3; color: #9d174d; }

  .node-name { font-size: 9pt; font-weight: 600; flex: 1; }
  .node-code { font-size: 7.5pt; color: #94a3b8; font-family: 'Courier New', monospace; }
  .node-head { font-size: 7.5pt; color: #64748b; font-style: italic; }

  .children { margin-left: 0; }

  .footer { margin-top: 14pt; border-top: 1pt solid #e2e8f0; padding-top: 6pt;
            text-align: right; font-size: 7.5pt; color: #94a3b8; }
</style>
</head>
<body>

<div class="header">
  <h1>Organizational Structure</h1>
  <p>Generated {{ $generatedAt }} &bull; Philippine Science High School</p>
</div>

<div class="tree">
  @foreach ($tree as $root)
    @include('hr.org._node', ['node' => $root, 'depth' => 0])
  @endforeach
</div>

<div class="footer">
  Printed from Atlas &bull; {{ $generatedAt }}
</div>

</body>
</html>
