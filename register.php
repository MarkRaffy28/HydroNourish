<?php
  session_start();
  require_once 'config/db.php';

  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }

  if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
  }

  $fields = [
    'name'             => '',
    'email'            => '',
    'username'         => '',
    'password'         => '',
    'confirm_password' => '',
  ];
  $errors  = array_fill_keys(array_keys($fields), '');

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
      $errors['name'] = 'Invalid request. Please refresh and try again.';
    } else {
      $fields['name']             = trim($_POST['name']             ?? '');
      $fields['email']            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
      $fields['username']         = trim($_POST['username']         ?? '');
      $fields['password']         = $_POST['password']         ?? '';
      $fields['confirm_password'] = $_POST['confirm_password'] ?? '';

      if (empty($fields['name'])) {
        $errors['name'] = 'Full name is required.';
      } elseif (strlen($fields['name']) < 2 || strlen($fields['name']) > 100) {
        $errors['name'] = 'Name must be between 2 and 100 characters.';
      }

      if (empty($fields['email'])) {
        $errors['email'] = 'Email address is required.';
      } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
      } else {
        $emailChk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $emailChk->bind_param('s', $fields['email']);
        $emailChk->execute();
        $emailChk->store_result();
        if ($emailChk->num_rows > 0) {
          $errors['email'] = 'An account with that email already exists.';
        }
        $emailChk->close();
      }

      if (empty($fields['username'])) {
        $errors['username'] = 'Username is required.';
      } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $fields['username'])) {
        $errors['username'] = 'Username may only contain letters, numbers, and underscores.';
      } elseif (strlen($fields['username']) < 3 || strlen($fields['username']) > 50) {
        $errors['username'] = 'Username must be between 3 and 50 characters.';
      } else {
        $chkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chkStmt->bind_param('s', $fields['username']);
        $chkStmt->execute();
        $chkStmt->store_result();
        if ($chkStmt->num_rows > 0) {
          $errors['username'] = 'That username is already taken.';
        }
        $chkStmt->close();
      }

      if (empty($fields['password'])) {
        $errors['password'] = 'Password is required.';
      } elseif (strlen($fields['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters.';
      }

      if (empty($fields['confirm_password'])) {
        $errors['confirm_password'] = 'Please confirm your password.';
      } elseif (empty($errors['password']) && $fields['password'] !== $fields['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match.';
      }

      if (!array_filter($errors)) {
        $hashedPassword = password_hash($fields['password'], PASSWORD_DEFAULT);
        $insertStmt = $conn->prepare(
          "INSERT INTO users (name, email, username, password, created_at)
          VALUES (?, ?, ?, ?, NOW())"
        );
        $insertStmt->bind_param(
          'ssss',
          $fields['name'],
          $fields['email'],
          $fields['username'],
          $hashedPassword
        );

        if ($insertStmt->execute()) {
          header('Location: login.php?registered=1');
          exit;
        } else {
          $errors['email'] = 'Something went wrong. Please try again.';
        }
        $insertStmt->close();
      }
    }
  }

  $safe      = array_map(fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), $fields);
  $csrfToken = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Register | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;1,9..40,300&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    
    
    <link rel="stylesheet" href="css/register.css">
    <script src="js/app.js"></script>
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
        <h1>Hydro<br/><span>Nourish</span></h1>
        <p>IoT-based Automated Irrigation &amp; Fertigation System</p>
      </div>

      <div class="feature-list">
        <div class="feature-item">
          <div class="fi-icon"><i class="fas fa-droplet"></i></div>
          Real-time soil moisture tracking
        </div>
        <div class="feature-item">
          <div class="fi-icon"><i class="fas fa-spray-can-sparkles"></i></div>
          Automated Irrigation & Fertigation Scheduling
        </div>
        <div class="feature-item">
          <div class="fi-icon"><i class="fas fa-chart-line"></i></div>
          Sensor data logging and graphing
        </div>
        <div class="feature-item">
          <div class="fi-icon"><i class="fas fa-bell"></i></div>
          GSM notification messages
        </div>
      </div>
    </aside>

    <main class="panel-right">
      <div class="form-wrapper">

        <div class="form-header">
          <span class="eyebrow"><i class="fas fa-seedling"></i> Create Account</span>
          <h2>Join the system</h2>
          <p>Fill in your details to get started</p>
        </div>

        <form method="POST" action="" novalidate>
          <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

          <div class="form-grid">

            <!-- Full Name -->
            <div class="form-group span-2">
              <label for="name">Full Name</label>
              <div class="input-wrap">
                <i class="fas fa-id-card icon-left"></i>
                <input
                  type="text"
                  id="name"
                  name="name"
                  placeholder="Enter your full name"
                  value="<?= $safe['name'] ?>"
                  autocomplete="name"
                  class="<?= $errors['name'] ? 'is-invalid' : '' ?>"
                  autofocus
                />
              </div>
              <?php if ($errors['name']): ?>
                <span class="field-error">
                  <i class="fas fa-circle-exclamation"></i>
                  <?= htmlspecialchars($errors['name'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- Email -->
            <div class="form-group span-2">
              <label for="email">Email Address</label>
              <div class="input-wrap">
                <i class="fas fa-envelope icon-left"></i>
                <input
                  type="email"
                  id="email"
                  name="email"
                  placeholder="you@gmail.com"
                  value="<?= $safe['email'] ?>"
                  autocomplete="email"
                  class="<?= $errors['email'] ? 'is-invalid' : '' ?>"
                />
              </div>
              <?php if ($errors['email']): ?>
                <span class="field-error">
                  <i class="fas fa-circle-exclamation"></i>
                  <?= htmlspecialchars($errors['email'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- Username -->
            <div class="form-group span-2">
              <label for="username">Username</label>
              <div class="input-wrap">
                <i class="fas fa-user icon-left"></i>
                <input
                  type="text"
                  id="username"
                  name="username"
                  placeholder="letters, numbers, underscores"
                  value="<?= $safe['username'] ?>"
                  autocomplete="username"
                  class="<?= $errors['username'] ? 'is-invalid' : '' ?>"
                />
              </div>
              <?php if ($errors['username']): ?>
                <span class="field-error">
                  <i class="fas fa-circle-exclamation"></i>
                  <?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- Password -->
            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrap">
                <i class="fas fa-lock icon-left"></i>
                <input
                  type="password"
                  id="password"
                  name="password"
                  placeholder="Min. 8 characters"
                  autocomplete="new-password"
                  class="<?= $errors['password'] ? 'is-invalid' : '' ?>"
                  oninput="updateStrength(this.value)"
                />
                <button type="button" class="btn-eye" onclick="togglePwd('password','eyePass')" aria-label="Toggle password">
                  <i class="fas fa-eye" id="eyePass"></i>
                </button>
              </div>
              <div class="strength-bar">
                <span id="s1"></span><span id="s2"></span><span id="s3"></span><span id="s4"></span>
              </div>
              <div class="strength-label" id="strengthLabel"></div>
              <?php if ($errors['password']): ?>
                <span class="field-error">
                  <i class="fas fa-circle-exclamation"></i>
                  <?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <div class="input-wrap">
                <i class="fas fa-shield-halved icon-left"></i>
                <input
                  type="password"
                  id="confirm_password"
                  name="confirm_password"
                  placeholder="Repeat password"
                  autocomplete="new-password"
                  class="<?= $errors['confirm_password'] ? 'is-invalid' : '' ?>"
                />
                <button type="button" class="btn-eye" onclick="togglePwd('confirm_password','eyeConfirm')" aria-label="Toggle confirm password">
                  <i class="fas fa-eye" id="eyeConfirm"></i>
                </button>
              </div>
              <?php if ($errors['confirm_password']): ?>
                <span class="field-error">
                  <i class="fas fa-circle-exclamation"></i>
                  <?= htmlspecialchars($errors['confirm_password'], ENT_QUOTES, 'UTF-8') ?>
                </span>
              <?php endif; ?>
            </div>

          </div>

          <button type="submit" name="register" class="btn-submit">
            <span class="btn-content">
              <i class="fas fa-user-plus"></i>
              Create Account
            </span>
          </button>

          <p class="terms-note">
            By registering you agree to the system's terms of use &amp; privacy policy.
          </p>
        </form>

        <div class="divider"><span>Already have an account?</span></div>
        <p class="login-link">
          <a href="login.php"><i class="fas fa-arrow-left-to-bracket"></i> Log in here</a>
        </p>

      </div>
    </main>
  </body>
</html>