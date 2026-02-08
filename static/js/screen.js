// API Base URL - change this to match your API server
const baseUrl = "http://192.168.0.234:5002";

// API Endpoints
const API_ENDPOINTS = {
  LOCATIONS: "/api/v1/locations",
  STORES: "/api/v1/stores",
  QUANTITY_UNITS: "/api/v1/quantity-units",
  PRODUCT_CREATE: "/api/v1/product",
  PRODUCT_PROCESS: "/api/v1/product/process",
  AMAZON_LOOKUP: "/api/v1/product/lookup/amazon",
  BARCODE_SCAN: "./api/action/scan",
  CRON: "./cron.php",
  SCREEN: "screen.php",
  SSE_DATA: "incl/sse/sse_data.php"
};

function openNav() {
  document.getElementById("myNav").style.height = "100%";
}

function closeNav() {
  document.getElementById("myNav").style.height = "0%";
}

function sendBarcode(barcode) {
  var xhttp = new XMLHttpRequest();
  xhttp.open("GET", API_ENDPOINTS.BARCODE_SCAN + "?add=" + barcode, true);
  xhttp.send();
  closeNav();
}

function sendQuantity() {
  var q = prompt("Enter quantity", "1");
  sendBarcode('<?php echo BBConfig::getInstance()["BARCODE_Q"] ?>' + q);
}

function buildProductFormHtml(
  barcode,
  locationOptions,
  storeOptions,
  quantityUnitOptions,
  productName = ""
) {
  var template = document.getElementById("product-form-template");
  var clone = template.content.cloneNode(true);

  // Set badge type and text
  var badge = clone.getElementById("title-badge");
  if (productName) {
    badge.classList.remove("unknown-badge");
    badge.classList.add("unknown-badge-new-product");
    badge.querySelector(".badge-text").innerHTML =
      '<i class="fas fa-tag"></i> New Product';
  }

  // Set product name and barcode
  clone.querySelector(".product-name").textContent = productName;
  clone.querySelector(".product-barcode").textContent = barcode;

  // Set product name input value
  clone.getElementById("product-name").value = productName;

  // Insert location, store, and quantity unit elements
  clone.querySelector(".location-container").appendChild(locationOptions);
  clone.querySelector(".store-container").appendChild(storeOptions);
  clone
    .querySelector(".quantity-unit-container")
    .appendChild(quantityUnitOptions.stock);

  // Set up Amazon lookup button
  clone.querySelector(".amazon-lookup-btn").onclick = function () {
    openAmazonLookup(barcode);
  };

  // Set up form buttons
  clone.querySelector(".lookup-process-btn").onclick = function () {
    submitProduct(barcode, true);
  };

  clone.querySelector(".create-btn").onclick = function () {
    submitProduct(barcode);
  };

  return clone;
}

function buildSelectFromData(id, placeholderText, dataArray, nameKey = "name") {
  var selectTemplate = document.getElementById("select-template");
  var optionTemplate = document.getElementById("option-template");

  var selectClone = selectTemplate.content.cloneNode(true);
  var selectElement = selectClone.querySelector("select");
  selectElement.id = id;

  // Add placeholder option
  var placeholderOption = optionTemplate.content.cloneNode(true);
  var placeholderOptionElement = placeholderOption.querySelector("option");
  placeholderOptionElement.value = "";
  placeholderOptionElement.textContent = placeholderText;
  selectElement.appendChild(placeholderOption);

  // Add data options
  dataArray.forEach(function (item) {
    var optionClone = optionTemplate.content.cloneNode(true);
    var optionElement = optionClone.querySelector("option");
    optionElement.value = item[nameKey];
    optionElement.textContent = item[nameKey];
    selectElement.appendChild(optionClone);
  });

  return selectClone;
}

function createInput(id, placeholder, type = "text") {
  var input = document.createElement("input");
  input.type = type;
  input.id = id;
  input.className = "form-input";
  input.placeholder = placeholder;
  return input;
}

