<?php
  session_start();
  require_once 'tomato_db.php';

  // Destroy the session
  $_SESSION = [];
  if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
          session_name(), '', time() - 42000,
          $params['path'], $params['domain'],
          $params['secure'], $params['httponly']
      );
  }

  session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Logged Out — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link rel="stylesheet" href="css/logout.css">
  <script src="js/jquery.min.js"></script>
</head>
<body>

  <aside class="panel-left" aria-hidden="true">
    <span class="floater">🍅</span>
    <span class="floater">🌿</span>
    <span class="floater">💧</span>
    <span class="floater">🌱</span>
    <span class="floater">🪴</span>

    <div class="brand">
      <span class="tomato-icon">🍅</span>
      <h1>Tomato<br/><span>Cultivation</span></h1>
      <p>IoT-based Automated Irrigation & Fertigation System</p>
    </div>

    <div class="stats">
      <div class="stat">
        <span class="stat-val">24/7</span>
        <span class="stat-lbl">Monitoring</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat">
        <span class="stat-val">Auto</span>
        <span class="stat-lbl">Irrigation</span>
      </div>
      <div class="stat-divider"></div>
      <div class="stat">
        <span class="stat-val">IoT</span>
        <span class="stat-lbl">Sensors</span>
      </div>
    </div>
  </aside>

  <main class="panel-right">
    <div class="form-wrapper">

      <div class="form-header">
        <span class="eyebrow"><i class="fas fa-seedling"></i> Session Ended</span>
        <h2>See you soon!</h2>
        <p>You've been safely logged out of your account</p>
      </div>

      <div class="logout-card">
        <div class="logout-icon-wrap">
          <i class="fas fa-check"></i>
        </div>
        <h3>Logged out successfully</h3>
        <p>Your session has been securely terminated. All cultivation data has been saved.</p>

        <div class="countdown-bar-wrap">
          <div class="countdown-bar" id="countdownBar"></div>
        </div>
        <p class="countdown-label">Redirecting in <span id="countdownNum">5</span>s…</p>
      </div>

      <a href="login.php" class="btn-submit">
        <span class="btn-content">
          <i class="fas fa-arrow-right-to-bracket"></i>
          Log In Again
        </span>
      </a>

      <div class="divider"><span>or</span></div>

      <p class="register-link">
        New user?
        <a href="register.php">Create an account</a>
      </p>

    </div>
  </main>

  <script>
    let seconds = 5;
    const $numEl = $('#countdownNum');

    const timer = setInterval(() => {
      seconds--;
      $numEl.text(seconds);

      if (seconds <= 0) {
        clearInterval(timer);
        window.location.href = 'login.php';
      }
    }, 1000);
  </script>
</body>
</html>