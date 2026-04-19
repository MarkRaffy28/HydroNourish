<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
      header('Location: login.php');
      exit;
  }

  if (isset($_GET['ajax'])) {
    error_reporting(0);
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    if ($action === 'get_messages') {
      $result = $conn->query(
        "SELECT id, message, recipients, status, sent_at 
        FROM gsm_messages 
        WHERE sent_at >= NOW() - INTERVAL 24 HOUR 
        ORDER BY sent_at DESC 
        LIMIT 50"
      );

      $rows = [];
      if ($result) {
        while ($row = $result->fetch_assoc()) {
          $row['recipients'] = explode(',', $row['recipients']);
          $rows[] = $row;
        }
      }
      echo json_encode($rows);
      exit;
    }

    if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $message    = trim($_POST['message']    ?? '');
      $recipients = trim($_POST['recipients'] ?? '');
      
      if ($message === '' || $recipients === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing fields']);
        exit;
      }

      $stmt = $conn->prepare("INSERT INTO gsm_messages (message, recipients, sent_at, user_id, status) VALUES (?, ?, NOW(), ?, 'pending')");
      $stmt->bind_param("ssi", $message, $recipients, $_SESSION['user_id']);
      if ($stmt->execute()) {
        echo json_encode(['ok' => true, 'id' => $stmt->insert_id]);
      } else {
        echo json_encode(['ok' => false, 'error' => $conn->error]);
      }
      exit;
    }

    if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
      $id = intval($_POST['id'] ?? 0);
      if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM gsm_messages WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        echo json_encode(['ok' => true]);
      } else {
        echo json_encode(['ok' => false, 'error' => 'Invalid ID']);
      }
      exit;
    }

    if ($action === 'get_contacts') {
      $res_opt = $conn->query("SELECT id, gsm_recipients FROM options LIMIT 1");

      $recipients = [];
      if ($res_opt && $res_opt->num_rows > 0) {
        $opt_row = $res_opt->fetch_assoc();
        $recipients = json_decode($opt_row['gsm_recipients'], true) ?: [];
      }
      
      echo json_encode($recipients);
      exit;
    }

    if ($action === 'add_contact') {
      $name = $_POST['name'] ?? '';
      $number = $_POST['number'] ?? '';
      
      $res_opt = $conn->query("SELECT id, gsm_recipients FROM options LIMIT 1");
      if ($res_opt && $res_opt->num_rows > 0) {
        $opt_row = $res_opt->fetch_assoc();
        $opt_id = $opt_row['id'];
        $recipients = json_decode($opt_row['gsm_recipients'], true) ?: [];
      } else {
        $conn->query("INSERT INTO options (gsm_recipients) VALUES ('[]')");
        $opt_id = $conn->insert_id;
        $recipients = [];
      }
        
      if ($name && $number) {
        if (!str_starts_with($number, '+63')) {
          $number = '+63' . ltrim($number, '0');
        }

        $exists = false;
        foreach ($recipients as $r) {
          if (is_array($r) && isset($r['number']) && $r['number'] === $number) {
            $exists = true; break;
          } else if (!is_array($r) && $r === $number) {
            $exists = true; break;
          }
        }
        if (!$exists) {
          $recipients[] = ['name' => $name, 'number' => $number];
          $json = json_encode($recipients);
          $stmt = $conn->prepare("UPDATE options SET gsm_recipients = ? WHERE id = ?");
          $stmt->bind_param("si", $json, $opt_id);
          $stmt->execute();
        }
      }
      echo json_encode(['ok' => true]);
      exit;
    }

    if ($action === 'delete_contact') {
      $index = isset($_POST['index']) ? (int)$_POST['index'] : -1;
      $res_opt = $conn->query("SELECT id, gsm_recipients FROM options LIMIT 1");
      if ($res_opt && $res_opt->num_rows > 0) {
        $opt_row = $res_opt->fetch_assoc();
        $opt_id = $opt_row['id'];
        $recipients = json_decode($opt_row['gsm_recipients'], true) ?: [];
        
        if ($index >= 0 && isset($recipients[$index])) {
          array_splice($recipients, $index, 1);
          $json = json_encode($recipients);
          $stmt = $conn->prepare("UPDATE options SET gsm_recipients = ? WHERE id = ?");
          $stmt->bind_param("si", $json, $opt_id);
          $stmt->execute();
        }
      }
      echo json_encode(['ok' => true]);
      exit;
    }

    if ($action === 'get_toggle') {
      $res = $conn->query("SELECT gsm_texting_enabled FROM options LIMIT 1");
      echo json_encode(['ok' => true, 'enabled' => $res && $row = $res->fetch_assoc() ? (int)$row['gsm_texting_enabled'] : 1]);
      exit;
    }

    if ($action === 'save_toggle') {
      $val = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 1;
      $conn->query("UPDATE options SET gsm_texting_enabled = $val");
      echo json_encode(['ok' => true]);
      exit;
    }

    if ($action === 'get_status') {
      $status = ['gsm_signal' => 0, 'gsm_carrier' => 'Unknown'];
      $res = $conn->query("SELECT gsm_signal, gsm_carrier FROM sensor_logs ORDER BY id DESC LIMIT 1");
      if ($res && $row = $res->fetch_assoc()) {
        $status = $row;
      }
      echo json_encode(['ok' => true, 'status' => $status]);
      exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
  }

  $gsm_enabled = 1;
  $res_toggle = $conn->query("SELECT gsm_texting_enabled FROM options LIMIT 1");
  if ($res_toggle && $row = $res_toggle->fetch_assoc()) {
    $gsm_enabled = (int)$row['gsm_texting_enabled'];
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GSM SMS Alerts | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/index.css">
    <link rel="stylesheet" href="css/monitor.css">
    <link rel="stylesheet" href="css/gsm.css">

    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/gsm.js"></script>
  </head>
  <body>
    <div class="layout">
      <?php
        $active_page = 'gsm';
        include 'components/sidebar.php';
      ?>

      <div class="main">
        <?php 
          $page_title = "GSM SMS Alerts";
          $page_icon = "fa-mobile-screen-button";
          include "components/header.php" 
        ?>

        <!-- ══ GSM VIEW ══ -->
        <div class="page-view active" id="view-gsm">
          <div class="page-content">

            <!-- ══ GSM SMS ALERTS HERO (Optional, but let's keep it clean) ══ -->
            <div class="hero m-b-28">
              <div class="hero-emoji">📱</div>
              <h1>GSM Direct <span>Alerts</span></h1>
              <p class="hero-sub">Directly send manual alerts via the SIM800L module to farm staff or groups.</p>
            </div>

            <div class="page-section" id="gsm-section">
              <div class="section-head">
                <div class="section-icon green-icon">
                  <i class="fas fa-mobile-screen-button"></i>
                </div>
                <div class="section-label">GSM Control Panel</div>
              </div>

              <div class="gsm-grid">
                <div class="gsm-left-col">
                  <!-- COMPOSE CARD -->
                  <div class="card">
                    <div class="card-head">
                      <div class="card-head-label"><i class="fas fa-paper-plane"></i> Compose Message</div>
                      <span class="badge badge-green" id="gsm-signal-badge"><i class="fas fa-signal"></i> Signal: --</span>
                    </div>
                    <div class="card-body">

                    <!-- Modem status bar -->
                    <div class="gsm-status-bar gsm-status-bar-flex">
                      <div class="gsm-status-left">
                        <div class="gsm-signal">
                          <span></span><span></span><span></span><span></span>
                        </div>
                        <div class="gsm-status-info">
                          <strong>SIM800L Active</strong>
                        </div>
                      </div>
                      <div class="gsm-status-right">
                        <select id="gsm_texting_enabled" class="gsm-toggle-select" onchange="saveGsmToggle()">
                          <option value="1" <?= $gsm_enabled === 1 ? 'selected' : '' ?>>Texting: ON</option>
                          <option value="0" <?= $gsm_enabled === 0 ? 'selected' : '' ?>>Texting: OFF</option>
                        </select>
                      </div>
                    </div>

                    <!-- ── RECIPIENT MODE TABS ── -->
                    <div class="compose-group">
                      <label class="compose-label"><i class="fas fa-address-book"></i> Send To</label>
                      <div class="gsm-mode-tabs">
                        <button type="button" class="gsm-mode-tab active" id="tab-saved" onclick="gsmSetMode('saved')">
                          <i class="fas fa-users"></i> Saved Contacts
                        </button>
                        <button type="button" class="gsm-mode-tab" id="tab-manual" onclick="gsmSetMode('manual')">
                          <i class="fas fa-keyboard"></i> Unsaved Number
                        </button>
                        <button type="button" class="gsm-mode-tab" id="tab-broadcast" onclick="gsmSetMode('broadcast')">
                          <i class="fas fa-tower-broadcast"></i> Broadcast All
                        </button>
                      </div>
                    </div>

                    <!-- SAVED CONTACTS panel -->
                    <div class="gsm-recipient-panel visible" id="panel-saved">
                      <div class="contact-actions-bar">
                        <label class="compose-label"><i class="fas fa-users"></i> Select Contacts</label>
                        <button type="button" class="btn-toggle start btn-sm-add" onclick="openAddContactModal()"><i class="fas fa-plus"></i>&nbsp; Add Contact</button>
                      </div>
                      <div id="saved-contacts-list" class="contacts-list-container">
                        <div class="contacts-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                      </div>
                    </div>

                    <!-- MANUAL NUMBER panel -->
                    <div class="gsm-manual-wrap" id="panel-manual">
                      <div class="compose-group m-b-6">
                        <div class="modal-input-addon-wrap">
                          <span class="modal-input-addon">+63</span>
                          <div class="gsm-tags-box" id="gsm-tags-box" onclick="document.getElementById('gsm-tag-input').focus()">
                            <input
                              type="text"
                              id="gsm-tag-input"
                              class="gsm-tag-input"
                              placeholder="Type 917... then Enter"
                              minlength="10"
                              maxlength="10"
                              autocomplete="off"
                            />
                          </div>
                        </div>
                        <div class="gsm-manual-hint">
                          <i class="fas fa-circle-info"></i>
                          Use 10-digit format. <strong>Enter</strong> to add. These numbers are not saved.
                        </div>
                      </div>
                    </div>

                    <!-- BROADCAST panel -->
                    <div class="gsm-recipient-panel" id="panel-broadcast">
                      <div class="gsm-broadcast-banner visible">
                        <i class="fas fa-tower-broadcast"></i>
                        <div>
                          <strong>Broadcast to All Staff</strong><br>
                          <span id="broadcast-count-txt">Message will be sent to all registered contacts simultaneously.</span>
                        </div>
                      </div>
                    </div>

                    <!-- Message textarea -->
                    <div class="compose-group">
                      <label class="compose-label"><i class="fas fa-comment"></i> Message</label>
                      <textarea
                        class="compose-textarea"
                        id="gsm-msg"
                        placeholder="Type your SMS message here…"
                        oninput="gsmUpdateCounter(this)"
                        maxlength="320"
                      ></textarea>
                      <div class="gsm-char-counter" id="gsm-char-count">0 / 160 chars · 1 SMS</div>
                    </div>

                    <button class="btn-gsm-send" id="gsm-send-btn" onclick="gsmSend()">
                      <i class="fas fa-paper-plane"></i> Send SMS
                    </button>

                  </div>
                </div>

                <!-- Modem Info (Moved here) -->
                <div class="card">
                  <div class="card-head">
                    <div class="card-head-label"><i class="fas fa-microchip"></i> Modem Info</div>
                  </div>
                  <div class="card-body">
                    <div class="modem-row">
                      <span class="modem-key"><i class="fas fa-network-wired"></i> Network</span>
                      <span class="modem-val" id="modem-carrier-val">--</span>
                    </div>
                    <div class="modem-row">
                      <span class="modem-key"><i class="fas fa-signal"></i> Signal Strength</span>
                      <span class="modem-val" id="modem-signal-val">--</span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="gsm-right-col">
                  <!-- TODAY'S SMS STATS -->
                  <div class="card">
                    <div class="card-head">
                      <div class="card-head-label"><i class="fas fa-chart-bar"></i> Today's SMS Stats</div>
                      <span class="badge badge-blue"><?= date('M j, Y') ?></span>
                    </div>
                    <div class="card-body">
                      <div class="sms-stats-grid">
                        <div class="sms-stat-box">
                          <div class="sms-stat-val green" id="gsm-sent-count">0</div>
                          <div class="sms-stat-lbl">Sent</div>
                        </div>
                        <div class="sms-stat-box">
                          <div class="sms-stat-val green" id="gsm-delivered-count">0</div>
                          <div class="sms-stat-lbl">Delivered</div>
                        </div>
                        <div class="sms-stat-box">
                          <div class="sms-stat-val red" id="gsm-failed-count">0</div>
                          <div class="sms-stat-lbl">Failed</div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Sent Log -->
                  <div class="card flex-1">
                    <div class="card-head">
                      <div class="card-head-label"><i class="fas fa-clock-rotate-left"></i> Recent Sent</div>
                      <span class="badge badge-blue">Last 24h</span>
                    </div>
                    <div class="card-body" id="gsm-sent-log">
                    </div>
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

    <!-- Add Contact Modal -->
    <div class="modal-overlay" id="add-contact-modal">
      <form class="modal-card" onsubmit="saveNewContact(); return false;">
        <div class="modal-icon green"><i class="fas fa-user-plus"></i></div>
        <h3 class="modal-header-h3">Add New Contact</h3>
        <div class="modal-input-group">
          <label class="compose-label">Name</label>
          <input type="text" id="new-contact-name" class="compose-input" placeholder="e.g. John Doe" required>
        </div>
        <div class="modal-input-group">
          <label class="compose-label">Contact Number</label>
          <div class="modal-input-addon-wrap">
            <span class="modal-input-addon">+63</span>
            <input type="text" id="new-contact-number" class="compose-input modal-input-noshadow" placeholder="9171234567" minlength="10" maxlength="10" required>
          </div>
        </div>
        <div class="modal-actions-row">
          <button type="button" class="btn-modal-cancel" onclick="closeAddContactModal()">Cancel</button>
          <button type="submit" class="btn-modal-save">Save</button>
        </div>
      </form>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="delete-modal">
      <form class="modal-card" onsubmit="document.getElementById('confirm-delete-btn').click(); return false;">
        <div class="modal-icon red"><i class="fas fa-trash-can"></i></div>
        <h3 class="modal-header-h3">Remove Contact?</h3>
        <p class="delete-modal-msg">Are you sure you want to remove <strong id="modal-delete-display" class="delete-modal-strong"></strong> from the contacts list?</p>
        <div class="modal-actions-row">
          <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
          <button type="submit" class="btn-modal-danger" id="confirm-delete-btn">Remove</button>
        </div>
      </form>
    </div>

    <!-- Delete Log Confirmation Modal -->
    <div class="modal-overlay" id="delete-log-modal">
      <form class="modal-card" onsubmit="confirmDeleteLog(); return false;">
        <div class="modal-icon red"><i class="fas fa-trash-can"></i></div>
        <h3 class="modal-header-h3">Delete Message?</h3>
        <p class="delete-modal-msg">Are you sure you want to delete this message record? This action cannot be undone.</p>
        <div class="modal-actions-row">
          <button type="button" class="btn-modal-cancel" onclick="closeDeleteLogModal()">Cancel</button>
          <button type="submit" class="btn-modal-danger">Delete</button>
        </div>
      </form>
    </div>
  </body>
</html>