function showUnknownBarcodeForm(res, productName, barcode) {
  document.getElementById("content").style.backgroundColor = "#000";
  // document.getElementById('beep_nosuccess').play();
  document.getElementById("log").style.display = "none";

  // if the productName is available use it to prefill the product name field
  var prefillName = productName ? he.decode(productName) : "";

  // Fetch locations, stores, and quantity units from API
  Promise.all([
    fetch(baseUrl + API_ENDPOINTS.LOCATIONS).then((r) => r.json()),
    fetch(baseUrl + API_ENDPOINTS.STORES).then((r) => r.json()),
    fetch(baseUrl + API_ENDPOINTS.QUANTITY_UNITS).then((r) => r.json())
  ])
    .then(([locationsResult, storesResult, quantityUnitsResult]) => {
      var locations = locationsResult.data || [];
      var locationSelect = buildSelectFromData(
        "default-location",
        "Select location",
        locations
      );

      var stores = storesResult.data || [];
      var storeSelect = buildSelectFromData(
        "default-store",
        "Select store",
        stores
      );

      var quantityUnits = quantityUnitsResult.data || [];
      var stockUnitSelect = buildSelectFromData(
        "quantity-unit-stock",
        "Select unit",
        quantityUnits
      );
      var purchaseUnitSelect = buildSelectFromData(
        "default-quantity-unit",
        "Select unit",
        quantityUnits
      );

      var quantityUnitSelects = {
        stock: stockUnitSelect,
        purchase: purchaseUnitSelect
      };

      var formElement = document.getElementById("scan-result");
      formElement.innerHTML = "";
      formElement.appendChild(
        buildProductFormHtml(
          barcode,
          locationSelect,
          storeSelect,
          quantityUnitSelects,
          prefillName
        )
      );

      // Set the product name value if available
      if (prefillName) {
        document.getElementById("product-name").value = prefillName;
      }
    })
    .catch((error) => {
      console.error("Failed to load form data:", error);
      // Fallback to text inputs if API fails
      var quantityUnitInputs = {
        stock: createInput("quantity-unit-stock", "Enter quantity unit stock"),
        purchase: createInput(
          "default-quantity-unit",
          "Enter default quantity unit"
        )
      };

      var formElement = document.getElementById("scan-result");
      formElement.innerHTML = "";
      formElement.appendChild(
        buildProductFormHtml(
          barcode,
          locationInput,
          storeInput,
          quantityUnitInputs
        )
      );

      // Set the product name value if available
      if (prefillName) {
        document.getElementById("product-name").value = prefillName;
      }
    });
}

function submitProduct(barcode, process = false, create = false) {
  var productName = document.getElementById("product-name").value;
  var defaultLocation = document.getElementById("default-location").value;
  var defaultStore = document.getElementById("default-store").value;
  var quantityUnitStock = document.getElementById("quantity-unit-stock").value;
  var asin = document.getElementById("product-asin").value;

  // Clear all previous errors
  document.querySelectorAll(".form-input").forEach(function (el) {
    el.classList.remove("error");
  });

  // Validate required fields
  var hasError = false;

  if (!productName) {
    document.getElementById("product-name").classList.add("error");
    hasError = true;
  }

  if (!defaultLocation) {
    document.getElementById("default-location").classList.add("error");
    hasError = true;
  }

  if (!defaultStore) {
    document.getElementById("default-store").classList.add("error");
    hasError = true;
  }

  if (!quantityUnitStock) {
    document.getElementById("quantity-unit-stock").classList.add("error");
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
    min_stock_amount: 1,
    asin: asin
  };

  console.log("Submitting product data:", productData);

  // Show processing animation if process=true
  if (process) {
    document.getElementById("processing-overlay").classList.add("active");
  }

  // Send to API
  var xhr = new XMLHttpRequest();

  if (process) {
    // Add productData key for adding and processing
    if (create) {
      productData.createProduct = true;
    }

    xhr.open("POST", baseUrl + API_ENDPOINTS.PRODUCT_PROCESS, true);
  } else {
    xhr.open("POST", baseUrl + API_ENDPOINTS.PRODUCT_CREATE, true);
  }
  xhr.setRequestHeader("Content-Type", "application/json");

  xhr.onload = function () {
    // Hide processing overlay
    document.getElementById("processing-overlay").classList.remove("active");

    if (xhr.status >= 200 && xhr.status < 300) {
      // Success
      document.getElementById("content").style.backgroundColor = "#33a532";
      document.getElementById("scan-result").textContent =
        "Product created: " + productName;
      document.getElementById("log").style.display = "block";
      // $db->deleteBarcode(barcode);

      setTimeout(function () {
        cancelCreate();
      }, 4000);
    } else {
      // Error
      document.getElementById("content").style.backgroundColor = "#CC0605";
      document.getElementById("scan-result").textContent =
        "Error creating product: " + xhr.statusText;
      console.error("API Error:", xhr.responseText);
    }
  };

  xhr.onerror = function () {
    // Hide processing overlay
    document.getElementById("processing-overlay").classList.remove("active");

    document.getElementById("content").style.backgroundColor = "#CC0605";
    document.getElementById("scan-result").textContent =
      "Failed to connect to API";
    console.error("Network error");
  };

  xhr.send(JSON.stringify(productData));

  // ALSO send to PHP handler for server-side action
  console.log("sending to PHP handler");
  var phpXhr = new XMLHttpRequest();
  phpXhr.open("POST", API_ENDPOINTS.SCREEN, true);
  phpXhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
  phpXhr.onload = function () {
    if (phpXhr.status === 200) {
      console.log("PHP action completed:", phpXhr.responseText);
    } else {
      console.error(
        "PHP handler error (status " + phpXhr.status + "):",
        phpXhr.responseText
      );
      try {
        var errorData = JSON.parse(phpXhr.responseText);
        console.error("Error details:", errorData);
      } catch (e) {
        console.error("Raw response:", phpXhr.responseText);
      }
    }
  };
  phpXhr.onerror = function () {
    console.error("PHP handler network error");
  };
  phpXhr.send(
    "action=submitProduct&" +
      "barcode=" +
      encodeURIComponent(barcode) +
      "&" +
      "process=" +
      (process ? "true" : "false") +
      "&" +
      "product_name=" +
      encodeURIComponent(productName) +
      "&" +
      "location=" +
      encodeURIComponent(defaultLocation) +
      "&" +
      "store=" +
      encodeURIComponent(defaultStore)
  );
}

