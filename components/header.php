<?php
  require_once "config/db.php";

  $stmt = $conn->query("SELECT * FROM sensor_logs ORDER BY created_at DESC LIMIT 1");
  $row = $stmt->fetch_assoc();
  $stmt->close();
  
  $is_offline = true;

  if ($row['created_at']) {
    $last_time = strtotime($row['created_at']);

    if (time() - $last_time < 20) {
        $is_offline = false;
    }
  }

  $page_title ??= "Dashboard";
  $page_icon ??= "fa-house";
?>

<div class="topbar">
  <div class="topbar-title"> 
    <i class="fas <?= $page_icon ?>"></i> <?= $page_title ?>
  </div>
  <div class="connection-status-badge <?= $is_offline ? "offline" : "online" ?>">
    <span class="connection-status-dot <?= $is_offline ? "offline" : "online" ?>"></span> 
    <?= $is_offline ? "Offline" : "Online" ?>
  </div>
  <small class="connection-status-time">
    <?php
      if ($is_offline) {
          if (!empty($row['created_at'])) {
              $diff = time() - strtotime($row['created_at']);

              $days = floor($diff / 86400);
              $hours = floor(($diff % 86400) / 3600);
              $minutes = floor(($diff % 3600) / 60);
              $seconds = $diff % 60;

              $parts = [];

              if ($days > 0) {
                  $parts[] = $days . 'd';
                  if ($hours > 0) $parts[] = $hours . 'h';
              } elseif ($hours > 0) {
                  $parts[] = $hours . 'h';
                  if ($minutes > 0) $parts[] = $minutes . 'm';
              } elseif ($minutes > 0) {
                  $parts[] = $minutes . 'm';
                  if ($seconds > 0) $parts[] = $seconds . 's';
              } else {
                  $parts[] = $seconds . 's';
              }

              echo 'Last Seen ' . implode(' ', array_slice($parts, 0, 2)) . ' ago';
          } else {
              echo 'No data yet';
          }
      }
    ?>
  </small>
  <div class="topbar-clock clock">—</div>
</div>