

# AutoGrow

AutoGrow is an automated plant watering system built using ESP32 devices, a web platform, and real-time weather data.

It monitors soil moisture and temperature, checks upcoming rainfall, and controls a water valve based on user-defined thresholds to keep plants hydrated without wasting water.

The system consists of three main components:

1. **Heltec WiFi LoRa 32 (v3)** – Sensor hub, MQTT communication, and decision maker  
2. **Waveshare ESP32-S3-Zero** – Valve controller  
3. **Website** – Device management, weather integration, and configuration  



## Heltec WiFi LoRa 32 (v3)

This is the main hub device.

### Responsibilities:
- Connects to the home WiFi and MQTT broker (HiveMQ)
- Reads soil sensor data (moisture and temperature)
- Receives configuration from the website:
  - Whether rain is due in the next 6 hours
  - Yellow Threshold: The moisture % at which the soil will be watered if no rain is expected
  - Red Threshold: The moisture % at which the soil will be watered regardless of weather conditions
- Decides whether watering is required, maintaining the existing configuration when server communication breaks down
- Sends commands to valve controller via ESP-NOW
- Displays live data on OLED screen

### Watering Logic:
- **Below red threshold** → always water  
- **Between red and yellow** → water only if no rain expected  
- **Above yellow** → no watering  

### Storage:
Uses "Preferences" to persist:
- WiFi credentials
- MQTT credentials
- Thresholds and rain status


## Waveshare ESP32-S3-Zero

This is the valve controller, built as a separate unit to allow distance between the sensor and the valve.

### Responsibilities:
- Connects to the "hub" via ESP-NOW
- Responds to commands to allow or stop water flow
- Has a local failsafe to ensure plants are not over-watered if communication breaks down


## Website

The web platform acts as the control centre for live weather data and user interaction. 
Built on the PHP framework Laravel, using the Vue starter kit.


<small>https://laravel.com/starter-kits</small>

### Responsibilities:
- User registration and authentication
- Device pairing via code
- Stores device configurations and locations
- Fetches upcoming rain forecasts for each device
- Sends hourly config updates to devices via MQTT


## Docker

Hosts containers for the website, MySQL database, and phpMyAdmin for database management.

## Setting up the system

### Running the website:
A startup script "up.sh" can be run within a linux terminal to build and start up the containers.

**A .env file is necessary to run the website and allow the system to function. This has not been included as it contains sensitive data relating to existing HiveMQ credentials.**

### From a user's perspective:
The hub and valve controller come as a pair and will be able to communicate automatically once powered on. The hub will start up in "Access Point" mode. The user should first register their account on the website and select "Add a new device" to receive a pairing code.

Once done:
1) On any device to connect to the new network using the credentials provided on the OLED screen
2) A page will pop up on the device either immediately or after accessing the browser 
3) Within the page, enter the name and password of your home network, and the pairing code provided by the website
4) After submission the hub will connect to the home network and receive the necessary configuration for long-term communication.
5) On the website, the user can enter the device's location using their postcode and set the desired moisture % thresholds
6) The hub's sensor should be buried into the soil and the valve controller connected to the home irrigation's water supply

Following this, irrigation will continue automatically and conditions/configurations can be monitored per device on a graph within the website. 