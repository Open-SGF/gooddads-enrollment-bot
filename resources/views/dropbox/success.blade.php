<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dropbox Connected</title>
</head>
<body>
    <main>
        <h1>Dropbox Connected</h1>
        <p>The application can now upload participant PDFs to Dropbox.</p>
        <p>You can now close this tab.</p>
        @if (is_string($connectedEmail) && $connectedEmail !== '')
            <p>Connected account: {{ $connectedEmail }}</p>
        @endif
    </main>
</body>
</html>