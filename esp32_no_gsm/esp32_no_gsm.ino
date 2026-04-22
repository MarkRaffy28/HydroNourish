/*
* ===============================================
* HYDRONOURISH - Smart Irrigation & Fertigation System
* ===============================================
* 
* Features:
* - Automatic watering based on soil moisture
* - Scheduled fertigation (fertilizer + irrigation) using RTC
* - Real-time monitoring of temperature, humidity, pressure
* - Tank level monitoring with low-level alerts
* - RGB LED status indicators
* - LCD display with rotating information screens
* - Buzzer alerts for system events
* - Comprehensive sensor error detection and fail-safe mechanisms
* - Serial debug interface
* 
* Author: Mark Raffy Romero
* Version: 3.1 (Removed GSM and Fixed Connection Refused)
* Date: April 20, 2026
* 
* ===============================================
*/

#include <Wire.h>
#include <LiquidCrystal_I2C.h>
#include <Adafruit_BMP280.h>
#include <Adafruit_AHTX0.h>
#include <RTClib.h>
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>

/* ===============================================
* PIN DEFINITIONS
* =============================================== */
// Actuators
#define BUZZER_PIN      13
#define WATER_RELAY     27
#define FERT_RELAY      26

// Analog Sensors
#define SOIL_PIN        34

// Ultrasonic Sensors
#define WATER_TRIG      18
#define WATER_ECHO      19
#define FERT_TRIG       4  
#define FERT_ECHO       5 

// RGB LED
#define RGB_R_PIN       14
#define RGB_G_PIN       12
#define RGB_B_PIN       15

/* ===============================================
* CALIBRATION CONSTANTS & THRESHOLDS
* =============================================== */

// Soil Moisture Sensor Calibration
#define SOIL_DRY                 2700  // ADC value when soil is completely dry
#define SOIL_WET                 1000  // ADC value when soil is fully saturated

// Sensor Staleness Detection
#define SENSOR_TIMEOUT_MS        2000  // Sensor data older than 2 seconds = error
#define ENV_UNCHANGED_THRESHOLD  15    // 15 identical readings = error
#define SOIL_AVG_SAMPLES         5     // Adjust if needed

int WATER_WATERING_PERCENT              = 50;
int TANK_LOW_PERCENT                    = 20;
int TANK_HIGH_PERCENT                   = 80;
int WATER_FULL_CM                       = 10;
int WATER_EMPTY_CM                      = 25;
int FERT_FULL_CM                        = 10;
int FERT_EMPTY_CM                       = 25;
int FERT_INTERVAL_MINUTES               = 1;
unsigned long FERT_DURATION_MS          = 5000;
bool BUZZER_ENABLED                     = true;
bool LCD_ENABLED                        = true;
bool BACKLIGHT_ENABLED                  = true;
bool FERTIGATION_ENABLED                = true;
unsigned long BUZZER_ALERT_INTERVAL_MS = 60000;

/* ===============================================
* HARDWARE OBJECT INSTANTIATION
* =============================================== */
LiquidCrystal_I2C lcd(0x27, 16, 2);    // LCD with I2C address 0x27
Adafruit_BMP280 bmp;                   // BMP280 pressure sensor
Adafruit_AHTX0 aht;                    // AHT20 temperature & humidity sensor
RTC_DS3231 rtc;                        // DS3231 Real-Time Clock

/* ===============================================
 * FORWARD DECLARATIONS
 * =============================================== */
void fetchDataTaskFn(void* param);
bool httpBegin(WiFiClientSecure &client, HTTPClient &http, const char* path);

/* ===============================================
* DATA STRUCTURES
* =============================================== */

// Ultrasonic Sensor Structure
struct Ultrasonic {
  int trig;                           // Trigger pin
  int echo;                           // Echo pin
  int relay;                          // Associated relay control pin (reserved, unused in current logic)
  volatile unsigned long startMicros; // Echo pulse start time
  volatile unsigned long endMicros;   // Echo pulse end time
  volatile bool done;                 // Flag: measurement complete
  float distance;                     // Calculated distance in cm
};

// RGB LED State Structure
struct RGBState {
  bool r, g, b;                      // Individual channel states
  unsigned long blinkMs;             // Blink interval (0 = solid)
  unsigned long lastToggle;          // Last blink toggle timestamp
  bool ledOn;                        // Current on/off state for blinking
};

// RGB Priority Levels (Higher priority overrides lower)
enum RGBPriority {
  RGB_NONE = 0,
  RGB_IDLE,          // Normal operation - color cycling
  RGB_WATERING,      // Watering in progress
  RGB_FERTIGATING,   // Fertigation in progress
  RGB_LOW_TANK,      // Low tank level warning
  RGB_ERROR          // Sensor error - highest priority
};

// Debug Mode Selection
enum DebugMode { 
  NONE,     // No debug output
  WATER,    // Water sensor debug
  SOIL,     // Soil sensor debug
  FERT,     // Fertilizer sensor debug
  TEMP,     // Temperature sensor debug
  HUM,      // Humidity sensor debug
  PRES,     // Pressure sensor debug
  TIME,     // RTC time debug
};

/* ===============================================
* GLOBAL VARIABLES - Sensor Data
* =============================================== */
int soilPercent = 0;              // Soil moisture percentage (0-100%)
int waterLevel = -1;              // Water tank level percentage (-1 = error)
int fertLevel = -1;               // Fertilizer tank level percentage (-1 = error)
float waterDistance = -1;         // Water tank ultrasonic distance (cm)
float fertDistance = -1;          // Fertilizer tank ultrasonic distance (cm)
float temperature = 0;            // Temperature in Celsius
float humidity = 0;               // Relative humidity percentage
float pressure = 0;               // Atmospheric pressure in hPa

/* ===============================================
* GLOBAL VARIABLES - System State
* =============================================== */
bool watering = false;               // Water pump active flag
bool fertigating = false;            // Fertigation pump active flag
unsigned long fertigateStart = 0;    // Fertigation start timestamp
unsigned long lastFertigateTime = 0; // Last fertigation time (minutes since epoch)
unsigned long lastWiFiCheck = 0;     // Last WiFI checked time
 
/* ===============================================
* GLOBAL VARIABLES - Sensor Error Tracking
* =============================================== */
bool soilError = false;           // Soil sensor error flag
bool waterSensorError = false;    // Water ultrasonic sensor error flag
bool fertSensorError = false;     // Fertilizer ultrasonic sensor error flag
bool tempError = false;           // Temperature sensor error flag
bool humError = false;            // Humidity sensor error flag
bool presError = false;           // Pressure sensor error flag
bool rtcError = false;            // RTC error flag

// Sensor Staleness Tracking (for ultrasonic sensors)
unsigned long lastWaterUpdate = 0; // Last water sensor update timestamp
unsigned long lastFertUpdate = 0;  // Last fertilizer sensor update timestamp

// Sensor Staleness Tracking (for environment readings)
float lastTemperature = -999;      // Previous reading
int tempUnchangedCount = 0;        // Consecutive identical count

float lastHumidity = -999;
int humUnchangedCount = 0;

float lastPressure = -999;
int pressureUnchangedCount = 0;

// Sensor Staleness Tracking (for soil moisture)
int soilReadings[SOIL_AVG_SAMPLES];  // Circular buffer for soil readings
int soilReadIndex = 0;               // Current position in circular buffer
long soilTotal = 0;                  // Running sum of soil readings
bool soilBufferFilled = false;       // Flag: buffer has 10 valid samples

/* ===============================================
* GLOBAL VARIABLES - Alert Management
* =============================================== */
bool waterLowAlerted = false;     // Water tank low alert sent flag
bool fertLowAlerted = false;      // Fertilizer tank low alert sent flag
unsigned long lastWaterBuzzer = 0;
unsigned long lastFertBuzzer  = 0;

