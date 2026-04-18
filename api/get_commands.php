<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once "../config/db.php";

/**
 * ESP32 API - Fetch Scheduled or Manual Fertigation
 * The ESP32 should call this periodically (e.g., every minute).
 */

try {
    $irrigation = 0;
    $fertigation = 0;
    $command_id = 0;
    $source = "none";

    // 1. Check for manual pending commands (Check both irrigation and fertigation)
    $query_manual = "SELECT id, irrigation, fertigation FROM commands WHERE status = 'pending' AND (irrigation = 1 OR fertigation = 1) ORDER BY created_at ASC LIMIT 1";
    $res_manual = $conn->query($query_manual);

    if ($res_manual && $row = $res_manual->fetch_assoc()) {
        $irrigation = (int)$row['irrigation'];
        $fertigation = (int)$row['fertigation'];
        $command_id = (int)$row['id'];
        $source = "manual";
    } else {
        // 2. Check for scheduled tasks (irrigation or fertigation) for the current time
        $today = date('Y-m-d');
        $currentTime = date('H:i'); 

        $stmt_sched = $conn->prepare("
            SELECT id, task_type 
            FROM   farm_schedules 
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
            $command_id = (int)$row['id'];
            $source = "scheduled";

            if ($row['task_type'] === 'fertigation') {
                $fertigation = 1;
            } else {
                $irrigation = 1;
            }
            
            // Auto-mark scheduled task as 'in_progress'
            $update_stmt = $conn->prepare("UPDATE farm_schedules SET status = 'in_progress' WHERE id = ?");
            $update_stmt->bind_param("i", $command_id);
            $update_stmt->execute();
        }
    }

    if ($irrigation || $fertigation) {
        echo json_encode([
            "status" => "success",
            "command_id" => $command_id,
            "irrigation" => $irrigation,
            "fertigation" => $fertigation,
            "source" => $source
        ]);
    } else {
        echo json_encode([
            "status" => "no_commands",
            "irrigation" => 0,
            "fertigation" => 0
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
