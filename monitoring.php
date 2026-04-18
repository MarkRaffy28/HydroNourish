<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
      if (isset($_GET['ajax'])) {
          http_response_code(403);
          echo json_encode(['error' => 'Unauthorized']);
          exit;
      }
      header('Location: login.php');
      exit;
  }

  $username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');

  /* ══ AJAX HANDLER ══ */
  if (isset($_GET['ajax'])) {
      header('Content-Type: application/json');
      $type = $_GET['type'] ?? 'latest';

      if ($type === 'latest') {
          $result = $conn->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT 1");
          $data = $result ? $result->fetch_assoc() : [];
          echo json_encode($data ?: (object)[]);
          exit;
      }

      if ($type === 'history') {
          $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
          $result = $conn->query("SELECT * FROM sensor_logs ORDER BY id DESC LIMIT $limit");
          $rows = [];
          if ($result) {
              while ($row = $result->fetch_assoc()) {
                  $rows[] = $row;
              }
          }
          echo json_encode(array_reverse($rows)); // For charts
          exit;
      }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Monitoring — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  
  <link rel="stylesheet" href="css/app.css">
  <link rel="stylesheet" href="css/monitor.css">

  <script src="js/jquery.min.js"></script>
  <script src="js/app.js"></script>
  <script src="js/env_soil_logic.js"></script>
  <script src="js/monitoring.js"></script>
</head>
<body>
<div class="layout">

  <?php 
    $active_page = 'monitoring';
    include 'components/sidebar.php'; 
  ?>

  <div class="main">
    <?php 
      $page_title = "IoT Sensor Monitoring";
      $page_icon = "fa-wifi";
      include "components/header.php" 
    ?>

    <div class="page-content">

      <!-- PAGE HERO -->
      <div class="page-hero">
        <div class="page-hero-badge">IoT Sensor Network — Live Data <span id="rtc-time-display" class="rtc-time-hero">--:--:--</span></div>
        <h1>Sensor <span>Monitoring</span></h1>
        <p>Real-time readings from all IoT nodes across the Farm. Soil moisture, temperature, humidity, solar, and water level — all live.</p>
      </div>

      <!-- ERRORS PANEL -->
      <div id="errorPanel" class="error-panel">
        <div class="error-panel-title"><i class="fas fa-triangle-exclamation"></i> Sensor Errors Detected</div>
        <div id="errorChips" class="error-chips-container"></div>
      </div>

      <!-- QUICK STATS -->
      <?php $show_meta = false; include 'components/env_soil_metrics.php'; ?>

      <!-- TANK LEVELS -->
      <div class="page-section">
        <div class="section-head">
          <div class="section-icon water"><i class="fas fa-water"></i></div>
          <div class="section-label">Tank Levels</div>
        </div>
        <div class="grid-2">
          <div class="card delay-1" id="tank-water-card">
            <div class="card-head">
              <div class="card-head-label">
                <i class="fas fa-tank-water"></i> Water Tank
                <span id="water-dist" class="tank-dist-chip">-- cm</span>
              </div>
              <div class="live-badge chart-lbl-water" id="water-pct">--%</div>
            </div>
            <div class="card-body">
              <div class="progress-wrap"><div class="progress-fill water" id="water-bar"></div></div>
            </div>
          </div>
          <div class="card delay-2" id="tank-fert-card">
            <div class="card-head">
              <div class="card-head-label">
                <i class="fas fa-flask"></i> Fertilizer Tank
                <span id="fert-dist" class="tank-dist-chip">-- cm</span>
              </div>
              <div class="live-badge chart-lbl-solar" id="fert-pct">--%</div>
            </div>
            <div class="card-body">
              <div class="progress-wrap"><div class="progress-fill solar" id="fert-bar"></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- PUMP STATUS -->
      <div class="page-section">
        <div class="section-head">
          <div class="section-icon red"><i class="fas fa-cogs"></i></div>
          <div class="section-label">Pump Status</div>
        </div>
        <div class="grid-2">
          <div class="card delay-1">
            <div class="card-body pump-card-body">
              <div class="pump-icon-wrap"><i class="fas fa-water pump-icon" id="pump-water-icon"></i></div>
              <div class="pump-info">
                <div class="pump-label">Water Pump</div>
                <div class="pump-status" id="pump-water-status">Idle</div>
              </div>
              <div id="pump-water-badge" class="badge pump-badge">Idle</div>
            </div>
          </div>
          <div class="card delay-2">
            <div class="card-body pump-card-body">
              <div class="pump-icon-wrap"><i class="fas fa-seedling pump-icon" id="pump-fert-icon"></i></div>
              <div class="pump-info">
                <div class="pump-label">Fertigation Pump</div>
                <div class="pump-status" id="pump-fert-status">Idle</div>
              </div>
              <div id="pump-fert-badge" class="badge pump-badge">Idle</div>
            </div>
          </div>
        </div>
      </div>

      <!-- CHARTS -->
      <div class="page-section">
        <div class="section-head">
          <div class="section-icon solar"><i class="fas fa-chart-area"></i></div>
          <div class="section-label">Sensor History (Last 30 Readings)</div>
        </div>
        <div class="grid-2">
          <div class="card delay-1">
            <div class="card-head">
              <div class="card-head-label">Temperature</div>
              <div class="chart-lbl-temp" id="lbl-temp">-- °C</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartTemp"></canvas></div>
          </div>
          <div class="card delay-2">
            <div class="card-head">
              <div class="card-head-label">Humidity</div>
              <div class="chart-lbl-hum" id="lbl-hum">-- %</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartHum"></canvas></div>
          </div>
          <div class="card delay-3">
            <div class="card-head">
              <div class="card-head-label">Pressure</div>
              <div class="chart-lbl-pres" id="lbl-pres">-- hPa</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartPres"></canvas></div>
          </div>
          <div class="card delay-4">
            <div class="card-head">
              <div class="card-head-label">Soil Moisture</div>
              <div class="chart-lbl-soil" id="lbl-soil">-- %</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartSoil"></canvas></div>
          </div>
          <div class="card delay-5">
            <div class="card-head">
              <div class="card-head-label">Water Tank Level</div>
              <div class="chart-lbl-water" id="lbl-water">-- %</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartWater"></canvas></div>
          </div>
          <div class="card delay-6">
            <div class="card-head">
              <div class="card-head-label">Fertilizer Tank Level</div>
              <div class="chart-lbl-fert" id="lbl-fert">-- %</div>
            </div>
            <div class="card-body chart-body"><canvas id="chartFert"></canvas></div>
          </div>
        </div>
      </div>

      <!-- LOG TABLE -->
      <div class="page-section">
        <div class="section-head">
          <div class="section-icon text log-section-icon"><i class="fas fa-list"></i></div>
          <div class="section-label">Recent Log</div>
        </div>
        <div class="card delay-1">
          <div class="card-head">
            <div class="card-head-label">Sensor Readings Log</div>
            <div class="badge log-badge" id="logCount">— rows</div>
          </div>
          <div class="card-body log-table-body">
            <table class="log-table">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Timestamp</th>
                  <th>Soil %</th>
                  <th>Water %</th>
                  <th>Fert %</th>
                  <th>Temp °C</th>
                  <th>Hum %</th>
                  <th>Pres hPa</th>
                  <th>GSM</th>
                  <th>Watering</th>
                  <th>Fertigating</th>
                  <th>Errors</th>
                </tr>
              </thead>
              <tbody id="logBody">
                <tr><td colspan="12" class="log-loading">Loading...</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <?php include "components/footer.php" ?>
  </div>
</div>

<script>
  document.addEventListener('click', e => {
    const sidebar = document.getElementById('sidebar');
    if (sidebar && !sidebar.contains(e.target) && !e.target.closest('.mobile-toggle')) sidebar.classList.remove('open');
  });
</script>
</body>
</html>