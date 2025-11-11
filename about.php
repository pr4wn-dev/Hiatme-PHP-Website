<?php
require_once 'includes/hiatme_config.php';
require_once 'includes/menu_config.php';
$csrf_token = $hiatme_methods->GetCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About - Hiatme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="styles/main.css">
</head>
<body>
    <header>
        <?php echo renderMenu($hiatme_methods); ?>
    </header>
    <main>
        <h1>About Hiatme</h1>
        <p>This is the about page.</p>
    </main>
    <script src="scripts/menu.js"></script>
</body>
</html>