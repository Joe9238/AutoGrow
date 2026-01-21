#include <WiFi.h>
#include <esp_now.h>

// -------------------- Peer MAC --------------------
uint8_t heltecMac[] = { 0x10, 0x51, 0xDB, 0x2B, 0x5A, 0x6C }; // Heltec WiFi LoRa MAC

// -------------------- ESP-NOW Receive --------------------
void onReceive(const esp_now_recv_info *info, const uint8_t *incomingData, int len) {
  char msg[64];
  memcpy(msg, incomingData, len);
  msg[len] = '\0';

  Serial.print("Received: ");
  Serial.println(msg);

  if (strcmp(msg, "TIME?") == 0)
  {
    char reply[32];
    sprintf(reply, "TIME:%lu", millis() / 1000);

    esp_now_send(info->src_addr, (uint8_t *)reply, strlen(reply));
  }
}

// -------------------- Setup --------------------
void setup() {
  Serial.begin(115200);
  delay(1000);

  Serial.println("ESP32-S3 Time Server Starting...");

  WiFi.mode(WIFI_STA);

  if (esp_now_init() != ESP_OK) {
    Serial.println("ESP-NOW init failed");
    return;
  }

  esp_now_register_recv_cb(onReceive);

  esp_now_peer_info_t peerInfo = {};
  memcpy(peerInfo.peer_addr, heltecMac, 6);
  peerInfo.channel = 0;
  peerInfo.encrypt = false;

  if (esp_now_add_peer(&peerInfo) != ESP_OK) {
    Serial.println("Failed to add Heltec peer");
    return;
  }

  Serial.println("ESP-NOW ready");
}

// -------------------- Loop --------------------
void loop() {
  // Actions are purely event-based
}
