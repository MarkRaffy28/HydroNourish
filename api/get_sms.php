<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
require_once "../config/db.php";

/**
 * ESP32 API - Fetch Pending SMS
 * Oldest pending message is fetched so SIM800L can send it.
 */

try {
    $result = $conn->query("SELECT id, message, recipients FROM gsm_messages WHERE status = 'pending' ORDER BY id ASC LIMIT 1");
    if ($result && $row = $result->fetch_assoc()) {
        $recipientObjects = [];

        // Fetch contacts map from options
        $res_opt = $conn->query("SELECT gsm_recipients FROM options LIMIT 1");
        $saved = [];
        if ($res_opt && $opt_row = $res_opt->fetch_assoc()) {
            $saved = json_decode($opt_row['gsm_recipients'], true) ?: [];
        }

        if (trim($row['recipients']) === 'ALL_STAFF') {
            foreach ($saved as $contact) {
                if (is_array($contact) && isset($contact['number'])) {
                    $recipientObjects[] = ["number" => $contact['number']];
                }
            }
        } else {
            $numbers = explode(',', $row['recipients']);
            foreach ($numbers as $num) {
                $num = trim($num);
                if ($num) {
                    $recipientObjects[] = ["number" => $num];
                }
            }
        }

        // Enforce MAX_RECIPIENTS 10 constraint
        if (count($recipientObjects) > 10) {
            $recipientObjects = array_slice($recipientObjects, 0, 10);
        }

        echo json_encode([
            "status" => "pending",
            "id" => (int)$row['id'],
            "message" => $row['message'],
            "recipients" => $recipientObjects
        ]);
    } else {
        echo json_encode(["status" => "none", "message" => "No pending SMS"]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>