/* ===============================================
* GLOBAL VARIABLES - Buzzer Control
* =============================================== */
bool buzzerActive = false;        // Buzzer sequence active flag
unsigned long buzzerStart = 0;    // Buzzer sequence start time
int buzzerTimes = 0;              // Number of beeps in sequence
int buzzerOnTime = 100;           // Duration of each beep (ms)
int buzzerOffTime = 100;          // Duration between beeps (ms)
bool buzzerState = false;         // Current buzzer state (on/off)

/* ===============================================
* GLOBAL VARIABLES - LCD Display
* =============================================== */
bool activeDisplay = false;       // Active message currently shown
unsigned long activeUntil = 0;    // Timestamp when active message expires
unsigned long lastIdle = 0;       // Last idle screen rotation timestamp
int idleIndex = 0;                // Current idle screen index (0-7)

/* ===============================================
* GLOBAL VARIABLES - RGB LED
* =============================================== */
RGBState rgb = {false, false, false, 0, 0, true}; // RGB LED state
RGBPriority rgbPriority = RGB_NONE;                // Current RGB priority level

/* ===============================================
* GLOBAL VARIABLES - Debug Mode
* =============================================== */
DebugMode debugMode = NONE;       // Current debug mode

/* ===============================================
* GLOBAL VARIABLES - Ultrasonic Sensors
* =============================================== */
Ultrasonic waterUS = {WATER_TRIG, WATER_ECHO, WATER_RELAY, 0, 0, false, -1};
Ultrasonic fertUS  = {FERT_TRIG,  FERT_ECHO,  FERT_RELAY,  0, 0, false, -1};

const char* ssid = "TECNO SPARK 30C";
const char* password = "spaghetti";

// ── Server config ─────────────────────────────────────────────
const char* BASE_DOMAIN  = "https://hydronourish.markraffyromero28.workers.dev/";  // Base URL of the HydroNourish API server
const char* PATH_RECEIVE = "receive_data";                         // Endpoint: POST sensor data
const char* PATH_OPTIONS = "get_data";                          // Endpoint: GET configurable system options

static unsigned long lastSend = 0;                   // Timestamp of last server data send

static volatile bool sendPending          = false;   // HTTP data send in flight
static volatile bool optionsFetchPending  = false;   // Options fetch task in flight
volatile bool latchIrrigation  = false;
volatile bool latchFertigation = false;
static String pendingPayload              = "";      // Payload string staged for HTTP send task
static SemaphoreHandle_t payloadMutex     = NULL;    // Mutex protecting pendingPayload across tasks

bool overrideIrrigation = false;      // Server-commanded irrigation override flag
bool overrideFertigation = false;     // Server-commanded fertigation override flag
int  pendingCommandId   = -1;         // ID of the active server command awaiting acknowledgement
unsigned long lastOptionsCheck = 0;   // Last options fetch timestamp
volatile int httpFailStreak = 0;               // Consecutive HTTP failure count across all endpoints
const int HTTP_BACKOFF_THRESHOLD = 3; // Failures before switching to backoff intervals

/* ===============================================
* INTERRUPT SERVICE ROUTINES
* =============================================== */

// ISR for water tank ultrasonic sensor echo pin
void IRAM_ATTR echoISRWater() {
  if (digitalRead(WATER_ECHO)) {
    waterUS.startMicros = micros();  // Rising edge - start of pulse
  } else {
    waterUS.endMicros = micros();    // Falling edge - end of pulse
    waterUS.done = true;             // Mark measurement as complete
  }
}

// ISR for fertilizer tank ultrasonic sensor echo pin
void IRAM_ATTR echoISRFert() {
  if (digitalRead(FERT_ECHO)) {
    fertUS.startMicros = micros();   // Rising edge - start of pulse
  } else {
    fertUS.endMicros = micros();     // Falling edge - end of pulse
    fertUS.done = true;              // Mark measurement as complete
  }
}

/* ===============================================
* ULTRASONIC SENSOR FUNCTIONS
* =============================================== */

/**
* Trigger an ultrasonic sensor measurement
* Sends a 10us pulse to the trigger pin
*/
void triggerUltrasonic(Ultrasonic &us) {
  digitalWrite(us.trig, LOW);
  delayMicroseconds(2);
  digitalWrite(us.trig, HIGH);
  delayMicroseconds(10);
  digitalWrite(us.trig, LOW);
}

/**
* Update ultrasonic sensor distance reading
* Calculates distance from echo pulse duration
* Updates the lastUpdate timestamp for staleness detection
*/
void updateUltrasonic(Ultrasonic &us, unsigned long &lastUpdate) {
  if (us.done) {
    // Calculate distance: (time * speed_of_sound) / 2
    // Speed of sound = 343 m/s = 0.0343 cm/µs = 0.034 cm/µs (approx)
    us.distance = (us.endMicros - us.startMicros) * 0.034 / 2;
    us.done = false;
    lastUpdate = millis();  // Record successful update time
  }
}

/**
* Convert ultrasonic distance to percentage level
* Maps distance range (full to empty) to 0-100%
* Returns -1 if distance is invalid
*/
int levelFromCM(float cm, int full, int empty) {
  if (cm < 0) return -1;
  return constrain(map(cm, full, empty, 100, 0), 0, 100);
}

/**
* Read soil moisture sensor with rolling average
* Uses circular buffer to maintain average of last N readings
* Returns averaged raw ADC value
*/
int readSoilAverage() {
  // Read current raw value
  int currentReading = analogRead(SOIL_PIN);
  
  // Subtract the oldest reading from the total
  soilTotal = soilTotal - soilReadings[soilReadIndex];
  
  // Store the new reading
  soilReadings[soilReadIndex] = currentReading;
  
  // Add the new reading to the total
  soilTotal = soilTotal + currentReading;
  
  // Advance to the next position in the circular buffer
  soilReadIndex = (soilReadIndex + 1) % SOIL_AVG_SAMPLES;
  
  // Mark buffer as filled after first complete cycle
  if (soilReadIndex == 0) {
    soilBufferFilled = true;
  }
  
  // Calculate and return average
  return soilTotal / SOIL_AVG_SAMPLES;
}

/* ===============================================
* SENSOR ERROR DETECTION FUNCTIONS
* =============================================== */

/**
* Check soil moisture sensor for errors
* Valid ADC range: 300-4095
*/
bool checkSoilError(int raw) {
  return (raw < 300 || raw > 4095);
}

/**
* Check ultrasonic sensor for errors
* Detects: stale data, invalid distance, invalid level
*/
bool checkUltrasonicError(float distance, int level, unsigned long lastUpdate) {
  // Check if data is stale (no update within timeout period)
  if (millis() - lastUpdate > SENSOR_TIMEOUT_MS) return true;
  
  // Check for invalid distance readings
  if (distance <= 0) return true;
  if (distance > 400) return true;  // Max reliable range for HC-SR04
  
  // Check for invalid level calculation
  if (level < 0) return true;
  
  return false;
}

/**
* Check temperature sensor for errors
* Valid range: -40°C to 85°C (typical sensor range)
*/
bool checkTempError(float t) {
  if (t < -40 || t > 85) return true;
  
  // Stuck value detection
  if (abs(t - lastTemperature) < 0.01) {
    tempUnchangedCount++;
    if (tempUnchangedCount >= ENV_UNCHANGED_THRESHOLD) return true;
  } else {
    tempUnchangedCount = 0;
  }
  
  lastTemperature = t;
  return false;
}

