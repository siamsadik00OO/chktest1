function copyToClipboard(element) {
  var $temp = $("<input>");
  $("body").append($temp);
  $temp.val($(element).text()).select();
  document.execCommand("copy");
  $temp.remove();
}

// Global variables
let isChecking = false;
let checkingInterval;
let webshareApiKeys = [];
let savedGatewayKeys = {};

$(document).ready(function() {
  // Hide result sections initially
  $("#bode").hide();
  $("#bode2").hide();
  $("#bode3").hide();
  
  // Toggle visibility for approved CVV cards
  $('#mostra').click(function() {
    $("#bode").slideToggle();
  });
  
  // Toggle visibility for approved CCN cards
  $('#mostra3').click(function() {
    $("#bode3").slideToggle();
  });
  
  // Toggle visibility for declined cards
  $('#mostra2').click(function() {
    $("#bode2").slideToggle();
  });

  // Disable stop button initially
  $('#stop').prop('disabled', true);
  
  // Change API fields based on selected gateway
  $('#gatewaySelect').change(function() {
    updateApiFields();
    loadSavedApiKeys();
  });
  
  // Initial API fields setup
  updateApiFields();
  
  // Load saved Webshare API keys from localStorage
  loadWebshareKeys();
  
  // Add Webshare API key
  $('#addWebshareKey').click(function() {
    const key = $('#webshareApiKey').val().trim();
    if (key && !webshareApiKeys.includes(key)) {
      webshareApiKeys.push(key);
      saveWebshareKeys();
      updateWebshareKeysList();
      $('#webshareApiKey').val('');
    }
  });
  
  // Remove selected Webshare API key
  $('#removeWebshareKey').click(function() {
    const selectedKey = $('#webshareKeysList option:selected').val();
    if (selectedKey) {
      webshareApiKeys = webshareApiKeys.filter(key => key !== selectedKey);
      saveWebshareKeys();
      updateWebshareKeysList();
    }
  });
  
  // CSV Export
  $('#exportCSV').click(function() {
    exportResults('csv');
  });
  
  // TXT Export
  $('#exportTXT').click(function() {
    exportResults('txt');
  });
  
  // Auto-detect gateway checkbox
  $('#autoDetectGateway').change(function() {
    if ($(this).is(':checked')) {
      $('#gatewaySelect').prop('disabled', true);
    } else {
      $('#gatewaySelect').prop('disabled', false);
    }
  });
});

// Update API field labels based on gateway
function updateApiFields() {
  const gateway = $('#gatewaySelect').val();
  
  // Default labels
  let apiKeyLabel = "API Key";
  let secretKeyLabel = "Secret Key";
  
  // Customize labels based on gateway
  switch(gateway) {
    // Major Gateways
    case 'stripe':
      apiKeyLabel = "Publishable Key (pk_...)";
      secretKeyLabel = "Payment Intent (pi_...)";
      break;
    case 'paypal':
      apiKeyLabel = "Client ID";
      secretKeyLabel = "Client Secret";
      break;
    case 'adyen':
      apiKeyLabel = "API Key";
      secretKeyLabel = "Merchant Account";
      break;
    case 'authorize':
      apiKeyLabel = "API Login ID";
      secretKeyLabel = "Transaction Key";
      break;
    case 'braintree':
      apiKeyLabel = "Merchant ID";
      secretKeyLabel = "Public Key";
      break;
    case 'checkout':
      apiKeyLabel = "Public Key (pk_...)";
      secretKeyLabel = "Secret Key (sk_...)";
      break;
    case 'worldpay':
      apiKeyLabel = "Client Key";
      secretKeyLabel = "Merchant ID";
      break;
    case 'square':
      apiKeyLabel = "Application ID";
      secretKeyLabel = "Location ID";
      break;
      
    // E-commerce & Platforms
    case 'shopify':
      apiKeyLabel = "Shop ID";
      secretKeyLabel = "Checkout Token";
      break;
    case 'klarna':
      apiKeyLabel = "API Key";
      secretKeyLabel = "Authorization Token";
      break;
    case 'twocheckout':
      apiKeyLabel = "Merchant Code";
      secretKeyLabel = "Public Key";
      break;
    case 'bluesnap':
      apiKeyLabel = "API Key";
      secretKeyLabel = "Merchant ID";
      break;
    case 'razorpay':
      apiKeyLabel = "Key ID";
      secretKeyLabel = "Key Secret";
      break;
    case 'airwallex':
      apiKeyLabel = "API Key";
      secretKeyLabel = "Client ID";
      break;
    case 'mollie':
      apiKeyLabel = "API Key";
      secretKeyLabel = "Profile ID";
      break;
      
    // Subscription Services
    case 'nordvpn':
      apiKeyLabel = "Service ID";
      secretKeyLabel = "Token";
      break;
    case 'patreon':
      apiKeyLabel = "Client ID";
      secretKeyLabel = "Client Secret";
      break;
    case 'xsolla':
      apiKeyLabel = "Project ID";
      secretKeyLabel = "API Key";
      break;
    case 'gocardless':
      apiKeyLabel = "Access Token";
      secretKeyLabel = "Creditor ID";
      break;
    case 'midtrans':
      apiKeyLabel = "Client Key";
      secretKeyLabel = "Server Key";
      break;
      
    // Regional Gateways
    case 'payu':
      apiKeyLabel = "Merchant ID";
      secretKeyLabel = "API Key";
      break;
    case 'cybersource':
      apiKeyLabel = "Merchant ID";
      secretKeyLabel = "API Key";
      break;
    case 'micropayment':
      apiKeyLabel = "Access Key";
      secretKeyLabel = "Project ID";
      break;
  }
  
  // Update labels
  $('label[for="apiKey"]').text(apiKeyLabel);
  $('label[for="secretKey"]').text(secretKeyLabel + " (optional)");
}

