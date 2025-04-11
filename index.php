<!DOCTYPE html>
<html>
<head>
  <title>Multi-Gateway Card Checker</title>
  <link href="style.css" rel="stylesheet">
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #1e2a78, #ff3864);
      background-repeat: no-repeat;
      background-attachment: fixed; 
      background-size: 100% 100%;
      color: #f8f9fa;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .card {
      background-color: rgba(0, 0, 0, 0.6);
      border-radius: 10px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
      margin-bottom: 20px;
    }
    .rainbow_text_animated {
      background: linear-gradient(to right, #6666ff, #0099ff, #00ff00, #ff3399, #6666ff);
      -webkit-background-clip: text;
      background-clip: text;
      font-size: 42px;
      font-family: 'Segoe UI', sans-serif;
      font-weight: bold;
      text-shadow: 0 0 35px #FF0000, 0 0 60px #FF0000;
      color: transparent;
      animation: rainbow_animation 2s ease-in-out infinite;
      background-size: 400% 100%;
    }
    @keyframes rainbow_animation {
      0%,100% {
        background-position: 0 0;
      }
      50% {
        background-position: 100% 0;
      }
    }
    textarea {
      background-color: rgba(20, 20, 20, 0.8) !important;
      color: #11ff00 !important;
      border: 1px solid #444 !important;
      border-radius: 5px !important;
      font-family: 'Courier New', monospace;
    }
    .btn-warning {
      background-color: #FF9800;
      border-color: #FF9800;
      font-weight: bold;
      transition: all 0.3s;
    }
    .btn-warning:hover {
      background-color: #F57C00;
      border-color: #F57C00;
      transform: translateY(-2px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }
    .badge {
      font-size: 14px;
      padding: 5px 10px;
      margin: 0 5px;
    }
    .aprovadas, .edrovadas, .reprovadas {
      font-family: 'Courier New', monospace;
      font-size: 14px;
      word-break: break-all;
    }
    .card-title {
      border-bottom: 1px solid rgba(255,255,255,0.2);
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    .proxy-config {
      margin-top: 10px;
      padding: 10px;
      border-radius: 5px;
      background-color: rgba(0,0,0,0.3);
    }
    .api-config {
      margin-top: 10px;
      padding: 10px;
      border-radius: 5px;
      background-color: rgba(0,0,0,0.3);
    }
    .gateway-select {
      padding: 10px;
      margin-bottom: 15px;
      background-color: rgba(0,0,0,0.3);
      border-radius: 5px;
    }
  </style>
</head>
<body>
  <br>
  <center>
    <div class="row col-md-12">
      <div class="card col-sm-12">
        <h1 class="card-body h1"><span class="badge badge-dark rainbow_text_animated">Multi-Gateway Card Checker</span></h1>
        <div class="card-body">
          <div class="md-form">
            <div class="col-md-12">
              <center>
                <div class="md-form">
                  <span>Approved CVV:</span>&nbsp;<span id="cLive" class="badge badge-success">0</span>
                  <span>Approve CCN:</span>&nbsp;<span id="cWarn" class="badge badge-warning">0</span>
                  <span>Declined:</span>&nbsp;<span id="cDie" class="badge badge-danger">0</span>
                  <span>Checked:</span>&nbsp;<span id="total" class="badge badge-info">0</span>
                  <span>Total:</span>&nbsp;<span id="carregadas" class="badge badge-dark">0</span>
                </div>
                <br>
                <textarea type="text" style="text-align: center; maxlength="700" placeholder="Enter Cards Here (format: XXXXXXXXXXXXXXXX|MM|YYYY|CVV)" id="lista" class="md-textarea form-control" rows="4"></textarea>
              </center>
              &nbsp;
            </div>
            <div class="gateway-select">
              <h5>Payment Gateway</h5>
              <select id="gatewaySelect" class="form-control">
                <optgroup label="Major Gateways">
                  <option value="stripe">Stripe</option>
                  <option value="paypal">PayPal</option>
                  <option value="adyen">Adyen</option>
                  <option value="authorize">Authorize.net</option>
                  <option value="braintree">Braintree</option>
                  <option value="checkout">Checkout.com</option>
                  <option value="worldpay">Worldpay</option>
                  <option value="square">Square</option>
                </optgroup>
                <optgroup label="E-commerce & Platforms">
                  <option value="shopify">Shopify Payments</option>
                  <option value="klarna">Klarna</option>
                  <option value="twocheckout">2Checkout</option>
                  <option value="bluesnap">BlueSnap</option>
                  <option value="razorpay">Razorpay</option>
                  <option value="airwallex">Airwallex</option>
                  <option value="mollie">Mollie</option>
                </optgroup>
                <optgroup label="Subscription Services">
                  <option value="nordvpn">NordVPN</option>
                  <option value="patreon">Patreon</option>
                  <option value="xsolla">Xsolla</option>
                  <option value="gocardless">GoCardless</option>
                  <option value="midtrans">Midtrans</option>
                </optgroup>
                <optgroup label="Regional Gateways">
                  <option value="payu">PayU</option>
                  <option value="cybersource">CyberSource</option>
                  <option value="micropayment">Micropayment</option>
                </optgroup>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="proxy-config">
                  <h5>Webshare API Settings</h5>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="useProxy" checked>
                    <label class="form-check-label" for="useProxy">Use Proxy</label>
                  </div>
                  <div class="form-group">
                    <label for="webshareApiKey">Webshare API Key</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="webshareApiKey" placeholder="Enter Webshare API key">
                      <div class="input-group-append">
                        <button class="btn btn-success" type="button" id="addWebshareKey">Add</button>
                      </div>
                    </div>
                  </div>
                  <div class="mt-2">
                    <select multiple class="form-control" id="webshareKeysList" style="height: 100px;"></select>
                    <button class="btn btn-danger btn-sm mt-2" id="removeWebshareKey">Remove Selected</button>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="api-config">
                  <h5>Gateway API Settings <a href="docs/view.php?file=api_key_guide.md" target="_blank" class="badge badge-info" title="How to find API keys"><i class="fas fa-question-circle"></i> Help</a></h5>
                  <div class="form-group">
                    <label for="apiKey">API Key</label>
                    <input type="text" class="form-control" id="apiKey" placeholder="Enter API key">
                  </div>
                  <div class="form-group">
                    <label for="secretKey">Secret Key (optional)</label>
                    <input type="text" class="form-control" id="secretKey" placeholder="Enter secret key">
                  </div>
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="saveApiKey">
                    <label class="form-check-label" for="saveApiKey">Save API Keys (session only)</label>
                  </div>
                </div>
              </div>
            </div>
            
            <div class="row mt-3">
              <div class="col-md-12">
                <div class="advanced-options">
                  <div class="card">
                    <div class="card-header">
                      <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#advancedOptions" aria-expanded="false">
                        Advanced Options <i class="fas fa-chevron-down"></i>
                      </button>
                    </div>
                    <div id="advancedOptions" class="collapse">
                      <div class="card-body">
                        <div class="row">
                          <div class="col-md-6">
                            <div class="form-group">
                              <label for="threadsCount">Threads</label>
                              <input type="number" class="form-control" id="threadsCount" min="1" max="10" value="1">
                              <small class="form-text text-muted">Number of simultaneous checks (1-10)</small>
                            </div>
                            <div class="form-group">
                              <label for="timeoutMs">Timeout (ms)</label>
                              <input type="number" class="form-control" id="timeoutMs" min="1000" max="60000" value="30000">
                              <small class="form-text text-muted">Request timeout in milliseconds</small>
                            </div>
                          </div>
                          <div class="col-md-6">
                            <div class="form-group">
                              <label>Export Options</label>
                              <div class="btn-group w-100">
                                <button class="btn btn-secondary" id="exportCSV">Export CSV</button>
                                <button class="btn btn-secondary" id="exportTXT">Export TXT</button>
                              </div>
                            </div>
                            <div class="form-check mt-3">
                              <input class="form-check-input" type="checkbox" id="autoDetectGateway">
                              <label class="form-check-label" for="autoDetectGateway">Auto-detect gateway from BIN</label>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <center>
              <button class="btn btn-warning" style="width: 200px; outline: none;" id="testar" onclick="start()"><b>START</b></button>
              <button class="btn btn-danger" style="width: 200px; outline: none;" id="stop" onclick="stop()"><b>STOP</b></button>
            </center>
          </div>
        </div>
      </center>
      &nbsp;&nbsp;<br>&nbsp;&nbsp;<br>
      <div class="col-md-12">
        <div class="card">
          <div style="position: absolute; top: 0; right: 0;">
            <button id="mostra" class="btn btn-success">Show</button><br>
          </div>
          <div class="card-body">
            <h6 style="font-weight: bold; color:green" class="card-title">Approved CVV: <span id="cLive2" class="badge badge-success">0</span></h6>
            <div id="bode"><span id=".aprovadas" class="aprovadas"></span>
          </div>
        </div>
      </div>
    </div>
    &nbsp;&nbsp;&nbsp;</br>
    <div class="col-md-12">
      <div class="card">
        <div style="position: absolute; top: 0; right: 0;">
          <button id="mostra3" class="btn btn-warning">Show</button><br>
        </div>
        <div class="card-body">
          <h6 style="font-weight: bold; color:yellow;" class="card-title">Approve CCN: <span id="cWarn2" class="badge badge-warning">0</span></h6>
          <div id="bode3"><span id=".edrovadas" class="edrovadas"></span>
        </div>
      </div>
    </div>
  </div>
  &nbsp;&nbsp;&nbsp;</br>
  <div class="col-md-12">
    <div class="card">
      <div style="position: absolute; top: 0; right: 0;">
        <button id="mostra2" class="btn btn-danger">Show</button><br>
      </div>
      <div class="card-body">
        <h6 style="font-weight: bold; color: red;" class="card-title">Declined: <span id="cDie2" class="badge badge-danger">0</span></h6>
        <div id="bode2"><span id=".reprovadas" class="reprovadas"></span>
      </div>
    </div>
  </div>
</div>
</div>
</div>
<br>
<footer>
  <center>
    <span class="badge badge-dark rainbow_text_animated"><h3>Multi-Gateway Checker</h3></span>
    <p>Supports multiple payment processors for comprehensive card validation</p>
  </center>
</footer>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/4.6.0/js/bootstrap.min.js"></script>
<script src="script.js"></script>

</body>
</html>