/**
* Check humidity sensor for errors
* Valid range: 0-100%
*/
bool checkHumError(float h) {
  // Range check: 0-100% (physical limits)
  if (h < 0 || h > 100) return true;
  
  // Stuck value detection
  if (abs(h - lastHumidity) < 0.01) {
    humUnchangedCount++;
    if (humUnchangedCount >= ENV_UNCHANGED_THRESHOLD) return true;
  } else {
    humUnchangedCount = 0;
  }
  
  lastHumidity = h;
  return false;
}

/**
* Check pressure sensor for errors
* Valid range: 870-1085 hPa (typical atmospheric pressure)
*/
bool checkpresError(float p) {
  if (p < 870 || p > 1085) return true;
  
  // Stuck value detection
  if (abs(p - lastPressure) < 0.01) {
    pressureUnchangedCount++;
    if (pressureUnchangedCount >= ENV_UNCHANGED_THRESHOLD) return true;
  } else {
    pressureUnchangedCount = 0;
  }
  
  lastPressure = p;
  return false;
}

/**
* Check RTC for errors
* Invalid if year is before 2023 (system design date)
*/
bool checkRTCError(DateTime now) {
  // Check year range (system design date to reasonable future)
  if (now.year() < 2023 || now.year() > 2100) return true;
  
  // Check month range (1-12)
  if (now.month() < 1 || now.month() > 12) return true;
  
  // Check day range (1-31, simplified check)
  if (now.day() < 1 || now.day() > 31) return true;
  
  // Check hour range (0-23)
  if (now.hour() > 23) return true;
  
  // Check minute range (0-59)
  if (now.minute() > 59) return true;
  
  // Check second range (0-59)
  if (now.second() > 59) return true;
  
  return false;  // All checks passed
}

/* ===============================================
* LCD DISPLAY FUNCTIONS
* =============================================== */

/**
* Show an active message on LCD for specified duration
* Interrupts idle screen rotation
*/
void showActive(const char* line1, const char* line2, unsigned long durationMs) {
  if (!LCD_ENABLED) return;
  activeDisplay = true;
  activeUntil = millis() + durationMs;
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print(line1);
  lcd.setCursor(0, 1);
  lcd.print(line2);
}

/**
* Display idle information screens (rotates every 2 seconds)
* Screens cycle through: soil, water, fertilizer, temp, humidity, pressure, time, WiFi
*/
void showIdle() {
  if (!LCD_ENABLED) return; 
  // Throttle screen updates to every 2 seconds
  if (millis() - lastIdle < 2000) return;
  lastIdle = millis();
  
  lcd.clear();
  DateTime now = rtc.now();

  switch (idleIndex) {
    case 0:  // Soil moisture status
      lcd.print("Soil: ");
      lcd.print(soilPercent);
      lcd.print("%");
      lcd.setCursor(0, 1);
      lcd.print(watering ? "Watering" : "Idle");
      break;

    case 1:  // Water tank level
      lcd.print("Water: ");
      lcd.print(waterLevel < 0 ? "Err" : String(waterLevel) + "%");
      lcd.setCursor(0, 1);
      lcd.print("Low:");
      lcd.print(TANK_LOW_PERCENT);
      lcd.print("% High:");
      lcd.print(TANK_HIGH_PERCENT);
      break;

    case 2:  // Fertilizer tank level
      lcd.print("Fert: ");
      lcd.print(fertLevel < 0 ? "Err" : String(fertLevel) + "%");
      lcd.setCursor(0, 1);
      lcd.print("Every ");
      lcd.print(FERT_INTERVAL_MINUTES);
      lcd.print(" min");
      break;

    case 3:  // Temperature
      lcd.print("Temperature: ");
      lcd.setCursor(0, 1);
      lcd.print(temperature);
      lcd.print(" ");
      lcd.print((char)223);  // Degree symbol
      lcd.print("C");
      break;

    case 4:  // Humidity
      lcd.print("Humidity: ");
      lcd.setCursor(0, 1);
      lcd.print(humidity);
      lcd.print("%");
      break;

    case 5:  // Atmospheric pressure
      lcd.print("Pressure: ");
      lcd.setCursor(0, 1);
      lcd.print(pressure);
      lcd.print(" hPa");
      break;

    case 6:  // Date and time (12-hour format with AM/PM)
      {
        if (rtcError) {
          lcd.print("RTC Error");
          lcd.setCursor(0, 1);
          lcd.print("Check Module");
          break;  // Don't try to display garbage data
        }

        int hour12 = now.hour();
        String ampm = "AM";
        
        // Convert 24-hour to 12-hour format
        if (hour12 == 0) {
          hour12 = 12;  // Midnight
        } else if (hour12 == 12) {
          ampm = "PM";  // Noon
        } else if (hour12 > 12) {
          hour12 -= 12; // Afternoon
          ampm = "PM";
        }

        // Display date
        const char* months[] = { "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec" };
        lcd.print(months[now.month() - 1]);
        lcd.print(". ");
        lcd.print(now.day(), DEC);
        lcd.print(", ");
        lcd.print(now.year(), DEC);
        
        // Display time
        lcd.setCursor(0, 1);
        lcd.print(hour12);
        lcd.print(":");
        if (now.minute() < 10) lcd.print('0');
        lcd.print(now.minute());
        lcd.print(":");
        if (now.second() < 10) lcd.print('0');
        lcd.print(now.second());
        lcd.print(" ");
        lcd.print(ampm);
      }
      break;

    case 7:
      if (WiFi.status() == WL_CONNECTED) {
        lcd.print("WiFi: OK");
        lcd.setCursor(0, 1);
        lcd.print(WiFi.localIP().toString());
      } else {
        lcd.print("WiFi:");
        lcd.setCursor(0, 1);
        lcd.print("Disconnected");
      }
      break;
    
    default: break;
  }
  
  // Rotate to next screen
  idleIndex = (idleIndex + 1) % 8;
}

/* ===============================================
* BUZZER ALERT FUNCTIONS
* =============================================== */

/**
* Start a non-blocking buzzer alert sequence
*/
void startBuzzerAlert(int times = 5, int onTime = 100, int offTime = 100) {
  if (!BUZZER_ENABLED) return; 
  buzzerActive = true;
  buzzerStart = millis();
  buzzerTimes = times;
  buzzerOnTime = onTime;
  buzzerOffTime = offTime;
  buzzerState = false;
}

/**
* Update buzzer state (non-blocking)
* Must be called continuously in main loop
*/
void updateBuzzer() {
  if (!buzzerActive) return;
  
  unsigned long now = millis();
  static int count = 0;
  static unsigned long nextToggle = 0;

  if (now >= nextToggle) {
    buzzerState = !buzzerState;
    digitalWrite(BUZZER_PIN, buzzerState ? HIGH : LOW);
    
    if (buzzerState) {
      // Buzzer just turned ON
      nextToggle = now + buzzerOnTime;
    } else {
      // Buzzer just turned OFF
      nextToggle = now + buzzerOffTime;
      count++;
      
      // Check if sequence is complete
      if (count >= buzzerTimes) {
        buzzerActive = false;
        digitalWrite(BUZZER_PIN, LOW);
        count = 0;
      }
    }
  }
}

/* ===============================================
* RGB LED FUNCTIONS
* =============================================== */

/**
* Apply current RGB state to LED pins
*/
void rgbApply(bool on) {
  digitalWrite(RGB_R_PIN, (rgb.r && on) ? HIGH : LOW);
  digitalWrite(RGB_G_PIN, (rgb.g && on) ? HIGH : LOW);
  digitalWrite(RGB_B_PIN, (rgb.b && on) ? HIGH : LOW);
}

/**
* Set RGB LED color and blink mode
*/
void rgbSet(bool r, bool g, bool b, unsigned long blinkMs = 0) {
  rgb.r = r;
  rgb.g = g;
  rgb.b = b;
  rgb.blinkMs = blinkMs;
  rgb.lastToggle = millis();
  rgb.ledOn = true;
  rgbApply(true);
}

