<?php
  header("Content-Type: application/json");
  header("Access-Control-Allow-Origin: *");

  require_once "../config/db.php";

  $content = file_get_contents('php://input');
  $input = json_decode($content, true);

  if (empty($input)) {
      $input = $_POST;
  }

  $command_id = isset($input['id']) ? intval($input['id']) : 0;
  $status = isset($input['status']) ? $input['status'] : 'done';

  if ($command_id > 0) {
    $stmt = $conn->prepare("UPDATE commands SET status = ?, executed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $status, $command_id);
    
    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Command updated to $status"]);
    } else {
      echo json_encode(["status" => "error", "message" => "Database update failed"]);
    }
  } else {
    echo json_encode(["status" => "error", "message" => "Invalid Command ID"]);
  }
