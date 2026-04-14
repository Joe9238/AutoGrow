#include <Wire.h>
#include "HT_SSD1306Wire.h"
#include <WiFi.h>
#include <esp_now.h>
#include <WebServer.h>
#include <Preferences.h>
#include <DNSServer.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>

DNSServer dnsServer;
const byte DNS_PORT = 53;

Preferences prefs;
String currentSSID = "";
WebServer server(80);

// -------------------- OLED Setup --------------------
#ifdef WIRELESS_STICK_V3
static SSD1306Wire display(0x3c, 500000, SDA_OLED, SCL_OLED, GEOMETRY_64_32, RST_OLED);
#else
static SSD1306Wire display(0x3c, 500000, SDA_OLED, SCL_OLED, GEOMETRY_128_64, RST_OLED);
#endif

// -------------------- RS-485 / UART Setup --------------------
const int TX_PIN = 6;
const int RX_PIN = 7;
const int DE_RE_PIN = 42;
const int WIFI_LED_PIN = 5;
const int WIFI_BUTTON_PIN = 4;

HardwareSerial sensorUART(1);

// -------------------- Modbus Request --------------------
// Read 1 register starting at 0x0000 from slave ID 1
byte requestFrame[] = {
  0x01,   // Slave ID
  0x03,   // Function code 3: Read Holding Registers
  0x00, 0x00,   // Start register high, low - which memory slot you want to start reading from 
  0x00, 0x02,   // Number of registers high, low
  0xC4, 0x0B    // CRC (Low byte, High Byte) https://crccalc.com/?crc=01%2003%2000%2000%2000%2002&method=CRC-16/MODBUS&datatype=hex&outtype=hex
};

struct sensorReadings {
  float moisture;
  float temperature;
};

// -------------------- ESP-NOW --------------------
uint8_t peerMac[] = { 0xE4, 0xB3, 0x23, 0xF7, 0xBF, 0x28 }; // ESP32-S3-Zero MAC
String lastTime = "Waiting...";

// -------------------- Sensor Reading ----------------
unsigned long lastSensorRead = 0;
const unsigned long SENSOR_INTERVAL = 5000; // 5 seconds

// ----------------------- WiFi Reset -----------------
bool apMode = false;
unsigned long wifiResetStart = 0;
const unsigned long LONG_PRESS_TIME = 10000; // 10 second hold


// -------------------- OLED Power --------------------
void VextON() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, LOW);   // LOW = ON
}

void VextOFF() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, HIGH);  // HIGH = OFF
}

// -------------------- ESP-NOW Receive --------------------
void onReceive(const esp_now_recv_info *info, const uint8_t *incomingData, int len) {
  char msg[64];
  memcpy(msg, incomingData, len);
  msg[len] = '\0';

  Serial.print("Message: ");
  Serial.println(msg);

  if (strncmp(msg, "TIME:", 5) == 0)
  {
    lastTime = String(msg + 5);
  }
}

// -------------------- WIFI Reset --------------------

String generatePassword(int length = 10) {
  const char charset[] =
    "ABCDEFGHIJKLMNOPQRSTUVWXYZ"
    "abcdefghijklmnopqrstuvwxyz"
    "0123456789";

  String pass = "";
  for (int i = 0; i < length; i++) {
    uint32_t r = esp_random();                 // hardware RNG
    pass += charset[r % (sizeof(charset) - 1)];
  }
  return pass;
}

void startHotspot() {
  prefs.begin("wifi", false);
  prefs.clear();
  prefs.end();
  prefs.begin("mqtt", false);
  prefs.clear();
  prefs.end();
  
  display.clear();
  display.setFont(ArialMT_Plain_10);

  display.drawString(0, 0, "AP MODE");

  WiFi.disconnect(true, true); // clear creds
  delay(200);

  String hotspotPassword = generatePassword(5);
  WiFi.mode(WIFI_AP_STA);
  WiFi.softAP("AutoGrow-Setup", hotspotPassword);

  IPAddress IP = WiFi.softAPIP();

  // Redirect all domains to ESP
  dnsServer.start(DNS_PORT, "*", IP);
  // Android
  server.on("/generate_204", handleRoot);

  // Windows
  server.on("/fwlink", handleRoot);

  // Apple (iOS/macOS)
  server.on("/hotspot-detect.html", handleRoot);
  server.on("/library/test/success.html", handleRoot);

  // Catch-all
  server.onNotFound(handleRoot);

  display.drawString(0, 15, "Connect to Wi-Fi hotspot");
  display.drawString(0, 27, "AutoGrow-Setup");
  display.drawString(0, 39, "Password: " + hotspotPassword);
  display.display();
  apMode = true;

  server.on("/", handleRoot);
  server.on("/save", HTTP_POST, handleSave);
  server.begin();
}

