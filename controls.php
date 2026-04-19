<?php
  session_start();
  require_once "config/db.php";

  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }

  $current_cmd = ['irrigation' => 0, 'fertigation' => 0];

  $res = $conn->query("
    SELECT * FROM commands
    WHERE status = 'pending'
    ORDER BY created_at DESC
    LIMIT 1
  ");

  if ($res && $row = $res->fetch_assoc()) {
    $current_cmd['irrigation']  = (int)$row['irrigation'];
    $current_cmd['fertigation'] = (int)$row['fertigation'];
  }

  $realtime = ['watering' => 0, 'fertigating' => 0, 'last_seen' => null];

  $res_sensor = $conn->query("
    SELECT watering, fertigating, created_at
    FROM sensor_logs
    ORDER BY created_at DESC
    LIMIT 1
  ");

  if ($res_sensor && $row_s = $res_sensor->fetch_assoc()) {
    $realtime['watering']    = (int)$row_s['watering'];
    $realtime['fertigating'] = (int)$row_s['fertigating'];
    $realtime['last_seen']   = $row_s['created_at'];
  }

  $is_offline = true;

  if ($realtime['last_seen']) {
    $last_time = strtotime($realtime['last_seen']);

    if (time() - $last_time < 60) {
        $is_offline = false;
    }
  }

  if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    if ($action === 'get_command_logs') {
      $result = $conn->query("SELECT * FROM commands ORDER BY created_at DESC LIMIT 20");
      $logs = [];
      if ($result) {
          while ($r = $result->fetch_assoc()) { $logs[] = $r; }
      }
      
      $curr = ['irrigation' => 0, 'fertigation' => 0];
      $res_c = $conn->query("SELECT * FROM commands WHERE status = 'pending' ORDER BY created_at DESC LIMIT 1");
      if ($res_c && $rc = $res_c->fetch_assoc()) {
        $curr['irrigation'] = (int)$rc['irrigation'];
        $curr['fertigation'] = (int)$rc['fertigation'];
      }
      
      $real = ['watering' => 0, 'fertigating' => 0, 'last_seen' => null];
      $res_s = $conn->query("SELECT watering, fertigating, created_at FROM sensor_logs ORDER BY created_at DESC LIMIT 1");
      if ($res_s && $rs = $res_s->fetch_assoc()) {
        $real['watering'] = (int)$rs['watering'];
        $real['fertigating'] = (int)$rs['fertigating'];
        $real['last_seen'] = $rs['created_at'];
      }
        
      $off = true;
      if ($real['last_seen'] && (time() - strtotime($real['last_seen']) < 60)) { $off = false; }
      
      echo json_encode([
        'logs' => $logs,
        'current_cmd' => $curr,
        'realtime' => $real,
        'is_offline' => $off
      ]);
      exit;
    }

    if ($action === 'save_command' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $irrigation = isset($_POST['irrigation']) ? (int)$_POST['irrigation'] : 0;
      $fertigation = isset($_POST['fertigation']) ? (int)$_POST['fertigation'] : 0;

      $check = $conn->query("SELECT id FROM commands WHERE status = 'pending' LIMIT 1");
      if ($check && $row = $check->fetch_assoc()) {
        $stmt = $conn->prepare("UPDATE commands SET irrigation = ?, fertigation = ?, created_at = NOW() WHERE id = ?");
        $stmt->bind_param("iii", $irrigation, $fertigation, $row['id']);
      } else {
        $stmt = $conn->prepare("INSERT INTO commands (irrigation, fertigation, status) VALUES (?, ?, 'pending')");
        $stmt->bind_param("ii", $irrigation, $fertigation);
      }

      if ($stmt->execute()) {
        echo json_encode(['status' => 'success']);
      } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
      }
      exit;
    }

    if ($action === 'get_settings') {
      $res = $conn->query("SELECT * FROM options LIMIT 1");
      if ($res && $res->num_rows > 0) {
        echo json_encode(['status' => 'success', 'data' => $res->fetch_assoc()]);
      } else {
        echo json_encode(['status' => 'error']);
      }
      exit;
    }

    if ($action === 'save_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $fields = [
        'water_watering_percent', 'tank_low_percent', 'tank_high_percent',
        'water_full_cm', 'water_empty_cm', 'fert_full_cm', 'fert_empty_cm',
        'fert_interval_minutes', 'fert_duration_ms', 'fertigation_enabled',
        'buzzer_enabled', 'gsm_texting_enabled', 'buzzer_alert_interval_ms',
        'lcd_enabled', 'backlight_enabled', 'rtc_set_time'
      ];
          
      $updates = [];
      $types = '';
      $params = [];
          
      foreach ($fields as $f) {
        if (isset($_POST[$f])) {
          $updates[] = "$f = ?";
          if ($f === 'rtc_set_time') {
            $types .= 's';
            $params[] = (string)$_POST[$f];
          } else {
            $types .= 'i';
            $params[] = (int)$_POST[$f];
          }
        }
      }
          
      $res = $conn->query("SELECT id FROM options LIMIT 1");
          
      if (!$res || $res->num_rows === 0) {
        $conn->query("INSERT INTO options (gsm_recipients) VALUES ('[]')");
        $res = $conn->query("SELECT id FROM options LIMIT 1");
      }
          
      if ($res && $row = $res->fetch_assoc()) {
        $opt_id = $row['id'];
        if (!empty($updates)) {
          $sql = "UPDATE options SET " . implode(', ', $updates) . " WHERE id = ?";
          $types .= 'i';
          $params[] = $opt_id;
                  
          $stmt = $conn->prepare($sql);
          if ($stmt) {
            $stmt->bind_param($types, ...$params);
            if ($stmt->execute()) {
              echo json_encode(['status' => 'success']);
              exit;
            }
          }
        }
      }

      echo json_encode(['status' => 'error', 'message' => $conn->error]);
      exit;
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Controls - HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/monitor.css">
    <link rel="stylesheet" href="css/controls.css">

    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/controls.js"></script>
  </head>

  <body>
    <div class="layout">
      <?php 
        $active_page = 'controls';
        include 'components/sidebar.php'; 
      ?>

      <main class="main">
        <?php
          $page_title = "Manual System Overrides";
          $page_icon = "fa-sliders";
          include 'components/header.php';
        ?>

        <div class="page-content">
          <div class="page-hero">
            <div class="hero-emoji">🔧</div>
            <div class="page-hero-flex">
              <div>
                <h1>System <span>Overrides</span></h1>
                <p>Manually trigger fertigation or irrigation cycles. Irrigation maintains an automatic mode based on sensors, but manual overrides take priority.</p>
              </div>
            </div>
          </div>

          <div class="controls-grid">

            <?php 
              $irr_active = $current_cmd['irrigation'] || $realtime['watering'];
              $irr_status = $realtime['watering'] ? 'active' : ($current_cmd['irrigation'] ? 'pending' : 'off');
            ?>

            <div class="control-card <?= $irr_active ? 'active' : '' ?>" id="card-irrigation">
              <div class="control-card-header-flex">
                <div class="control-icon"><i class="fas fa-droplet"></i></div>
                <div class="control-info">
                  <div class="control-title">Irrigation Override</div>
                  <div class="control-desc">Force water pump to start immediately. Status reflects live sensor feedback.</div>
                </div>
              </div>
              <div class="control-action">
                <div id="status-irrigation">
                  <?php if ($irr_status === 'active'): ?>
                    <span class="status-pill active"><i class="fas fa-play"></i> Running</span>
                  <?php elseif ($irr_status === 'pending'): ?>
                    <span class="status-pill pending"><i class="fas fa-spinner fa-spin"></i> Pending</span>
                  <?php else: ?>
                    <span class="status-pill off">Inactive</span>
                  <?php endif; ?>
                </div>
                <button class="btn-toggle <?= $irr_active ? 'stop' : 'start' ?>" id="btn-irrigation" onclick="toggleCommand('irrigation', <?= $irr_active ? 0 : 1 ?>)">
                  <?= $irr_active ? ($realtime['watering'] ? 'Emergency Stop' : 'Cancel') : 'Start Now' ?>
                </button>
              </div>
            </div>

            <?php 
              $fert_active = $current_cmd['fertigation'] || $realtime['fertigating'];
              $fert_status = $realtime['fertigating'] ? 'active' : ($current_cmd['fertigation'] ? 'pending' : 'off');
            ?>

            <div class="control-card <?= $fert_active ? 'active' : '' ?>" id="card-fertigation">
              <div class="control-card-header-flex">
                <div class="control-icon solar-icon"><i class="fas fa-flask"></i></div>
                <div class="control-info">
                  <div class="control-title">Fertigation Override</div>
                  <div class="control-desc">Force fertilizer dispenser cycle. Status reflects live sensor feedback.</div>
                </div>
              </div>
              <div class="control-action">
                <div id="status-fertigation">
                  <?php if ($fert_status === 'active'): ?>
                    <span class="status-pill active"><i class="fas fa-play"></i> Running</span>
                  <?php elseif ($fert_status === 'pending'): ?>
                    <span class="status-pill pending"><i class="fas fa-spinner fa-spin"></i> Pending</span>
                  <?php else: ?>
                    <span class="status-pill off">Inactive</span>
                  <?php endif; ?>
                </div>
                <button class="btn-toggle <?= $fert_active ? 'stop' : 'start' ?>" id="btn-fertigation" onclick="toggleCommand('fertigation', <?= $fert_active ? 0 : 1 ?>)">
                  <?= $fert_active ? ($realtime['fertigating'] ? 'Emergency Stop' : 'Cancel') : 'Start Now' ?>
                </button>
              </div>
            </div>

          </div>

          <div class="page-section">
            <div class="section-head" style="margin-top: 25px">
              <div class="section-icon solar"><i class="fas fa-cogs"></i></div>
              <div class="section-label">System Architecture Settings</div>
            </div>
            
            <div class="card card-settings-accent">
              <div class="card-body">
                <form id="frm-settings" onsubmit="event.preventDefault(); saveSettings();">
                  <div class="settings-container">
                    
                    <!-- Thresholds & Calibration -->
                    <div class="settings-card-premium">
                      <div class="settings-card-head">
                        <div class="settings-card-icon water"><i class="fas fa-droplet"></i></div>
                        <div class="settings-card-info">
                          <div class="settings-card-title">Watering & Calibration</div>
                          <div class="settings-card-desc">Configure sensor thresholds and ultrasonic tank depth calibration.</div>
                        </div>
                      </div>
                      <div class="settings-card-body">
                        <div class="setting-item-row">
                          <label class="setting-label">Watering Trigger (%)</label>
                          <input type="number" class="setting-input" id="water_watering_percent" min="0" max="100" />
                          <small class="input-hint">Threshold to start irrigation</small>
                        </div>
                        <div class="setting-item-grid">
                          <div class="setting-item">
                            <label class="setting-label">Tank Low (%)</label>
                            <input type="number" class="setting-input" id="tank_low_percent" min="0" max="100" />
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">Tank High (%)</label>
                            <input type="number" class="setting-input" id="tank_high_percent" min="0" max="100" />
                          </div>
                        </div>
                        <div class="setting-divider"></div>
                        <div class="setting-item-grid">
                          <div class="setting-item">
                            <label class="setting-label">Water Full (cm)</label>
                            <input type="number" class="setting-input" id="water_full_cm" min="1" />
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">Water Empty (cm)</label>
                            <input type="number" class="setting-input" id="water_empty_cm" min="1" />
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">Fert. Full (cm)</label>
                            <input type="number" class="setting-input" id="fert_full_cm" min="1" />
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">Fert. Empty (cm)</label>
                            <input type="number" class="setting-input" id="fert_empty_cm" min="1" />
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Fertigation Rules -->
                    <div class="settings-card-premium">
                      <div class="settings-card-head">
                        <div class="settings-card-icon solar"><i class="fas fa-flask"></i></div>
                        <div class="settings-card-info">
                          <div class="settings-card-title">Fertigation Rules</div>
                          <div class="settings-card-desc">Set the schedule and duration for automatic nutrient dispensing.</div>
                        </div>
                      </div>
                      <div class="settings-card-body">
                        <div class="setting-item-row">
                          <label class="setting-label">Engine Status</label>
                          <select class="setting-input" id="fertigation_enabled">
                            <option value="1">Enabled (Cyclic)</option>
                            <option value="0">Disabled (Manual Only)</option>
                          </select>
                        </div>
                        <div id="fert_interval_container">
                          <div class="setting-item-row">
                            <label class="setting-label">Fertigation Interval</label>
                            <div class="custom-input-group">
                              <input type="number" class="setting-input" id="fert_interval_amount" min="1" />
                              <select class="setting-input setting-input-highlight" id="fert_interval_unit">
                                <option value="1">Minutes</option>
                                <option value="60">Hours</option>
                                <option value="1440">Days</option>
                                <option value="43200">Months</option>
                              </select>
                            </div>
                          </div>
                        </div>
                        <div class="setting-item-row">
                          <label class="setting-label">Dispense Duration (ms)</label>
                          <input type="number" class="setting-input" id="fert_duration_ms" min="1000" step="1000" />
                          <small class="input-hint">1000ms = 1 second</small>
                        </div>
                      </div>
                    </div>

                    <!-- Hardware Notifications -->
                    <div class="settings-card-premium">
                      <div class="settings-card-head">
                        <div class="settings-card-icon green"><i class="fas fa-bell"></i></div>
                        <div class="settings-card-info">
                          <div class="settings-card-title">Alerts & Hardware</div>
                          <div class="settings-card-desc">Manage buzzer alerts, LCD display, and GSM notification status.</div>
                        </div>
                      </div>
                      <div class="settings-card-body">
                        <div class="setting-item-grid">
                          <div class="setting-item">
                            <label class="setting-label">Buzzer</label>
                            <select class="setting-input" id="buzzer_enabled">
                              <option value="1">Active</option>
                              <option value="0">Silenced</option>
                            </select>
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">GSM Texting</label>
                            <select class="setting-input" id="gsm_texting_enabled">
                              <option value="1">Enabled</option>
                              <option value="0">Paused</option>
                            </select>
                          </div>
                        </div>
                        <div class="setting-item-grid">
                          <div class="setting-item">
                            <label class="setting-label">LCD Power</label>
                            <select class="setting-input" id="lcd_enabled">
                              <option value="1">On</option>
                              <option value="0">Off</option>
                            </select>
                          </div>
                          <div class="setting-item">
                            <label class="setting-label">Backlight</label>
                            <select class="setting-input" id="backlight_enabled">
                              <option value="1">Auto</option>
                              <option value="0">Off</option>
                            </select>
                          </div>
                        </div>
                        <div class="setting-divider"></div>
                        <div class="setting-item-row">
                          <label class="setting-label">Error Buzzer Interval</label>
                          <div class="custom-input-group">
                            <input type="number" class="setting-input" id="buzzer_interval_amount" min="1" />
                            <select class="setting-input setting-input-highlight" id="buzzer_interval_unit">
                              <option value="0">Once Only</option>
                              <option value="1000">Seconds</option>
                              <option value="60000">Minutes</option>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Sync & RTC -->
                    <div class="settings-card-premium">
                      <div class="settings-card-head">
                        <div class="settings-card-icon"><i class="fas fa-clock"></i></div>
                        <div class="settings-card-info">
                          <div class="settings-card-title">Time Sync</div>
                          <div class="settings-card-desc">Synchronize the hardware RTC with your server's current local time.</div>
                        </div>
                      </div>
                      <div class="settings-card-body">
                        <div class="setting-item-row">
                          <label class="setting-label">Hardware Clock</label>
                          <div class="rtc-sync-container">
                            <div class="rtc-sync-status"><i class="fas fa-history"></i> Last synced via dashboard</div>
                            <button type="button" class="btn-rtc-sync" onclick="syncRtcTime(this)">
                              <i class="fas fa-sync-alt"></i> Force RTC Update
                            </button>
                          </div>
                          <input type="hidden" id="rtc_set_time" value="0" />
                        </div>
                      </div>
                    </div>

                  </div>
                  
                  <div class="settings-actions-bar">
                    <button type="submit" class="btn-save-premium" id="btn-save-settings">
                      <span class="btn-icon"><i class="fas fa-save"></i></span>
                      <span class="btn-text">Apply System Configuration</span>
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div class="page-section command-log">
            <div class="section-head">
              <div class="section-icon green"><i class="fas fa-clock-rotate-left"></i></div>
              <div class="section-label">Command Execution History</div>
            </div>
            <div class="card">
              <div class="card-body card-body-no-padding" id="command-log-container">
                <div class="loading-logs-placeholder">Loading logs...</div>
              </div>
            </div>
          </div>  
        </div>
        <?php include "components/footer.php" ?>
      </main>
    </div>
  </body>
</html>