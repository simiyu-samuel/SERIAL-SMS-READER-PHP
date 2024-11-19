<?php

require_once('PHP-Serial/src/PhpSerial.php');

// Database configuration
$db_config = [
    'host' => 'localhost',        // Database host
    'user' => 'root',             // Database username
    'password' => '',             // Database password
    'database' => 'icorerp_2'     // Database name
];

// Function to connect to the MySQL database
function connectToDb() {
    global $db_config;

    try {
        $pdo = new PDO(
            'mysql:host=' . $db_config['host'] . ';dbname=' . $db_config['database'],
            $db_config['user'],
            $db_config['password']
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        echo 'Database connection failed: ' . $e->getMessage() . "\n";
        return null;
    }
}

// Function to parse M-Pesa SMS message
function parseMpesaMessage($message) {
    $pattern = '/([A-Z0-9]{10}) Confirmed\.on (\d{1,2}\/\d{1,2}\/\d{2}) at (\d{1,2}:\d{2} ?(?:AM|PM))Ksh([\d,]+\.\d{2}) received from (\d{12}) (.+?)\./';

    if (preg_match($pattern, $message, $matches)) {
        return [
            'transaction_id' => $matches[1],
            'transaction_date' => $matches[2],
            'transaction_time' => $matches[3],
            'amount' => str_replace(',', '', $matches[4]),
            'sender_phone' => $matches[5],
            'sender_name' => trim($matches[6])
        ];
    }

    return null;
}

// Function to save parsed M-Pesa transaction to the database
function saveMpesaTransaction($data) {
    $pdo = connectToDb();

    if ($pdo) {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO mpesa_transactions (transaction_id, amount, sender_name, sender_phone, transaction_date, transaction_time)
                VALUES (:transaction_id, :amount, :sender_name, :sender_phone, :transaction_date, :transaction_time)"
            );

            $stmt->execute([
                ':transaction_id' => $data['transaction_id'],
                ':amount' => $data['amount'],
                ':sender_name' => $data['sender_name'],
                ':sender_phone' => $data['sender_phone'],
                ':transaction_date' => $data['transaction_date'],
                ':transaction_time' => $data['transaction_time']
            ]);

            echo "Transaction saved successfully.\n";
        } catch (PDOException $e) {
            echo 'Failed to save transaction: ' . $e->getMessage() . "\n";
        }
    }
}

// Function to delete an SMS from the GSM modem
function deleteSms($serial, $index) {
    $serial->sendMessage("AT+CMGD=$index,0\r");
    sleep(1);
    $response = $serial->readAll();

    if (strpos($response, "OK") !== false) {
        echo "SMS with index $index deleted.\n";
    } else {
        echo "Failed to delete SMS with index $index.\n";
    }
}

// Function to read SMS from the GSM modem
function readSms($serial) {
    $serial->sendMessage("AT+CMGF=1\r");  // Set SMS text mode
    sleep(1);
    $serial->sendMessage("AT+CMGL=\"ALL\"\r");  // List all SMS
    sleep(1);

    $response = $serial->readAll();  // Read the entire response
    echo "Response from modem: " . $response . "\n";

    if (preg_match_all('/\+CMGL: (\d+),\"(.*)\"/', $response, $matches)) {
        foreach ($matches[1] as $index => $msgIndex) {
            $message = $matches[2][$index];
            echo "Processing SMS: $message\n";

            $parsedData = parseMpesaMessage($message);
            if ($parsedData) {
                saveMpesaTransaction($parsedData);
                deleteSms($serial, $msgIndex);
            } else {
                echo "Failed to parse M-Pesa message.\n";
            }
        }
    } else {
        echo "No SMS messages found.\n";
    }
}

// Main function to initialize the GSM modem and process SMS
function main() {
    $serial = new PhpSerial();

    // Set serial port (update this for your system)
    $serial->deviceSet("COM19");  // Change to match your system's serial port
    $serial->confBaudRate(9600);
    $serial->confParity("none");
    $serial->confCharacterLength(8);
    $serial->confStopBits(1);

    $serial->deviceOpen();

    while (true) {
        readSms($serial);
        sleep(10);  // Check for SMS every 10 seconds
    }

    $serial->deviceClose();
}

// Run the script
main();
?>
