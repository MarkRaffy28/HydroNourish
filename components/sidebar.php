<?php
  $active_page = $active_page ?? 'home';
  function nav_active(string $page, string $active_page): string {
      return $page === $active_page ? ' active' : '';
  }
?>

<button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
  <i class="fas fa-bars"></i>
</button>

<nav class="sidebar" id="sidebar">
  <!-- LOGO -->
  <div class="sidebar-logo">
    <div class="logo-tomato">🍅</div>
    <div class="logo-text">
      <div class="logo-title">Hydro<span>Nourish</span></div>
    </div>
  </div>

  <!-- NAV LINKS -->
  <div class="sidebar-nav">
    <div class="nav-section-label">Overview</div>

    <a href="index.php"
       class="nav-link<?= nav_active('home', $active_page) ?>"
       id="nav-home"
       onclick="setActiveNav('nav-home')">
      <i class="fas fa-house"></i> Home
    </a>

    <a href="dashboard.php"
       class="nav-link<?= nav_active('dashboard', $active_page) ?>"
       id="nav-dashboard"
       onclick="setActiveNav('nav-dashboard')">
      <i class="fas fa-gauge-high"></i> Dashboard
    </a>

    <a href="gsm.php"
       class="nav-link<?= nav_active('gsm', $active_page) ?>"
       id="nav-gsm"
       onclick="setActiveNav('nav-gsm')">
      <i class="fas fa-mobile-screen-button"></i> GSM SMS
    </a>

    <div class="nav-section-label">Controls</div>

    <a href="monitoring.php"
       class="nav-link<?= nav_active('monitoring', $active_page) ?>"
       id="nav-monitoring"
       onclick="setActiveNav('nav-monitoring')">
      <i class="fas fa-wifi"></i> Monitoring
    </a>

    <a href="schedule.php"
       class="nav-link<?= nav_active('scheduling', $active_page) ?>"
       id="nav-scheduling"
       onclick="setActiveNav('nav-scheduling')">
      <i class="fas fa-calendar-days"></i> Scheduling
    </a>

    <a href="controls.php"
       class="nav-link<?= nav_active('controls', $active_page) ?>"
       id="nav-controls"
       onclick="setActiveNav('nav-controls')">
      <i class="fas fa-sliders"></i> System Controls
    </a>

    <div class="nav-section-label">Admin</div>

    <a href="mis.php"
       class="nav-link<?= nav_active('users', $active_page) ?>"
       id="nav-users"
       onclick="setActiveNav('nav-users')">
      <i class="fas fa-users"></i> Manage Users
    </a>
  </div>

  <!-- FOOTER -->
  <div class="sidebar-footer">
    <?php 
      $user_id = $_SESSION['user_id'] ??= 0;
      $stmt = $conn->query("SELECT username FROM users WHERE id = $user_id");
      $username = $stmt->fetch_assoc()['username'];
    ?>
    <div class="user-chip">
      <div class="user-avatar"><?= strtoupper(substr($username ?? 'U', 0, 1)) ?></div>
      <div class="user-name"><?= $username ?? 'User' ?></div>
    </div>
    <form method="post" action="logout.php">
      <button type="submit" class="btn-logout"> 
        <i class="fas fa-right-from-bracket"></i> Log Out
      </button>
    </form>
  </div>
</nav>