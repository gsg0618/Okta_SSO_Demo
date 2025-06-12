<?php
// Begin the PHP session so we have a place to store the username
session_start();

// these are unique to the Okta app for Zillow testing
$client_id = '0oao8j1udbMzZ22ps5d7';
$client_secret = 'WxrOwdZztLjK4DcAbljq-tDiVJxyzr0RALlyF7mEPYuhoBc07lHwbGNyo1dR0BF0';
$redirect_uri = 'http://localhost:80/';
$oktaOrg = 'dev-67831059.okta.com'; # copy this from the okta developer account profile

if (isset($_GET['logout'])) {
  $idToken = $_SESSION['id_token'] ?? null;
  session_destroy();
    // Clear the session cookie
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 4200,
        $params["path"], $params["domain"],
         $params["secure"], $params["httponly"]
    );
  }

  if ($idToken) {
      $okta_logout_url = "https://$oktaOrg/oauth2/default/v1/logout?" . http_build_query([
          'id_token_hint' => $idToken,
          'post_logout_redirect_uri' => $redirect_uri
      ]);
      header("Location: $okta_logout_url");
      exit;
  } else {
      header("Location: $redirect_uri");
      exit;
  }
}

#$metadata_url = "https://dev-67831059.okta.com/oauth2/default/.well-known/oauth-authorization-server";
$metadata_url = "https://dev-67831059.okta.com/oauth2/default/.well-known/openid-configuration";
// Fetch the authorization server metadata which contains a few URLs
// that we need later, such as the authorization and token endpoints
$metadata = http($metadata_url);

// a code will be present if the user is coming back from the Okta authZ page, we need to confirm it's good
if(isset($_GET['code'])) {
    // confirm the authZ server has same state as the local web server's state
    if($_SESSION['state'] != $_GET['state']) {
        die('Authorization server returned an invalid state parameter');
      }
    
    // if Okta's authZ server threw an error, describe it to user
    if(isset($_GET['error'])) {
        die('Authorization server returned an error: '.htmlspecialchars($_GET['error']));
    }

    // call our custom http fn with the code that the authZ server gave us to get a token
    $response = http($metadata->token_endpoint, [
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'redirect_uri' => $redirect_uri,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
      ]);

      // throw an error if we don't get back an access token
      if(!isset($response->access_token)) {
        die('Error fetching access token');
      }

      // we will use this access token to determine who is currently logged into our app
      // the token has an 'introspection endpoint' which will tell us the current username
      $token = http($metadata->introspection_endpoint, [
        'token' => $response->access_token,
        'client_id' => $client_id,
        'client_secret' => $client_secret,
      ]);
    
      if($token->active == 1) {
        $_SESSION['username'] = $token->username;
        $_SESSION['id_token'] = $response->id_token ?? null; // Store the ID token for logout
        header('Location: /');
        die();
      }
  }

// Start the HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Okta SSO Demo Application</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 2rem;
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
        }
        .logo-container {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .logo {
            max-width: 150px;
            height: auto;
        }
        .btn-login {
            background: linear-gradient(135deg, #007bff, #0056b3);
            border: none;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #0056b3, #003d80);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-logout {
            background: linear-gradient(135deg, #dc3545, #b02a37);
            border: none;
            font-weight: 600;
            padding: 0.75rem 2rem;
            transition: all 0.3s ease;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #b02a37, #842029);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .user-info {
            background-color: #f1f8ff;
            border-left: 4px solid #007bff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .username {
            font-weight: bold;
            font-size: 1.2rem;
            color: #0056b3;
        }
        .footer {
            margin-top: 3rem;
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
        }
        .sso-text {
            font-weight: 300;
            color: #6c757d;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
              <!--
                <div class="logo-container">
                    <img src="https://www.okta.com/sites/default/files/Okta_Logo_BrightBlue_Medium.png" alt="Okta Logo" class="logo">
                </div>
                -->
                <div class="card mb-4">
                    <div class="card-header text-center py-3">
                        Demo SSO Application
                    </div>
                    <div class="card-body text-center">
                        <?php if(isset($_SESSION['username'])): ?>
                            <!-- Logged in view -->
                            <div class="user-info">
                                <div class="mb-2">Successfully authenticated with Okta</div>
                                <div class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                            </div>
                            
                            <p class="text-muted mb-4">You are currently logged in through Okta SSO.</p>
                            
                            <a href="/?logout" class="btn btn-logout btn-lg">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </a>
                            
                        <?php else: ?>
                            <!-- Logged out view -->
                            <h2 class="mb-4">Welcome to the Demo SSO Page</h2>
                            <p class="sso-text">This application demonstrates Single Sign-On (SSO) functionality using Okta as the identity provider.</p>
                            
                            <?php
                            // Generate a random state parameter for CSRF security
                            $_SESSION['state'] = bin2hex(random_bytes(5));

                            // Build the authorization URL by starting with the authorization endpoint
                            // and adding a few query string parameters identifying this application
                            $authorize_url = $metadata->authorization_endpoint.'?'.http_build_query([
                                'response_type' => 'code',
                                'client_id' => $client_id,
                                'redirect_uri' => $redirect_uri,
                                'state' => $_SESSION['state'],
                                'scope' => 'openid profile email',
                            ]);
                            ?>
                            
                            <a href="<?php echo htmlspecialchars($authorize_url); ?>" class="btn btn-login btn-lg">
                                Sign In with Okta Open ID Connect
                            </a>
                            
                            <div class="mt-4">
                                <p class="text-muted">Click the button above to authenticate via SSO</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="footer">
                    <p>This is a demo application for Okta SSO - OIDC integration.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// fn to make http req and return json response
function http($url, $params=false) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // if there are params, turn this into a POST and not a GET
    if($params)
      curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    return json_decode(curl_exec($ch));
  }
?>