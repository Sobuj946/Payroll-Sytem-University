<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Salary slip</title>
    @include('payslips._styles')
    <style>
        @page { margin: 18mm 14mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; margin: 0; }
        .slip { padding: 0; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    @foreach ($slips as $slip)
        @include('payslips._slip')
        @unless ($loop->last) <div class="page-break"></div> @endunless
    @endforeach
</body>
</html>
