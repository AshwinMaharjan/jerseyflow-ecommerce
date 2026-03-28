  window.onload = function() {
    showPopup(
      "<?= $_SESSION['popup']['type']; ?>",
      "<?= $_SESSION['popup']['message']; ?>"
    );
  }
