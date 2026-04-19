<?php
  header("Content-Type: application/json");
  header("Access-Control-Allow-Origin: *");
  require_once "../config/db.php";

  try {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    $id      = isset($input['id']) ? intval($input['id']) : 0;
    $msg     = isset($input['message']) ? $input['message'] : '';
    $to      = isset($input['recipients']) ? $input['recipients'] : '';
    $outcome = isset($input['status']) ? $input['status'] : 'Sent'; 
    
    if (is_array($to)) {
      $to = implode(',', $to);
    }

    if ($id > 0) {
      $stmt = $conn->prepare("UPDATE gsm_messages SET status = ? WHERE id = ?");
      $stmt->bind_param("si", $outcome, $id);
      
      if ($stmt->execute()) {
        echo json_encode(["status" => "success", "action" => "updated", "id" => $id]);
      } else {
        throw new Exception($conn->error);
      }
    } else if (!empty($msg) && !empty($to)) {
      $stmt = $conn->prepare("INSERT INTO gsm_messages (message, recipients, status, user_id, sent_at) VALUES (?, ?, ?, 0, NOW())");
      $stmt->bind_param("sss", $msg, $to, $outcome);
      
      if ($stmt->execute()) {
        echo json_encode(["status" => "success", "action" => "logged", "id" => $conn->insert_id]);
      } else {
        throw new Exception($conn->error);
      }
    } else {
      http_response_code(400);
      echo json_encode(["status" => "error", "message" => "Missing required parameters (id OR message+recipients)"]);
    }

  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
  }
