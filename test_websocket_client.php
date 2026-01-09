#!/usr/bin/env php
<?php
/**
 * Test WebSocket Client for Barcode Buddy
 * 
 * This script sends test messages to the websocket server
 * to test the screen interface.
 * 
 * Usage: php test_websocket_client.php
 */

require_once __DIR__ . '/incl/configProcessing.inc.php';

// Get port from config
$port = $CONFIG->PORT_WEBSOCKET_SERVER ?? 47631;
$host = '127.0.0.1';

// Result codes from the screen.php JavaScript
// case '0': Success (green)
// case '1': Barcode Looked Up (light green)
// case '2': Unknown Barcode (yellow)
// case '4': Mode change
// case 'E': Error (red)

function sendTestMessage($type, $message) {
    global $host, $port;
    
    $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
    if (!$socket) {
        echo "Failed to create socket\n";
        return false;
    }
    
    $result = @socket_connect($socket, $host, $port);
    if (!$result) {
        echo "Failed to connect to websocket server at $host:$port\n";
        echo "Make sure the websocket server is running!\n";
        socket_close($socket);
        return false;
    }
    
    // Send message in format: command + data
    // Command '2' is echo/message
    $data = '2' . $type . $message;
    socket_write($socket, $data, strlen($data));
    socket_close($socket);
    
    return true;
}

function showMenu() {
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════╗\n";
    echo "║       Barcode Buddy WebSocket Test Client            ║\n";
    echo "╚═══════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Select a test message to send:\n";
    echo "\n";
    echo "  1) ✓ Success - Product scanned (Green)\n";
    echo "  2) 🔍 Barcode Looked Up (Light Green)\n";
    echo "  3) ❓ Unknown Barcode (Yellow)\n";
    echo "  4) 📝 Change Mode to 'Purchase'\n";
    echo "  5) 📝 Change Mode to 'Consume'\n";
    echo "  6) ⚠️  Error Message (Red)\n";
    echo "  7) 🧪 Custom message\n";
    echo "  8) 🔄 Send multiple test messages\n";
    echo "  0) Exit\n";
    echo "\n";
    echo "Choice: ";
}

function sendMultipleTests() {
    echo "\nSending test sequence...\n";
    
    // Mode change
    echo "1. Setting mode to Purchase...\n";
    sendTestMessage('4', 'Purchase');
    sleep(1);
    
    // Success
    echo "2. Scanning product 'Coca Cola 500ml'...\n";
    sendTestMessage('0', 'Coca Cola 500ml');
    sleep(2);
    
    // Barcode looked up
    echo "3. Looking up barcode...\n";
    sendTestMessage('1', 'Product: Milk 1L');
    sleep(2);
    
    // Unknown barcode
    echo "4. Unknown barcode...\n";
    sendTestMessage('2', '1234567890123');
    sleep(2);
    
    // Mode change
    echo "5. Setting mode to Consume...\n";
    sendTestMessage('4', 'Consume');
    sleep(1);
    
    // Success
    echo "6. Consuming 'Orange Juice'...\n";
    sendTestMessage('0', 'Orange Juice');
    
    echo "\nSequence complete!\n";
}

// Main loop
echo "Connecting to websocket server at $host:$port\n";

while (true) {
    showMenu();
    $choice = trim(fgets(STDIN));
    
    switch ($choice) {
        case '1':
            echo "\nSending success message...\n";
            if (sendTestMessage('0', 'Coca Cola 500ml')) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '2':
            echo "\nSending barcode lookup message...\n";
            if (sendTestMessage('1', 'Product: Organic Milk 1L')) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '3':
            echo "\nSending unknown barcode message...\n";
            if (sendTestMessage('2', '1234567890123')) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '4':
            echo "\nChanging mode to Purchase...\n";
            if (sendTestMessage('4', 'Purchase')) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '5':
            echo "\nChanging mode to Consume...\n";
            if (sendTestMessage('4', 'Consume')) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '6':
            echo "\nSending error message...\n";
            if (sendTestMessage('E', "Connection Error\n\nUnable to connect to Grocy server.\nPlease check your configuration.")) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '7':
            echo "\nEnter result code (0=success, 1=lookup, 2=unknown, 4=mode, E=error): ";
            $code = trim(fgets(STDIN));
            echo "Enter message text: ";
            $message = trim(fgets(STDIN));
            if (sendTestMessage($code, $message)) {
                echo "✓ Sent successfully!\n";
            }
            break;
            
        case '8':
            sendMultipleTests();
            break;
            
        case '0':
            echo "\nGoodbye!\n";
            exit(0);
            
        default:
            echo "\nInvalid choice. Please try again.\n";
    }
    
    sleep(1);
}
