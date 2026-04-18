<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
      header('Location: login.php');
      exit;
  }

  $user_id     = (int) $_SESSION['user_id'];
  $username    = htmlspecialchars($_SESSION['username'] ?? 'User',  ENT_QUOTES, 'UTF-8');
  $active_page = 'scheduling';

  // --- AJAX CRUD Logic ---
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) || isset($_GET['action']))) {
      header('Content-Type: application/json');
      $action = $_POST['action'] ?? $_GET['action'] ?? '';
      
      // Helper function for DB operations
      $execute_crud = function($action, $conn, $user_id) {
          if ($action === 'add') {
              $date  = $_POST['date']  ?? '';
              $type  = $_POST['type']  ?? 'fertigation';
              $name  = $_POST['name']  ?? '';
              $time  = $_POST['time']  ?? '06:00';
              $notes = $_POST['notes'] ?? '';

              if (!$date || !$name) return ['error' => 'Date and Name are required'];

              $stmt = $conn->prepare("INSERT INTO farm_schedules (user_id, schedule_date, task_type, task_name, task_time, task_notes) VALUES (?, ?, ?, ?, ?, ?)");
              $stmt->bind_param("isssss", $user_id, $date, $type, $name, $time, $notes);
              if ($stmt->execute()) return ['success' => true, 'id' => $conn->insert_id];
              return ['error' => $conn->error];
          }

          if ($action === 'update') {
              $id    = (int) ($_POST['id'] ?? 0);
              $date  = $_POST['date']  ?? '';
              $type  = $_POST['type']  ?? 'fertigation';
              $name  = $_POST['name']  ?? '';
              $time  = $_POST['time']  ?? '06:00';
              $notes = $_POST['notes'] ?? '';

              if (!$id || !$date || !$name) return ['error' => 'ID, Date and Name are required'];

              $stmt = $conn->prepare("UPDATE farm_schedules SET schedule_date = ?, task_type = ?, task_name = ?, task_time = ?, task_notes = ? WHERE id = ? AND user_id = ?");
              $stmt->bind_param("sssssii", $date, $type, $name, $time, $notes, $id, $user_id);
              if ($stmt->execute()) return ['success' => true];
              return ['error' => $conn->error];
          }

          if ($action === 'delete') {
              $id = (int) ($_POST['id'] ?? 0);
              if (!$id) return ['error' => 'ID is required'];

              $stmt = $conn->prepare("DELETE FROM farm_schedules WHERE id = ? AND user_id = ?");
              $stmt->bind_param("ii", $id, $user_id);
              if ($stmt->execute()) return ['success' => true];
              return ['error' => $conn->error];
          }

          return ['error' => 'Invalid action'];
      };

      echo json_encode($execute_crud($action, $conn, $user_id));
      exit;
  }

  // Pre-select date if provided
  $preselect_date = '';
  if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
      $preselect_date = htmlspecialchars($_GET['date']);
  }

  // Fetch all schedules for the user to populate the calendar
  $all_schedules = [];
  $stmt = $conn->prepare("
      SELECT id, schedule_date, task_type, task_name,
             TIME_FORMAT(task_time, '%H:%i') AS task_time,
             task_notes, status
      FROM   farm_schedules
      WHERE  user_id = ?
      ORDER  BY schedule_date ASC, task_time ASC
  ");
  $stmt->bind_param("i", $user_id);
  if ($stmt->execute()) {
      $result = $stmt->get_result();
      while ($row = $result->fetch_assoc()) {
          $date = $row['schedule_date'];
          if (!isset($all_schedules[$date])) $all_schedules[$date] = [];
          
          $all_schedules[$date][] = [
              'id'    => (int)$row['id'],
              'type'  => $row['task_type'],
              'name'  => $row['task_name'],
              'time'  => $row['task_time'] ? substr($row['task_time'], 0, 5) : '00:00',
              'notes' => $row['task_notes'] ?? '',
              'status'=> $row['status']
          ];
      }
  }
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Scheduling — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

  <link rel="stylesheet" href="css/app.css">
  <link rel="stylesheet" href="css/schedule.css"/>

  <script src="js/jquery.min.js"></script>
  <script src="js/app.js"></script>

  <style>
    /* ── TYPE SELECTOR ── */
    .type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
    .type-btn { background: #fff; border: 1.5px solid var(--border); border-radius: var(--radius-sm); padding: 10px; cursor: pointer; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 600; font-size: 0.82rem; color: var(--text-muted); }
    .type-btn:hover               { border-color: var(--green-mid); color: var(--green-mid); background: var(--green-pale); }
    .type-btn.selected-irrigation { background: var(--water-pale);  color: var(--water);     border-color: var(--water); }
    .type-btn.selected-fertigation{background: var(--solar-pale); color: var(--solar);    border-color: var(--solar); }
    .type-btn.selected-harvest    { background: var(--red-pale);    color: var(--red);       border-color: var(--red); }
    .type-btn.selected-maintenance{ background: var(--green-pale);  color: var(--green-mid); border-color: var(--green-mid); }
</style>

</head>
<body>
<div class="layout">

  <?php include 'components/sidebar.php'; ?>

  <div class="main">
    <?php 
      $page_title = "Farm Scheduling";
      $page_icon = "fa-calendar-days";
      include 'components/header.php';
    ?>

    <!-- PAGE -->
    <div class="page-content">

      <div class="page-hero">
        <div class="page-hero-badge"><i class="fas fa-calendar-check" style="margin-right:6px;"></i>Farm Planner Pro</div>
        <h1>Farm <span>Scheduling</span></h1>
        <p>Manage irrigation, fertigation, harvests, and general maintenance. Streamlined for efficient farm management across all cycles.</p>
      </div>

      <div class="sched-layout">

        <!-- LEFT: Calendar + Upcoming -->
        <div class="cal-col">

          <div class="cal-card">
            <div class="cal-header">
              <div class="cal-month-label" id="cal-month-label">—</div>
              <div class="cal-nav-btns">
                <button class="cal-nav-btn today-btn" onclick="goToday()">Today</button>
                <button class="cal-nav-btn" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                <button class="cal-nav-btn" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
              </div>
            </div>
            <div class="cal-body">
              <div class="cal-weekdays">
                <div class="cal-wd">Sun</div><div class="cal-wd">Mon</div><div class="cal-wd">Tue</div>
                <div class="cal-wd">Wed</div><div class="cal-wd">Thu</div><div class="cal-wd">Fri</div>
                <div class="cal-wd">Sat</div>
              </div>
              <div class="cal-days-grid" id="cal-days-grid"></div>
            </div>
            <div class="cal-legend">
              <div class="legend-item"><div class="legend-dot" style="background:var(--water);"></div>Irrigation</div>
              <div class="legend-item"><div class="legend-dot" style="background:var(--solar);"></div>Fertigation</div>
              <div class="legend-item"><div class="legend-dot" style="background:var(--red);"></div>Harvest</div>
              <div class="legend-item"><div class="legend-dot" style="background:var(--green-mid);"></div>Maintenance</div>
              <div class="legend-item" style="margin-left:auto;font-family:var(--font-mono);font-size:.68rem;">
                <i class="fas fa-circle-plus" style="margin-right:4px;color:var(--green-mid);"></i>Click date to add
              </div>
            </div>
          </div>

          <!-- UPCOMING -->
          <div class="detail-card" style="margin-top:18px;">
            <div class="detail-head">
              <div class="detail-head-icon" style="background:var(--water-pale);color:var(--water);"><i class="fas fa-list-check"></i></div>
              <div>
                <div class="detail-title">Upcoming Tasks</div>
                <div class="detail-subtitle" id="upcoming-count">Next 14 days</div>
              </div>
            </div>
            <div class="detail-body" id="upcoming-list">
              <div class="empty-state"><div class="empty-icon">⏳</div><div class="empty-title">Loading…</div></div>
            </div>
          </div>

        </div><!-- /cal-col -->

        <!-- RIGHT: Day Detail + Add Form -->
        <div class="detail-panel">

          <!-- Day events -->
          <div class="detail-card">
            <div class="detail-head">
              <div class="detail-head-icon" style="background:var(--green-pale);color:var(--green-mid);"><i class="fas fa-calendar-day"></i></div>
              <div>
                <div class="detail-title" id="selected-day-label">Select a date</div>
                <div class="detail-subtitle" id="selected-day-count">Click a date to see tasks</div>
              </div>
            </div>
            <div class="detail-body">
              <div id="day-events-list">
                <div class="empty-state">
                  <div class="empty-icon">📅</div>
                  <div class="empty-title">No date selected</div>
                  <div class="empty-desc">Click any date on the calendar to view and manage tasks</div>
                </div>
              </div>
            </div>
          </div>

          <!-- Add Task Form -->
          <div class="detail-card">
            <div class="detail-head">
              <div class="detail-head-icon" style="background:var(--solar-pale);color:var(--solar);"><i class="fas fa-plus"></i></div>
              <div>
                <div class="detail-title">Add New Task</div>
                <div class="detail-subtitle" id="add-form-date-label">Select a date first</div>
              </div>
            </div>
            <div class="detail-body">
              <div class="form-group">
                <label class="form-label">Task Type</label>
                <div class="type-selector">
                  <button type="button" class="type-btn" onclick="selectType('irrigation')"    id="type-irrigation">   <i class="fas fa-droplet"></i> Irrigation</button>
                  <button type="button" class="type-btn" onclick="selectType('fertigation')" id="type-fertigation"><i class="fas fa-flask"></i> Fertigation</button>
                  <button type="button" class="type-btn" onclick="selectType('harvest')"       id="type-harvest">      <i class="fas fa-basket-shopping"></i> Harvest</button>
                  <button type="button" class="type-btn" onclick="selectType('maintenance')"   id="type-maintenance">  <i class="fas fa-wrench"></i> Maintenance</button>
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Task Name</label>
                <input type="text" class="form-input" id="task-name" placeholder="e.g. Morning Irrigation" maxlength="120"/>
              </div>
              <div class="form-group">
                <label class="form-label">Execution Time</label>
                <input type="time" class="form-input" id="task-time" value="06:00"/>
              </div>
              <div class="form-group">
                <label class="form-label">Notes <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
                <textarea class="form-textarea" id="task-notes" placeholder="Additional details, duration, amounts…"></textarea>
              </div>
              <div class="btn-row">
                <button type="button" class="btn btn-primary" id="add-btn" onclick="addEvent()"><i class="fas fa-plus"></i> Add Task</button>
                <button type="button" class="btn btn-ghost"  onclick="clearForm()"><i class="fas fa-xmark"></i></button>
              </div>
            </div>
          </div>

        </div><!-- /detail-panel -->
      </div><!-- /sched-layout -->

    </div><!-- /page-content -->

    <?php include "components/footer.php" ?>
  </div><!-- /main -->
</div><!-- /layout -->

<!-- ═══════════════ EDIT MODAL ═══════════════ -->
<div class="modal-overlay" id="edit-modal" onclick="handleBackdropClick(event)">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-icon" id="modal-type-icon" style="background:var(--water-pale);color:var(--water);">
        <i class="fas fa-pen-to-square"></i>
      </div>
      <div>
        <div class="modal-title">Edit Task</div>
        <div class="modal-subtitle" id="modal-date-label">—</div>
      </div>
      <button type="button" class="modal-close" onclick="closeModal()" title="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Task Type</label>
        <div class="type-selector">
          <button type="button" class="type-btn" onclick="selectEditType('irrigation')"    id="edit-type-irrigation">   <i class="fas fa-droplet"></i> Irrigation</button>
          <button type="button" class="type-btn" onclick="selectEditType('fertigation')" id="edit-type-fertigation"><i class="fas fa-flask"></i> Fertigation</button>
          <button type="button" class="type-btn" onclick="selectEditType('harvest')"       id="edit-type-harvest">      <i class="fas fa-basket-shopping"></i> Harvest</button>
          <button type="button" class="type-btn" onclick="selectEditType('maintenance')"   id="edit-type-maintenance">  <i class="fas fa-wrench"></i> Maintenance</button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Task Name</label>
        <input type="text" class="form-input" id="edit-name" placeholder="Task name" maxlength="120"/>
      </div>
      <div class="form-group">
        <label class="form-label">Execution Time</label>
        <input type="time" class="form-input" id="edit-time"/>
      </div>
      <div class="form-group">
        <label class="form-label">Date <span style="font-weight:400;text-transform:none;letter-spacing:0;">(reschedule)</span></label>
        <input type="date" class="form-input" id="edit-date"/>
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">Notes <span style="font-weight:400;text-transform:none;letter-spacing:0;">(optional)</span></label>
        <textarea class="form-textarea" id="edit-notes" placeholder="Additional details, duration, amounts…"></textarea>
      </div>
    </div>
    <div class="modal-footer">
      <button type="button" class="btn btn-save"   onclick="saveEdit()">          <i class="fas fa-floppy-disk"></i> Save Changes</button>
      <button type="button" class="btn btn-delete" onclick="deleteFromModal()">   <i class="fas fa-trash-can"></i> Delete</button>
      <button type="button" class="btn btn-ghost"  onclick="closeModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- ═══════════════ DELETE CONFIRMATION MODAL ═══════════════ -->
<div class="modal-overlay" id="delete-modal" onclick="handleDeleteBackdropClick(event)">
  <div class="modal" style="max-width: 380px;">
    <div class="modal-head" style="background: var(--red-pale); border-bottom-color: rgba(214,48,49,0.15);">
      <div class="modal-head-icon" style="background: var(--red-pale); color: var(--red);">
        <i class="fas fa-triangle-exclamation"></i>
      </div>
      <div>
        <div class="modal-title">Delete Task</div>
        <div class="modal-subtitle" id="delete-task-name">—</div>
      </div>
      <button type="button" class="modal-close" onclick="closeDeleteModal()" title="Close"><i class="fas fa-xmark"></i></button>
    </div>
    <div class="modal-body" style="text-align: center; padding: 24px 20px;">
      <p style="margin: 0 0 8px; font-size: 0.95rem; color: var(--text);">Are you sure you want to delete this task?</p>
      <p style="margin: 0; font-size: 0.82rem; color: var(--text-muted);">This action cannot be undone.</p>
    </div>
    <div class="modal-footer" style="justify-content: center; gap: 10px;">
      <button type="button" class="btn btn-delete" onclick="confirmDeleteEvent()">
        <i class="fas fa-trash-can"></i> Delete
      </button>
      <button type="button" class="btn btn-ghost" onclick="closeDeleteModal()">Cancel</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toast"></div>

<!-- ═══════════════ JAVASCRIPT ═══════════════ -->
<script>

/* ── STATE ── */
const NOW      = new Date();
const todayKey = fmtKey(NOW.getFullYear(), NOW.getMonth() + 1, NOW.getDate());
let calDate    = new Date(NOW.getFullYear(), NOW.getMonth(), 1);
let events     = <?= json_encode($all_schedules) ?>;         // Initialized from PHP
let selectedDate = null;
let selectedType = 'fertigation';
let editType     = 'fertigation';
let editingKey   = null;
let editingId    = null;
let isSaving     = false;

/* ── Type config ── */
const TC = {
  irrigation:    { color:'var(--water)',     pale:'var(--water-pale)',  icon:'fa-droplet',         pill:'irrigation',    badge:'badge-irr', label:'Irrigation'},
  fertigation: { color:'var(--solar)',     pale:'var(--solar-pale)',  icon:'fa-flask',           pill:'fertigation', badge:'badge-spr', label:'Fertigation'},
  harvest:       { color:'var(--red)',       pale:'var(--red-pale)',    icon:'fa-basket-shopping', pill:'harvest',       badge:'badge-har', label:'Harvest'},
  maintenance:   { color:'var(--green-mid)',  pale:'var(--green-pale)',  icon:'fa-wrench',          pill:'maintenance',   badge:'badge-mnt', label:'Maintenance'},
};
const TYPES = Object.keys(TC);

function fmtKey(y, m, d) {
  return y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
}

/* ══════════ LOAD FROM SERVER ══════════ */
// Now handled directly by PHP on page load. 
// We only need to re-render when the month changes or an event is added.
function refreshCalendar() {
  renderCalendar();
  if (selectedDate) renderDayPanel();
  renderUpcoming();
}

/* ══════════ CALENDAR ══════════ */
const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function renderCalendar() {
  const y = calDate.getFullYear(), mo = calDate.getMonth();
  document.getElementById('cal-month-label').textContent = MONTHS[mo] + ' ' + y;
  const grid     = document.getElementById('cal-days-grid');
  grid.innerHTML = '';
  const firstDay = new Date(y, mo, 1).getDay();
  const daysInM  = new Date(y, mo + 1, 0).getDate();
  const prevDays = new Date(y, mo,     0).getDate();

  for (let i = firstDay - 1; i >= 0; i--)
    renderCell(grid, prevDays - i, fmtKey(y, mo, prevDays - i), true);       // prev month cells

  for (let d = 1; d <= daysInM; d++)
    renderCell(grid, d, fmtKey(y, mo + 1, d), false);                        // current month

  const rem = (firstDay + daysInM) % 7;
  if (rem > 0) for (let d = 1; d <= 7 - rem; d++)
    renderCell(grid, d, fmtKey(y, mo + 2, d), true);                         // next month cells
}

function renderCell(grid, d, key, otherMonth) {
  const cell = document.createElement('div');
  const cellDate = new Date(key + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  const isPast = cellDate < today;
  
  cell.className = 'cal-cell' + (otherMonth ? ' other-month' : '') + (isPast ? ' past-date' : '');
  if (key === todayKey && !otherMonth) cell.classList.add('today');
  if (key === selectedDate)            cell.classList.add('selected');

  const evs = events[key] || [];
  let pillsHTML = '';
  evs.slice(0, 3).forEach(ev => {
    const safe = ev.name.length > 14 ? ev.name.slice(0, 13) + '…' : ev.name;
    pillsHTML += `<div class="cal-event-pill ${TC[ev.type].pill}"><i class="fas ${TC[ev.type].icon}"></i> ${safe}</div>`;
  });
  if (evs.length > 3) 
    pillsHTML += `<div class="cal-event-pill maintenance">+${evs.length - 3} more</div>`;

  cell.innerHTML = `
    <div class="cal-cell-day">
      <span>${d}</span>
      ${key === todayKey && !otherMonth ? '<span class="cal-today-dot"></span>' : ''}
    </div>
    <div class="cal-cell-events">${pillsHTML}</div>
    <div class="cal-add-hint">+ Add task</div>`;

  cell.onclick = () => selectDate(key);
  grid.appendChild(cell);
}

/* ══════════ DATE SELECTION ══════════ */
function selectDate(key) {
  const cellDate = new Date(key + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  if (cellDate < today) { showToast('Cannot select past dates.', true); return; }
  
  selectedDate = key;
  renderCalendar();
  renderDayPanel();
  const d = new Date(key + 'T00:00:00');
  document.getElementById('add-form-date-label').textContent =
    d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});
  document.getElementById('selected-day-label').textContent =
    d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'});
}

/* ══════════ DAY PANEL ══════════ */
function renderDayPanel() {
  const list = document.getElementById('day-events-list');
  const evs  = events[selectedDate] || [];
  document.getElementById('selected-day-count').textContent =
    evs.length === 0 ? 'No tasks scheduled' : evs.length + ' task' + (evs.length !== 1 ? 's' : '') + ' scheduled';

  if (evs.length === 0) {
    list.innerHTML = `<div class="empty-state">
      <div class="empty-icon">🌱</div>
      <div class="empty-title">No tasks this day</div>
      <div class="empty-desc">Use the form below to schedule irrigation, fertigation, or other farm work.</div>
    </div>`;
    return;
  }

  list.innerHTML = '';
  evs.forEach(ev => {
    const cfg  = TC[ev.type];
    const item = document.createElement('div');
    item.className = 'event-item';
    item.innerHTML = `
      <div class="event-dot" style="background:${cfg.color};"></div>
      <div class="event-body">
        <div class="event-title">${escHtml(ev.name)}</div>
        <div class="event-meta">
          <span class="upcoming-badge ${cfg.badge}">${cfg.label}</span>
          ${ev.notes ? escHtml(ev.notes) : ''}
        </div>
        <div class="event-time"><i class="fas fa-clock" style="margin-right:4px;font-size:.65rem;"></i>${ev.time}</div>
      </div>
      <div class="event-actions">
        <button type="button" class="btn-edit-sm"   title="Edit"   onclick="openEditModal('${selectedDate}', ${ev.id})"><i class="fas fa-pen"></i></button>
        <button type="button" class="btn-danger-sm" title="Delete" onclick="confirmDelete('${selectedDate}', ${ev.id}, '${escHtml(ev.name)}')"><i class="fas fa-trash-can"></i></button>
      </div>`;
    list.appendChild(item);
  });
}

/* ══════════ UPCOMING ══════════ */
function renderUpcoming() {
  const list = document.getElementById('upcoming-list');
  const upcoming = [];
  for (let i = 0; i <= 14; i++) {
    const d = new Date(NOW.getFullYear(), NOW.getMonth(), NOW.getDate() + i);
    const key = fmtKey(d.getFullYear(), d.getMonth() + 1, d.getDate());
    if (events[key]) events[key].forEach(ev => upcoming.push({key, date: d, ev}));
  }
  upcoming.sort((a, b) => a.key.localeCompare(b.key) || a.ev.time.localeCompare(b.ev.time));
  document.getElementById('upcoming-count').textContent =
    'Next 14 days · ' + upcoming.length + ' task' + (upcoming.length !== 1 ? 's' : '');

  if (upcoming.length === 0) {
    list.innerHTML = `<div class="empty-state"><div class="empty-icon">📆</div><div class="empty-title">No upcoming tasks</div><div class="empty-desc">Schedule your first task above.</div></div>`;
    return;
  }

  list.innerHTML = '';
  upcoming.forEach(({key, date, ev}) => {
    const cfg      = TC[ev.type];
    const dayLabel = key === todayKey ? 'Today' : date.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    const item     = document.createElement('div');
    item.className = 'upcoming-item';
    item.innerHTML = `
      <div class="event-dot" style="background:${cfg.color};flex-shrink:0;"></div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.84rem;font-weight:700;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escHtml(ev.name)}</div>
        <div style="font-size:.72rem;color:var(--text-muted);margin-top:2px;">${dayLabel} · ${ev.time}</div>
      </div>
      <div style="display:flex;gap:5px;align-items:center;flex-shrink:0;">
        <span class="upcoming-badge ${cfg.badge}">${cfg.label}</span>
        <button type="button" class="btn-edit-sm" style="padding:4px 8px;font-size:.7rem;" title="Edit" onclick="openEditModal('${key}', ${ev.id})">
          <i class="fas fa-pen"></i>
        </button>
      </div>`;
    list.appendChild(item);
  });
}

/* ══════════ TYPE SELECTORS ══════════ */
function selectType(type) {
  selectedType = type;
  TYPES.forEach(t => {
    const btn = document.getElementById('type-' + t);
    if (!btn) return;
    TYPES.forEach(cls => btn.classList.remove('selected-' + cls));
    if (t === type) btn.classList.add('selected-' + type);
  });
}

function selectEditType(type) {
  editType = type;
  const cfg = TC[type];
  TYPES.forEach(t => {
    const btn = document.getElementById('edit-type-' + t);
    if (!btn) return;
    TYPES.forEach(cls => btn.classList.remove('selected-' + cls));
    if (t === type) btn.classList.add('selected-' + type);
  });
  const icon = document.getElementById('modal-type-icon');
  if (icon) { icon.style.background = cfg.pale; icon.style.color = cfg.color; }
}

/* ══════════ ADD EVENT ══════════ */
function addEvent() {
  if (!selectedDate) { showToast('Please select a date on the calendar first.', true); return; }
  
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const selected = new Date(selectedDate + 'T00:00:00');
  if (selected < today) { showToast('Cannot schedule tasks on past dates.', true); return; }
  
  const name = document.getElementById('task-name').value.trim();
  if (!name) { document.getElementById('task-name').focus(); showToast('Task name is required.', true); return; }
  if (isSaving) return;
  isSaving = true;

  const addBtn = document.getElementById('add-btn');
  addBtn.disabled = true;
  addBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';

  const body = new URLSearchParams({
    action : 'add',
    date   : selectedDate,
    type   : selectedType,
    name   : name,
    time   : document.getElementById('task-time').value  || '06:00',
    notes  : document.getElementById('task-notes').value.trim()
  });

  fetch('schedule.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        if (!events[selectedDate]) events[selectedDate] = [];
        events[selectedDate].push({
          id   : data.id,
          type : selectedType,
          name : name,
          time : document.getElementById('task-time').value || '06:00',
          notes: document.getElementById('task-notes').value.trim()
        });
        events[selectedDate].sort((a, b) => a.time.localeCompare(b.time));
        clearForm();
        renderCalendar();
        renderDayPanel();
        renderUpcoming();
        showToast('✓ Task added successfully!');
      } else {
        showToast('Error: ' + (data.error || 'Could not save task.'), true);
      }
    })
    .catch(() => showToast('Network error. Please try again.', true))
    .finally(() => {
      isSaving = false;
      addBtn.disabled = false;
      addBtn.innerHTML = '<i class="fas fa-plus"></i> Add Task';
    });
}