// Start checking cards
function start() {
  if (isChecking) return;
  
  isChecking = true;
  $('#testar').prop('disabled', true);
  $('#stop').prop('disabled', false);
  
  var linha = $("#lista").val();
  var linhastart = linha.split("\n");
  var total = linhastart.length;
  var ap = 0;
  var ed = 0;
  var rp = 0;
  
  $('#carregadas').html(total);
  
  let currentIndex = 0;
  
  // Get gateway, proxy and API details
  var gateway = $('#gatewaySelect').val();
  var useProxy = $('#useProxy').is(':checked');
  var proxyDetails = $('#proxyInput').val();
  var apiKey = $('#apiKey').val();
  var secretKey = $('#secretKey').val();
  
  checkingInterval = setInterval(function() {
    if (currentIndex >= total || !isChecking) {
      clearInterval(checkingInterval);
      $('#testar').prop('disabled', false);
      $('#stop').prop('disabled', true);
      isChecking = false;
      return;
    }
    
    let card = linhastart[currentIndex];
    currentIndex++;
    
    // Only process non-empty lines
    if (card.trim() !== '') {
      checkCard(card, gateway, useProxy, proxyDetails, apiKey, secretKey, function(resultado) {
        processResult(resultado, ap, ed, rp);
        removelinha();
        
        // Update counters
        if (resultado.match("#CVV")) {
          ap++;
        } else if (resultado.match("#CCN")) {
          ed++;
        } else {
          rp++;
        }
        
        // Update UI
        updateCounters(ap, ed, rp, currentIndex, total);
      });
    } else {
      removelinha();
    }
  }, 2500);
}

// Stop checking cards
function stop() {
  isChecking = false;
  clearInterval(checkingInterval);
  $('#testar').prop('disabled', false);
  $('#stop').prop('disabled', true);
}

// Webshare API keys functions
function loadWebshareKeys() {
  try {
    const keys = localStorage.getItem('webshareApiKeys');
    if (keys) {
      webshareApiKeys = JSON.parse(keys);
      updateWebshareKeysList();
    }
  } catch (e) {
    console.error('Error loading Webshare API keys:', e);
  }
}

function saveWebshareKeys() {
  try {
    localStorage.setItem('webshareApiKeys', JSON.stringify(webshareApiKeys));
  } catch (e) {
    console.error('Error saving Webshare API keys:', e);
  }
}

function updateWebshareKeysList() {
  const list = $('#webshareKeysList');
  list.empty();
  
  webshareApiKeys.forEach(key => {
    // Mask most of the key for display
    const displayKey = key.substring(0, 4) + '****' + key.substring(key.length - 4);
    list.append(`<option value="${key}">${displayKey}</option>`);
  });
}

// Save and load API keys for different gateways
function saveApiKeys() {
  if ($('#saveApiKey').is(':checked')) {
    const gateway = $('#gatewaySelect').val();
    const apiKey = $('#apiKey').val();
    const secretKey = $('#secretKey').val();
    
    if (apiKey || secretKey) {
      savedGatewayKeys[gateway] = {
        apiKey: apiKey,
        secretKey: secretKey
      };
      
      try {
        sessionStorage.setItem('savedGatewayKeys', JSON.stringify(savedGatewayKeys));
      } catch (e) {
        console.error('Error saving gateway API keys:', e);
      }
    }
  }
}

function loadSavedApiKeys() {
  try {
    const savedKeys = sessionStorage.getItem('savedGatewayKeys');
    if (savedKeys) {
      savedGatewayKeys = JSON.parse(savedKeys);
      const gateway = $('#gatewaySelect').val();
      
      if (savedGatewayKeys[gateway]) {
        $('#apiKey').val(savedGatewayKeys[gateway].apiKey || '');
        $('#secretKey').val(savedGatewayKeys[gateway].secretKey || '');
      } else {
        $('#apiKey').val('');
        $('#secretKey').val('');
      }
    }
  } catch (e) {
    console.error('Error loading saved gateway API keys:', e);
  }
}

