<?php

header("Content-Type: application/json");
require_once "../config/db.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Method not allowed"]);
    exit;
}

$raw  = file_get_contents("php://input");
$data = json_decode($raw, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON"]);
    exit;
}

function validate($data, $key, $default = 0) {
    return isset($data[$key]) ? $data[$key] : $default;
}

$stmt = $conn->prepare("INSERT INTO sensor_logs 
    (soil_percent, water_level, fert_level, 
     temperature, humidity, pressure, water_distance, fert_distance,
     watering, fertigating, gsm_signal, gsm_carrier, water_low, fert_low, rtc_time,
     soil_error, water_error, fert_error, hum_error, temp_error, pres_error, gsm_error, rtc_error) 
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

$soil_percent    = validate($data, 'soil_percent');
$water_level     = validate($data, 'water_level');
$fert_level      = validate($data, 'fert_level');
$temperature     = validate($data, 'temperature');
$humidity        = validate($data, 'humidity');
$pressure        = validate($data, 'pressure');
$water_distance  = validate($data, 'water_distance', null);
$fert_distance   = validate($data, 'fert_distance', null);
$watering        = validate($data, 'watering');
$fertigating     = validate($data, 'fertigating');
$gsm_signal      = validate($data, 'gsm_signal');
$gsm_carrier     = validate($data, 'gsm_carrier');
$water_low       = validate($data, 'water_low', 0);
$fert_low        = validate($data, 'fert_low', 0);
$rtc_time        = validate($data, 'rtc_time', null);
$soil_error      = validate($data, 'soil_error');
$water_error     = validate($data, 'water_error');
$fert_error      = validate($data, 'fert_error');
$hum_error       = validate($data, 'hum_error');
$temp_error      = validate($data, 'temp_error');
$pres_error      = validate($data, 'pres_error');
$gsm_error       = validate($data, 'gsm_error');
$rtc_error       = validate($data, 'rtc_error');

$stmt->bind_param("iiidddddiiisiisiiiiiiii", 
    $soil_percent,   $water_level,    $fert_level, 
    $temperature,    $humidity,       $pressure,    $water_distance, $fert_distance,
    $watering,       $fertigating,    $gsm_signal,  $gsm_carrier,    $water_low,     $fert_low, $rtc_time,
    $soil_error,     $water_error,    $fert_error,  $hum_error,      $temp_error,    $pres_error, $gsm_error, $rtc_error
);

if ($stmt->execute()) {
    echo json_encode(["status" => "ok", "id" => $conn->insert_id]);
} else {
    http_response_code(500);
    echo json_encode(["error" => $stmt->error]);
}

$stmt->close();
$conn->close();