/* ══════════ DELETE ══════════ */
let deleteTargetKey = null;
let deleteTargetId = null;

function confirmDelete(key, id, name) {
  deleteTargetKey = key;
  deleteTargetId = id;
  document.getElementById('delete-task-name').textContent = name;
  document.getElementById('delete-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
  document.getElementById('delete-modal').classList.remove('open');
  document.body.style.overflow = '';
  deleteTargetKey = null;
  deleteTargetId = null;
}

function handleDeleteBackdropClick(e) {
  if (e.target === document.getElementById('delete-modal')) closeDeleteModal();
}

function confirmDeleteEvent() {
  if (deleteTargetKey === null || deleteTargetId === null) return;
  const key = deleteTargetKey;
  const id = deleteTargetId;
  closeDeleteModal();
  deleteEvent(key, id);
}

function deleteEvent(key, id) {
  const body = new URLSearchParams({ action:'delete', id: String(id) });
  console.log('Sending delete request:', body.toString());
  fetch('schedule.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => {
      console.log('Delete response status:', r.status);
      return r.json();
    })
    .then(data => {
      console.log('Delete response data:', data);
      if (data.success || data.deleted) {
        if (events[key] && Array.isArray(events[key])) {
          events[key] = events[key].filter(e => Number(e.id) !== Number(id));
          if (events[key].length === 0) {
            delete events[key];
          }
        }
        renderCalendar();
        renderDayPanel();
        renderUpcoming();
        showToast('Task deleted.');
      } else {
        showToast('Error: ' + (data.error || 'Could not delete task.'), true);
      }
    })
    .catch(err => {
      console.error('Delete error:', err);
      showToast('Network error.', true);
    });
}