// Export results to file
function exportResults(format) {
  let content = '';
  let filename = 'card-checker-results_' + new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
  
  if (format === 'csv') {
    // CSV header
    content = 'Status,CardNumber,Month,Year,CVV,Response\n';
    
    // Approved CVV cards
    $('.aprovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        const cardMatch = line.match(/(\d+)\|(\d+)\|(\d+)\|(\d+)/);
        if (cardMatch) {
          content += `Approved,${cardMatch[1]},${cardMatch[2]},${cardMatch[3]},${cardMatch[4]},"${line.replace(/"/g, '""')}"\n`;
        }
      }
    });
    
    // Approved CCN cards
    $('.edrovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        const cardMatch = line.match(/(\d+)\|(\d+)\|(\d+)\|(\d+)/);
        if (cardMatch) {
          content += `CCN,${cardMatch[1]},${cardMatch[2]},${cardMatch[3]},${cardMatch[4]},"${line.replace(/"/g, '""')}"\n`;
        }
      }
    });
    
    // Declined cards
    $('.reprovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        const cardMatch = line.match(/(\d+)\|(\d+)\|(\d+)\|(\d+)/);
        if (cardMatch) {
          content += `Declined,${cardMatch[1]},${cardMatch[2]},${cardMatch[3]},${cardMatch[4]},"${line.replace(/"/g, '""')}"\n`;
        }
      }
    });
    
    filename += '.csv';
  } else { // TXT format
    // Approved CVV cards
    content += "=== APPROVED CVV CARDS ===\n\n";
    $('.aprovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        content += line.replace(/<[^>]*>/g, '') + '\n';
      }
    });
    
    // Approved CCN cards
    content += "\n=== APPROVED CCN CARDS ===\n\n";
    $('.edrovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        content += line.replace(/<[^>]*>/g, '') + '\n';
      }
    });
    
    // Declined cards
    content += "\n=== DECLINED CARDS ===\n\n";
    $('.reprovadas').html().split('<br>').forEach(line => {
      if (line.trim()) {
        content += line.replace(/<[^>]*>/g, '') + '\n';
      }
    });
    
    filename += '.txt';
  }
  
  // Create download link
  const blob = new Blob([content], { type: format === 'csv' ? 'text/csv;charset=utf-8' : 'text/plain;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

// Check a single card
function checkCard(card, gateway, useProxy, proxyDetails, apiKey, secretKey, callback) {
  // Save API keys if option is selected
  if ($('#saveApiKey').is(':checked')) {
    saveApiKeys();
  }
  
  // Get timeout setting from advanced options
  const timeoutMs = parseInt($('#timeoutMs').val()) || 30000;
  
  // Auto-detect gateway if enabled
  if ($('#autoDetectGateway').is(':checked')) {
    // Extract BIN (first 6 digits)
    const binMatch = card.match(/^(\d{6})/);
    if (binMatch) {
      const bin = binMatch[1];
      // This is a simple example for auto-detection, can be expanded
      if (bin.startsWith('4')) {
        gateway = 'stripe'; // Visa cards
      } else if (bin.startsWith('5')) {
        gateway = 'paypal'; // Mastercard
      } else if (bin.startsWith('3')) {
        gateway = 'adyen'; // Amex
      }
      // More BIN-based detection logic could be added
    }
  }
  
  // Get a webshare key if available and proxy is enabled
  let webshareKey = null;
  if (useProxy && webshareApiKeys.length > 0) {
    // Rotate webshare keys
    webshareKey = webshareApiKeys[Math.floor(Math.random() * webshareApiKeys.length)];
  }
  
  $.ajax({
    url: 'checker.php',
    type: 'POST',
    data: {
      lista: card,
      gateway: gateway,
      useProxy: useProxy ? 1 : 0,
      webshareApiKey: webshareKey,
      apiKey: apiKey,
      secretKey: secretKey,
      threads: parseInt($('#threadsCount').val()) || 1
    },
    timeout: timeoutMs,
    success: function(resultado) {
      callback(resultado);
    },
    error: function() {
      callback("❌ ERROR: Request timed out or failed");
      removelinha();
    }
  });
}

// Process check result
function processResult(resultado, ap, ed, rp) {
  if (resultado.match("#CVV")) {
    aprovadas(resultado + "");
  } else if (resultado.match("#CCN")) {
    edrovadas(resultado + "");
  } else {
    reprovadas(resultado + "");
  }
}

// Update counters in the UI
function updateCounters(ap, ed, rp, checked, total) {
  $('#cLive').html(ap);
  $('#cWarn').html(ed);
  $('#cDie').html(rp);
  $('#total').html(checked);
  $('#cLive2').html(ap);
  $('#cWarn2').html(ed);
  $('#cDie2').html(rp);
}

// Add a card to the approved CVV section
function aprovadas(str) {
  $(".aprovadas").append(str + "<br>");
}

// Add a card to the declined section
function reprovadas(str) {
  $(".reprovadas").append(str + "<br>");
}

// Add a card to the approved CCN section
function edrovadas(str) {
  $(".edrovadas").append(str + "<br>");
}

// Remove the checked card from the textarea
function removelinha() {
  var lines = $("#lista").val().split('\n');
  lines.splice(0, 1);
  $("#lista").val(lines.join("\n"));
}