function cancelCreate() {
  document.getElementById("content").style.backgroundColor = "#000";
  document.getElementById("scan-result").textContent = "waiting for barcode...";
  document.getElementById("log").style.display = "block";
}

// Amazon Lookup Functions
function openAmazonLookup(barcode) {
  // Open modal
  document.getElementById("amazon-modal").classList.add("active");

  // Show loading state using template
  var loadingTemplate = document.getElementById("amazon-loading-template");
  var loadingClone = loadingTemplate.content.cloneNode(true);
  loadingClone.querySelector(".barcode-text").textContent = barcode;

  var modalBody = document.getElementById("amazon-modal-body");
  modalBody.innerHTML = "";
  modalBody.appendChild(loadingClone);

  // Make API call to lookup Amazon products
  fetch(
    baseUrl +
      API_ENDPOINTS.AMAZON_LOOKUP +
      "?barcode=" +
      encodeURIComponent(barcode)
  )
    .then(function (response) {
      if (!response.ok) {
        throw new Error("Network response was not ok: " + response.status);
      }
      return response.json();
    })
    .then(function (data) {
      displayAmazonResults(data, barcode);
    })
    .catch(function (error) {
      console.error("Amazon lookup error:", error);

      var errorTemplate = document.getElementById("amazon-error-template");
      var errorClone = errorTemplate.content.cloneNode(true);
      errorClone.querySelector(".error-message").textContent = error.message;

      var modalBody = document.getElementById("amazon-modal-body");
      modalBody.innerHTML = "";
      modalBody.appendChild(errorClone);
    });
}

function displayAmazonResults(data, barcode) {
  var modalBody = document.getElementById("amazon-modal-body");

  console.dir(data.data.products);

  // Check if we have products
  if (!data.data || data.data.products.length === 0) {
    var noResultsTemplate = document.getElementById(
      "amazon-no-results-template"
    );
    var noResultsClone = noResultsTemplate.content.cloneNode(true);
    modalBody.innerHTML = "";
    modalBody.appendChild(noResultsClone);
    return;
  }

  // Clear existing content
  modalBody.innerHTML = "";

  // Build products using template
  var productTemplate = document.getElementById("amazon-product-template");

  data.data.products.forEach(function (product) {
    var name = product.name || "Unknown Product";
    var price = product.price || "Price not available";
    var asin = product.product_id || "";
    var url = product.url || "";
    var image = product.image_url || "";

    var productClone = productTemplate.content.cloneNode(true);

    // Set image
    var imgElement = productClone.querySelector(".amazon-product-image");
    imgElement.src = image;
    imgElement.alt = name;
    imgElement.onerror = function () {
      this.style.display = "none";
    };

    // Set product info
    productClone.querySelector(".amazon-product-title").textContent = name;
    productClone.querySelector(".amazon-product-price").textContent = price;

    // Set up select button
    var selectButton = productClone.querySelector(".amazon-select-button");
    selectButton.onclick = function () {
      selectAmazonProduct(asin, name, url, barcode);
    };

    modalBody.appendChild(productClone);
  });
}

function selectAmazonProduct(asin, name, url, barcode) {
  // Pre-fill the ASIN field
  document.getElementById("product-asin").value = he.decode(asin);

  // Close the modal
  closeAmazonModal();

  // Optional: You could also store the ASIN and URL for future reference
  console.log("Selected Amazon product:", {
    asin: asin,
    name: name,
    url: url,
    barcode: barcode
  });
}