/* ══════════ CLEAR FORM ══════════ */
function clearForm() {
  document.getElementById('task-name').value  = '';
  document.getElementById('task-time').value  = '06:00';
  document.getElementById('task-notes').value = '';
}

/* ══════════ NAVIGATION ══════════ */
function changeMonth(delta) {
  calDate.setMonth(calDate.getMonth() + delta);
  refreshCalendar();
}

function goToday() {
  calDate = new Date(NOW.getFullYear(), NOW.getMonth(), 1);
  selectDate(todayKey);
  refreshCalendar();
}

/* ══════════ EDIT MODAL ══════════ */
function openEditModal(key, id) {
  const ev = (events[key] || []).find(e => e.id === id);
  if (!ev) return;
  editingKey = key;
  editingId  = id;

  document.getElementById('edit-name').value  = ev.name;
  document.getElementById('edit-time').value  = ev.time;
  document.getElementById('edit-date').value  = key;
  document.getElementById('edit-notes').value = ev.notes || '';
  selectEditType(ev.type);

  const d = new Date(key + 'T00:00:00');
  document.getElementById('modal-date-label').textContent =
    d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'});

  document.getElementById('edit-modal').classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(() => document.getElementById('edit-name').focus(), 250);
}

function closeModal() {
  document.getElementById('edit-modal').classList.remove('open');
  document.body.style.overflow = '';
  editingKey = null; editingId = null;
}

