<?php
  header("Content-Type: application/json");
  header("Access-Control-Allow-Origin: *");
  require_once "../config/db.php";

  try {
    $result = $conn->query("SELECT * FROM options ORDER BY id DESC LIMIT 1");

    if ($result && $row = $result->fetch_assoc()) {
      if (isset($row['gsm_recipients'])) {
        $row['gsm_recipients'] = json_decode($row['gsm_recipients'], true) ?: [];
      }

      $numeric_fields = [
        'water_watering_percent', 'tank_low_percent', 'tank_high_percent',
        'water_full_cm', 'water_empty_cm', 'fert_full_cm', 'fert_empty_cm',
        'fert_interval_minutes', 'fert_duration_ms', 'buzzer_alert_interval_ms'
      ];
      foreach ($numeric_fields as $field) {
        if (isset($row[$field])) {
          $row[$field] = (int)$row[$field];
        }
      }
        
      $row['buzzer_enabled'] = isset($row['buzzer_enabled']) ? (bool)$row['buzzer_enabled'] : true;
      $row['gsm_texting_enabled'] = isset($row['gsm_texting_enabled']) ? (bool)$row['gsm_texting_enabled'] : true;
      $row['lcd_enabled'] = isset($row['lcd_enabled']) ? (bool)$row['lcd_enabled'] : true;
      $row['backlight_enabled'] = isset($row['backlight_enabled']) ? (bool)$row['backlight_enabled'] : true;
      $row['fertigation_enabled'] = isset($row['fertigation_enabled']) ? (bool)$row['fertigation_enabled'] : true;
      
      echo json_encode([
        "status" => "success",
        "options" => $row
      ]);
        
      if (!empty($row['rtc_set_time'])) {
        $conn->query("UPDATE options SET rtc_set_time = '' WHERE id = " . (int)$row['id']);
      }
    } else {
      echo json_encode([
        "status" => "success",
        "options" => [
          "gsm_recipients" => [],
          "water_watering_percent" => 50,
          "tank_low_percent" => 20,
          "tank_high_percent" => 80,
          "water_full_cm" => 10,
          "water_empty_cm" => 25,
          "fert_full_cm" => 10,
          "fert_empty_cm" => 25,
          "fertigation_enabled" => true,
          "fert_interval_minutes" => 1,
          "fert_duration_ms" => 5000,
          "buzzer_enabled" => true,
          "gsm_texting_enabled" => true,
          "buzzer_alert_interval_ms" => 60000,
          "lcd_enabled" => true,
          "backlight_enabled" => true,
          "rtc_set_time" => ""
        ]
      ]);
    }

  } catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
  }

