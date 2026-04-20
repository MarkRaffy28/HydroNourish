<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
  }

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

  $latest_row = null;
  $sensor_res = $conn->query("SELECT * FROM sensor_logs ORDER BY created_at DESC LIMIT 1");
  if ($sensor_res && $sensor_res->num_rows > 0) {
    $latest_row = $sensor_res->fetch_assoc();
  }

  $totalSensors = 7;
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
    <title>Dashboard | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

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
            <div class="hero m-b-28">
              <div class="hero-emoji">🍅</div>
              <h1>System <span>Overview</span></h1>
              <p class="hero-sub">Live summary of HydroNourish IoT: sensor data, automation, and upcoming tasks.</p>
            </div>

            <!-- ALERT -->
            <?php if ($errCount > 0): ?>
              <div class="alert-bar error">
                <i class="fas fa-triangle-exclamation color-error"></i>
                <span>
                  <strong><?= $errCount ?> Sensor Error<?= $errCount > 1 ? 's' : '' ?> Detected</strong>
                  — <?= $activeCount ?> of <?= $totalSensors ?> sensors reporting normally. Check System Health for details.
                </span>
                <a href="monitoring.php" class="alert-view-link">View in Monitoring →</a>
              </div>
            <?php else: ?>
            <div class="alert-bar success m-b-28">
              <i class="fas fa-circle-check"></i>
              <span>
                <strong>System OK</strong> — All <?= $totalSensors ?> sensors reporting normally.
              </span>
            </div>
            <?php endif; ?>

            <!-- ENVIRONMENT & SOIL SECTION -->
            <?php 
              $show_meta = true; 
              include 'components/env_soil_metrics.php'; 
            ?>

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
                        <div class="tank-pct-value tank-pct-water" id="water-pct">--%</div>
                        <div class="tank-visual-container water-type">
                          <div class="tank-liquid-fill" id="water-bar"></div>
                          <div class="tank-marker m-60"><span>60%</span></div>
                          <div class="tank-marker m-30"><span>30%</span></div>
                        </div>
                        <div class="tank-name-label">
                          <i class="fas fa-droplet color-water"></i> Water Tank
                          <span id="water-dist" class="badge badge-blue">-- cm</span>
                        </div>
                      </div>

                      <!-- Fertilizer Tank -->
                      <div class="tank-item">
                        <div class="tank-pct-value tank-pct-fert" id="fert-pct">--%</div>
                        <div class="tank-visual-container fertilizer-type">
                          <div class="tank-liquid-fill" id="fert-bar"></div>
                          <div class="tank-marker m-60"><span>60%</span></div>
                          <div class="tank-marker m-30"><span>30%</span></div>
                        </div>
                        <div class="tank-name-label">
                          <i class="fas fa-flask color-solar"></i> Fertilizer Tank
                          <span id="fert-dist" class="badge badge-yellow">-- cm</span>
                        </div>
                      </div>
                    </div>
                    
                    <div class="m-t-24 p-t-16 border-t">
                      <div class="kv-row">
                        <span class="key"><i class="fas fa-wifi color-success"></i> Sensors</span>
                        <?php if ($errCount > 0): ?>
                          <span class="flex-center-gap-6">
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
                      <div class="m-t-12">
                        <div class="f-s-068 fw-700 text-uppercase ls-01 text-muted m-b-8 flex-center-gap-6">
                          <i class="fas fa-triangle-exclamation color-error"></i> Active Sensor Errors
                        </div>
                        <?php foreach ($erroredSensors as $nodeId => $s): ?>
                        <div class="sensor-error-card">
                          <i class="fas fa-circle-xmark color-error f-s-078 m-t-2 shrink-0"></i>
                          <div class="flex-1 min-w-0">
                            <div class="flex-center-gap-7 m-b-3 flex-wrap">
                              <span class="error-node-id"><?= htmlspecialchars($nodeId) ?></span>
                              <span class="error-code-label"><?= htmlspecialchars($s['error_code']) ?></span>
                            </div>
                            <div class="f-s-072 text-muted lh-14"><?= htmlspecialchars($s['error_note']) ?></div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                      <div class="m-t-6 text-center">
                        <a href="monitoring.php" class="f-s-078 color-error fw-600 d-none-decor">
                          <i class="fas fa-arrow-right m-r-5"></i>View in Monitoring →
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
                      <div class="quick-link-icon green-icon"><i class="fas fa-wifi"></i></div>
                      <div><div class="quick-link-title">IoT Monitoring</div><div class="quick-link-desc">Live sensor readings & alerts</div></div>
                      <i class="fas fa-arrow-right arr"></i>
                    </a>
                    <a href="schedule.php" class="quick-link">
                      <div class="quick-link-icon water-icon"><i class="fas fa-calendar-days"></i></div>
                      <div><div class="quick-link-title">Scheduling</div><div class="quick-link-desc">Irrigation & Fertigation calendar</div></div>
                      <i class="fas fa-arrow-right arr"></i>
                    </a>
                    <a href="controls.php" class="quick-link">
                      <div class="quick-link-icon water-icon"><i class="fas fa-sliders"></i></div>
                      <div><div class="quick-link-title">Controls</div><div class="quick-link-desc">Override controls & IoT settings</div></div>
                      <i class="fas fa-arrow-right arr"></i>
                    </a>
                    <a href="mis.php" class="quick-link">
                      <div class="quick-link-icon red-icon"><i class="fas fa-users"></i></div>
                      <div><div class="quick-link-title">Manage Users</div><div class="quick-link-desc">User access & roles</div></div>
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
                    <a href="schedule.php" class="f-s-078 color-success d-none-decor fw-600">View All →</a>
                  </div>
                  <div class="card-body">
                    <?php if (empty($upcoming_schedules)): ?>
                    <div class="text-center p-v-20 text-muted">
                      <i class="fas fa-calendar-xmark f-s-15 m-b-8 block opacity-05"></i>
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
                        <div class="activity-dot dot-shadow" style="--dot-color:<?= $dotColor ?>; background:var(--dot-color);"></div>
                        <div class="activity-content">
                          <div class="activity-title"><?= htmlspecialchars($sched['task_name']) ?></div>
                          <div class="activity-desc"><?= htmlspecialchars($sched['task_notes'] ?: $sched['task_type']) ?></div>
                          <div class="activity-time"><?= $dateLabel ?>, <?= $timeLabel ?></div>
                        </div>
                        <span class="badge <?= $badgeClass ?> m-t-3"><?= $badgeLabel ?></span>
                      </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                    <div class="m-t-16 text-center">
                      <a href="schedule.php" class="f-s-084 color-success d-none-decor fw-600"><i class="fas fa-plus m-r-6"></i>Add New Task</a>
                    </div>
                  </div>
                </div>

                <!-- Mini Calendar -->
                <div class="card">
                  <div class="card-head">
                    <div class="flex-between flex-1">
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
                      <div class="legend-item"><i class="fas fa-droplet color-water"></i> Irrigation (<?= $legend_counts['irrigation'] ?>)</div>
                      <?php endif; ?>
                      <?php if ($legend_counts['fertigation'] > 0): ?>
                      <div class="legend-item"><i class="fas fa-spray-can color-solar"></i> Fertigation (<?= $legend_counts['fertigation'] ?>)</div>
                      <?php endif; ?>
                      <?php if ($legend_counts['harvest'] > 0): ?>
                      <div class="legend-item"><i class="fas fa-basket-shopping color-error"></i> Harvest (<?= $legend_counts['harvest'] ?>)</div>
                      <?php endif; ?>
                      <?php if ($legend_counts['maintenance'] > 0): ?>
                      <div class="legend-item"><i class="fas fa-wrench color-solar-lt"></i> Maintenance (<?= $legend_counts['maintenance'] ?>)</div>
                      <?php endif; ?>
                      <?php if ($legend_counts['irrigation'] == 0 && $legend_counts['fertigation'] == 0 && $legend_counts['harvest'] == 0 && $legend_counts['maintenance'] == 0): ?>
                      <div class="legend-item text-muted">No upcoming tasks</div>
                      <?php endif; ?>
                    </div>
                    <div class="m-t-14 text-center">
                      <a href="schedule.php" class="f-s-084 color-success fw-600 d-none-decor"><i class="fas fa-calendar-plus m-r-6"></i>Open Full Calendar</a>
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