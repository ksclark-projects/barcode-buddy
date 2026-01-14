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

    <title>Barcode Buddy Screen</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body,
        html {
            position: relative;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background: #000;
            color: #fff;
        }

        .main-container {
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .header {
            width: 100%;
            background: #000;
            padding: 20px 24px;
            border-bottom: 1px solid #333;
            flex: 0 0 auto;
        }

        .content {
            background: #000;
            width: 100%;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 24px 120px;
            transition: background-color 0.4s ease;
        }

        .hdr-left {
            font-size: 24px;
            font-weight: 600;
            letter-spacing: -0.5px;
        }

        .hdr-right {
            float: right;
            font-size: 14px;
            color: #999;
            font-weight: 400;
        }

        #soundbuttondiv, #backbuttondiv, #selectbuttondiv {
            position: fixed;
            bottom: 24px;
            z-index: 10;
        }

        #soundbuttondiv { right: 24px; }
        #backbuttondiv { left: 24px; }
        #selectbuttondiv {
            left: 50%;
            transform: translateX(-50%);
        }

        .h1 {
            font-size: 32px;
            font-weight: 700;
            letter-spacing: -1px;
            margin: 0;
        }

        .h2 {
            font-size: 48px;
            font-weight: 700;
            letter-spacing: 0px;
            margin: 0 0 16px;
            text-align: center;
            line-height: 1.2;
        }

        .h3 {
            font-size: 24px;
            font-weight: 500;
            margin: 16px 0;
            text-align: center;
            color: #999;
        }

        .h4 {
            font-size: 18px;
            font-weight: 600;
            margin: 24px 0 12px;
            color: #999;
        }

        .h5 {
            font-size: 14px;
            font-weight: 400;
            margin: 8px 0;
            color: #666;
            line-height: 1.6;
        }

        .bottom-button {
            background-color: #fff;
            color: #000;
            padding: 16px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(255,255,255,0.15);
            width: 64px;
            height: 64px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .bottom-button:hover {
            background-color: #f0f0f0;
            transform: scale(1.05);
        }

        .bottom-button:active {
            transform: scale(0.95);
        }

        .bottom-img {
            height: 28px;
            width: 28px;
            filter: invert(1);
        }

        @media only screen and (orientation: portrait) {
            .bottom-button {
                width: 72px;
                height: 72px;
            }
            .bottom-img {
                height: 32px;
                width: 32px;
            }
            .h2 {
                font-size: 36px;
            }
        }

        .overlay {
            height: 0;
            width: 100%;
            position: fixed;
            z-index: 100;
            bottom: 0;
            left: 0;
            background-color: rgba(0, 0, 0, 0.98);
            overflow: hidden;
            transition: height 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .overlay-content {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 80px auto 0;
            padding: 0 24px;
        }

        .overlay a {
            padding: 20px 24px;
            text-decoration: none;
            font-size: 20px;
            font-weight: 500;
            color: #fff;
            display: block;
            transition: all 0.2s ease;
            border-radius: 12px;
            margin-bottom: 8px;
            background: rgba(255,255,255,0.05);
        }

        .overlay a:hover,
        .overlay a:active {
            background: rgba(255,255,255,0.1);
            transform: translateX(4px);
        }

        .overlay .closebtn {
            position: absolute;
            top: 24px;
            right: 24px;
            font-size: 48px;
            background: none;
            margin: 0;
            padding: 8px 16px;
            color: #fff;
            border-radius: 50%;
        }

        .overlay .closebtn:hover {
            background: rgba(255,255,255,0.1);
        }

        #log {
            width: 100%;
            max-width: 600px;
        }

        #previous-events {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #333;
        }

        .create-form {
            max-width: 500px;
            margin: 32px auto 0;
            text-align: left;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #999;
            margin-bottom: 8px;
        }

        .form-input {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            background: rgba(255,255,255,0.05);
            border: 1px solid #333;
            border-radius: 12px;
            color: #fff;
            font-family: inherit;
            transition: all 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            background: rgba(255,255,255,0.08);
            border-color: #666;
        }

        .form-button {
            width: 100%;
            padding: 18px;
            font-size: 16px;
            font-weight: 600;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 16px;
        }

        .form-button:hover {
            background: #f0f0f0;
            transform: translateY(-2px);
        }

        .form-button:active {
            transform: translateY(0);
        }

        .form-button.secondary {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }

        .form-button.secondary:hover {
            background: rgba(255,255,255,0.15);
        }

        .form-input.error {
            border-color: #ff4444;
            color: #ff4444;
        }

        .form-input.error:focus {
            border-color: #ff6666;
        }

        /* Processing Animation */
        .processing-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            flex-direction: column;
        }

        .processing-overlay.active {
            display: flex;
        }

        .spinner {
            width: 64px;
            height: 64px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .processing-text {
            margin-top: 24px;
            font-size: 18px;
            font-weight: 500;
            color: #fff;
        }

        .unknown-badge-new-product {
            display: inline-block;
            background: hsla(156, 100%, 50%, 0.20);
            color: #ffffffff;
            padding: 16px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }

        .unknown-badge {
            display: inline-block;
            background: hsla(219, 100%, 70%, 0.20);
            color: #ffffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 24px;
        }

        @media screen and (max-height: 450px) {
            .overlay a {
                font-size: 16px;
                padding: 12px 24px;
            }
            .overlay .closebtn {
                font-size: 36px;
                top: 16px;
                right: 16px;
            }
        }

        /* Amazon Lookup Modal */
        .amazon-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 24px;
        }

        .amazon-modal.active {
            display: flex;
        }

        .amazon-modal-content {
            background: #1a1a1a;
            border-radius: 16px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.5);
        }

        .amazon-modal-header {
            padding: 24px;
            border-bottom: 1px solid #333;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .amazon-modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .amazon-modal-close {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #fff;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
        }

        .amazon-modal-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .amazon-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        .amazon-product {
            display: flex;
            gap: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            margin-bottom: 16px;
            transition: all 0.2s ease;
        }

        .amazon-product:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .amazon-select-button {
            padding: 12px 24px;
            background: #fff;
            color: #000;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
            white-space: nowrap;
            align-self: center;
        }

        .amazon-select-button:hover {
            background: #f0f0f0;
            transform: scale(1.05);
        }

        .amazon-select-button:active {
            transform: scale(0.95);
        }

        .amazon-product-image {
            width: 80px;
            height: 80px;
            object-fit: contain;
            border-radius: 8px;
            background: #fff;
            padding: 8px;
            flex-shrink: 0;
        }

        .amazon-product-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .amazon-product-title {
            font-size: 16px;
            font-weight: 600;
            color: #fff;
            margin-bottom: 8px;
            line-height: 1.4;
        }

        .amazon-product-price {
            font-size: 18px;
            font-weight: 700;
            color: #4CAF50;
            margin-bottom: 4px;
        }

        .amazon-product-rating {
            font-size: 14px;
            color: #999;
        }

        .amazon-loading {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        .amazon-error {
            text-align: center;
            padding: 40px;
            color: #ff4444;
        }

        .amazon-no-results {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 16px;
        }
    </style>

</head>
<body>
<script src="./incl/js/nosleep.min.js"></script>
<script src="./incl/js/he.js"></script>

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

<script>
    // API Base URL - change this to match your API server
    const baseUrl = 'http://localhost:5002';

    function openNav() {
        document.getElementById("myNav").style.height = "100%";
    }

    function closeNav() {
        document.getElementById("myNav").style.height = "0%";
    }

    function sendBarcode(barcode) {
        var xhttp = new XMLHttpRequest();
        xhttp.open("GET", "./api/action/scan?add=" + barcode, true);
        xhttp.send();
        closeNav();
    }

    function sendQuantity() {
        var q = prompt('Enter quantity', '1');
        sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_Q"] ?>' + q);
    }

    function buildProductFormHtml(barcode, locationOptions, storeOptions, quantityUnitOptions, productName='') {
        var badge = productName 
            ? '<div id="title-badge" class="unknown-badge-new-product"><i class="fas fa-tag"></i> New Product</div>'
            : '<div id="title-badge" class="unknown-badge"><i class="fas fa-exclamation-triangle"></i> Unknown Barcode</div>';
        
        return badge +
            '<div style="font-size: 32px; font-weight: 700; letter-spacing: -1px; margin-bottom: 24px; word-break: break-all;">' + he.encode(productName) + ' (' + he.encode(barcode) + ')</div>' +
            '<div class="create-form">' +
            '  <div class="form-group">' +
            '    <label class="form-label" for="product-name">Name</label>' +
            '    <input type="text" id="product-name" class="form-input" placeholder="Enter product name" value="' + he.encode(productName) + '" autofocus>' +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label class="form-label" for="product-asin">ASIN</label>' +
            '    <input type="text" id="product-asin" class="form-input" placeholder="Amazon ASIN" value="">' +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label class="form-label" for="default-location">Default Location</label>' +
            '    ' + locationOptions +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label class="form-label" for="default-store">Default Store</label>' +
            '    ' + storeOptions +
            '  </div>' +
            '  <div class="form-group">' +
            '    <label class="form-label" for="quantity-unit-stock">Quantity Unit Stock</label>' +
            '    ' + quantityUnitOptions.stock +
            '  </div>' +
            '  <button class="form-button" onclick="submitProduct(\'' + barcode + '\', true)">' +
            '    Lookups & Process Product' +
            '  </button>' +
            '  <button class="form-button" onclick="submitProduct(\'' + barcode + '\')">' +
            '    Create Product' +
            '  </button>' +
            '  <button class="form-button" onclick="openAmazonLookup(\'' + barcode + '\')">' +
            '    Amazon Lookup' +
            '  </button>' +
            '  <button class="form-button secondary" onclick="cancelCreate()">' +
            '    Cancel' +
            '  </button>' +
            '</div>';
    }

    function showUnknownBarcodeForm(res, productName, barcode) {
        document.getElementById('content').style.backgroundColor = '#000';
        // document.getElementById('beep_nosuccess').play();
        document.getElementById('log').style.display = 'none';
        
        // if the productName is available use it to prefill the product name field
        var prefillName = productName ? he.decode(productName) : '';

        // Fetch locations, stores, and quantity units from API
        Promise.all([
            fetch(baseUrl + '/api/v1/locations').then(r => r.json()),
            fetch(baseUrl + '/api/v1/stores').then(r => r.json()),
            fetch(baseUrl + '/api/v1/quantity-units').then(r => r.json())
        ])
            .then(([locationsResult, storesResult, quantityUnitsResult]) => {
                var locations = locationsResult.data || [];
                var locationOptions = '<select id="default-location" class="form-input"><option value="">Select location</option>';
                locations.forEach(function(loc) {
                    locationOptions += '<option value="' + loc.name + '">' + loc.name + '</option>';
                });
                locationOptions += '</select>';
                
                var stores = storesResult.data || [];
                var storeOptions = '<select id="default-store" class="form-input"><option value="">Select store</option>';
                stores.forEach(function(store) {
                    storeOptions += '<option value="' + store.name + '">' + store.name + '</option>';
                });
                storeOptions += '</select>';
                
                var quantityUnits = quantityUnitsResult.data || [];
                var stockUnitOptions = '<select id="quantity-unit-stock" class="form-input"><option value="">Select unit</option>';
                var purchaseUnitOptions = '<select id="default-quantity-unit" class="form-input"><option value="">Select unit</option>';
                quantityUnits.forEach(function(unit) {
                    stockUnitOptions += '<option value="' + unit.name + '">' + unit.name + '</option>';
                    purchaseUnitOptions += '<option value="' + unit.name + '">' + unit.name + '</option>';
                });
                stockUnitOptions += '</select>';
                purchaseUnitOptions += '</select>';
                
                var quantityUnitOptions = {
                    stock: stockUnitOptions,
                    purchase: purchaseUnitOptions
                };
                
                document.getElementById('scan-result').innerHTML = buildProductFormHtml(barcode, locationOptions, storeOptions, quantityUnitOptions, prefillName);
                
                // Set the product name value if available
                if (prefillName) {
                    document.getElementById('product-name').value = prefillName;
                }
            })
            .catch(error => {
                console.error('Failed to load form data:', error);
                // Fallback to text inputs if API fails
                var locationInput = '<input type="text" id="default-location" class="form-input" placeholder="Enter default location">';
                var storeInput = '<input type="text" id="default-store" class="form-input" placeholder="Enter default store">';
                var quantityUnitInputs = {
                    stock: '<input type="text" id="quantity-unit-stock" class="form-input" placeholder="Enter quantity unit stock">',
                    purchase: '<input type="text" id="default-quantity-unit" class="form-input" placeholder="Enter default quantity unit">'
                };
                document.getElementById('scan-result').innerHTML = buildProductFormHtml(barcode, locationInput, storeInput, quantityUnitInputs);
                
                // Set the product name value if available
                if (prefillName) {
                    document.getElementById('product-name').value = prefillName;
                }
            });
        
    }

    function submitProduct(barcode,process=false) {
        var productName = document.getElementById('product-name').value;
        var defaultLocation = document.getElementById('default-location').value;
        var defaultStore = document.getElementById('default-store').value;
        var quantityUnitStock = document.getElementById('quantity-unit-stock').value;

        // Clear all previous errors
        document.querySelectorAll('.form-input').forEach(function(el) {
            el.classList.remove('error');
        });
        
        // Validate required fields
        var hasError = false;
        
        if (!productName) {
            document.getElementById('product-name').classList.add('error');
            hasError = true;
        }
        
        if (!defaultLocation) {
            document.getElementById('default-location').classList.add('error');
            hasError = true;
        }
        
        if (!defaultStore) {
            document.getElementById('default-store').classList.add('error');
            hasError = true;
        }
        
        if (!quantityUnitStock) {
            document.getElementById('quantity-unit-stock').classList.add('error');
            hasError = true;
        }
        
        if (hasError) {
            return;
        }
        
        // Prepare data for API
        var productData = {
            barcode: barcode,
            name: productName,
            location_name: defaultLocation,
            store_name: defaultStore,
            qu_name_stock: quantityUnitStock,
            qu_name_purchase: quantityUnitStock,
            min_stock_amount: 1
        };

        console.log('Submitting product data:', productData);
        
        // Show processing animation if process=true
        if (process) {
            document.getElementById('processing-overlay').classList.add('active');
        }
        
        // Send to API
        var xhr = new XMLHttpRequest();
        
        if (process) {
        xhr.open('POST', baseUrl + '/api/v1/product/process', true);

        }else{
        xhr.open('POST', baseUrl + '/api/v1/product', true);

        }
        xhr.setRequestHeader('Content-Type', 'application/json');
        
        xhr.onload = function() {
            // Hide processing overlay
            document.getElementById('processing-overlay').classList.remove('active');
            
            if (xhr.status >= 200 && xhr.status < 300) {
                // Success
                document.getElementById('content').style.backgroundColor = '#33a532';
                document.getElementById('scan-result').textContent = 'Product created: ' + productName;
                document.getElementById('log').style.display = 'block';
                // $db->deleteBarcode(barcode);
    
                
                setTimeout(function() {
                    cancelCreate();
                }, 4000);
            } else {
                // Error
                document.getElementById('content').style.backgroundColor = '#CC0605';
                document.getElementById('scan-result').textContent = 'Error creating product: ' + xhr.statusText;
                console.error('API Error:', xhr.responseText);
            }
        };
        
        xhr.onerror = function() {
            // Hide processing overlay
            document.getElementById('processing-overlay').classList.remove('active');
            
            document.getElementById('content').style.backgroundColor = '#CC0605';
            document.getElementById('scan-result').textContent = 'Failed to connect to API';
            console.error('Network error');
        };
        
        xhr.send(JSON.stringify(productData));
        
        // ALSO send to PHP handler for server-side action
        console.log("sending to PHP handler");
        var phpXhr = new XMLHttpRequest();
        phpXhr.open('POST', 'screen.php', true);
        phpXhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        phpXhr.onload = function() {
            if (phpXhr.status === 200) {
                console.log('PHP action completed:', phpXhr.responseText);
            } else {
                console.error('PHP handler error (status ' + phpXhr.status + '):', phpXhr.responseText);
                try {
                    var errorData = JSON.parse(phpXhr.responseText);
                    console.error('Error details:', errorData);
                } catch(e) {
                    console.error('Raw response:', phpXhr.responseText);
                }
            }
        };
        phpXhr.onerror = function() {
            console.error('PHP handler network error');
        };
        phpXhr.send(
            'action=submitProduct&' +
            'barcode=' + encodeURIComponent(barcode) + '&' +
            'process=' + (process ? 'true' : 'false') + '&' +
            'product_name=' + encodeURIComponent(productName) + '&' +
            'location=' + encodeURIComponent(defaultLocation) + '&' +
            'store=' + encodeURIComponent(defaultStore)
        );
    }

    function cancelCreate() {
        document.getElementById('content').style.backgroundColor = '#000';
        document.getElementById('scan-result').textContent = 'waiting for barcode...';
        document.getElementById('log').style.display = 'block';
    }

    // Amazon Lookup Functions
    function openAmazonLookup(barcode) {
        var productName = document.getElementById('product-name').value;
        var searchQuery = productName || barcode;
        
        // Open modal
        document.getElementById('amazon-modal').classList.add('active');
        
        // Show loading state
        document.getElementById('amazon-modal-body').innerHTML = '<div class="amazon-loading"><div class="spinner"></div><p style="margin-top: 16px;">Searching Amazon for "' + he.encode(searchQuery) + '"...</p></div>';
        
        // Mock API response - simulate network delay
        setTimeout(function() {
            var mockData = {
                products: [
                    {
                        asin: "B08N5WRWNW",
                        name: "Echo Dot (4th Gen) | Smart speaker with Alexa",
                        price: "$49.99",
                        url: "https://www.amazon.com/dp/B08N5WRWNW",
                        image: "https://m.media-amazon.com/images/I/61V-RYl7ZOL._AC_SL1000_.jpg"
                    },
                    {
                        asin: "B09B8V1LZ3",
                        name: "Amazon Basics AAA Performance Alkaline Batteries (36 Count)",
                        price: "$14.99",
                        url: "https://www.amazon.com/dp/B09B8V1LZ3",
                        image: "https://m.media-amazon.com/images/I/71nDX36Y9VL._AC_SL1500_.jpg"
                    },
                    {
                        asin: "B0B7RXZM1T",
                        name: "Fire TV Stick 4K Max streaming device",
                        price: "$54.99",
                        url: "https://www.amazon.com/dp/B0B7RXZM1T",
                        image: "https://m.media-amazon.com/images/I/51TjJOTfslL._AC_SL1000_.jpg"
                    },
                    {
                        asin: "B07XJ8C8F5",
                        name: "Echo Show 8 (2nd Gen) | HD smart display with Alexa",
                        price: "$129.99",
                        url: "https://www.amazon.com/dp/B07XJ8C8F5",
                        image: "https://m.media-amazon.com/images/I/51uVqzPC6qL._AC_SL1000_.jpg"
                    },
                    {
                        asin: "B09B93ZDG4",
                        name: "Kindle Paperwhite (16 GB) - Now with a 6.8 display",
                        price: "$139.99",
                        url: "https://www.amazon.com/dp/B09B93ZDG4",
                        image: "https://m.media-amazon.com/images/I/51QCk82iGcL._AC_SL1000_.jpg"
                    }
                ]
            };
            
            displayAmazonResults(mockData, barcode);
        }, 800); // Simulate 800ms API delay
    }

    function displayAmazonResults(data, barcode) {
        var modalBody = document.getElementById('amazon-modal-body');
        
        // Check if we have products
        if (!data.products || data.products.length === 0) {
            modalBody.innerHTML = 
                '<div class="amazon-no-results">' +
                '<i class="fas fa-search" style="font-size: 48px; margin-bottom: 16px;"></i>' +
                '<p style="font-size: 18px;">No products found</p>' +
                '<p style="font-size: 14px; margin-top: 8px;">Try adjusting your search terms</p>' +
                '</div>';
            return;
        }
        
        // Build products HTML
        var html = '';
        data.products.forEach(function(product) {
            var name = product.name || 'Unknown Product';
            var price = product.price || 'Price not available';
            var asin = product.asin || '';
            var url = product.url || '';
            var image = product.image || '';
            
            html += 
                '<div class="amazon-product">' +
                '  <img class="amazon-product-image" src="' + he.encode(image) + '" alt="' + he.encode(name) + '" onerror="this.style.display=\'none\'">' +
                '  <div class="amazon-product-info">' +
                '    <div class="amazon-product-title">' + he.encode(name) + '</div>' +
                '    <div class="amazon-product-price">' + he.encode(price) + '</div>' +
                '    <div class="amazon-product-rating"><i class="fas fa-external-link-alt"></i> View on Amazon</div>' +
                '  </div>' +
                '  <button class="amazon-select-button" onclick="selectAmazonProduct(\'' + he.encode(asin) + '\', \'' + he.encode(name) + '\', \'' + he.encode(url) + '\', \'' + barcode + '\')">' +
                '    Select' +
                '  </button>' +
                '</div>';
        });
        
        modalBody.innerHTML = html;
    }

    function selectAmazonProduct(asin, name, url, barcode) {
        // Pre-fill the ASIN field
        document.getElementById('product-asin').value = he.decode(asin);
        
        // Close the modal
        closeAmazonModal();
        
        // Optional: You could also store the ASIN and URL for future reference
        console.log('Selected Amazon product:', { asin: asin, name: name, url: url, barcode: barcode });
    }

    function closeAmazonModal() {
        document.getElementById('amazon-modal').classList.remove('active');
    }

    var noSleep = new NoSleep();
    var wakeLockEnabled = false;
    var isFirstStart = true;


    function goHome() {
        if (document.referrer === "") {
            window.location.href = './index.php'
        } else {
            window.close();
        }
    }

    function toggleSound() {
        if (!wakeLockEnabled) {
            noSleep.enable();
            wakeLockEnabled = true;
            document.getElementById('beep_success').muted = false;
            document.getElementById('beep_nosuccess').muted = false;
            <?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
            echo " document.documentElement.requestFullscreen();";
        }?>
            document.getElementById("muteimg").src = "incl/img/unmute.svg";
        } else {
            noSleep.disable();
            <?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
            echo " document.exitFullscreen();";
        } ?>
            wakeLockEnabled = false;
            document.getElementById('beep_success').muted = true;
            document.getElementById('beep_nosuccess').muted = true;
            document.getElementById("muteimg").src = "incl/img/mute.svg";
        }
    }


    function syncCache() {
        var xhttp = new XMLHttpRequest();
        xhttp.open("GET", "./cron.php", true);
        xhttp.send();
    }

    if (typeof (EventSource) !== "undefined") {
        syncCache()
        var source = new EventSource("incl/sse/sse_data.php");

        var currentScanId = 0;
        var connectFailCounter = 0;
        var lastFail = 0;

        source.addEventListener("error", function (event) {
            switch (event.target.readyState) {
                case EventSource.CONNECTING:
                    document.getElementById('grocy-sse').textContent = 'Reconnecting...';
                    if (Date.now() - lastFail < 10000) {
                    	connectFailCounter++;
                    }
                    if (connectFailCounter === 10) {
                        source.close();
                        document.getElementById('grocy-sse').textContent = 'Unavailable';
                        document.getElementById('scan-result').textContent = 'Unable to connect to Barcode Buddy';
                    }
                    lastFail = Date.now()
                    break;
                case EventSource.CLOSED:
                    console.log('Connection failed (CLOSED)');
                    break;
            }
        }, false);

        async function resetScan(scanId) {
            await sleep(3000);
            if (currentScanId == scanId) {
                document.getElementById('content').style.backgroundColor = '#000000ff';
                document.getElementById('scan-result').textContent = 'waiting for barcode...';
                document.getElementById('event').textContent = '';
            }
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        function resultScan(color, message, text, sound) {
            document.getElementById('content').style.backgroundColor = color;
            document.getElementById('event').textContent = message;
            document.getElementById('scan-result').textContent = text;
            // document.getElementById(sound).play();
            document.getElementById('log-entries').innerText = '\r\n' + text + document.getElementById('log-entries').innerText;
            currentScanId++;
            resetScan(currentScanId);
        }

        source.onopen = function () {
            document.getElementById('grocy-sse').textContent = 'Connected';
            if (isFirstStart) {
                isFirstStart = false;
                document.getElementById('scan-result').textContent = 'waiting for barcode...';
                var http = new XMLHttpRequest();
                http.open("GET", "incl/sse/sse_data.php?getState");
                http.send();
            }
        };

        source.onmessage = function (event) {
            var resultJson = JSON.parse(event.data);
            
            var resultCode = resultJson.data.substring(0, 1);
            var resultText = resultJson.data.substring(1, resultJson.data.length);
            // // if the eventtype is 8 the barcode is between ( and ) at the end of the string
            // var resultText = resultJson.data.substring(1, resultJson.data.length - 1);

            // var barcodeMatch = resultText.match(/\(([^)]+)\)$/);
            // var barcode = barcodeMatch ? barcodeMatch[1] : null;
            // // Remove the barcode and parentheses from resultText
            // var productName = resultText.replace(/\s*\([^)]+\)$/, '');
            // Extract eventType from resultJson, it is the last character if it is an integer
            var eventType = parseInt(resultJson.data.slice(-1));

            console.log("Result Code:", resultCode);
            switch (resultCode) {
                case '0':
                    resultText = resultJson.data.substring(1, resultJson.data.length - 1);
                    resultScan("#33a532", "", he.decode(resultText), "beep_success");
                    break;
                case '1':
                    if (eventType == 20) {
                        // Unknown product already scanned , increasing inventory

                    }
                    if (eventType == 8) {
                        // EVENT_TYPE_ADD_NEW_BARCODE 
                        // Prefill the title
                        resultText = resultJson.data.substring(1, resultJson.data.length - 1);

                        var barcodeMatch = resultText.match(/\(([^)]+)\)$/);
                        var barcode = barcodeMatch ? barcodeMatch[1] : null;
                        // Remove the barcode and parentheses from resultText
                        var productName = resultText.replace(/\s*\([^)]+\)$/, '');
                        // resultText = resultJson.data.substring(1, resultJson.data.length - 1);
                        console.log("resultText for new barcode:", resultText);
                        console.log("the product name is:", productName);
                        console.log("barcode for new product form:", barcode);
                        showUnknownBarcodeForm(resultText, productName, barcode);
                    }else{
                        resultScan("#a2ff9b", "Barcode Looked Up", he.decode(resultText), "beep_success");
                    }
                    break;
                case '2':
                    showUnknownBarcodeForm(resultText);
                    break;
                case '4':
                    document.getElementById('mode').textContent = resultText;
                    break;
                case 'E':
                    document.getElementById('content').style.backgroundColor = '#CC0605';
                    document.getElementById('grocy-sse').textContent = 'disconnected';
                    document.getElementById('scan-result').style.display = 'none'
                    document.getElementById('previous-events').style.display = 'none'
                    document.getElementById('event').setAttribute('style', 'white-space: pre;');
                    document.getElementById('event').textContent = "\r\n\r\n" + resultText;
                    break;
            }
        };
    } else {
        document.getElementById('content').style.backgroundColor = '#f9868b';
        document.getElementById('grocy-sse').textContent = 'Disconnected';
        document.getElementById('event').textContent = 'Sorry, your browser does not support server-sent events';
    }
</script>

</body>
</html>
