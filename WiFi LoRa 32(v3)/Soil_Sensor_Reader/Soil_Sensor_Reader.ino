#include <Wire.h>
#include "HT_SSD1306Wire.h"

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
  0x03,   // Function code: Read Holding Registers
  0x00, 0x00,   // Start register high, low
  0x00, 0x01,   // Number of registers high, low
  0x84, 0x0A    // CRC (for these bytes)
};

// -------------------- OLED Power Control --------------------
void VextON() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, LOW);   // LOW = ON
}

void VextOFF() {
  pinMode(Vext, OUTPUT);
  digitalWrite(Vext, HIGH);  // HIGH = OFF
}

// -------------------- Setup --------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("Soil Sensor OLED Test Starting...");

  // Turn OLED power on
  VextON();
  delay(100);

  // Init display
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
  digitalWrite(DE_RE_PIN, LOW);   // Receive mode
}

// -------------------- Send Modbus Request --------------------
void sendRequest() {
  digitalWrite(DE_RE_PIN, HIGH);   // Transmit mode
  delay(2);

  sensorUART.write(requestFrame, sizeof(requestFrame));
  sensorUART.flush();

  delay(2);
  digitalWrite(DE_RE_PIN, LOW);    // Back to receive mode
}

// -------------------- Read Response --------------------
int readMoisture() {
  byte response[7];
  int index = 0;
  unsigned long start = millis();

  while (millis() - start < 500) {
    while (sensorUART.available()) {
      response[index++] = sensorUART.read();
      if (index >= 7) break;
    }
    if (index >= 7) break;
  }

  if (index < 7) {
    Serial.println("No response from sensor");
    return -1;
  }

  // Basic sanity check
  if (response[0] != 0x01 || response[1] != 0x03) {
    Serial.println("Invalid response header");
    return -1;
  }

  int moisture = (response[3] << 8) | response[4];
  return moisture;
}

// -------------------- Display --------------------
void displayValue(int moisture) {
  display.clear();

  display.setFont(ArialMT_Plain_10);
  display.drawString(0, 0, "Soil Sensor");

  if (moisture >= 0) {
    display.drawString(0, 14, "Moisture:");
    display.setFont(ArialMT_Plain_16);
    display.drawString(0, 28, String(moisture));
  } else {
    display.drawString(0, 14, "ERROR");
  }

  display.display();
}

// -------------------- Main Loop --------------------
void loop() {
  sendRequest();
  delay(200);

  int moisture = readMoisture();

  if (moisture >= 0) {
    Serial.print("Moisture: ");
    Serial.println(moisture);
  }

  displayValue(moisture);

  delay(3000);
}
