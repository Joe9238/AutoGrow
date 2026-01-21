#include <Wire.h>
#include "HT_SSD1306Wire.h"
#include <WiFi.h>
#include <esp_now.h>

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

  display.drawString(0, 0, "Soil Sensor");

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
  // Read soil sensor
  sendRequest();
  delay(200);

  sensorReadings readings = readSensors();

  // Ask ESP32-S3 for elapsed time
  const char *msg = "TIME?";
  esp_now_send(peerMac, (uint8_t *)msg, strlen(msg));

  // Update display
  displayValue(readings.moisture, readings.temperature, lastTime);

  delay(3000);
}
