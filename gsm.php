<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_GET['ajax'])) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// ── AJAX HANDLER ──
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $action = $_GET['ajax'];

    // 1. Get Recent Messages (Last 24h)
    if ($action === 'get_messages') {
        $result = $conn->query("SELECT id, message, recipients, status, sent_at FROM gsm_messages WHERE sent_at >= NOW() - INTERVAL 24 HOUR ORDER BY sent_at DESC LIMIT 50");
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

    // 2. Save / Send Message
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

    // 3. Delete Message
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

    // 4. Contacts CRUD
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
            // Convert to format +63xxxxxxxxx
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

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid action']);
    exit;
}

$username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>GSM SMS Alerts — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <link rel="stylesheet" href="css/app.css">
  <link rel="stylesheet" href="css/index.css">
  <link rel="stylesheet" href="css/monitor.css">

  <script src="js/jquery.min.js"></script>
  <script src="js/app.js"></script>
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
        <div class="hero" style="margin-bottom:28px;">
          <div class="hero-tomato">📱</div>
          <div class="hero-tag">Communication — Farm</div>
          <h1>GSM Direct <span>Alerts</span></h1>
          <p class="hero-sub">Directly send manual alerts via the SIM800L module to farm staff or groups. Monitor GSM health.</p>
        </div>

        <div class="page-section" id="gsm-section">
          <div class="section-head">
            <div class="section-icon" style="background:var(--green-pale);color:var(--green-mid);">
              <i class="fas fa-mobile-screen-button"></i>
            </div>
            <div class="section-label">GSM Control Panel</div>
            <div class="section-meta">
              <span class="gsm-pulse-dot"></span> Modem Online · SIM Ready
            </div>
          </div>

          <div class="gsm-grid">

            <!-- COMPOSE CARD -->
            <div class="card">
              <div class="card-head">
                <div class="card-head-label"><i class="fas fa-paper-plane"></i> Compose Message</div>
                <span class="badge badge-green"><i class="fas fa-signal"></i> Signal: Good</span>
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
                      <span>SMART PH · +63</span>
                    </div>
                  </div>
                  <div class="gsm-status-right">
                    <select id="gsm_texting_enabled" class="gsm-toggle-select" onchange="saveGsmToggle()">
                      <option value="1">Texting: ON</option>
                      <option value="0">Texting: OFF</option>
                    </select>
                  </div>
                </div>

                <!-- ── RECIPIENT MODE TABS ── -->
                <div class="compose-group">
                  <label class="compose-label"><i class="fas fa-address-book"></i> &nbsp;Send To</label>
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
                    <button type="button" class="btn-toggle start btn-sm-add" onclick="openAddContactModal()"><i class="fas fa-plus"></i> Add Contact</button>
                  </div>
                  <div id="saved-contacts-list" class="contacts-list-container">
                    <div class="contacts-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
                  </div>
                </div>

                <!-- MANUAL NUMBER panel -->
                <div class="gsm-manual-wrap" id="panel-manual">
                  <div class="compose-group" style="margin-bottom:6px;">
                    <div class="gsm-tags-box" id="gsm-tags-box" onclick="document.getElementById('gsm-tag-input').focus()">
                      <input
                        type="text"
                        id="gsm-tag-input"
                        class="gsm-tag-input"
                        placeholder="+63917… then press Enter or comma"
                        autocomplete="off"
                      />
                    </div>
                    <div class="gsm-manual-hint">
                      <i class="fas fa-circle-info"></i>
                      Type a number and press <strong>Enter</strong> or <strong>,</strong> to add. These numbers are not saved.
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

                <!-- Priority -->
                <div class="compose-group">
                  <label class="compose-label"><i class="fas fa-flag"></i> &nbsp;Priority</label>
                  <select class="compose-select" id="gsm-priority">
                    <option value="normal">🟢 Normal</option>
                    <option value="urgent">🟡 Urgent</option>
                    <option value="critical">🔴 Critical Alert</option>
                  </select>
                </div>

                <!-- Quick templates -->
                <div class="compose-group">
                  <label class="compose-label"><i class="fas fa-bolt"></i> &nbsp;Quick Templates</label>
                  <div class="quick-msg-chips">
                    <span class="quick-chip" onclick="gsmSetMsg('ALERT: Soil moisture dropped below 40%. Irrigation triggered automatically.')">
                      <i class="fas fa-droplet"></i> Low Moisture
                    </span>
                    <span class="quick-chip" onclick="gsmSetMsg('NOTICE: Fertigation scheduled for tomorrow at 6:00 AM. Prepare the chemical tank.')">
                      <i class="fas fa-spray-can"></i> Fertigation Notice
                    </span>
                    <span class="quick-chip" onclick="gsmSetMsg('ALERT: Battery level critical at 15%. Solar panels may need inspection.')">
                      <i class="fas fa-battery-quarter"></i> Low Battery
                    </span>
                    <span class="quick-chip" onclick="gsmSetMsg('INFO: All systems normal. Daily system health check passed. Next irrigation in 2h.')">
                      <i class="fas fa-circle-check"></i> System OK
                    </span>
                    <span class="quick-chip" onclick="gsmSetMsg('HARVEST REMINDER: Tomatoes ready for harvest. Schedule picking crew for tomorrow.')">
                      <i class="fas fa-basket-shopping"></i> Harvest
                    </span>
                  </div>
                </div>

                <!-- Message textarea -->
                <div class="compose-group">
                  <label class="compose-label"><i class="fas fa-comment"></i> &nbsp;Message</label>
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

            <div class="gsm-right-col">

              <!-- TODAY'S SMS STATS — counts start at 0, loaded dynamically from DB -->
              <div class="card">
                <div class="card-head">
                  <div class="card-head-label"><i class="fas fa-chart-bar"></i> Today's SMS Stats</div>
                  <span class="badge badge-muted"><?= date('M j, Y') ?></span>
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
              <div class="card" style="flex:1;">
                <div class="card-head">
                  <div class="card-head-label"><i class="fas fa-clock-rotate-left"></i> Recent Sent</div>
                  <span class="badge badge-blue">Last 24h</span>
                </div>
                <div class="card-body" id="gsm-sent-log">
                  <!-- Populated by loadGsmMessages() -->
                </div>
              </div>

              <!-- Modem Info -->
              <div class="card">
                <div class="card-head">
                  <div class="card-head-label"><i class="fas fa-microchip"></i> Modem Info</div>
                  <span class="badge badge-green"><span class="gsm-pulse-dot" style="width:6px;height:6px;margin-right:2px;"></span> Online</span>
                </div>
                <div class="card-body">
                  <div class="modem-row">
                    <span class="modem-key"><i class="fas fa-sim-card"></i> Module</span>
                    <span class="modem-val">SIM800L v2.0</span>
                  </div>
                  <div class="modem-row">
                    <span class="modem-key"><i class="fas fa-network-wired"></i> Network</span>
                    <span class="modem-val">SMART Telecom PH</span>
                  </div>
                  <div class="modem-row">
                    <span class="modem-key"><i class="fas fa-signal"></i> Signal</span>
                    <span class="modem-val">-72 dBm (Good)</span>
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

