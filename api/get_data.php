<?php
  header("Content-Type: application/json");
  header("Access-Control-Allow-Origin: *");

  require_once "../config/db.php";

  if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
  }

  try {
      $options = null;

      $result = $conn->query("SELECT * FROM options ORDER BY id DESC LIMIT 1");

      if ($result && $row = $result->fetch_assoc()) {
        $numeric_fields = [
          'water_watering_percent', 'tank_low_percent', 'tank_high_percent',
          'water_full_cm', 'water_empty_cm', 'fert_full_cm', 'fert_empty_cm',
          'fert_interval_minutes', 'fert_duration_ms', 'buzzer_alert_interval_ms'
        ];
        foreach ($numeric_fields as $field) {
          if (isset($row[$field])) $row[$field] = (int)$row[$field];
        }

        $row['buzzer_enabled']      = isset($row['buzzer_enabled'])      ? (bool)$row['buzzer_enabled']      : true;
        $row['lcd_enabled']         = isset($row['lcd_enabled'])         ? (bool)$row['lcd_enabled']         : true;
        $row['backlight_enabled']   = isset($row['backlight_enabled'])   ? (bool)$row['backlight_enabled']   : true;
        $row['fertigation_enabled'] = isset($row['fertigation_enabled']) ? (bool)$row['fertigation_enabled'] : true;

        $options = $row;

        if (!empty($row['rtc_set_time'])) {
          $conn->query("UPDATE options SET rtc_set_time = '' WHERE id = " . (int)$row['id']);
        }
      } else {
        $options = [
          "water_watering_percent"  => 50,
          "tank_low_percent"        => 20,
          "tank_high_percent"       => 80,
          "water_full_cm"           => 10,
          "water_empty_cm"          => 25,
          "fert_full_cm"            => 10,
          "fert_empty_cm"           => 25,
          "fertigation_enabled"     => true,
          "fert_interval_minutes"   => 1,
          "fert_duration_ms"        => 5000,
          "buzzer_enabled"          => true,
          "buzzer_alert_interval_ms"=> 60000,
          "lcd_enabled"             => true,
          "backlight_enabled"       => true,
          "rtc_set_time"            => ""
        ];
      }


      $irrigation = 0;
      $fertigation = 0;
      $command_id = 0;
      $source = "none";

      $res_manual = $conn->query("
        SELECT id, irrigation, fertigation FROM commands
        WHERE  status = 'pending' AND (irrigation = 1 OR fertigation = 1)
        ORDER  BY created_at ASC LIMIT 1
      ");

      if ($res_manual && $row = $res_manual->fetch_assoc()) {
        $irrigation  = (int)$row['irrigation'];
        $fertigation = (int)$row['fertigation'];
        $command_id  = (int)$row['id'];
        $source      = "manual";
      } else {
        $today       = date('Y-m-d');
        $currentTime = date('H:i');

        $stmt_sched = $conn->prepare("
          SELECT id, task_type FROM farm_schedules
          WHERE  schedule_date = ?
            AND  TIME_FORMAT(task_time, '%H:%i') = ?
            AND  (task_type = 'fertigation' OR task_type = 'irrigation')
            AND  status = 'scheduled'
          LIMIT 1
        ");
        $stmt_sched->bind_param("ss", $today, $currentTime);
        $stmt_sched->execute();
        $res_sched = $stmt_sched->get_result();

        if ($res_sched && $row = $res_sched->fetch_assoc()) {
          $command_id  = (int)$row['id'];
          $source      = "scheduled";
          $fertigation = $row['task_type'] === 'fertigation' ? 1 : 0;
          $irrigation  = $row['task_type'] === 'irrigation'  ? 1 : 0;

          $update_stmt = $conn->prepare("UPDATE farm_schedules SET status = 'in_progress' WHERE id = ?");
          $update_stmt->bind_param("i", $command_id);
          $update_stmt->execute();
        }
      }

      $command = ($irrigation || $fertigation)
        ? ["command_id" => $command_id, "irrigation" => $irrigation, "fertigation" => $fertigation, "source" => $source]
        : ["command_id" => 0,           "irrigation" => 0,           "fertigation" => 0,            "source" => "none"];


      echo json_encode([
        "status"  => "success",
        "options" => $options,
        "command" => $command
      ]);

  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
  }

  mysqli_close($conn);