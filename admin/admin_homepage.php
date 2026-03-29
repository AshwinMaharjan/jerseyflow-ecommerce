<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Homepage | JerseyFlow</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
    <link rel="stylesheet" href="../assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="../style/footer.css">
    <link rel="stylesheet" href="../style/admin_menu.css">
</head>
<body>

    <?php include 'admin_navbar.php'; ?>

    <div class="page-wrapper">
        <?php include 'admin_menu.php'; ?>

        <div class="main-content">
            <h1>Dashboard</h1>
            <p>Welcome to the admin panel.</p>
        </div>
    </div>

    <?php include 'footer.php'; ?>

</body>
</html>