void handleSave() {
  String ssid = server.arg("ssid");
  String pass = server.arg("pass");
  String code = server.arg("code");

  Serial.println("SSID: " + ssid);
  Serial.println("PASS: " + pass);

  // Keep AP alive + try STA connection
  WiFi.mode(WIFI_AP_STA);
  WiFi.begin(ssid.c_str(), pass.c_str());

  int attempts = 0;
  const int maxAttempts = 20; // ~10 seconds

  while (WiFi.status() != WL_CONNECTED && attempts < maxAttempts) {
    delay(500);
    attempts++;
  }

  if (WiFi.status() == WL_CONNECTED) {
    // Save only if successful
    prefs.begin("wifi", false);
    prefs.putString("ssid", ssid);
    prefs.putString("pass", pass);
    prefs.end();

    prefs.begin("mqtt", false);
    prefs.putString("code", code);
    prefs.end();

    String html = R"rawliteral(
      <html>
      <body>
        <h2 style="color:green;">Connected successfully!</h2>
        <p>You can now close this page.</p>
      </body>
      </html>
    )rawliteral";

    server.send(200, "text/html", html);

    delay(2000);
    ESP.restart();
  } else {
    WiFi.disconnect(false); // disconnect STA only, keep AP

    String html = R"rawliteral(
      <html>
      <body>
        <h2 style="color:red;">Connection Failed</h2>
        <p>Check SSID or password and try again.</p>
        <a href="/">Go Back</a>
      </body>
      </html>
    )rawliteral";

    server.send(200, "text/html", html);
  }
}

// --------------------- AP website ------------
void handleRoot() {
  String html = R"rawliteral(
  <html>
  <body>
    <h2>AutoGrow Setup</h2>

    <form action="/save" method="POST" onsubmit="showConnecting()">
      2.4GHz WiFi SSID:<br>
      <input name="ssid"><br>

      WiFi Password:<br>
      <input name="pass" type="password"><br>

      Pairing Code:<br>
      <input name="code"><br><br>

      <input type="submit" value="Save">
    </form>

    <p id="status" style="color:blue;"></p>

    <script>
      function showConnecting() {
        document.getElementById("status").innerText = "Connecting...";
      }
    </script>

  </body>
  </html>
  )rawliteral";

  server.send(200, "text/html", html);
}

// --------------------- WiFi Status ------------

void drawWiFiStatus() {
  if (apMode) {
    display.drawString(60, 0, "AP Mode");  // Access Point mode (redundant here but added just in case)
    return;
  }

  if (WiFi.status() == WL_CONNECTED) {
    display.drawString(60, 0, "WiFi: " + WiFi.SSID());  // Connected
  } else {
    display.drawString(90, 0, "No WiFi");     // Not connected
  }
}

// --------------------- Register with website ------------

void registerDevice() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected");
    return;
  }

  HTTPClient http;

  String url = "http://192.168.68.53/api/device/register";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");

  // Get MAC as UID
  String uid = WiFi.macAddress();
  uid.replace(":", "");

  prefs.begin("mqtt", false);
  String pairingCode = prefs.getString("code", "");
  prefs.end();

  String payload = "{";
  payload += "\"uid\":\"" + uid + "\",";
  payload += "\"code\":\"" + pairingCode + "\"";
  payload += "}";
  Serial.println(payload);
  int httpResponseCode = http.POST(payload);

  if (httpResponseCode > 0) {
    String response = http.getString();
    Serial.println(response);

    StaticJsonDocument<512> doc;
    deserializeJson(doc, response);

    if (doc["success"]) {
      String user = doc["mqtt"]["username"];
      String pass = doc["mqtt"]["password"];

      // Save to prefs
      prefs.begin("mqtt", false);
      prefs.putString("user", user);
      prefs.putString("pass", pass);
      prefs.end();

      Serial.println("MQTT creds saved!");

    } else {
      Serial.println("Registration failed");
    }

  } else {
    Serial.println("HTTP error");
  }

  http.end();
}

