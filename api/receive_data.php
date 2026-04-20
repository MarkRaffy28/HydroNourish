<?php
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");

    require_once "../config/db.php";

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
      echo json_encode(["error" => "Method not allowed"]);
      exit;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
      http_response_code(400);
      echo json_encode(["error" => "Invalid or empty JSON"]);
      exit;
    }

    $f = fn($key, $default = 0) => $data[$key] ?? $default;

    $soil_percent   = $f('soil_percent');
    $water_level    = $f('water_level');
    $fert_level     = $f('fert_level');
    $temperature    = $f('temperature');
    $humidity       = $f('humidity');
    $pressure       = $f('pressure');
    $water_distance = isset($data['water_distance']) ? (float)$data['water_distance'] : null;
    $fert_distance  = isset($data['fert_distance'])  ? (float)$data['fert_distance'] : null;
    $watering       = $f('watering');
    $fertigating    = $f('fertigating');
    $water_low      = $f('water_low');
    $fert_low       = $f('fert_low');
    $rtc_time       = isset($data['rtc_time']) ? (string)$data['rtc_time'] : null;
    $soil_error     = $f('soil_error');
    $water_error    = $f('water_error');
    $fert_error     = $f('fert_error');
    $hum_error      = $f('hum_error');
    $temp_error     = $f('temp_error');
    $pres_error     = $f('pres_error');
    $rtc_error      = $f('rtc_error');

    $stmt = $conn->prepare("INSERT INTO sensor_logs 
      (soil_percent, water_level, fert_level,
      temperature, humidity, pressure, water_distance, fert_distance,
      watering, fertigating, water_low, fert_low, rtc_time,
      soil_error, water_error, fert_error, hum_error, temp_error, pres_error, rtc_error)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

    $stmt->bind_param("iiidddddiiiisiiiiiii",
      $soil_percent,  $water_level,  $fert_level,
      $temperature,   $humidity,     $pressure,    $water_distance, $fert_distance,
      $watering,      $fertigating,  $water_low,   $fert_low,       $rtc_time,
      $soil_error,    $water_error,  $fert_error,  $hum_error,      $temp_error,
      $pres_error,    $rtc_error
    );

    if (!$stmt->execute()) {
      http_response_code(500);
      echo json_encode(["error" => "Sensor log failed: " . $stmt->error]);
      $stmt->close();
      mysqli_close($conn);
      exit;
    }

    $log_id = $conn->insert_id;
    $stmt->close();


    $command_result = null;

    $command_id = isset($data['command_id']) ? intval($data['command_id']) : 0;
    $command_status = $data['command_status'] ??= 'done';

    if ($command_id > 0) {
      $stmt = $conn->prepare("UPDATE commands SET status = ?, executed_at = NOW() WHERE id = ?");
      $stmt->bind_param("si", $command_status, $command_id);

      $command_result = $stmt->execute()
        ? ["updated" => true,  "id" => $command_id, "status" => $command_status]
        : ["updated" => false, "error" => $stmt->error];

      $stmt->close();
    }


    $response = ["status" => "ok", "log_id" => $log_id];

    if ($command_result !== null) {
      $response["command"] = $command_result;
    }

    echo json_encode($response);

    mysqli_close($conn);