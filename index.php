<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Home | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/index.css">

    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
  </head>
  <body>
    <div class="layout">

      <?php
        $active_page = 'home';
        include 'components/sidebar.php';
      ?>

      <div class="main">
        <button class="mobile-toggle home-toggle" onclick="toggleSidebar()">
          <i class="fas fa-bars"></i>
        </button>

        <div class="page-view active" id="view-home">
          <div class="landing-hero">
            <div class="landing-bg-grid"></div>
            <div class="landing-tomato-bg">🍅</div>
            <div class="landing-content">
              <div class="landing-eyebrow">Automated Irrigation and Fertigation</div>
              <h1 class="landing-title">Hydro <span class="accent">Nourish</span></h1>
              <p class="landing-desc">Solar-powered IoT automation for precision irrigation, fertigation, and real-time sensor monitoring. Grow smarter, grow greener.</p>
              <div class="landing-actions">
                <a href="dashboard.php" class="btn-landing-primary"><i class="fas fa-gauge-high"></i> Open Dashboard</a>
                <a href="monitoring.php" class="btn-landing-ghost"><i class="fas fa-wifi"></i> View Sensors</a>
              </div>
              <div class="landing-features">
                <div class="landing-feature-card">
                  <div class="feature-icon solar"><i class="fas fa-solar-panel"></i></div>
                  <div class="feature-card-title">Solar Powered</div>
                  <div class="feature-card-desc">100% renewable energy with battery backup</div>
                </div>
                <div class="landing-feature-card">
                  <div class="feature-icon water"><i class="fas fa-water"></i></div>
                  <div class="feature-card-title">Smart Irrigation</div>
                  <div class="feature-card-desc">Moisture-triggered automated watering</div>
                </div>
                <div class="landing-feature-card">
                  <div class="feature-icon green"><i class="fas fa-spray-can"></i></div>
                  <div class="feature-card-title">Auto Fertigation</div>
                  <div class="feature-card-desc">Scheduled fertigation (Ammonium & Complete)</div>
                </div>
                <div class="landing-feature-card">
                  <div class="feature-icon red"><i class="fas fa-wifi"></i></div>
                  <div class="feature-card-title">IoT Network</div>
                  <div class="feature-card-desc">12 sensors monitoring farm live</div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </body>
</html>