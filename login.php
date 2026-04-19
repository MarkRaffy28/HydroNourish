<?php
  session_start();
  require_once 'config/db.php';

  $error = '';

  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }

  if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
  }

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      $error = 'Invalid request. Please try again.';

    } else {
      $username_input   = trim($_POST['username'] ?? '');
      $password_raw     = $_POST['password'] ?? '';          
      $password_trimmed = trim($password_raw);               

      if (empty($username_input) || empty($password_raw)) {
        $error = 'Please enter both username and password.';

      } elseif (!isset($conn) || $conn->connect_error) {
        $error = 'Database connection error. Please try again later.';

      } else {
        $stmt = $conn->prepare(
          "SELECT id, username, password FROM users WHERE username = ? LIMIT 1"
        );

        if (!$stmt) {
          $error = 'System error: ' . $conn->error;
        } else {
          $stmt->bind_param('s', $username_input);
          $stmt->execute();
          $result = $stmt->get_result();

          if ($result && $result->num_rows > 0) {
            $user       = $result->fetch_assoc();
            $passwordOk = false;

            if (password_verify($password_raw, $user['password'])) {
              $passwordOk = true;

            } elseif (password_verify($password_trimmed, $user['password'])) {
              $passwordOk = true;

            } elseif ($password_raw === $user['password'] || $password_trimmed === $user['password']) {
              $newHash    = password_hash($password_raw, PASSWORD_DEFAULT);
              $upgradeStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
              if ($upgradeStmt) {
                $upgradeStmt->bind_param('si', $newHash, $user['id']);
                $upgradeStmt->execute();
                $upgradeStmt->close();
              }
              $passwordOk = true;
            }

            if ($passwordOk) {
              session_regenerate_id(true);
              $_SESSION['user_id']  = $user['id'];
              $_SESSION['username'] = $user['username'];

              header('Location: index.php');
              exit;

            } else {
              $error = 'Invalid username or password.';
            }

          } else {
            $error = 'Invalid username or password.';
          }

          $stmt->close();
        }
      }
    }
  }

  $savedUsername = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
  $csrfToken     = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <link rel="stylesheet" href="css/login.css">

    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
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
          <span class="stat-lbl">Irrigation % Fertigation</span>
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
          <span class="eyebrow"><i class="fas fa-seedling"></i> Secure Access</span>
          <h2>Welcome back</h2>
          <p>Log in to your HydroNourish dashboard</p>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger" role="alert">
            <i class="fas fa-circle-exclamation"></i>
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered']) && $_GET['registered'] === '1'): ?>
          <div class="alert alert-success" role="alert">
            <i class="fas fa-circle-check"></i>
            Account created successfully! You can now log in.
          </div>
        <?php endif; ?>

        <form method="POST" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

          <div class="form-group">
            <label for="username">Username or Email</label>
            <div class="input-wrap">
              <i class="fas fa-user icon-left"></i>
              <input
                type="text"
                id="username"
                name="username"
                required
                placeholder="Enter your username or email"
                value="<?= $savedUsername ?>"
                autocomplete="username"
                autofocus
              />
            </div>
          </div>

          <div class="form-group">
            <label for="password">Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock icon-left"></i>
              <input
                type="password"
                id="password"
                name="password"
                required
                placeholder="Enter your password"
                autocomplete="current-password"
              />
              <button type="button" class="btn-eye" onclick="togglePassword()" aria-label="Toggle password visibility">
                <i class="fas fa-eye" id="toggleIcon"></i>
              </button>
            </div>
          </div>

          <button type="submit" name="login" class="btn-submit">
            <span class="btn-content">
              <i class="fas fa-arrow-right-to-bracket"></i>
              Log In
            </span>
          </button>
        </form>

        <div class="divider"><span>New to the system?</span></div>

        <p class="register-link">
          Don't have an account?
          <a href="register.php">Create one here</a>
        </p>

      </div>
    </main>
  </body>
</html>