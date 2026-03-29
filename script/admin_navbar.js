(function () {

  // Admin search
  const searchInput = document.getElementById('adminSearchInput');

  searchInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && searchInput.value.trim()) {
      window.location.href =
        'admin_search.php?q=' + encodeURIComponent(searchInput.value.trim());
    }
  });

})();

function openLogoutModal() {
  document.getElementById("logoutModal").style.display = "flex";
}

function closeLogoutModal() {
  document.getElementById("logoutModal").style.display = "none";
}

// close when clicking outside
window.onclick = function(e) {
  let modal = document.getElementById("logoutModal");
  if (e.target === modal) {
    modal.style.display = "none";
  }
};