/**
* Update RGB blinking (non-blocking)
* Must be called continuously in main loop
*/
void rgbUpdate() {
  if (rgb.blinkMs == 0) return;

  unsigned long now = millis();
  if (now - rgb.lastToggle >= rgb.blinkMs) {
    rgb.lastToggle = now;
    rgb.ledOn = !rgb.ledOn;
    rgbApply(rgb.ledOn);
  }
}

/**
* Turn off RGB LED
*/
void rgbOff() {
  rgbSet(false, false, false, 0);
}

// Color Preset Functions
void rgbRed(unsigned long blinkMs = 0)    { rgbSet(true,  false, false, blinkMs); }
void rgbGreen(unsigned long blinkMs = 0)  { rgbSet(false, true,  false, blinkMs); }
void rgbBlue(unsigned long blinkMs = 0)   { rgbSet(false, false, true,  blinkMs); }
void rgbYellow(unsigned long blinkMs = 0) { rgbSet(true,  true,  false, blinkMs); }
void rgbCyan(unsigned long blinkMs = 0)   { rgbSet(false, true,  true,  blinkMs); }
void rgbPurple(unsigned long blinkMs = 0) { rgbSet(true,  false, true,  blinkMs); }
void rgbWhite(unsigned long blinkMs = 0)  { rgbSet(true,  true,  true,  blinkMs); }

/**
* RGB idle mode - cycles through colors slowly
* Called when system is in normal operation with no alerts
*/
void rgbIdleLoop() {
  static unsigned long lastChange = 0;
  static int index = 0;

  if (millis() - lastChange < 2000) return;  // Change every 2 seconds
  lastChange = millis();

  switch (index) {
    case 0: rgbRed();    break;
    case 1: rgbGreen();  break;
    case 2: rgbBlue();   break;
  }
  
  index = (index + 1) % 3;
}

/**
* Update RGB LED based on system state priority
* Priority order (high to low): Error > Low Tank > Fertigating > Watering > Idle
*/
void updateRGBPriority(
  bool anySensorError,
  bool waterLow,
  bool fertLow,
  bool watering,
  bool fertigating
) {
  // Determine current priority level
  if (anySensorError) {
    rgbPriority = RGB_ERROR;
  } else if (waterLow || fertLow) {
    rgbPriority = RGB_LOW_TANK;
  } else if (fertigating) {
    rgbPriority = RGB_FERTIGATING;
  } else if (watering) {
    rgbPriority = RGB_WATERING;
  } else {
    rgbPriority = RGB_IDLE;
  }

  // Only update LED if priority has changed
  static RGBPriority lastPriority = RGB_NONE;
  if (rgbPriority == lastPriority) return;
  lastPriority = rgbPriority;

  // Apply appropriate color/pattern based on priority
  switch (rgbPriority) {
    case RGB_ERROR:
      rgbYellow(500);   // Blinking yellow - sensor error
      break;

    case RGB_LOW_TANK:
      rgbRed(700);      // Slow red blink - low tank warning
      break;

    case RGB_FERTIGATING:
      rgbCyan(150);     // Fast cyan blink - fertigation active
      break;

    case RGB_WATERING:
      rgbBlue(300);     // Blue blink - watering active
      break;

    case RGB_IDLE:
      // Color cycling handled by rgbIdleLoop() in main loop
      break;

    default:
      rgbOff();
      break;
  }
}

/* ===============================================
* WIFI FUNCTIONS
* =============================================== */

/**
 * Check WiFi connection status and attempt reconnection if disconnected
 * Makes up to 3 reconnection attempts, each with a 5-second timeout
 * Blocking during reconnect attempts — avoid calling too frequently
 * Called every 30 seconds from the main loop
 */
void checkWiFiStatus() {
  if (WiFi.status() == WL_CONNECTED) return;

  const int MAX_ATTEMPTS = 3;
  Serial.println("[WiFi] Not connected, attempting reconnect...");

  WiFi.disconnect();
  WiFi.begin(ssid, password);

  for (int attempt = 0; attempt < MAX_ATTEMPTS; attempt++) {
    Serial.print("[WiFi] Attempt ");
    Serial.print(attempt + 1);
    Serial.print("/");
    Serial.print(MAX_ATTEMPTS);
    Serial.print(" ...");

    int waited = 0;
    while (WiFi.status() != WL_CONNECTED && waited < 10) {
      delay(500);
      Serial.print(".");
      waited++;
    }

    if (WiFi.status() == WL_CONNECTED) {
      Serial.println("\n[WiFi] Reconnected: " + WiFi.localIP().toString());
      return;
    }

    Serial.println("\n[WiFi] Attempt failed, retrying...");
    delay(500);
  }

  Serial.println("[WiFi] All attempts failed — will retry later");
}

/* ===============================================
* SERIAL DEBUG FUNCTIONS
* =============================================== */

