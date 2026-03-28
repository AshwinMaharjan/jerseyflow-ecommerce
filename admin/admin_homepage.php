<?php if(isset($_SESSION['popup'])): ?>
  <script src="script/admin_homepage.js"></script>
<?php unset($_SESSION['popup']); endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Homepage | JerseyFlow</title>
    <link rel="icon" href="../images/logo_icon.ico" type="image/x-icon">
</head>
<body>
    <?php include ("admin_navbar.php")?>
    <?php include ("admin_menu.php")?>
</body>
</html>