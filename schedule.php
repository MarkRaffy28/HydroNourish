<?php
  session_start();
  require_once 'config/db.php';

  if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
  }

  $user_id     = (int) $_SESSION['user_id'];

  if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) || isset($_GET['action']))) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
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

  $preselect_date = '';
  if (!empty($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
      $preselect_date = htmlspecialchars($_GET['date']);
  }

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
    <title>Scheduling | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com"/>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/schedule.css"/>

    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
    <script>
      window.SCHED_DATA = <?= json_encode($all_schedules) ?>;
      window.SCHED_PRESELECT = '<?= $preselect_date ?>';
    </script>
    <script src="js/schedule.js"></script>
  </head>
  <body>
    <div class="layout">
      <?php 
        $active_page = 'scheduling';
        include 'components/sidebar.php'; 
      ?>

      <div class="main">
        <?php 
          $page_title = "Farm Scheduling";
          $page_icon = "fa-calendar-days";
          include 'components/header.php';
        ?>

        <div class="page-content">
          <div class="page-hero">
            <div class="hero-emoji">📅</div>
            <h1>Farm <span>Scheduling</span></h1>
            <p>Manage irrigation, fertigation, harvests, and general maintenance. Designed for efficient farm management across all cycles.</p>
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
                  <div class="legend-item"><div class="legend-dot water-bg"></div>Irrigation</div>
                  <div class="legend-item"><div class="legend-dot solar-bg"></div>Fertigation</div>
                  <div class="legend-item"><div class="legend-dot red-bg"></div>Harvest</div>
                  <div class="legend-item"><div class="legend-dot green-mid-bg"></div>Maintenance</div>
                  <div class="legend-item m-l-auto f-mono f-s-068">
                    <i class="fas fa-circle-plus m-r-4 green-mid-color"></i>Click date to add
                  </div>
                </div>
              </div>

              <!-- UPCOMING -->
              <div class="detail-card m-t-18">
                <div class="detail-head">
                  <div class="detail-head-icon water-icon"><i class="fas fa-list-check"></i></div>
                  <div>
                    <div class="detail-title">Upcoming Tasks</div>
                    <div class="detail-subtitle" id="upcoming-count">Next 14 days</div>
                  </div>
                </div>
                <div class="detail-body" id="upcoming-list">
                  <div class="empty-state"><div class="empty-icon">⏳</div><div class="empty-title">Loading…</div></div>
                </div>
              </div>
            </div>

            <!-- RIGHT: Day Detail + Add Form -->
            <div class="detail-panel">

              <!-- Day events -->
              <div class="detail-card">
                <div class="detail-head">
                  <div class="detail-head-icon green-icon"><i class="fas fa-calendar-day"></i></div>
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
                  <div class="detail-head-icon solar-icon"><i class="fas fa-plus"></i></div>
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
                    <label class="form-label">Notes <span class="fw-400 t-transform-none space-0">(optional)</span></label>
                    <textarea class="form-textarea" id="task-notes" placeholder="Additional details, duration, amounts…"></textarea>
                  </div>
                  <div class="btn-row">
                    <button type="button" class="btn btn-primary" id="add-btn" onclick="addEvent()"><i class="fas fa-plus"></i> Add Task</button>
                    <button type="button" class="btn btn-ghost"  onclick="clearForm()"><i class="fas fa-xmark"></i></button>
                  </div>
                </div>
              </div>

            </div>
          </div>

        </div>

        <?php include "components/footer.php" ?>
      </div>
    </div>

    <div class="modal-overlay" id="edit-modal" onclick="handleBackdropClick(event)">
      <div class="modal">
        <div class="modal-head">
          <div class="modal-head-icon water-icon" id="modal-type-icon">
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
            <label class="form-label">Date <span class="fw-400 t-transform-none space-0">(reschedule)</span></label>
            <input type="date" class="form-input" id="edit-date"/>
          </div>
          <div class="form-group m-b-0">
            <label class="form-label">Notes <span class="fw-400 t-transform-none space-0">(optional)</span></label>
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

    <div class="modal-overlay" id="delete-modal" onclick="handleDeleteBackdropClick(event)">
      <form class="modal-card" onsubmit="confirmDeleteEvent(); return false;">
        <div class="modal-icon red"><i class="fas fa-trash-can"></i></div>
        <h3 class="modal-header-h3">Delete Task?</h3>
        <p class="delete-modal-msg">Are you sure you want to delete <strong id="delete-task-name" class="delete-modal-strong">—</strong>? This action cannot be undone.</p>
        <div class="modal-actions-row">
          <button type="button" class="btn-modal-cancel" onclick="closeDeleteModal()">Cancel</button>
          <button type="submit" class="btn-modal-danger">Delete</button>
        </div>
      </form>
    </div>
  </body>
</html>