<!-- Modal Overlays -->

<!-- Add Contact Modal -->
<div class="modal-overlay" id="add-contact-modal">
  <div class="modal-card">
    <div class="modal-icon green"><i class="fas fa-user-plus"></i></div>
    <h3 class="modal-header-h3">Add New Contact</h3>
    <div class="modal-input-group">
      <label class="compose-label">Name</label>
      <input type="text" id="new-contact-name" class="compose-input" placeholder="e.g. John Doe">
    </div>
    <div class="modal-input-group">
      <label class="compose-label">Contact Number</label>
      <div class="modal-input-addon-wrap">
        <span class="modal-input-addon">+63</span>
        <input type="text" id="new-contact-number" class="compose-input modal-input-noshadow" placeholder="9171234567">
      </div>
    </div>
    <div class="modal-actions-row">
      <button class="btn-modal-cancel" onclick="closeAddContactModal()">Cancel</button>
      <button class="btn-modal-save" onclick="saveNewContact()">Save</button>
    </div>
  </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="delete-modal">
  <div class="modal-card">
    <div class="modal-icon red"><i class="fas fa-trash-can"></i></div>
    <h3 class="modal-header-h3">Remove Contact?</h3>
    <p class="delete-modal-msg">Are you sure you want to remove <strong id="modal-delete-display" class="delete-modal-strong"></strong> from the contacts list?</p>
    <div class="modal-actions-row">
      <button class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
      <button class="btn-modal-danger" id="confirm-delete-btn">Remove</button>
    </div>
  </div>
</div>