function handleBackdropClick(e) {
  if (e.target === document.getElementById('edit-modal')) closeModal();
}

function saveEdit() {
  if (editingKey === null || editingId === null) return;
  
  const newDate = document.getElementById('edit-date').value || editingKey;
  const editDate = new Date(newDate + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  if (editDate < today) { showToast('Cannot reschedule to a past date.', true); return; }
  
  const name = document.getElementById('edit-name').value.trim();
  if (!name) { document.getElementById('edit-name').focus(); showToast('Task name is required.', true); return; }

  const time    = document.getElementById('edit-time').value || '06:00';
  const notes   = document.getElementById('edit-notes').value.trim();

  const body = new URLSearchParams({
    action: 'update',
    id    : editingId,
    date  : newDate,
    type  : editType,
    name, time, notes
  });

  fetch('schedule.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        // Remove from old date
        if (events[editingKey]) {
          events[editingKey] = events[editingKey].filter(e => e.id !== editingId);
          if (events[editingKey].length === 0) delete events[editingKey];
        }
        // Add to new date
        if (!events[newDate]) events[newDate] = [];
        events[newDate].push({ id: editingId, type: editType, name, time, notes });
        events[newDate].sort((a, b) => a.time.localeCompare(b.time));

        // If moved to a different month, navigate there
        if (newDate !== editingKey) {
          const [py, pm] = newDate.split('-').map(Number);
          calDate = new Date(py, pm - 1, 1);
          selectedDate = newDate;
        }

        closeModal();
        renderCalendar();
        renderDayPanel();
        renderUpcoming();
        showToast('✓ Task updated successfully!');
      } else {
        showToast('Error: ' + (data.error || 'Could not update task.'), true);
      }
    })
    .catch(() => showToast('Network error.', true));
}

