#include <WiFi.h>
#include <esp_now.h>

// -------------------- MOSFET Pin --------------------
const int VALVE_PIN = 7;

// -------------------- Peer MAC --------------------
uint8_t heltecMac[] = { 0x10, 0x51, 0xDB, 0x2B, 0x5A, 0x6C };

// -------------------- Timing --------------------
const unsigned long ON_TIME     = 5000;   // 5 seconds ON
const unsigned long OFF_TIME    = 25000;  // 25 seconds OFF

unsigned long stateStartTime = 0;
bool valveOn = false;

// -------------------- ESP-NOW Receive --------------------
void onReceive(const esp_now_recv_info *info, const uint8_t *incomingData, int len) {
  char msg[64];
  memcpy(msg, incomingData, len);
  msg[len] = '\0';

  if (strcmp(msg, "TIME?") == 0) {
    char reply[32];
    sprintf(reply, "TIME:%lu", millis() / 1000);
    esp_now_send(info->src_addr, (uint8_t *)reply, strlen(reply));
  }
}

// -------------------- Setup --------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  pinMode(VALVE_PIN, OUTPUT);
  digitalWrite(VALVE_PIN, LOW); // Start CLOSED
  valveOn = false;

  WiFi.mode(WIFI_STA);
  esp_now_init();
  esp_now_register_recv_cb(onReceive);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, heltecMac, 6);
  esp_now_add_peer(&peerInfo);

  stateStartTime = millis();
  Serial.println("Valve controller started");
}

// -------------------- Loop --------------------
void loop() {
  unsigned long now = millis();

  if (valveOn) {
    // Valve currently ON
    if (now - stateStartTime >= ON_TIME) {
      digitalWrite(VALVE_PIN, LOW);
      valveOn = false;
      stateStartTime = now;
      Serial.println("Valve CLOSED");
    }
  } else {
    // Valve currently OFF
    if (now - stateStartTime >= OFF_TIME) {
      digitalWrite(VALVE_PIN, HIGH);
      valveOn = true;
      stateStartTime = now;
      Serial.println("Valve OPEN");
    }
  }
}
