GT06 GPS TCP Server For Laravel
This repository contains a PHP-based TCP server that listens for connections from GT06 GPS devices, processes the incoming GPS data, and logs the information in real-time. The server handles IMEI extraction, GPS data decoding, and provides a template for developing more sophisticated GPS tracking and processing solutions.

Features
TCP Server: Listens for incoming connections on port 1234 and maintains an open socket for real-time data streaming.
Hexadecimal Payload Processing: Logs and processes raw data from GT06 GPS devices in hex format.
IMEI Extraction: Identifies the device's IMEI for tracking and data association.
GPS Data Parsing: Decodes and converts GPS data, including latitude, longitude, speed, and timestamp.
Error Handling: Logs connection and payload errors to ensure robustness.
Prerequisites
PHP (minimum version 7.x recommended)
Composer (optional, for additional package management)
Ensure your environment allows long-running scripts since the server is designed to continuously run and handle incoming data.

Installation
Clone the Repository:

```
git clone https://github.com/yourusername/gt06-gps-tcp-server.git
cd gt06-gps-tcp-server
```
Setup PHP Configuration:

Update PHP settings for long-running scripts in GpsTcpServer.php:

```
ini_set('max_execution_time', 0);
ini_set('default_socket_timeout', -1);
ini_set('max_input_time', -1);
```
Run the Server:

Start the GPS server with the Artisan command:

```
php artisan gps:server
```
The server will begin listening on tcp://0.0.0.0:1234 for incoming connections.

Code Overview
The main functionality of the TCP server is in GpsTcpServer.php, which includes the following methods:

handle(): Initializes the socket server, manages incoming connections, and logs the data.
processGpsData(): Parses incoming payloads, extracts GPS data, and handles the hex decoding.
hex_dump(): Converts the raw data into a readable hexadecimal format for easier debugging.
GetCrc16(): Calculates a CRC16 checksum, used for GPS data integrity verification.
Example Connection Log
Upon receiving data from a GT06 device, the server logs:

Connection Status: Logs each new connection and client disconnection.
Raw Payload: Outputs the raw hexadecimal payload for debugging.
GPS Coordinates: Converts hex GPS data to decimal latitude and longitude, along with additional tracking details like speed and timestamp.
Usage
This server is ideal for tracking applications where GT06 devices send periodic GPS data. The server can be extended to store data in a database or to trigger notifications based on the GPS coordinates.

License
This project is open-source and licensed under the MIT License.

This README gives a clear setup guide, feature summary, and code overview for the GT06 GPS TCP Server project. Let me know if you'd like to further customize it!
