<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
      header('Location: login.php');
      exit;
  }

  // ── AJAX HANDLER ──
  if (isset($_GET['ajax'])) {
      header('Content-Type: application/json');
      $type = $_GET['type'] ?? 'latest';
      if ($type === 'latest') {
          $result = $conn->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 1");
          $data = $result ? $result->fetch_assoc() : [];
          echo json_encode($data ?: (object)[]);
          exit;
      }
  }

  $username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');

  // Fetch latest sensor status
  $latest_row = null;
  $sensor_res = $conn->query("SELECT * FROM sensor_logs ORDER BY created_at DESC LIMIT 1");
  if ($sensor_res && $sensor_res->num_rows > 0) {
      $latest_row = $sensor_res->fetch_assoc();
  }

  $totalSensors = 8;
  $errCount = 0;
  $erroredSensors = [];

  if ($latest_row) {
      $sensors = [
          'Soil-Node-01' => ['err' => $latest_row['soil_error'], 'code' => 'ERR-S01', 'note' => 'Soil moisture sensor timeout or out of range.'],
          'Water-Node-01' => ['err' => $latest_row['water_error'], 'code' => 'ERR-W01', 'note' => 'Ultrasonic water level sensor disconnected.'],
          'Fert-Node-01'  => ['err' => $latest_row['fert_error'], 'code' => 'ERR-F01', 'note' => 'Fertilizer level sensor reading error.'],
          'Env-Temp-01'   => ['err' => $latest_row['temp_error'], 'code' => 'ERR-T01', 'note' => 'DHT/BME temperature sensor error.'],
          'Env-Hum-01'    => ['err' => $latest_row['hum_error'], 'code' => 'ERR-H01', 'note' => 'DHT/BME humidity sensor error.'],
          'Env-Pres-01'   => ['err' => $latest_row['pres_error'], 'code' => 'ERR-P01', 'note' => 'BME pressure sensor reading failure.'],
          'GSM-Modem'     => ['err' => ($latest_row['gsm_error'] == 1 || $latest_row['gsm_signal'] == -1) ? 1 : 0, 'code' => 'ERR-G01', 'note' => 'GSM SIM800L module signal lost or error.'],
          'RTC-Module'    => ['err' => $latest_row['rtc_error'],  'code' => 'ERR-R01', 'note' => 'Real Time Clock sync failure.']
      ];

      foreach ($sensors as $name => $s) {
          if ($s['err'] == 1) {
              $errCount++;
              $erroredSensors[$name] = [
                  'error_code' => $s['code'],
                  'error_note' => $s['note']
              ];
          }
      }
  }
  $activeCount = $totalSensors - $errCount;

  $upcoming_schedules = [];
  $user_id = $_SESSION['user_id'];
  $sched_result = $conn->query("
      SELECT id, schedule_date, task_type, task_name, task_time, task_notes 
      FROM farm_schedules 
      WHERE user_id = $user_id AND schedule_date >= CURDATE() 
      ORDER BY schedule_date, task_time 
      LIMIT 5
  ");
  if ($sched_result && $sched_result->num_rows > 0) {
      while ($row = $sched_result->fetch_assoc()) {
          $upcoming_schedules[] = $row;
      }
  }

  $dash_cal_months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  $dash_current_month = $dash_cal_months[date('n') - 1] . ' ' . date('Y');

  $legend_counts = ['irrigation' => 0, 'fertigation' => 0, 'harvest' => 0, 'maintenance' => 0];
  $legend_result = $conn->query("SELECT task_type, COUNT(*) as cnt FROM farm_schedules WHERE user_id = $user_id AND schedule_date >= CURDATE() AND schedule_date < DATE_ADD(CURDATE(), INTERVAL 30 DAY) GROUP BY task_type");
  if ($legend_result && $legend_result->num_rows > 0) {
      while ($row = $legend_result->fetch_assoc()) {
          if (isset($legend_counts[$row['task_type']])) {
              $legend_counts[$row['task_type']] = (int) $row['cnt'];
          }
      }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  
  <link rel="stylesheet" href="css/app.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/monitor.css">

  <script src="js/jquery.min.js"></script>
  <script src="js/app.js"></script>
  <script src="js/dashboard.js"></script>
  <script src="js/env_soil_logic.js"></script>
</head>
<body>
<div class="layout">

  <?php
    $active_page = 'dashboard';
    include 'components/sidebar.php';
  ?>

  <div class="main">
    <?php 
      $page_title = "Dashboard";
      $page_icon = "fa-house";
      include "components/header.php" 
    ?>

    <!-- ══ DASHBOARD VIEW ══ -->
    <div class="page-view active" id="view-dashboard">
      <div class="page-content">

        <!-- HERO -->
        <div class="hero" style="margin-bottom:28px;">
          <div class="hero-tomato">🍅</div>
          <div class="hero-tag">Tomato Cultivation — Farm</div>
          <h1>System <span>Overview</span></h1>
          <p class="hero-sub">Live summary of your solar IoT farm: energy, water, automation, and upcoming tasks — all in one place.</p>
        </div>

        <!-- ALERT -->
        <?php if ($errCount > 0): ?>
        <div class="alert-bar" style="margin-bottom:28px;background:var(--red-pale);border-color:rgba(214,48,49,0.25);color:var(--red);">
          <i class="fas fa-triangle-exclamation" style="color:var(--red);"></i>
          <span><strong><?= $errCount ?> Sensor Error<?= $errCount > 1 ? 's' : '' ?> Detected</strong> — <?= $activeCount ?> of <?= $totalSensors ?> sensors reporting normally. Check System Health for details.</span>
          <a href="monitoring.php" style="margin-left:auto;font-size:0.78rem;color:var(--red);font-weight:700;text-decoration:none;white-space:nowrap;">View in Monitoring →</a>
        </div>
        <?php else: ?>
        <div class="alert-bar" style="margin-bottom:28px;">
          <i class="fas fa-circle-check"></i>
          <span><strong>System OK</strong> — All <?= $totalSensors ?> sensors reporting normally. Next irrigation cycle in 2h 14m. Battery charging.</span>
        </div>
        <?php endif; ?>

        <!-- ENVIRONMENT & SOIL SECTION -->
        <?php $show_meta = true; include 'components/env_soil_metrics.php'; ?>

        <!-- TANK LEVELS + QUICK LINKS -->
        <div class="page-section">
          <div class="grid-2">

            <!-- Tank Levels Card -->
            <div class="card tank-dashboard-card">
              <div class="card-head">
                <div class="card-head-label"><i class="fas fa-water"></i> Tank Levels</div>
              </div>
              <div class="card-body">
                <div class="tank-card-container">
                  <!-- Water Tank -->
                  <div class="tank-item">
                    <div class="tank-pct-value" id="water-pct" style="color: var(--water);">--%</div>
                    <div class="tank-visual-container water-type">
                      <div class="tank-liquid-fill" id="water-bar" style="height: 0%;"></div>
                      <div class="tank-marker m-60"><span>60%</span></div>
                      <div class="tank-marker m-30"><span>30%</span></div>
                    </div>
                    <div class="tank-name-label">
                      <i class="fas fa-tank-water" style="color: var(--water);"></i> Water Tank
                      <span id="water-dist" class="tank-dist-chip">-- cm</span>
                    </div>
                  </div>

                  <!-- Fertilizer Tank -->
                  <div class="tank-item">
                    <div class="tank-pct-value" id="fert-pct" style="color: var(--solar);">--%</div>
                    <div class="tank-visual-container">
                      <div class="tank-liquid-fill" id="fert-bar" style="height: 0%;"></div>
                      <div class="tank-marker m-60"><span>60%</span></div>
                      <div class="tank-marker m-30"><span>30%</span></div>
                    </div>
                    <div class="tank-name-label">
                      <i class="fas fa-flask" style="color: var(--solar);"></i> Fertilizer Tank
                      <span id="fert-dist" class="tank-dist-chip">-- cm</span>
                    </div>
                  </div>
                </div>
                
                <div style="margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border);">
                  <div class="kv-row">
                    <span class="key"><i class="fas fa-wifi" style="color:var(--green-mid)"></i> Sensors</span>
                    <?php if ($errCount > 0): ?>
                      <span style="display:flex;align-items:center;gap:6px;">
                        <span class="badge badge-green"><?= $activeCount ?> Online</span>
                        <span class="badge badge-red"><?= $errCount ?> Error<?= $errCount > 1 ? 's' : '' ?></span>
                      </span>
                    <?php else: ?>
                      <span class="badge badge-green"><?= $totalSensors ?> / <?= $totalSensors ?> Online</span>
                    <?php endif; ?>
                  </div>
                </div>
                
                <!-- Errored sensor list -->
                <?php if ($errCount > 0): ?>
                <div style="margin-top:12px;">
                  <div style="font-size:0.68rem;font-weight:700;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-triangle-exclamation" style="color:var(--red);"></i> Active Sensor Errors
                  </div>
                  <?php foreach ($erroredSensors as $nodeId => $s): ?>
                  <div style="display:flex;align-items:flex-start;gap:9px;margin-bottom:8px;padding:8px 10px;background:var(--red-pale);border:1px solid rgba(214,48,49,0.18);border-left:3px solid var(--red);border-radius:var(--radius-sm);">
                    <i class="fas fa-circle-xmark" style="color:var(--red);font-size:0.78rem;margin-top:2px;flex-shrink:0;"></i>
                    <div style="flex:1;min-width:0;">
                      <div style="display:flex;align-items:center;gap:7px;margin-bottom:3px;flex-wrap:wrap;">
                        <span style="font-family:var(--font-mono);font-size:0.7rem;font-weight:700;color:var(--red);"><?= htmlspecialchars($nodeId) ?></span>
                        <span style="font-family:var(--font-mono);font-size:0.65rem;background:#ffe0e0;color:var(--red);border-radius:4px;padding:1px 6px;"><?= htmlspecialchars($s['error_code']) ?></span>
                      </div>
                      <div style="font-size:0.72rem;color:var(--text-muted);line-height:1.4;"><?= htmlspecialchars($s['error_note']) ?></div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                  <div style="margin-top:6px;text-align:center;">
                    <a href="monitoring.php" style="font-size:0.78rem;color:var(--red);font-weight:600;text-decoration:none;">
                      <i class="fas fa-arrow-right" style="margin-right:5px;"></i>View in Monitoring →
                    </a>
                  </div>
                </div>
                <?php endif; ?>
              </div>
            </div>

            <!-- Quick Navigation -->
            <div class="card">
              <div class="card-head"><div class="card-head-label"><i class="fas fa-rocket"></i> Quick Access</div></div>
              <div class="card-body">
                <a href="monitoring.php" class="quick-link">
                  <div class="quick-link-icon" style="background:var(--green-pale);color:var(--green-mid);"><i class="fas fa-wifi"></i></div>
                  <div><div class="quick-link-title">IoT Monitoring</div><div class="quick-link-desc">Live sensor readings & alerts</div></div>
                  <i class="fas fa-arrow-right arr"></i>
                </a>
                <a href="schedule.php" class="quick-link">
                  <div class="quick-link-icon" style="background:var(--water-pale);color:var(--water);"><i class="fas fa-calendar-days"></i></div>
                  <div><div class="quick-link-title">Scheduling</div><div class="quick-link-desc">Irrigation & Fertigation calendar</div></div>
                  <i class="fas fa-arrow-right arr"></i>
                </a>
                <a href="mis.php" class="quick-link">
                  <div class="quick-link-icon" style="background:var(--red-pale);color:var(--red);"><i class="fas fa-users"></i></div>
                  <div><div class="quick-link-title">Manage Users</div><div class="quick-link-desc">User access &amp; roles</div></div>
                  <i class="fas fa-arrow-right arr"></i>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- UPCOMING TASKS + CALENDAR -->
        <div class="page-section">
          <div class="grid-2">

            <!-- Upcoming Tasks -->
            <div class="card">
              <div class="card-head">
                <div class="card-head-label"><i class="fas fa-list-check"></i> Upcoming Tasks</div>
                <a href="schedule.php" style="font-size:0.78rem;color:var(--green-mid);text-decoration:none;font-weight:600;">View All →</a>
              </div>
              <div class="card-body">
                <?php if (empty($upcoming_schedules)): ?>
                <div style="text-align:center;padding:20px 0;color:var(--text-muted);">
                  <i class="fas fa-calendar-xmark" style="font-size:1.5rem;margin-bottom:8px;display:block;opacity:0.5;"></i>
                  <p>No upcoming tasks scheduled.</p>
                </div>
                <?php else: ?>
                  <?php foreach ($upcoming_schedules as $sched): ?>
                  <?php 
                    $dotColor = 'var(--green-mid)';
                    $badgeClass = 'badge-green';
                    $badgeLabel = 'Task';
                    if ($sched['task_type'] === 'fertigation') { $dotColor = 'var(--solar)'; $badgeClass = 'badge-yellow'; $badgeLabel = 'Fertigation'; }
                    elseif ($sched['task_type'] === 'irrigation') { $dotColor = 'var(--water)'; $badgeClass = 'badge-blue'; $badgeLabel = 'Irrigation'; }
                    elseif ($sched['task_type'] === 'harvest') { $dotColor = 'var(--red)'; $badgeClass = 'badge-red'; $badgeLabel = 'Harvest'; }
                    elseif ($sched['task_type'] === 'maintenance') { $dotColor = 'var(--solar-lt)'; $badgeClass = 'badge-yellow'; $badgeLabel = 'Maintenance'; }
                    
                    $schedDate = strtotime($sched['schedule_date']);
                    $dateLabel = date('M j, Y', $schedDate);
                    if (date('Y-m-d') === date('Y-m-d', $schedDate)) $dateLabel = 'Today';
                    elseif (date('Y-m-d', strtotime('+1 day')) === date('Y-m-d', $schedDate)) $dateLabel = 'Tomorrow';
                    
                    $timeLabel = date('g:i A', strtotime($sched['task_time']));
                  ?>
                  <div class="activity-item">
                    <div class="activity-dot" style="background:<?= $dotColor ?>;box-shadow:0 0 6px <?= $dotColor ?>;"></div>
                    <div class="activity-content">
                      <div class="activity-title"><?= htmlspecialchars($sched['task_name']) ?></div>
                      <div class="activity-desc"><?= htmlspecialchars($sched['task_notes'] ?: $sched['task_type']) ?></div>
                      <div class="activity-time"><?= $dateLabel ?>, <?= $timeLabel ?></div>
                    </div>
                    <span class="badge <?= $badgeClass ?>" style="margin-top:3px;"><?= $badgeLabel ?></span>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top:16px;text-align:center;">
                  <a href="schedule.php" style="font-size:0.84rem;color:var(--green-mid);text-decoration:none;font-weight:600;"><i class="fas fa-plus" style="margin-right:6px;"></i>Add New Task</a>
                </div>
              </div>
            </div>

            <!-- Mini Calendar -->
            <div class="card">
              <div class="card-head">
                <div style="display:flex;align-items:center;justify-content:space-between;flex:1;">
                  <div class="card-head-label"><i class="fas fa-calendar"></i> <span id="dash-month-label"><?= $dash_current_month ?></span></div>
                  <div class="cal-nav">
                    <button onclick="dashChangeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                    <button onclick="dashChangeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                  </div>
                </div>
              </div>
              <div class="card-body calendar-body">
                <div class="cal-grid" id="dash-cal-grid"></div>
                <div class="cal-legend">
                  <?php if ($legend_counts['irrigation'] > 0): ?>
                  <div class="legend-item"><i class="fas fa-droplet" style="color:var(--water);"></i> Irrigation (<?= $legend_counts['irrigation'] ?>)</div>
                  <?php endif; ?>
                  <?php if ($legend_counts['fertigation'] > 0): ?>
                  <div class="legend-item"><i class="fas fa-spray-can" style="color:var(--solar);"></i> Fertigation (<?= $legend_counts['fertigation'] ?>)</div>
                  <?php endif; ?>
                  <?php if ($legend_counts['harvest'] > 0): ?>
                  <div class="legend-item"><i class="fas fa-basket-shopping" style="color:var(--red);"></i> Harvest (<?= $legend_counts['harvest'] ?>)</div>
                  <?php endif; ?>
                  <?php if ($legend_counts['maintenance'] > 0): ?>
                  <div class="legend-item"><i class="fas fa-wrench" style="color:var(--solar-lt);"></i> Maintenance (<?= $legend_counts['maintenance'] ?>)</div>
                  <?php endif; ?>
                  <?php if ($legend_counts['irrigation'] == 0 && $legend_counts['fertigation'] == 0 && $legend_counts['harvest'] == 0 && $legend_counts['maintenance'] == 0): ?>
                  <div class="legend-item" style="color:var(--text-muted);">No upcoming tasks</div>
                  <?php endif; ?>
                </div>
                <div style="margin-top:14px;text-align:center;">
                  <a href="schedule.php" style="font-size:0.84rem;color:var(--green-mid);font-weight:600;text-decoration:none;"><i class="fas fa-calendar-plus" style="margin-right:6px;"></i>Open Full Calendar</a>
                </div>
              </div>
            </div>

          </div>
        </div>



      </div>

      <?php include "components/footer.php" ?>
    </div>

  </div>
</div>
</body>
</html> 