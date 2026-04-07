<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Incomplete Intake Form</title>
</head>
<body>
    <h1>Incomplete Intake Form</h1>

    <p>Hello,</p>

    <p>The intake form for participant <strong>{{ $participant->fullName() }}</strong> is incomplete. The following fields are missing:</p>

    <ul>
        @foreach ($missingFields as $section => $fields)
            <li>
                <strong>{{ $section }}</strong>
                <ul>
                    @foreach ($fields as $field)
                        <li>{{ $field }}</li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ul>

    <p>Please review and resubmit in order to generate the PDF forms.</p>

    <p>Thank you!</p>
</body>
</html>