document.addEventListener('DOMContentLoaded', () => {
  const sidebarToggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('overlay');

  if (sidebarToggle && sidebar && overlay) {
    function toggleSidebar() {
      sidebar.classList.toggle('active');
      overlay.classList.toggle('active');
    }

    sidebarToggle.addEventListener('click', toggleSidebar);
    overlay.addEventListener('click', toggleSidebar);
  }
});