<script>
  // ── Mode Switching ──
  function gsmSetMode(mode) {
    document.querySelectorAll('.gsm-mode-tab').forEach(btn => btn.classList.remove('active'));
    document.getElementById('tab-' + mode).classList.add('active');

    document.getElementById('panel-saved').classList.remove('visible');
    document.getElementById('panel-manual').classList.remove('visible');
    document.getElementById('panel-broadcast').classList.remove('visible');
    
    document.getElementById('panel-' + mode).classList.add('visible');
    
    if (mode === 'manual') {
      document.getElementById('gsm-tag-input').focus();
    }
  }

  // ── Manual Tags ──
  const tagInput = document.getElementById('gsm-tag-input');
  const tagBox = document.getElementById('gsm-tags-box');
  const tags = new Set();

  if (tagInput) {
    tagInput.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        addTag(tagInput.value.trim().replace(',', ''));
        tagInput.value = '';
      } else if (e.key === 'Backspace' && tagInput.value === '' && tags.size > 0) {
        const lastTag = Array.from(tags).pop();
        removeTag(lastTag);
      }
    });
  }

  function addTag(val) {
    if (!val || tags.has(val)) return;
    if (!/^\+?[0-9]{10,13}$/.test(val)) {
      showToast('Invalid phone number format', true);
      return;
    }
    tags.add(val);
    renderTags();
  }

  function removeTag(val) {
    tags.delete(val);
    renderTags();
  }

  function renderTags() {
    // Remove old tags
    tagBox.querySelectorAll('.gsm-tag').forEach(t => t.remove());
    // Add current tags
    tags.forEach(val => {
      const t = document.createElement('span');
      t.className = 'gsm-tag';
      t.innerHTML = `${val} <i class="fas fa-times" onclick="removeTag('${val}')"></i>`;
      tagBox.insertBefore(t, tagInput);
    });
  }

  // ── Compose Logic ──
  function gsmSetMsg(txt) {
    const field = document.getElementById('gsm-msg');
    field.value = txt;
    gsmUpdateCounter(field);
  }

  function gsmUpdateCounter(el) {
    const len = el.value.length;
    const smsCount = Math.ceil(len / 160) || 1;
    document.getElementById('gsm-char-count').textContent = `${len} / ${smsCount * 160} chars · ${smsCount} SMS`;
  }

  function gsmSend() {
    const msg = document.getElementById('gsm-msg').value.trim();
    const mode = document.querySelector('.gsm-mode-tab.active').id.replace('tab-', '');
    let recipients = '';

    if (mode === 'saved') {
      let checked = [];
      $('.contact-checkbox:checked').each(function() {
          checked.push($(this).val());
      });
      recipients = checked.join(',');
    } else if (mode === 'manual') {
      recipients = Array.from(tags).join(',');
    } else {
      recipients = 'ALL_STAFF';
    }

    if (!msg) {
      showToast('Please type a message', true);
      return;
    }
    if (!recipients) {
      showToast('Please select or enter recipients', true);
      return;
    }

    const btn = document.getElementById('gsm-send-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending…';

    const body = new URLSearchParams({ message: msg, recipients: recipients });
    fetch('gsm.php?ajax=save', { method: 'POST', body })
      .then(r => r.json())
      .then(res => {
        if (res.ok) {
          showToast('SMS sent correctly via SIM800L', false);
          document.getElementById('gsm-msg').value = '';
          gsmUpdateCounter(document.getElementById('gsm-msg'));
          if (mode === 'manual') { tags.clear(); renderTags(); }
          loadGsmStats();
          loadGsmMessages();
        } else {
          showToast(res.error || 'Failed to send SMS', true);
        }
      })
      .catch(() => showToast('Connection error', true))
      .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-paper-plane"></i> Send SMS';
      });
  }


  function loadGsmStats() {
    fetch('gsm.php?ajax=get_messages')
      .then(r => r.json())
      .then(rows => {
        const sentCount = rows.length;
        document.getElementById('gsm-sent-count').textContent = sentCount;
        // Simulating delivered/failed for dummy stats
        document.getElementById('gsm-delivered-count').textContent = Math.floor(sentCount * 0.9);
        document.getElementById('gsm-failed-count').textContent = Math.ceil(sentCount * 0.1);
      }); 
  }

  function loadGsmMessages() {
    fetch('gsm.php?ajax=get_messages')
      .then(r => r.json())
      .then(rows => {
        const log = document.getElementById('gsm-sent-log');
        if (rows.length === 0) {
          log.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-muted);font-size:0.8rem;">No messages today</div>';
          return;
        }
        log.innerHTML = rows.map(r => {
          const time = new Date(r.sent_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
          const statusClass = (r.status || 'Sent').toLowerCase();
          const recipients = Array.isArray(r.recipients) ? r.recipients.join(', ') : r.recipients;
          return `
            <div class="gsm-log-item">
              <div class="gsm-log-meta">
                <span class="gsm-log-to"><i class="fas fa-user"></i> ${recipients}</span>
                <span class="gsm-log-time">${time}</span>
              </div>
              <div class="gsm-log-msg">${r.message}</div>
              <div class="gsm-log-status" style="display:flex; justify-content:space-between; align-items:center;">
                <span><i class="fas fa-check-double green"></i> ${r.status || 'Sent'}</span>
                <button onclick="gsmDeleteMsg(${r.id})" style="background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:0.7rem;" title="Delete Log">
                  <i class="fas fa-trash"></i>
                </button>
              </div>
            </div>
          `;
        }).join('');
      });
  }

  function gsmDeleteMsg(id) {
    if (!confirm('Delete this log entry?')) return;
    const body = new URLSearchParams({ id });
    fetch('gsm.php?ajax=delete', { method: 'POST', body })
      .then(r => r.json())
      .then(res => {
        if (res.ok) {
          loadGsmStats();
          loadGsmMessages();
        }
      });
  }

  // ── Contacts CRUD ──
  function loadContacts() {
    $.get('gsm.php?ajax=get_contacts', function(data) {
        let html = '';
        if (data.length === 0) {
            html = '<div class="contacts-empty">No contacts found.<br><br><span class="contacts-empty-sub">Click Add Contact above to begin.</span></div>';
        } else {
            data.forEach((contact, i) => {
                let name = contact.name || 'Unknown';
                let num = contact.number || contact; 
                html += `
                <div class="contact-item">
                  <label class="contact-label-row">
                    <input type="checkbox" class="contact-checkbox" value="${num}">
                    <span><strong>${name}</strong> <span class="contact-number-hint">${num}</span></span>
                  </label>
                  <button type="button" class="btn-contact-del" onclick="openDeleteModal(${i}, '${name}')">
                    <i class="fas fa-trash-can"></i>
                  </button>
                </div>
                `;
            });
        }
        $('#saved-contacts-list').html(html);
        $('#broadcast-count-txt').text(`Message will be sent to all ${data.length} registered contacts simultaneously.`);
    });
  }

  function openAddContactModal() {
    document.getElementById('new-contact-name').value = '';
    document.getElementById('new-contact-number').value = '';
    document.getElementById('add-contact-modal').classList.add('open');
  }

  function closeAddContactModal() {
    document.getElementById('add-contact-modal').classList.remove('open');
  }

  function saveNewContact() {
    let name = document.getElementById('new-contact-name').value.trim();
    let num = document.getElementById('new-contact-number').value.trim();
    if (!name || !num) {
      showToast('Please provide both name and number', true);
      return;
    }

    $.post('gsm.php?ajax=add_contact', { name: name, number: num }, function(res) {
        if (res.ok) {
            showToast('Contact saved correctly', false);
            closeAddContactModal();
            loadContacts();
        } else {
            showToast('Error saving contact', true);
        }
    });
  }

  let contactToDelete = -1;
  function openDeleteModal(index, name) {
    contactToDelete = index;
    $('#modal-delete-display').text(name);
    $('#delete-modal').addClass('open');
  }

  function closeDeleteModal() {
    $('#delete-modal').removeClass('open');
  }

  $('#confirm-delete-btn').on('click', function() {
      $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
      $.post('gsm.php?ajax=delete_contact', { index: contactToDelete }, function(res) {
          $('#confirm-delete-btn').prop('disabled', false).html('Remove');
          if (res.ok) {
              closeDeleteModal();
              loadContacts();
              showToast('Contact removed', false);
          }
      });
  });

  function loadGsmToggle() {
    $.get('gsm.php?ajax=get_toggle', function(res) {
      if (res.ok) {
        $('#gsm_texting_enabled').val(res.enabled);
      }
    });
  }

  function saveGsmToggle() {
    let val = $('#gsm_texting_enabled').val();
    $.post('gsm.php?ajax=save_toggle', { enabled: val }, function(res) {
      if (res.ok) {
        showToast(val == 1 ? 'Global Texting Enabled' : 'Global Texting Disabled', false);
      }
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadContacts();
    loadGsmStats();
    loadGsmMessages();
    loadGsmToggle();
    
    // Auto-refresh log every 10s
    setInterval(() => {
      loadGsmStats();
      loadGsmMessages();
    }, 10000);

    // Close modals on outside click
    window.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
      }
    });
  });
</script>
</body>
</html>
