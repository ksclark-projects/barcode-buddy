<?php
/**
 * Barcode Buddy for Grocy
 *
 * PHP version 7
 *
 * LICENSE: This source file is subject to version 3.0 of the GNU General
 * Public License v3.0 that is attached to this project.
 *
 *  A screen to supervise barcode scanning.
 *
 * @author     Marc Ole Bulling
 * @copyright  2019 Marc Ole Bulling
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU GPL v3.0
 * @since      File available since Release 1.0
 */

require_once __DIR__ . "/incl/configProcessing.inc.php";
require_once __DIR__ . "/incl/config.inc.php";
require_once __DIR__ . "/incl/db.inc.php";
require_once __DIR__ . "/incl/redis.inc.php";

$CONFIG->checkIfAuthenticated(true);

// Initialize database connection
$db = DatabaseConnection::getInstance();

// Handle button press from JavaScript
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submitProduct') {
    try {
        $barcode = $_POST['barcode'] ?? '';
        $process = ($_POST['process'] ?? 'false') === 'true';
        $productName = $_POST['product_name'] ?? '';
        $location = $_POST['location'] ?? '';
        $store = $_POST['store'] ?? '';
        
        // Perform your PHP action here
        error_log("Product submitted: $productName (barcode: $barcode, process: " . ($process ? 'true' : 'false') . ")");
        
        // Example: Delete barcode after processing
        $db->deleteBarcodeByCode($barcode);
        
        // Example: Log to database
        // $db->logProductSubmission($barcode, $productName, $process);
        
        // Example: Write to file
        // file_put_contents(__DIR__ . '/logs/product_submissions.log', 
        //     date('Y-m-d H:i:s') . " - $productName ($barcode) - Process: " . ($process ? 'Yes' : 'No') . "\n", 
        //     FILE_APPEND);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'PHP action completed']);
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        error_log("Error in submitProduct: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    }
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>


    <link rel="apple-touch-icon" sizes="57x57" href="./incl/img/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="./incl/img/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="./incl/img/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="./incl/img/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="./incl/img/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="./incl/img/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="./incl/img/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="./incl/img/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="./incl/img/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="./incl/img/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="./incl/img/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="./incl/img/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="./incl/img/favicon/favicon-16x16.png">
    <meta name="msapplication-TileImage" content="./incl/img/favicon/ms-icon-144x144.png">
    <meta name="msapplication-navbutton-color" content="#ccc">
    <meta name="msapplication-TileColor" content="#ccc">
    <meta name="apple-mobile-web-app-status-bar-style" content="#ccc">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#ccc">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="./static/screen.css">

    <title>Barcode Buddy Screen</title>

    <script>
        window.__CONFIG__ = {
        GROCERY_API_URL: <?= json_encode($CONFIG->GROCERY_API_URL) ?>
        };
    </script>    

</head>
<body>
<script src="./incl/js/nosleep.min.js"></script>
<script src="./incl/js/he.js"></script>
<script src="./static/js/screen.js"></script>

<div class="main-container">
    <div id="processing-overlay" class="processing-overlay">
        <div class="spinner"></div>
        <div class="processing-text">Processing product, please wait...</div>
    </div>
    <div id="header" class="header">
    <span class="hdr-right h4">
      Status: <span id="grocy-sse">Connecting...</span><br>
    </span>
        <span id="mode" class="h1 hdr-left"></span>
    </div>
    <div id="content" class="content">
        <p id="scan-result" class="h2">If you see this for more than a couple of seconds, please check if the websocket
            server has been started and is available</p>
        <div id="log">
            <p id="event" class="h3"></p><br>
            <div id="previous-events">
                <p class="h4 p-t10"> previous scans: </p>
                <span id="log-entries" class="h5"></span>
            </div>
        </div>
    </div>
</div>

<!-- Amazon Lookup Modal -->
<div id="amazon-modal" class="amazon-modal">
    <div class="amazon-modal-content">
        <div class="amazon-modal-header">
            <h2 class="amazon-modal-title">Amazon Product Search</h2>
            <button class="amazon-modal-close" onclick="closeAmazonModal()">&times;</button>
        </div>
        <div id="amazon-modal-body" class="amazon-modal-body">
            <div class="amazon-loading">Loading products...</div>
        </div>
    </div>
</div>

<!-- HTML Templates for JavaScript -->
<template id="amazon-loading-template">
    <div class="amazon-loading">
        <div class="spinner"></div>
        <p style="margin-top: 16px;">Searching Amazon for barcode "<span class="barcode-text"></span>"...</p>
    </div>
</template>

<template id="amazon-error-template">
    <div class="amazon-error">
        <i class="fas fa-exclamation-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
        <p style="font-size: 18px;">Error loading products</p>
        <p style="font-size: 14px; margin-top: 8px;" class="error-message"></p>
    </div>
</template>

<template id="amazon-no-results-template">
    <div class="amazon-no-results">
        <i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px;"></i>
        <p style="font-size: 18px;">No products found</p>
        <p style="font-size: 14px; margin-top: 8px;">Try adjusting your search terms</p>
    </div>
</template>

<template id="amazon-product-template">
    <div class="amazon-product">
        <img class="amazon-product-image" src="" alt="">
        <div class="amazon-product-info">
            <div class="amazon-product-title"></div>
            <div class="amazon-product-price"></div>
            <div class="amazon-product-rating"><i class="fas fa-external-link-alt"></i> View on Amazon</div>
        </div>
        <button class="amazon-select-button">Select</button>
    </div>
</template>

<template id="select-template">
    <select class="form-input"></select>
</template>

<template id="option-template">
    <option></option>
</template>

<template id="product-form-template">
    <div>
        <div id="title-badge" class="unknown-badge">
            <i class="fas fa-exclamation-triangle"></i> <span class="badge-text">Unknown Barcode</span>
        </div>
        <div class="product-title" style="font-size: 32px; font-weight: 700; letter-spacing: -1px; margin-bottom: 24px; word-break: break-all;">
            <span class="product-name"></span> (<span class="product-barcode"></span>)
        </div>
        <div class="create-form">
            <div class="form-group">
                <label class="form-label" for="product-name">Name</label>
                <input type="text" id="product-name" class="form-input" placeholder="Enter product name" autofocus>
            </div>
            <div class="form-group">
                <label class="form-label" for="product-asin">ASIN</label>
                <div class="input-group">
                    <input type="text" id="product-asin" class="form-input" placeholder="Amazon ASIN" value="">
                    <button class="icon-button amazon-lookup-btn"><i class="fab fa-amazon"></i></button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" for="default-location">Default Location</label>
                <div class="location-container"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="default-store">Default Store</label>
                <div class="store-container"></div>
            </div>
            <div class="form-group">
                <label class="form-label" for="quantity-unit-stock">Quantity Unit Stock</label>
                <div class="quantity-unit-container"></div>
            </div>
            <button class="form-button lookup-process-btn">Lookup & Process Product</button>
            <button class="form-button create-btn">Create Product</button>
            <button class="form-button secondary" onclick="cancelCreate()">Cancel</button>
        </div>
    </div>
</template>

<audio id="beep_success" muted="muted" src="incl/websocket/beep.ogg" type="audio/ogg" preload="auto"></audio>
<audio id="beep_nosuccess" muted="muted" src="incl/websocket/buzzer.ogg" type="audio/ogg" preload="auto"></audio>
<div id="soundbuttondiv">
    <button class="bottom-button" onclick="toggleSound()" id="soundbutton"><img class="bottom-img"
                                                                                id="muteimg"
                                                                                src="incl/img/mute.svg"
                                                                                alt="Toggle sound and wakelock">
    </button>
</div>
<div id="backbuttondiv">
    <button class="bottom-button" onclick="goHome()" id="backbutton"><img class="bottom-img" src="incl/img/back.svg"
                                                                          alt="Go back to overview">
    </button>
</div>
<div id="selectbuttondiv">
    <button class="bottom-button" onclick="openNav()" id="selectbutton"><img class="bottom-img" src="incl/img/cart.svg"
                                                                             alt="Set mode">
    </button>
</div>


<div id="myNav" class="overlay">
    <a href="javascript:void(0)" class="closebtn" onclick="closeNav()">&times;</a>
    <div class="overlay-content">
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_P"] ?>')">Purchase</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_C"] ?>')">Consume</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_O"] ?>')">Open</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_GS"] ?>')">Inventory</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_AS"] ?>')">Add to shoppinglist</a>
        <a href="#" onclick="sendQuantity()">Set quantity</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_CA"] ?>')">Consume All</a>
        <a href="#" onclick="sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_CS"] ?>')">Consume (spoiled)</a>
    </div>
</div>

<
</body>
</html>
