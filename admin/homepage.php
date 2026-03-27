<?php if(isset($_SESSION['popup'])): ?>
<script>
  window.onload = function() {
    showPopup(
      "<?= $_SESSION['popup']['type']; ?>",
      "<?= $_SESSION['popup']['message']; ?>"
    );
  }
</script>
<?php unset($_SESSION['popup']); endif; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <?php include ("admin_navbar.php")?>
</body>
</html>