// -------------------- Setup --------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("Soil Sensor Starting...");

  // Turn OLED power on
  VextON();
  delay(100);

  display.init();
  display.clear();
  display.setContrast(255);

  display.setFont(ArialMT_Plain_10);
  display.drawString(0, 0, "Soil Sensor Test");
  display.drawString(0, 12, "Starting...");
  display.display();

  // Init UART
  sensorUART.begin(9600, SERIAL_8N1, RX_PIN, TX_PIN);
  pinMode(DE_RE_PIN, OUTPUT);
  digitalWrite(DE_RE_PIN, LOW);

  // ESP-NOW Wi-Fi
  WiFi.mode(WIFI_STA);

  if (esp_now_init() != ESP_OK) {
    Serial.println("ESP-NOW init failed");
    display.drawString(0, 28, "ESP-NOW FAIL");
    display.display();
    return;
  }

  esp_now_register_recv_cb(onReceive);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, peerMac, 6);
  peerInfo.channel = 0;
  peerInfo.encrypt = false;

  if (esp_now_add_peer(&peerInfo) != ESP_OK) {
    Serial.println("Failed to add peer");
    display.drawString(0, 28, "Peer FAIL");
    display.display();
    return;
  }

  prefs.begin("wifi", true);
  currentSSID = prefs.getString("ssid", "");
  String savedPASS = prefs.getString("pass", "");
  prefs.end();

  if (currentSSID != "") {
    WiFi.begin(currentSSID.c_str(), savedPASS.c_str());
    delay(5000);
    prefs.begin("mqtt", true);
    String mqttUser = prefs.getString("user", "");
    String mqttPass = prefs.getString("pass", "");
    prefs.end();

    if (mqttUser == "" || mqttPass == "") {
      Serial.println("No MQTT creds → registering...");
      registerDevice();
    }
  }

  // setup wifi pins
  pinMode(WIFI_BUTTON_PIN, INPUT_PULLUP);
  pinMode(WIFI_LED_PIN, OUTPUT);
  digitalWrite(WIFI_LED_PIN, LOW);

  Serial.println("ESP-NOW ready");
}

// -------------------- Send Modbus Request --------------------
void sendRequest() {
  digitalWrite(DE_RE_PIN, HIGH);
  delay(2);

  sensorUART.write(requestFrame, sizeof(requestFrame));
  sensorUART.flush();

  delay(2);
  digitalWrite(DE_RE_PIN, LOW);
}

// -------------------- Read Modbus --------------------
sensorReadings readSensors() {
  int expectedBytes = 9;
  byte response[expectedBytes];
  int index = 0;
  unsigned long start = millis();

  while (millis() - start < 500) {
    while (sensorUART.available()) {
      response[index++] = sensorUART.read();
      if (index >= expectedBytes) break;
    }
    if (index >= expectedBytes) break;
  }

  if (index < expectedBytes) {
    Serial.println("No response from sensor");
    return { -1, -1 };
  }

  if (response[0] != 0x01 || response[1] != 0x03) {
    Serial.println("Invalid response header");
    return { -1, -1 };
  }

  float moisture = float((response[3] << 8) | response[4]) / 10.0;
  float temperature = float((response[5] << 8) | response[6]) / 10.0;

  return { moisture, temperature };
}

// -------------------- Display --------------------
void displayValue(float moisture, float temperature, String timeStr) {
  display.clear();
  display.setFont(ArialMT_Plain_10);

  display.drawString(0, 0, "AutoGrow");
  drawWiFiStatus(); 

  if (moisture >= 0) {
    display.drawString(0, 12, "Moist: " + String(moisture, 1) + "%");
  } else {
    display.drawString(0, 12, "MOISTURE ERROR");
  }

  if (temperature >= 0) {
    display.drawString(0, 24, "Temp: " + String(temperature, 1) + " C");
  } else {
    display.drawString(0, 24, "TEMP ERROR");
  }

  display.drawString(0, 36, "Time:");
  display.drawString(0, 48, timeStr);

  display.display();
}

// -------------------- Main Loop --------------------
void loop() {
  if (apMode) {
    dnsServer.processNextRequest(); 
    server.handleClient();
    return;
  }

  unsigned long now = millis();
  int wifiButtonPinState = digitalRead(WIFI_BUTTON_PIN);


  // -------- Soil Sensor Every 5 Seconds --------
  if (now - lastSensorRead >= SENSOR_INTERVAL && wifiButtonPinState == HIGH) {
    lastSensorRead = now;

    sendRequest();
    delay(50);

    sensorReadings readings = readSensors();

    const char *msg = "TIME?";
    esp_now_send(peerMac, (uint8_t *)msg, strlen(msg));

    displayValue(readings.moisture, readings.temperature, lastTime);
  }

  // -------- Button Handling (Long Press) --------
  if (wifiButtonPinState == LOW) {
    digitalWrite(WIFI_LED_PIN, HIGH);
     
    display.clear();
    display.setFont(ArialMT_Plain_10);

    display.drawString(0, 0, "DEVICE WILL RESET IN:");
    if (wifiResetStart == 0) {
      wifiResetStart = now;
    }

    int secondsLeft = 10 - ((now - wifiResetStart) / 1000);
    display.drawString(0, 30, String(secondsLeft));

    display.display();

    // Long press detected
    if (!apMode && (now - wifiResetStart > LONG_PRESS_TIME)) {
      startHotspot();
    }

  } else {
    digitalWrite(WIFI_LED_PIN, LOW);
    wifiResetStart = 0;
  }
}

