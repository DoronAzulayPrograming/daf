<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="/public/daf-logos/daf-logo-noslogan-white.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="/public/bootstrap-5.3.3-dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="/public/app.css" rel="stylesheet" />
    
    <HeadOutlet />
</head>
<body>
    
    <?= $this->RenderChildContent() ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <DafJs />
    <ScriptsOutlet />
</body>
</html>