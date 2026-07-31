<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111827; }
        h1 { font-size: 13pt; margin: 2px 0; text-align: center; }
        h2 { font-size: 11pt; margin: 12px 0 5px; }
        h3 { font-size: 10pt; margin: 9px 0 4px; }
        p { margin: 4px 0; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; margin: 7px 0; }
        th, td { border: 0.6px solid #64748b; padding: 5px; vertical-align: top; }
        th { background: #eef2ff; font-weight: bold; }
        .header { text-align: center; margin-bottom: 14px; }
        .meta { font-size: 8pt; color: #475569; }
        .label { font-weight: bold; }
        .signature { width: 48%; display: inline-block; margin-top: 24px; vertical-align: top; }
        .footer { position: fixed; bottom: 0; width: 100%; border-top: 1px solid #111; padding-top: 3px; font-size: 7pt; }
        .page-break { page-break-before: always; }
        ul { margin: 4px 0 4px 18px; }
    </style>
</head>
<body>
@include('alp.document-content', ['document' => $document, 'cycle' => $cycle])
</body>
</html>