function deleteFromModal() {
  if (editingKey === null || editingId === null) return;
  const ev   = (events[editingKey] || []).find(e => e.id === editingId);
  const name = ev ? ev.name : 'this task';
  closeModal();
  setTimeout(() => confirmDelete(editingKey, editingId, name), 100);
}

/* ══════════ KEYBOARD ══════════ */
document.addEventListener('keydown', e => {
  const editModal = document.getElementById('edit-modal');
  const deleteModal = document.getElementById('delete-modal');
  
  if (editModal.classList.contains('open')) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'Enter' && e.ctrlKey) saveEdit();
  }
  
  if (deleteModal.classList.contains('open')) {
    if (e.key === 'Escape') closeDeleteModal();
    if (e.key === 'Enter') confirmDeleteEvent();
  }
});

/* ══════════ SIDEBAR CLOSE ON OUTSIDE CLICK ══════════ */
document.addEventListener('click', e => {
  const sidebar = document.getElementById('sidebar');
  if (sidebar && !sidebar.contains(e.target) && !e.target.closest('.mobile-toggle'))
    sidebar.classList.remove('open');
});

/* ══════════ UTILITY ══════════ */
function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ══════════ INIT ══════════ */
selectType('fertigation');

const preselectDate = '<?= $preselect_date ?>';
if (preselectDate) {
  const [py, pm] = preselectDate.split('-').map(Number);
  calDate = new Date(py, pm - 1, 1);
  selectDate(preselectDate);
} else {
  selectDate(todayKey);
}
refreshCalendar();
</script>
</body>
</html>