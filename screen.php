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
require_once __DIR__ . "/incl/redis.inc.php";

$CONFIG->checkIfAuthenticated(true);


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
            letter-spacing: -2px;
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
    </style>

</head>
<body>
<script src="./incl/js/nosleep.min.js"></script>
<script src="./incl/js/he.js"></script>

<div class="main-container">
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
            document.getElementById(sound).play();
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
            var resultText = resultJson.data.substring(1);
            switch (resultCode) {
                case '0':
                    resultScan("#33a532", "", he.decode(resultText), "beep_success");
                    break;
                case '1':
                    resultScan("#a2ff9b", "Barcode Looked Up", he.decode(resultText), "beep_success");
                    break;
                case '2':
                    resultScan("#eaff8a", "Unknown Barcode", resultText, "beep_nosuccess");
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
