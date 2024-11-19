# SERIAL-SMS-READER-PHP

This project automates the processing of M-Pesa SMS messages received on a GSM modem. It parses the messages, extracts transaction details, and saves them to a MySQL database. PHP version of the earlier serial-sms-reader I creadted using python.

## Features

- Parses M-Pesa confirmation SMS to extract transaction details.
- Saves transaction details (ID, amount, sender name, phone, date, and time) to a database.
- Deletes processed SMS from the GSM modem.

## Prerequisites

1. A GSM modem connected to your computer.
2. PHP installed on your system.
3. MySQL database set up with a table for storing transactions.
4. The `PHP-Serial` library installed.

## Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/yourusername/mpesa-sms-processor.git
   ```