/**
* Handle serial commands for debugging and configuration
* Available commands:
* - debug      : Show debug menu
* - water/soil/fert/temp/hum/pres/time : Enter specific debug mode
* - exit       : Exit debug mode
* - In TIME mode: YYYY-MM-DD HH:MM:SS to set RTC
*/
void handleSerial() {
  if (!Serial.available()) return;
  
  String cmd = Serial.readStringUntil('\n');
  cmd.trim();

  // Command processing
  if (cmd.equalsIgnoreCase("debug")) {
    debugMode = NONE;
    Serial.println("=== DEBUG MENU ===");
    Serial.println("water     - Water sensor debug");
    Serial.println("soil      - Soil sensor debug");
    Serial.println("fert      - Fertilizer sensor debug");
    Serial.println("temp      - Temperature sensor debug");
    Serial.println("hum       - Humidity sensor debug");
    Serial.println("pres      - Pressure sensor debug");
    Serial.println("time      - RTC time debug");
    Serial.println("exit      - Exit debug mode");
  }
  else if (cmd.equalsIgnoreCase("water")) {
    debugMode = WATER;
    Serial.println(">>> Water sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("soil")) {
    debugMode = SOIL;
    Serial.println(">>> Soil sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("fert")) {
    debugMode = FERT;
    Serial.println(">>> Fertilizer sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("temp")) {
    debugMode = TEMP;
    Serial.println(">>> Temperature sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("hum")) {
    debugMode = HUM;
    Serial.println(">>> Humidity sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("pres")) {
    debugMode = PRES;
    Serial.println(">>> Pressure sensor debug mode");
  }
  else if (cmd.equalsIgnoreCase("time")) {
    debugMode = TIME;
    Serial.println(">>> RTC time debug mode");
    Serial.println("Format: YYYY-MM-DD HH:MM:SS to set time");
  }
  else if (cmd.equalsIgnoreCase("exit")) {
    debugMode = NONE;
    Serial.println(">>> Exited debug mode");
  }
  else {
    // Mode-specific command handling
    if (debugMode == TIME) {
      // Parse and set RTC time: YYYY-MM-DD HH:MM:SS
      if (cmd.length() == 19) {
        int y  = cmd.substring(0,  4).toInt();
        int m  = cmd.substring(5,  7).toInt();
        int d  = cmd.substring(8,  10).toInt();
        int h  = cmd.substring(11, 13).toInt();
        int mi = cmd.substring(14, 16).toInt();
        int s  = cmd.substring(17, 19).toInt();

        // Validate values
        if (y >= 2000 && m >= 1 && m <= 12 && d >= 1 && d <= 31 &&
            h >= 0 && h <= 23 && mi >= 0 && mi <= 59 && s >= 0 && s <= 59) {
          rtc.adjust(DateTime(y, m, d, h, mi, s));
          Serial.println("✓ RTC updated successfully!");
        } else {
          Serial.println("✗ Invalid time values!");
        }
      } else {
        Serial.println("✗ Invalid format! Use: YYYY-MM-DD HH:MM:SS");
      }
    }
  }
}

/**
* Output debug information based on current debug mode
* Called continuously in main loop
*/
void printDebugInfo() {
  switch (debugMode) {
    case WATER:
      Serial.print("Water Tank: ");
      Serial.print(waterDistance);
      Serial.print(" cm, ");
      Serial.print(waterLevel);
      Serial.print("%, Age: ");
      Serial.print(millis() - lastWaterUpdate);
      Serial.print(" ms");
      if (waterSensorError) Serial.print(" [ERROR]");
      Serial.println();
      break;

    case SOIL: {
      int currentRaw  = analogRead(SOIL_PIN);           // Current reading
      int averagedRaw = soilTotal / SOIL_AVG_SAMPLES;   // Averaged reading
      Serial.print("Soil: Current=");
      Serial.print(currentRaw);
      Serial.print(", Avg=");
      Serial.print(averagedRaw);
      Serial.print(", ");
      Serial.print(soilPercent);
      Serial.print("%");
      if (!soilBufferFilled) Serial.print(" (filling)");
      if (soilError) Serial.print(" [ERROR]");
      Serial.println();
    }
    break;

    case FERT:
      Serial.print("Fertilizer Tank: ");
      Serial.print(fertDistance);
      Serial.print(" cm, ");
      Serial.print(fertLevel);
      Serial.print("%, Age: ");
      Serial.print(millis() - lastFertUpdate);
      Serial.print(" ms");
      if (fertSensorError) Serial.print(" [ERROR]");
      Serial.println();
      break;

    case TEMP:
      Serial.print("Temperature: ");
      Serial.print(temperature);
      Serial.print(" °C");
      if (tempError) Serial.print(" [ERROR]");
      Serial.println();
      break;

    case HUM:
      Serial.print("Humidity: ");
      Serial.print(humidity);
      Serial.print("%");
      if (humError) Serial.print(" [ERROR]");
      Serial.println();
      break;

    case PRES:
      Serial.print("Pressure: ");
      Serial.print(pressure);
      Serial.print(" hPa");
      if (presError) Serial.print(" [ERROR]");
      Serial.println();
      break;

    case TIME: {
        DateTime now = rtc.now();
        Serial.print("Date: ");
        Serial.print(now.year());
        Serial.print("-");
        if (now.month() < 10) Serial.print("0");
        Serial.print(now.month());
        Serial.print("-");
        if (now.day() < 10) Serial.print("0");
        Serial.print(now.day());
        Serial.print("  Time: ");
        if (now.hour() < 10) Serial.print("0");
        Serial.print(now.hour());
        Serial.print(":");
        if (now.minute() < 10) Serial.print("0");
        Serial.print(now.minute());
        Serial.print(":");
        if (now.second() < 10) Serial.print("0");
        Serial.print(now.second());
        if (rtcError) Serial.print(" [ERROR]");
        Serial.println();
      }
      break;

    default:
      break;
  }
}

/* ===============================================
* WIFI API FUNCTIONS
* =============================================== */

/**
 * Initialize an HTTP connection to the server
 * Builds the full URL from BASE_DOMAIN + path
 */
bool httpBegin(WiFiClientSecure &client, HTTPClient &http, const char* path) {
  return http.begin(client, String(BASE_DOMAIN) + path);
}

/**
 * FreeRTOS task: sends sensor data payload to the server via HTTP POST
 * Reads the pending payload from shared memory (mutex-protected)
 * Runs on Core 0 to avoid blocking the main loop on Core 1
 * Self-deletes after completion
 */
void sendTaskFn(void* param) {
  String payload;
  if (xSemaphoreTake(payloadMutex, pdMS_TO_TICKS(500)) == pdTRUE) {
    payload = pendingPayload;
    sendPending = false;
    xSemaphoreGive(payloadMutex);
  } else {
    vTaskDelete(NULL);
    return;
  }

  if (WiFi.status() == WL_CONNECTED) {
    WiFiClientSecure netClient;
    netClient.setInsecure();
    HTTPClient http;

    if (!httpBegin(netClient, http, PATH_RECEIVE)) {
      Serial.println("[HTTP] begin() failed");
      httpFailStreak++;
      vTaskDelete(NULL);
      return;
    }

    http.setTimeout(5000);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Connection", "close");
    
    int code = http.POST(payload);
    if (code > 0) {
      String responseBody = http.getString();
      Serial.printf("[HTTP] POST %d: %s\n", code, responseBody.c_str());
      httpFailStreak = 0;
      pendingCommandId = -1;
    } else {
      Serial.printf("[HTTP] POST failed: %s\n", http.errorToString(code).c_str());
      httpFailStreak++;
    }

    http.end();
    netClient.stop(); 
    vTaskDelay(pdMS_TO_TICKS(200));
  }

  vTaskDelete(NULL);  // Self-delete when done
}

/**
 * Builds a JSON payload from all current sensor readings and system state,
 * then dispatches a FreeRTOS task to POST it to the server
 * Called every 10 seconds from the main loop
 * Skips if a previous send is still in flight (sendPending == true)
 * Payload includes: soil, water, fert levels, temp, humidity, pressure, distances, pump states, error flags, RTC time
 */
void sendToServer() {
  if (sendPending) return;  // Previous send still in flight — skip

  DateTime nowSend = rtc.now();
  char timeBuf[20];
  sprintf(timeBuf, "%04d-%02d-%02d %02d:%02d:%02d",
    nowSend.year(), nowSend.month(), nowSend.day(),
    nowSend.hour(), nowSend.minute(), nowSend.second()
  );

  String payload = "{";
  payload += "\"soil_percent\":"   + String(soilPercent)              + ",";
  payload += "\"water_level\":"    + String(waterLevel)               + ",";
  payload += "\"fert_level\":"     + String(fertLevel)                + ",";
  payload += "\"temperature\":"    + String(temperature)              + ",";
  payload += "\"humidity\":"       + String(humidity)                 + ",";
  payload += "\"pressure\":"       + String(pressure)                 + ",";
  payload += "\"water_distance\":" + String(waterDistance)            + ",";
  payload += "\"fert_distance\":"  + String(fertDistance)             + ",";
  payload += "\"watering\":"       + String(watering     ? 1 : 0)     + ",";
  payload += "\"fertigating\":"    + String(fertigating  ? 1 : 0)     + ",";
  payload += "\"water_low\":"      + String((waterLevel <= TANK_LOW_PERCENT && !waterSensorError) ? 1 : 0) + ",";
  payload += "\"fert_low\":"       + String((fertLevel  <= TANK_LOW_PERCENT && !fertSensorError)  ? 1 : 0) + ",";
  payload += "\"rtc_time\":\""     + String(timeBuf)                  + "\",";
  payload += "\"soil_error\":"     + String(soilError    ? 1 : 0)     + ",";
  payload += "\"water_error\":"    + String(waterSensorError ? 1 : 0) + ",";
  payload += "\"fert_error\":"     + String(fertSensorError  ? 1 : 0) + ",";
  payload += "\"temp_error\":"     + String(tempError    ? 1 : 0)     + ",";
  payload += "\"hum_error\":"      + String(humError     ? 1 : 0)     + ",";
  payload += "\"pres_error\":"     + String(presError    ? 1 : 0)     + ",";
  payload += "\"rtc_error\":"      + String(rtcError     ? 1 : 0)     + ",";
  payload += "\"command_id\":"     + String(pendingCommandId >= 0 ? pendingCommandId : 0) + ",";
  payload += "\"command_status\":\"done\"";
  payload += "}";

  Serial.println("[HTTP] Payload: " + payload);

  if (xSemaphoreTake(payloadMutex, pdMS_TO_TICKS(100)) == pdTRUE) {
    pendingPayload = payload;
    sendPending = true;
    xSemaphoreGive(payloadMutex);
  }

  xTaskCreatePinnedToCore(sendTaskFn, "httpSend", 8192, NULL, 1, NULL, 0);  // Core 0
}

/**
 * FreeRTOS task: fetches system options AND pending commands from the server via HTTP GET
 * Updates all runtime-configurable globals:
 *   - Threshold percentages (watering, tank low/high)
 *   - Tank calibration values (full/empty distances in cm)
 *   - Fertigation interval and duration
 *   - Feature toggles (buzzer, LCD, backlight, fertigation)
 *   - RTC sync time (if provided by server)
 *   - Override flags (overrideIrrigation, overrideFertigation, pendingCommandId)
 * Also applies LCD backlight state immediately after loading
 * Called once on startup and every 30 seconds from the main loop
 * Self-deletes after completion
 */         
void fetchDataTaskFn(void* param) {
  WiFiClientSecure netClient;
  netClient.setInsecure();
  HTTPClient http;

  if (!httpBegin(netClient, http, PATH_OPTIONS)) {
    Serial.println("[DATA] begin() failed");
    httpFailStreak++;
    optionsFetchPending = false;
    vTaskDelete(NULL);
    return;
  }

  http.setTimeout(5000);         
  http.addHeader("Connection", "close");

  int code = http.GET();
  if (code > 0) {
    String body = http.getString();
    Serial.printf("[DATA] GET %d\n", code);

    if (body.indexOf("\"status\":\"success\"") >= 0) {

      // ── Parse options ──────────────────────────────────────
      auto extractInt = [&](const char* key) -> int {
        String search = String("\"") + key + "\":";
        int idx = body.indexOf(search);
        if (idx < 0) return -1;
        return body.substring(idx + search.length()).toInt();
      };
      auto extractBool = [&](const char* key) -> bool {
        String search = String("\"") + key + "\":";
        int idx = body.indexOf(search);
        if (idx < 0) return true;
        String val = body.substring(idx + search.length(), idx + search.length() + 5);
        return val.indexOf("true") >= 0;
      };

      int v;
      if ((v = extractInt("water_watering_percent")) >= 0)  WATER_WATERING_PERCENT  = v;
      if ((v = extractInt("tank_low_percent"))        >= 0)  TANK_LOW_PERCENT        = v;
      if ((v = extractInt("tank_high_percent"))       >= 0)  TANK_HIGH_PERCENT       = v;
      if ((v = extractInt("water_full_cm"))           >= 0)  WATER_FULL_CM           = v;
      if ((v = extractInt("water_empty_cm"))          >= 0)  WATER_EMPTY_CM          = v;
      if ((v = extractInt("fert_full_cm"))            >= 0)  FERT_FULL_CM            = v;
      if ((v = extractInt("fert_empty_cm"))           >= 0)  FERT_EMPTY_CM           = v;
      if ((v = extractInt("fert_interval_minutes"))   >= 0)  FERT_INTERVAL_MINUTES   = v;
      if ((v = extractInt("fert_duration_ms"))        >= 0)  FERT_DURATION_MS        = (unsigned long)v;
      if ((v = extractInt("buzzer_alert_interval_ms")) >= 0) BUZZER_ALERT_INTERVAL_MS = (unsigned long)v;

      BUZZER_ENABLED      = extractBool("buzzer_enabled");
      LCD_ENABLED         = extractBool("lcd_enabled");
      BACKLIGHT_ENABLED   = extractBool("backlight_enabled");
      FERTIGATION_ENABLED = extractBool("fertigation_enabled");

      if (!LCD_ENABLED) {
        lcd.noBacklight();
        lcd.clear();
      } else {
        if (BACKLIGHT_ENABLED) lcd.backlight();
        else                   lcd.noBacklight();
      }

      int rtcIdx = body.indexOf("\"rtc_set_time\":\"");
      if (rtcIdx >= 0) {
        int start = rtcIdx + 16;
        String timeStr = body.substring(start, start + 19);
        if (timeStr.length() == 19) {
          int y  = timeStr.substring(0,  4).toInt();
          int mo = timeStr.substring(5,  7).toInt();
          int d  = timeStr.substring(8,  10).toInt();
          int h  = timeStr.substring(11, 13).toInt();
          int mi = timeStr.substring(14, 16).toInt();
          int s  = timeStr.substring(17, 19).toInt();

          if (y >= 2000 && mo >= 1 && mo <= 12 && d >= 1 && d <= 31 &&
              h >= 0 && h <= 23 && mi >= 0 && mi <= 59 && s >= 0 && s <= 59) {
            rtc.adjust(DateTime(y, mo, d, h, mi, s));
            lastFertigateTime = rtc.now().unixtime() / 60;
            Serial.println("[DATA] RTC synced to: " + timeStr);
          }
        }
      }

      // ── Parse command ──────────────────────────────────────
      int cmdId = extractInt("command_id");
      int irr   = extractInt("irrigation");
      int fert  = extractInt("fertigation");

      if (cmdId > 0 && (irr || fert)) {
        pendingCommandId   = cmdId;
        if (irr  == 1) latchIrrigation  = true;  // SET latch, never clear here
        if (fert == 1) latchFertigation = true;
        Serial.printf("[DATA] Command id=%d irr=%d fert=%d\n", cmdId, irr, fert);
      }

      Serial.println("[DATA] Data applied.");
      httpFailStreak = 0;
    }
  } else {
    Serial.printf("[DATA] GET failed: %s\n", http.errorToString(code).c_str());
    httpFailStreak++;
  }

  http.end();
  netClient.stop(); 
  vTaskDelay(pdMS_TO_TICKS(200));
  optionsFetchPending = false;
  vTaskDelete(NULL);
}

/* ===============================================
* AUTOMATION CONTROL FUNCTIONS
* =============================================== */

/**
* Handle automatic watering based on soil moisture
* Implements fail-safe mechanisms:
* - Requires valid soil and water sensors
* - Prevents watering if tank is too low
* - Stops immediately if sensors fail during operation
*/
void handleAutomaticWatering() {
  // START WATERING CONDITIONS
  // All conditions must be true:
  // 1. Not currently watering
  // 2. Soil sensor is working properly
  // 3. Water sensor is working properly
  // 4. Soil moisture is below threshold
  // 5. Water tank level is above low threshold
  if (!watering &&
      !soilError &&
      !waterSensorError &&
      (soilPercent < WATER_WATERING_PERCENT || latchIrrigation) &&
      waterLevel > TANK_LOW_PERCENT) {
    
    // Start watering
    watering = true;
    digitalWrite(WATER_RELAY, LOW);  // Relay active LOW
    showActive("Watering", "Pump ON", 4000);
    startBuzzerAlert(3, 100, 100);   // 3 short beeps

    latchIrrigation    = false;
    overrideIrrigation = false;
    
    Serial.println(">>> Watering STARTED");
  }

  // STOP WATERING CONDITIONS
  // Stop if ANY condition is true:
  // 1. Soil moisture reached target level
  // 2. Water tank level is too low

  if (watering && 
    (soilPercent >= WATER_WATERING_PERCENT || waterLevel <= TANK_LOW_PERCENT)) {
    
    // Stop watering
    watering = false;
    digitalWrite(WATER_RELAY, HIGH);  // Relay inactive HIGH
    
    // Display appropriate message
    if (soilPercent >= WATER_WATERING_PERCENT) {
      showActive("Watering", "Complete", 4000);
      Serial.println(">>> Watering COMPLETE (soil satisfied)");
    } else {
      showActive("Watering", "Tank Low!", 4000);
      Serial.println(">>> Watering STOPPED (tank low)");
    }
  }
}

/**
* Handle scheduled fertigation (fertilizer + irrigation)
* Implements RTC-based timing and fail-safe mechanisms:
* - Requires valid RTC and fertilizer sensor
* - Fertigates at fixed intervals (FERT_INTERVAL_MINUTES)
* - Fixed fertigation duration (FERT_DURATION_MS)
* - Prevents fertigation if tank is too low
* - Stops immediately if sensors fail during operation
*/
void handleScheduledFertigation() {
  DateTime now = rtc.now();
  unsigned long currentMinutes = now.unixtime() / 60;
  unsigned long minutesSinceLastFertigate = (currentMinutes >= lastFertigateTime) 
    ? (currentMinutes - lastFertigateTime) 
    : 0;

  // START FERTIGATION CONDITIONS
  // All conditions must be true:
  // 1. Not currently fertigating
  // 2. Fertilizer sensor is working properly
  // 3. RTC is working properly
  // 4. Enough time has passed since last fertigation
  // 5. Fertilizer tank level is above low threshold
  if (!fertigating &&
      (FERTIGATION_ENABLED || latchFertigation) &&  
      !fertSensorError &&
      !rtcError &&
      (minutesSinceLastFertigate >= FERT_INTERVAL_MINUTES || latchFertigation) &&
      fertLevel > TANK_LOW_PERCENT) {
    
    // Start fertigation
    fertigating = true;
    fertigateStart = millis();
    digitalWrite(FERT_RELAY, LOW);  // Relay active LOW
    showActive("Fertigation", "Pump ON", FERT_DURATION_MS);
    startBuzzerAlert(5, 50, 50);    // 5 fast beeps

    latchFertigation    = false;
    overrideFertigation = false;
    
    Serial.println(">>> Fertigation STARTED");
  }

  // STOP FERTIGATION CONDITIONS
  // Stop if ANY condition is true:
  // 1. Fertigation duration completed
  // 2. Fertilizer tank level is too low

  if (fertigating && 
    (millis() - fertigateStart >= FERT_DURATION_MS || fertLevel <= TANK_LOW_PERCENT)) {

    // Stop fertigation
    fertigating = false;
    digitalWrite(FERT_RELAY, HIGH);  // Relay inactive HIGH
    
    // Update timer only if fertigation completed successfully
    if (millis() - fertigateStart >= FERT_DURATION_MS) {
      lastFertigateTime = currentMinutes;
      Serial.println(">>> Fertigation COMPLETE");
    } else {
      showActive("Fertigation", "Tank Low!", 3000);
      Serial.println(">>> Fertigation STOPPED (tank low)");
    }
  }
}

/**
* Handle tank low-level alerts
* Triggers buzzer alerts when tank levels drop below threshold
* Uses alert flags to prevent repeated alerts
*/
void handleTankLevelAlerts() {
  // Water tank low alert
  if (waterLevel <= TANK_LOW_PERCENT && !waterSensorError) {
    if (!waterLowAlerted) {
      if (BUZZER_ALERT_INTERVAL_MS == 0 || millis() - lastWaterBuzzer >= BUZZER_ALERT_INTERVAL_MS) {
        startBuzzerAlert(15, 100, 100);
        lastWaterBuzzer = millis();
      }

      waterLowAlerted = true;
      Serial.println("!!! Water tank LOW");
    }
  } else {
    waterLowAlerted = false;  // Reset when level recovers
  }

  // Fertilizer tank low alert
  if (fertLevel <= TANK_LOW_PERCENT && !fertSensorError) {
    if (!fertLowAlerted) {
      if (BUZZER_ALERT_INTERVAL_MS == 0 || millis() - lastFertBuzzer >= BUZZER_ALERT_INTERVAL_MS) {
        startBuzzerAlert(20, 50, 50);
        lastFertBuzzer = millis();
      }

      fertLowAlerted = true;
      Serial.println("!!! Fertilizer tank LOW");
    }
  } else {
    fertLowAlerted = false;  // Reset when level recovers
  }
}

/**
* Emergency stop for all outputs when critical sensors fail
* This is a fail-safe mechanism to prevent crop damage
*/
void handleSensorErrorEmergencyStop() {
  // Stop watering if soil or water sensor fails
  if ((waterSensorError || soilError) && watering) {
    digitalWrite(WATER_RELAY, HIGH);
    watering = false;
    showActive("ERROR: Water", "System Stopped", 5000);
    Serial.println("!!! EMERGENCY STOP: Watering (sensor error)");
  }

  // Stop fertigation if fertilizer sensor or RTC fails
  if ((fertSensorError || rtcError) && fertigating) {
    digitalWrite(FERT_RELAY, HIGH);
    fertigating = false;
    showActive("ERROR: Fert", "System Stopped", 5000);
    Serial.println("!!! EMERGENCY STOP: Fertigation (sensor error)");
  }
}

/**
* Display sensor error information on LCD
* Only shows if no active message is currently displayed
*/
void handleSensorErrorDisplay(bool anySensorError) {
  if (!anySensorError) return;
  if (activeDisplay) return;  // Don't interrupt active messages

  // Build error message string
  String errorMsg = "";
  if (soilError)        errorMsg += "Soil ";
  if (waterSensorError) errorMsg += "Water ";
  if (fertSensorError)  errorMsg += "Fert ";
  if (tempError)        errorMsg += "Temp ";
  if (humError)         errorMsg += "Hum ";
  if (presError)        errorMsg += "Pres ";
  if (rtcError)         errorMsg += "RTC ";
  
  errorMsg.trim();
  if (errorMsg == "") errorMsg = "Unknown";

  showActive("Sensor Error", errorMsg.c_str(), 5000);
}

/* ===============================================
* ARDUINO SETUP FUNCTION
* =============================================== */
void setup() {
  payloadMutex = xSemaphoreCreateMutex();

  // Initialize serial communication
  Serial.begin(115200);
  Serial.println("\n=================================");
  Serial.println("  HydroNourish System Initializing");
  Serial.println("=================================\n");

  // Initialize I2C bus
  Wire.begin(21, 22);

  WiFi.begin(ssid, password);
  Serial.print("Connecting to WiFi");
  int wifiRetry = 0;
  while (WiFi.status() != WL_CONNECTED && wifiRetry < 20) {
    delay(500);
    Serial.print(".");
    wifiRetry++;
  }
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi connected: " + WiFi.localIP().toString());
  } else {
    Serial.println("\n✗ WiFi connection failed");
  }

  // Initialize LCD
  lcd.init();
  lcd.backlight();
  lcd.setCursor(0, 0);
  lcd.print("HydroNourish v3");
  lcd.setCursor(0, 1);
  lcd.print("Initializing...");
  delay(2000);

  delay(1000);

  // Configure digital pins
  pinMode(BUZZER_PIN, OUTPUT);
  pinMode(WATER_RELAY, OUTPUT);
  pinMode(FERT_RELAY,  OUTPUT);
  pinMode(RGB_R_PIN, OUTPUT);
  pinMode(RGB_G_PIN, OUTPUT);
  pinMode(RGB_B_PIN, OUTPUT);
  pinMode(SOIL_PIN,    INPUT);

  // Set initial states
  rgbOff();
  digitalWrite(WATER_RELAY, HIGH);  // Relays inactive HIGH
  digitalWrite(FERT_RELAY,  HIGH);
  digitalWrite(BUZZER_PIN,  LOW);
  Serial.println("✓ GPIO configured");

  // Configure ultrasonic sensors
  pinMode(waterUS.trig, OUTPUT);
  pinMode(waterUS.echo, INPUT);
  pinMode(fertUS.trig,  OUTPUT);
  pinMode(fertUS.echo,  INPUT);

  // Attach interrupts for ultrasonic sensors
  attachInterrupt(digitalPinToInterrupt(WATER_ECHO), echoISRWater, CHANGE);
  attachInterrupt(digitalPinToInterrupt(FERT_ECHO),  echoISRFert,  CHANGE);
  Serial.println("✓ Ultrasonic sensors configured");

  // Initialize sensors
  if (!aht.begin()) {
    Serial.println("✗ AHT sensor not found!");
  } else {
    Serial.println("✓ AHT sensor initialized");
  }

  if (!bmp.begin(0x77)) {
    Serial.println("✗ BMP280 not found!");
  } else {
    Serial.println("✓ BMP280 sensor initialized");
  }

  if (!rtc.begin()) {
    Serial.println("✗ RTC not found!");
  } else {
    Serial.println("✓ RTC initialized");
  }

  DateTime now = rtc.now();
  if (checkRTCError(now)) {
    Serial.println("⚠ WARNING: RTC data invalid!");
    Serial.println("  Please check RTC module connection or battery");
    Serial.println("  Fertigation will be DISABLED until RTC is valid");
    
    lcd.clear();
    lcd.print("RTC ERROR!");
    lcd.setCursor(0, 1);
    lcd.print("Check Module");
    delay(3000);
  }

  // Initialize soil sensor averaging buffer
  for (int i = 0; i < SOIL_AVG_SAMPLES; i++) {
    soilReadings[i] = 0;
  }
  soilReadIndex    = 0;
  soilTotal        = 0;
  soilBufferFilled = false;

  // Initialize fertigation timer
  lastFertigateTime = now.unixtime() / 60;
  optionsFetchPending = true;
  xTaskCreatePinnedToCore(fetchDataTaskFn, "httpData", 8192, NULL, 1, NULL, 0);

  // System ready
  lcd.clear();
  lcd.setCursor(0, 0);
  lcd.print("System Ready!");
  delay(2000);
  lcd.clear();

  Serial.println("\n=================================");
  Serial.println("  System Ready");
  Serial.println("=================================");
  Serial.println("\nSerial Commands:");
  Serial.println("  debug - Show debug menu");
  Serial.println("  water, soil, fert, temp, hum, pres, time - Debug modes");
  Serial.println("  exit  - Exit debug mode\n");
}

/* ===============================================
* ARDUINO MAIN LOOP
* =============================================== */
void loop() {
  /* =========================================
  * SECTION 1: INPUT HANDLING
  * ========================================= */
  
  // Handle serial commands
  handleSerial();

  // Reconnect WiFi if dropped (every 30 seconds)
  if (millis() - lastWiFiCheck > 30000) {
    lastWiFiCheck = millis();
    checkWiFiStatus();
  }

  static int lastStreakState = 0; // 0 = normal, 1 = backoff
  int currentStreakState = (httpFailStreak >= HTTP_BACKOFF_THRESHOLD) ? 1 : 0;

  if (currentStreakState != lastStreakState) {
    // Streak state changed — reset all timers to enforce new interval immediately
    lastOptionsCheck = millis();
    lastSend         = millis();
    lastStreakState  = currentStreakState;
    Serial.printf("[HTTP] Backoff state changed: %s\n", currentStreakState ? "BACKOFF" : "NORMAL");
  }

  unsigned long optInterval  = (httpFailStreak >= HTTP_BACKOFF_THRESHOLD) ? 60000UL : 10000UL;
  unsigned long sendInterval = (httpFailStreak >= HTTP_BACKOFF_THRESHOLD) ? 30000UL : 5000UL;

  if (millis() - lastOptionsCheck > optInterval && !optionsFetchPending) {
    lastOptionsCheck = millis();
    optionsFetchPending = true;
    xTaskCreatePinnedToCore(fetchDataTaskFn, "httpData", 8192, NULL, 1, NULL, 0);
  }

  /* =========================================
  * SECTION 2: SENSOR DATA ACQUISITION
  * ========================================= */
  
  // Read ultrasonic sensors (non-blocking)
  triggerUltrasonic(waterUS);
  triggerUltrasonic(fertUS);
  updateUltrasonic(waterUS, lastWaterUpdate);
  updateUltrasonic(fertUS,  lastFertUpdate);
  
  // Calculate tank levels
  waterDistance = waterUS.distance;
  fertDistance  = fertUS.distance;
  waterLevel = levelFromCM(waterDistance, WATER_FULL_CM, WATER_EMPTY_CM);
  fertLevel  = levelFromCM(fertDistance,  FERT_FULL_CM,  FERT_EMPTY_CM);

  // Read soil moisture sensor
  int soilRaw = readSoilAverage();
  soilPercent = constrain(map(soilRaw, SOIL_DRY, SOIL_WET, 0, 100), 0, 100);

  // Read temperature and humidity
  sensors_event_t humEvent, tempEvent;
  aht.getEvent(&humEvent, &tempEvent);
  temperature = tempEvent.temperature;
  humidity    = humEvent.relative_humidity;

  // Read atmospheric pressure
  pressure = bmp.readPressure() / 100.0F;  // Convert Pa to hPa

  // Get current time
  DateTime now = rtc.now();

  /* =========================================
  * SECTION 3: SENSOR ERROR DETECTION
  * ========================================= */
  
  soilError        = checkSoilError(soilRaw);
  waterSensorError = checkUltrasonicError(waterDistance, waterLevel, lastWaterUpdate);
  fertSensorError  = checkUltrasonicError(fertDistance,  fertLevel,  lastFertUpdate);
  tempError        = checkTempError(temperature);
  humError         = checkHumError(humidity);
  presError        = checkpresError(pressure);
  rtcError         = checkRTCError(now);

  bool anySensorError = soilError || waterSensorError || fertSensorError || tempError || humError || presError || rtcError;

  /* =========================================
  * SECTION 4: FAIL-SAFE EMERGENCY STOP
  * ========================================= */
  
  // CRITICAL: Stop all outputs if sensors fail
  // This must happen BEFORE any automation logic
  handleSensorErrorEmergencyStop();

  /* =========================================
  * SECTION 5: AUTOMATION CONTROL
  * ========================================= */
  
  // Handle automatic watering (with safety checks)
  handleAutomaticWatering();

  // Handle scheduled fertigation (with safety checks)
  handleScheduledFertigation();

  // Handle tank level alerts
  handleTankLevelAlerts();

  // Send data to server every 10 seconds
  if (millis() - lastSend > sendInterval) {
    lastSend = millis();
    sendToServer();
  }

  /* =========================================
  * SECTION 6: OUTPUT UPDATES
  * ========================================= */
  
  // Update buzzer (non-blocking)
  updateBuzzer();

  // Update RGB LED priority and color
  updateRGBPriority(
    anySensorError,
    waterLevel <= TANK_LOW_PERCENT && !waterSensorError,
    fertLevel  <= TANK_LOW_PERCENT && !fertSensorError,
    watering,
    fertigating
  );

  // Handle RGB idle cycling
  if (rgbPriority == RGB_IDLE) {
    rgbIdleLoop();
  }
  
  // Update RGB blinking (non-blocking)
  rgbUpdate();

  /* =========================================
  * SECTION 7: DISPLAY UPDATES
  * ========================================= */
  
  // Update LCD display
  if (LCD_ENABLED) {
    handleSensorErrorDisplay(anySensorError);

    if (activeDisplay) {
      if (millis() > activeUntil) {
        activeDisplay = false;
        lcd.clear();
      }
    } else {
      showIdle();
    }
  }

  /* =========================================
  * SECTION 8: DEBUG OUTPUT
  * ========================================= */
  
  // Print debug information based on cuFrrent mode
  printDebugInfo();

  /* =========================================
  * SECTION 9: LOOP DELAY
  * ========================================= */
  
  // Small delay to prevent overwhelming the system
  // All functions are non-blocking, so this is safe
  delay(200);
}

/* ===============================================
* END OF PROGRAM
* =============================================== */
