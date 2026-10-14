<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Intake Form</title>
</head>
<body>
    <h1>Intake Form Submitted</h1>

    <p>Hello,</p>

    <p>A new intake form has been generated for <strong>{{ $participant->fullName() }}</strong>.</p>

    @if ($version)
        <p>PDF version: {{ $version->version }}</p>
        <p>Filename: {{ $version->filename }}</p>

        @if ($version->version === 1)
            <p>This is the first tracked version.</p>
        @elseif ($version->changed_fields === [])
            <p>No PDF fields changed since the previous version.</p>
        @else
            <p>Fields changed since the previous version:</p>
            <ul>
                @foreach ($version->changed_fields as $field)
                    <li>{{ $field }}</li>
                @endforeach
            </ul>
        @endif
    @endif

    <p>Thank you!</p>
</body>
</html>