function closeAmazonModal() {
  document.getElementById("amazon-modal").classList.remove("active");
}

var noSleep = new NoSleep();
var wakeLockEnabled = false;
var isFirstStart = true;

function goHome() {
  if (document.referrer === "") {
    window.location.href = "./index.php";
  } else {
    window.close();
  }
}

// function toggleSound() {
// if (!wakeLockEnabled) {
// 	noSleep.enable();
// 	wakeLockEnabled = true;
// 	document.getElementById('beep_success').muted = false;
// 	document.getElementById('beep_nosuccess').muted = false;
// 	<?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
// 	echo " document.documentElement.requestFullscreen();";
// }?>
// 	document.getElementById("muteimg").src = "incl/img/unmute.svg";
// } else {
// 	noSleep.disable();
// 	<?php if (BBConfig::getInstance()["WS_FULLSCREEN"]) {
// 	echo " document.exitFullscreen();";
// } ?>
// 	wakeLockEnabled = false;
// 	document.getElementById('beep_success').muted = true;
// 	document.getElementById('beep_nosuccess').muted = true;
// 	document.getElementById("muteimg").src = "incl/img/mute.svg";
// }
// }

function syncCache() {
  var xhttp = new XMLHttpRequest();
  xhttp.open("GET", API_ENDPOINTS.CRON, true);
  xhttp.send();
}

if (typeof EventSource !== "undefined") {
  syncCache();
  var source = new EventSource(API_ENDPOINTS.SSE_DATA);

  var currentScanId = 0;
  var connectFailCounter = 0;
  var lastFail = 0;

  source.addEventListener(
    "error",
    function (event) {
      switch (event.target.readyState) {
        case EventSource.CONNECTING:
          document.getElementById("grocy-sse").textContent = "Reconnecting...";
          if (Date.now() - lastFail < 10000) {
            connectFailCounter++;
          }
          if (connectFailCounter === 10) {
            source.close();
            document.getElementById("grocy-sse").textContent = "Unavailable";
            document.getElementById("scan-result").textContent =
              "Unable to connect to Barcode Buddy";
          }
          lastFail = Date.now();
          break;
        case EventSource.CLOSED:
          console.log("Connection failed (CLOSED)");
          break;
      }
    },
    false
  );

  async function resetScan(scanId) {
    await sleep(3000);
    if (currentScanId == scanId) {
      document.getElementById("content").style.backgroundColor = "#000000ff";
      document.getElementById("scan-result").textContent =
        "waiting for barcode...";
      document.getElementById("event").textContent = "";
    }
  }

  function sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  function resultScan(color, message, text, sound) {
    document.getElementById("content").style.backgroundColor = color;
    document.getElementById("event").textContent = message;
    document.getElementById("scan-result").textContent = text;
    // document.getElementById(sound).play();
    document.getElementById("log-entries").innerText =
      "\r\n" + text + document.getElementById("log-entries").innerText;
    currentScanId++;
    resetScan(currentScanId);
  }

  source.onopen = function () {
    document.getElementById("grocy-sse").textContent = "Connected";
    if (isFirstStart) {
      isFirstStart = false;
      document.getElementById("scan-result").textContent =
        "waiting for barcode...";
      var http = new XMLHttpRequest();
      http.open("GET", API_ENDPOINTS.SSE_DATA + "?getState");
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
      case "0":
        resultText = resultJson.data.substring(1, resultJson.data.length - 1);
        resultScan("#33a532", "", he.decode(resultText), "beep_success");
        break;
      case "1":
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
          var productName = resultText.replace(/\s*\([^)]+\)$/, "");
          showUnknownBarcodeForm(resultText, productName, barcode);
        } else {
          resultScan(
            "#a2ff9b",
            "Barcode Looked Up",
            he.decode(resultText),
            "beep_success"
          );
        }
        break;
      case "2":
        showUnknownBarcodeForm(resultText);
        break;
      case "4":
        document.getElementById("mode").textContent = resultText;
        break;
      case "E":
        document.getElementById("content").style.backgroundColor = "#CC0605";
        document.getElementById("grocy-sse").textContent = "disconnected";
        document.getElementById("scan-result").style.display = "none";
        document.getElementById("previous-events").style.display = "none";
        document
          .getElementById("event")
          .setAttribute("style", "white-space: pre;");
        document.getElementById("event").textContent = "\r\n\r\n" + resultText;
        break;
    }
  };
} else {
  document.getElementById("content").style.backgroundColor = "#f9868b";
  document.getElementById("grocy-sse").textContent = "Disconnected";
  document.getElementById("event").textContent =
    "Sorry, your browser does not support server-sent events";
}
