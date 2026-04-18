<?php
  session_start();

  if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }

  require_once "config/db.php";

  $username    = $_SESSION['username'] ?? 'User';
  $active_page  = 'controls';

  /* Current command */
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

  /* Sensor status */
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

  /* Offline check */
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

      // 4. Get Command Logs
      if ($action === 'get_command_logs') {
          // get logs
          $result = $conn->query("SELECT * FROM commands ORDER BY created_at DESC LIMIT 20");
          $logs = [];
          if ($result) {
              while ($r = $result->fetch_assoc()) { $logs[] = $r; }
          }
          
          // get current master state (copied from above logic)
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

      // 5. Save Command
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

      // 6. Config Settings
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
          
          // Options ID assumption
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

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/monitor.css">

    <!-- Scripts -->
    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>


    <style>
      /* =========================
        GRID & CARD LAYOUT
      ========================== */
      .controls-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 24px;
      }

      .control-card {
        background: #fff;
        border-radius: var(--radius-lg);
        border: 1.5px solid var(--border);
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 20px;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
      }

      .control-card:hover {
        border-color: var(--green-mid);
        box-shadow: 0 10px 30px var(--shadow-md);
      }

      .control-card.active .control-icon {
        background: var(--green-mid);
        color: #fff;
        transform: scale(1.1);
      }

      .control-icon {
        width: 60px;
        height: 60px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.8rem;
        background: var(--green-pale);
        color: var(--green-mid);
        transition: all 0.3s;
      }

      .control-info { flex: 1; }

      .control-title {
        font-family: var(--font-display);
        font-size: 1.4rem;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 4px;
      }

      .control-desc {
        font-size: 0.85rem;
        color: var(--text-muted);
        line-height: 1.5;
      }

      .control-action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-top: 1px solid var(--border);
        padding-top: 20px;
      }

      /* =========================
        STATUS PILL
      ========================== */
      .status-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 12px;
        border-radius: 100px;
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
      }

      .status-pill.off {
        background: var(--sand);
        color: var(--text-muted);
      }

      .status-pill.pending {
        background: var(--solar-pale);
        color: var(--solar);
        animation: pulse 2s infinite;
      }

      .status-pill.active {
        background: var(--green-pale);
        color: var(--green-mid);
      }

      @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
      }

      /* =========================
        BUTTONS
      ========================== */
      .btn-toggle {
        padding: 10px 24px;
        border-radius: var(--radius-sm);
        border: none;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
        font-family: var(--font-body);
      }

      .btn-toggle.start { background: var(--green-mid); color: #fff; }
      .btn-toggle.stop  { background: var(--red); color: #fff; }

      .btn-toggle:hover {
        opacity: 0.9;
        transform: translateY(-1px);
      }

      .btn-toggle:active { transform: translateY(0); }
      .btn-toggle:disabled { opacity: 0.5; cursor: not-allowed; }

      /* =========================
        LOGS
      ========================== */
      .command-log { margin-top: 40px; }

      .log-item {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
      }

      .log-item:last-child {
        border-bottom: none;
      }

      .log-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.8rem;
      }

      .log-details { flex: 1; }

      .log-time {
        font-family: var(--font-mono);
        font-size: 0.7rem;
        color: var(--text-muted);
      }

      /* =========================
        CONNECTION STATUS
      ========================== */
      .connection-status {
        padding: 12px 20px;
        border-radius: var(--radius-md);
        background: #fff;
        border: 1.5px solid var(--border);
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 180px;
      }

      .connection-status.online {
        border-color: var(--green-mid);
      }

      .connection-status.offline {
        border-color: var(--red);
        opacity: 0.8;
      }

      .status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
      }

      .online .status-dot {
        background: var(--green-mid);
        box-shadow: 0 0 10px var(--green-mid);
        animation: pulse-green 2s infinite;
      }

      .offline .status-dot {
        background: var(--red);
      }

      @keyframes pulse-green {
        0% { transform: scale(1); opacity: 1; }
        50% { transform: scale(1.4); opacity: 0.5; }
        100% { transform: scale(1); opacity: 1; }
      }

      /* MODAL STYLES */
      .modal-overlay {
        position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px);
        display: none; align-items: center; justify-content: center; z-index: 1000;
        animation: fadeIn 0.2s ease;
      }
      .modal-card {
        background: #fff; width: 90%; max-width: 400px; border-radius: var(--radius-lg);
        padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); transform: translateY(20px);
        transition: transform 0.3s; text-align: center;
      }
      .modal-overlay.open { display: flex; }
      .modal-overlay.open .modal-card { transform: translateY(0); }
      .modal-icon {
        width: 60px; height: 60px; background: var(--red-pale); color: var(--red);
        border-radius: 50%; display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; margin: 0 auto 20px;
      }
      @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
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
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
              <div>
                <div class="page-hero-badge">Manual Mode</div>
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
              <div style="display:flex; gap:20px; align-items:center;">
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
              <div style="display:flex; gap:20px; align-items:center;">
                <div class="control-icon" style="color:var(--solar); background:var(--solar-pale);"><i class="fas fa-flask"></i></div>
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
            <div class="section-head">
              <div class="section-icon solar"><i class="fas fa-cogs"></i></div>
              <div class="section-label">System Architecture Settings</div>
            </div>
            
            <div class="card">
              <div class="card-body">
                <form id="frm-settings" onsubmit="event.preventDefault(); saveSettings();">
                  <div class="settings-grid">
                    
                    <div class="setting-group"><i class="fas fa-droplet"></i> General Thresholds</div>
                    <div class="setting-item">
                      <label class="setting-label">Watering Trigger (%)</label>
                      <input type="number" class="setting-input" id="water_watering_percent" min="0" max="100" />
                    </div>
                    <div class="setting-item">
                      <label class="setting-label">Tank Low Warning (%)</label>
                      <input type="number" class="setting-input" id="tank_low_percent" min="0" max="100" />
                    </div>
                    <div class="setting-item">
                      <label class="setting-label">Tank High Capacity (%)</label>
                      <input type="number" class="setting-input" id="tank_high_percent" min="0" max="100" />
                    </div>

                    <div class="setting-group"><i class="fas fa-ruler-vertical"></i> Ultrasonic Tank Calibration (CM)</div>
                    <div class="setting-item">
                      <label class="setting-label">Water Tank Full (cm)</label>
                      <input type="number" class="setting-input" id="water_full_cm" min="1" />
                    </div>
                    <div class="setting-item">
                      <label class="setting-label">Water Tank Empty (cm)</label>
                      <input type="number" class="setting-input" id="water_empty_cm" min="1" />
                    </div>
                    <div class="setting-item">
                      <label class="setting-label">Fertilizer Tank Full (cm)</label>
                      <input type="number" class="setting-input" id="fert_full_cm" min="1" />
                    </div>
                    <div class="setting-item">
                      <label class="setting-label">Fertilizer Tank Empty (cm)</label>
                      <input type="number" class="setting-input" id="fert_empty_cm" min="1" />
                    </div>

                    <div class="setting-group"><i class="fas fa-flask"></i> Fertigation Rules</div>
                    <div class="setting-item">
                      <label class="setting-label">Engine Status</label>
                      <select class="setting-input" id="fertigation_enabled">
                        <option value="1">Enabled (Cyclic)</option>
                        <option value="0">Disabled (Manual Only)</option>
                      </select>
                    </div>

                    <div class="setting-item" id="fert_interval_container">
                      <label class="setting-label">Fertigation Interval</label>
                      <div class="custom-input-group">
                        <input type="number" class="setting-input" id="fert_interval_amount" min="1" required />
                        <select class="setting-input setting-input-highlight" id="fert_interval_unit">
                          <option value="1">Minutes</option>
                          <option value="60">Hours</option>
                          <option value="1440">Days</option>
                          <option value="43200">Months (30d)</option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="setting-item">
                      <label class="setting-label">Fertigation Duration (ms)</label>
                      <input type="number" class="setting-input" id="fert_duration_ms" min="1000" step="1000" />
                    </div>

                    <div class="setting-group"><i class="fas fa-bell"></i> Farm Alerts & Indicators</div>
                    <div class="setting-item">
                      <label class="setting-label">Hardware Buzzer Status</label>
                      <select class="setting-input" id="buzzer_enabled">
                        <option value="1">Active</option>
                        <option value="0">Silenced</option>
                      </select>
                    </div>

                    <div class="setting-item">
                      <label class="setting-label">LCD Display Screen</label>
                      <select class="setting-input" id="lcd_enabled">
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                      </select>
                    </div>
                    
                    <div class="setting-item">
                      <label class="setting-label">LCD Display Backlight</label>
                      <select class="setting-input" id="backlight_enabled">
                        <option value="1">Enabled</option>
                        <option value="0">Disabled</option>
                      </select>
                    </div>

                    <div class="setting-item">
                      <label class="setting-label">Error Buzzer Interval</label>
                      <div class="custom-input-group">
                        <input type="number" class="setting-input" id="buzzer_interval_amount" min="1" required />
                        <select class="setting-input setting-input-highlight" id="buzzer_interval_unit">
                          <option value="0">Once (One Time Only)</option>
                          <option value="1000">Seconds</option>
                          <option value="60000">Minutes</option>
                        </select>
                      </div>
                    </div>

                    <div class="setting-group"><i class="fas fa-clock"></i> Time Sync</div>
                    <div class="setting-item">
                      <label class="setting-label">Hardware RTC Synchronization</label>
                      <div class="custom-input-group">
                        <input type="text" class="setting-input" value="Sync to current local time" disabled />
                        <button type="button" class="setting-input" style="background:var(--green-mid);color:#fff;font-weight:bold;cursor:pointer;flex:unset;padding:10px 20px;" onclick="syncRtcTime(this)">
                          <i class="fas fa-sync-alt"></i> Force Sync
                        </button>
                      </div>
                      <input type="hidden" id="rtc_set_time" value="0" />
                    </div>

                  </div>
                  
                  <div class="settings-actions-bar">
                    <button type="submit" class="btn-toggle start" id="btn-save-settings"><i class="fas fa-save"></i> Save Configuration</button>
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
              <div class="card-body" id="command-log-container" style="padding:0;">
                <div style="padding:40px; text-align:center; color:var(--text-muted);">Loading logs...</div>
              </div>
            </div>
          </div>  
        </div>
        <?php include "components/footer.php" ?>
      </main>
    </div>

    </div>

    <!-- SCRIPTS -->
    <script>
      function toggleCommand(type, state) {
        const btn = $(`#btn-${type}`);

        btn.prop('disabled', true)
          .html('<i class="fas fa-sync fa-spin"></i>');

        const data = {
          irrigation: type === 'irrigation'
            ? state
            : ($('#card-irrigation').hasClass('active') ? 1 : 0),

          fertigation: type === 'fertigation'
            ? state
            : ($('#card-fertigation').hasClass('active') ? 1 : 0)
        };

        $.ajax({
          url: 'controls.php?ajax=save_command',
          method: 'POST',
          data: data,

          success: function (res) {
            if (res.status === 'success') {
              loadCommandLogs();
            } else {
              btn.prop('disabled', false).html(state ? 'Start Now' : 'Cancel');
            }
          },

          error: function () {
            btn.prop('disabled', false).html(state ? 'Start Now' : 'Cancel');
          }
        });
      }

      function loadCommandLogs() {
        $.get('controls.php?ajax=get_command_logs', function (res) {
          if (!res) return;

          // 1. Update Logs List
          let html = '';
          const data = res.logs || [];

          if (data.length === 0) {
            html = `<div style="padding:40px; text-align:center; color:var(--text-muted);">No recent commands found.</div>`;
          } else {
            data.forEach(log => {
              const isIrr = log.irrigation == 1;
              const icon   = isIrr ? 'fa-droplet' : 'fa-flask';
              const color  = isIrr ? 'var(--water)' : 'var(--solar)';
              const bg     = isIrr ? 'var(--water-pale)' : 'var(--solar-pale)';
              const statusColor = log.status === 'done' ? 'var(--green-mid)' : (log.status === 'pending' ? 'var(--solar)' : 'var(--red)');
              const actionText = log.irrigation == 0 && log.fertigation == 0 ? 'Stop' : 'Triggered';

              html += `
                <div class="log-item">
                  <div class="log-icon" style="background:${bg}; color:${color};"><i class="fas ${icon}"></i></div>
                  <div class="log-details">
                    <div style="font-weight:600;">Manual ${isIrr ? 'Irrigation' : 'Fertigation'} ${actionText}</div>
                    <div class="log-time">Created: ${log.created_at} ${log.executed_at ? '· Executed: ' + log.executed_at : ''}</div>
                  </div>
                  <div style="font-size:0.75rem; font-weight:700; color:${statusColor}; text-transform:uppercase;">${log.status}</div>
                </div>`;
            });
          }
          $('#command-log-container').html(html);

          // 2. Update Manual Control Cards
          const updateCard = (type, isActive, status) => {
            const card = $(`#card-${type}`);
            const btn = $(`#btn-${type}`);
            const statusBox = $(`#status-${type}`);

            // Update Card Class
            if (isActive) card.addClass('active'); else card.removeClass('active');

            // Update Status Pill
            let pillHtml = '<span class="status-pill off">Inactive</span>';
            if (status === 'active') pillHtml = '<span class="status-pill active"><i class="fas fa-play"></i> Running</span>';
            else if (status === 'pending') pillHtml = '<span class="status-pill pending"><i class="fas fa-spinner fa-spin"></i> Pending</span>';
            statusBox.html(pillHtml);

            // Update Button
            let btnText = 'Start Now';
            if (isActive) {
               btnText = (status === 'active') ? 'Emergency Stop' : 'Cancel';
               btn.addClass('stop').removeClass('start');
               btn.attr('onclick', `toggleCommand('${type}', 0)`);
               // Only disable if pending
               if (status === 'pending') btn.prop('disabled', true);
               else btn.prop('disabled', false);
            } else {
               btn.addClass('start').removeClass('stop');
               btn.attr('onclick', `toggleCommand('${type}', 1)`);
               btn.prop('disabled', false);
            }
            // Update text only if not currently spinning from a click
            if(!btn.find('.fa-spin').length || !isActive) btn.html(btnText);
          };

          const irrActive = res.current_cmd.irrigation || res.realtime.watering;
          const irrStatus = res.realtime.watering ? 'active' : (res.current_cmd.irrigation ? 'pending' : 'off');
          updateCard('irrigation', irrActive, irrStatus);

          const fertActive = res.current_cmd.fertigation || res.realtime.fertigating;
          const fertStatus = res.realtime.fertigating ? 'active' : (res.current_cmd.fertigation ? 'pending' : 'off');
          updateCard('fertigation', fertActive, fertStatus);
          
          // 3. Update connection status check
          if (res.is_offline) {
             $('.connection-status').addClass('offline').removeClass('online').find('.status-label').text('Modem Offline');
          } else {
             $('.connection-status').addClass('online').removeClass('offline').find('.status-label').text('ESP32 Online');
          }
        });
      }

      function loadSettings() {
        $.get('controls.php?ajax=get_settings', function (res) {
          if(res.status === 'success' && res.data) {
            const data = res.data;
            $('#water_watering_percent').val(data.water_watering_percent);
            $('#tank_low_percent').val(data.tank_low_percent);
            $('#tank_high_percent').val(data.tank_high_percent);
            $('#water_full_cm').val(data.water_full_cm);
            $('#water_empty_cm').val(data.water_empty_cm);
            $('#fert_full_cm').val(data.fert_full_cm);
            $('#fert_empty_cm').val(data.fert_empty_cm);
            $('#fert_duration_ms').val(data.fert_duration_ms);
            $('#buzzer_enabled').val(data.buzzer_enabled == 0 ? 0 : 1);
            $('#lcd_enabled').val(data.lcd_enabled == 0 ? 0 : 1);
            $('#backlight_enabled').val(data.backlight_enabled == 0 ? 0 : 1);
            $('#fertigation_enabled').val(data.fertigation_enabled == 0 ? 0 : 1);
            $('#rtc_set_time').val(data.rtc_set_time || 0);

            let totalMins = parseInt(data.fert_interval_minutes) || 1;
            if (totalMins <= 0) totalMins = 1;
            
            let unit = 1;
            if (totalMins % 43200 === 0) { unit = 43200; }
            else if (totalMins % 1440 === 0) { unit = 1440; }
            else if (totalMins % 60 === 0) { unit = 60; }
            
            $('#fert_interval_unit').val(unit);
            $('#fert_interval_amount').val(totalMins / unit);

            // buzzer interval ms logic
            let buzzMs = parseInt(data.buzzer_alert_interval_ms);
            if (isNaN(buzzMs) || buzzMs <= 0) {
              $('#buzzer_interval_unit').val(0);
              $('#buzzer_interval_amount').val(1).prop('disabled', true);
            } else {
              let buzzUnit = 1000;
              if (buzzMs % 60000 === 0) { buzzUnit = 60000; }
              $('#buzzer_interval_unit').val(buzzUnit);
              $('#buzzer_interval_amount').val(buzzMs / buzzUnit).prop('disabled', false);
            }

            $('#fertigation_enabled').trigger('change');
            $('#buzzer_interval_unit').trigger('change');
          }
        });
      }

      function saveSettings() {
        const btn = $('#btn-save-settings');
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Saving...');
        
        let unit = parseInt($('#fert_interval_unit').val()) || 1;
        let amount = parseInt($('#fert_interval_amount').val()) || 1;
        let totalMins = amount * unit;

        let buzzUnit = parseInt($('#buzzer_interval_unit').val()) || 0;
        let totalBuzzMs = 0;
        if (buzzUnit > 0) {
           let buzzAmount = parseInt($('#buzzer_interval_amount').val()) || 1;
           totalBuzzMs = buzzAmount * buzzUnit;
        }

        const data = {
          water_watering_percent: $('#water_watering_percent').val(),
          tank_low_percent: $('#tank_low_percent').val(),
          tank_high_percent: $('#tank_high_percent').val(),
          water_full_cm: $('#water_full_cm').val(),
          water_empty_cm: $('#water_empty_cm').val(),
          fert_full_cm: $('#fert_full_cm').val(),
          fert_empty_cm: $('#fert_empty_cm').val(),
          fert_duration_ms: $('#fert_duration_ms').val(),
          fert_interval_minutes: totalMins,
          fertigation_enabled: $('#fertigation_enabled').val(),
          buzzer_enabled: $('#buzzer_enabled').val(),
          buzzer_alert_interval_ms: totalBuzzMs,
          lcd_enabled: $('#lcd_enabled').val(),
          backlight_enabled: $('#backlight_enabled').val(),
          rtc_set_time: $('#rtc_set_time').val()
        };

        $.post('controls.php?ajax=save_settings', data, function(res) {
          btn.prop('disabled', false).html('<i class="fas fa-save"></i> Save Configuration');
          if (res.status === 'success') {
             showToast('System configuration saved!', false);
          } else {
             showToast('Error saving configuration', true);
          }
        });
      }

      $('#fertigation_enabled').on('change', function() {
         if ($(this).val() === '1') {
            $('#fert_interval_container').show();
         } else {
            $('#fert_interval_container').hide();
         }
      });

      $('#buzzer_interval_unit').on('change', function() {
         if ($(this).val() === '0') {
            $('#buzzer_interval_amount').prop('disabled', true);
         } else {
            $('#buzzer_interval_amount').prop('disabled', false);
         }
      });

      function syncRtcTime(btn) {
         $(btn).html('<i class="fas fa-spinner fa-spin"></i>').prop('disabled', true);
         
         const d = new Date();
         const pad = (n) => n.toString().padStart(2, '0');
         const ts = d.getFullYear() + "-" + pad(d.getMonth() + 1) + "-" + pad(d.getDate()) + " " + pad(d.getHours()) + ":" + pad(d.getMinutes()) + ":" + pad(d.getSeconds());
         
         $('#rtc_set_time').val(ts);
         saveSettings();
         setTimeout(function() {
            $(btn).html('<i class="fas fa-sync-alt"></i> Force Sync').prop('disabled', false);
         }, 1000);
      }

      $(document).ready(function () {
        loadSettings();
        loadCommandLogs();
        setInterval(loadCommandLogs, 5000);
      });
    </script>
  </body>
</html>