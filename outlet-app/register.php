 <?php
ob_start();
session_start();

$supabaseUrl = getenv('SUPABASE_URL') ?: 'https://xerpchdsykqafrsxbqef.supabase.co';
$supabaseKey = getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';

ob_end_clean(); 

$headers = [
  "apikey: $supabaseKey",
  "Authorization: Bearer $supabaseKey",
  "Content-Type: application/json"
];

$host = $_SERVER['HTTP_HOST'];
$hostParts = explode('.', $host);
if (count($hostParts) >= 3) {
  $subdomain = $hostParts[0]; 
} elseif (count($hostParts) == 2 && $hostParts[1] === 'localhost') {
  $subdomain = $hostParts[0]; 
} else {
  $subdomain = null;
}
error_log("Detected subdomain: " . ($subdomain ?? 'null'));

$companyId = null;
$outlets = [];
$errorMessage = null;

if ($subdomain) {
  
  $companyContext = stream_context_create([
    'http' => [
      'method' => 'GET',
      'header' => implode("\r\n", $headers)
    ]
  ]);
  $companyUrl = "$supabaseUrl/rest/v1/companies?subdomain=eq.$subdomain&select=id,name";
  $companyResponse = @file_get_contents($companyUrl, false, $companyContext);
  $companyData = json_decode($companyResponse, true);
  error_log("Company data: " . print_r($companyData, true));

  if (!empty($companyData) && isset($companyData[0]['id'])) {
    $companyId = $companyData[0]['id'];

    
    $outletUrl = "$supabaseUrl/rest/v1/outlets?company_id=eq.$companyId&select=id,name";
    $outletContext = stream_context_create([
      'http' => [
        'method' => 'GET',
        'header' => implode("\r\n", $headers)
      ]
    ]);
    $outletResponse = @file_get_contents($outletUrl, false, $outletContext);
    $outlets = json_decode($outletResponse, true) ?? [];
    error_log("Outlets: " . print_r($outlets, true));
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = $_SERVER['HTTP_HOST'];
  $subdomain = explode('.', $host)[0];
  
  $firstName = $_POST['first_name'];
  $lastName = $_POST['last_name'];
  $email = $_POST['email'];
  $password = $_POST['password'];
  $phone = $_POST['tel'];
  $role = $_POST['role'] ?? 'driver';
  $outletId = $_POST['outlet_id'] ?? null;

 $authPayload = json_encode([
  'email' => $email,
  'password' => $password,
  'options' => [
    'data' => [
      'full_name'   => "$firstName $lastName",
      'phone'       => $phone,
      'role'        => $role,
      'company_id'  => $companyId,
      'outlet_id'   => $outletId,
      'language'    => $_POST['language'] ?? 'en'
    ]
  ]
]);

  $ch = curl_init("$supabaseUrl/auth/v1/signup");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $authPayload
  ]);
  $response = curl_exec($ch);
  if (curl_errno($ch)) {
    error_log("Curl error during signup: " . curl_error($ch));
  }
  curl_close($ch);
  error_log("Supabase signup response: " . $response);
  $userData = json_decode($response, true);

  if (isset($userData['error'])) {
    error_log("Supabase signup error: " . print_r($userData, true));
    $errorMessage = "Auth Error: " . $userData['error']['message'];
  }

  $userId = $userData['user']['id'];

  
  if ($userId) {
    $profilePayload = json_encode([
      'id' => $userId,
      'full_name' => "$firstName $lastName",
      'phone' => $phone,
      'role' => $role,
      'company_id' => $companyId,
      'outlet_id' => ($outletId && $role === 'outlet_manager') ? $outletId : null,
      'language' => $_POST['language'] ?? 'en'
    ]);
    $profileContext = stream_context_create([
      'http' => [
        'method' => 'POST',
        'header' => implode("\r\n", $headers),
        'content' => $profilePayload
      ]
    ]);
    $profileResponse = @file_get_contents("$supabaseUrl/rest/v1/profiles", false, $profileContext);
    if ($profileResponse === false) {
      error_log("Failed to insert user profile for user ID: $userId");
    } else {
      $responseData = json_decode($profileResponse, true);
      if (isset($responseData['code']) && $responseData['code'] >= 400) {
        error_log("Supabase profile insert error: " . print_r($responseData, true));
      }
    }
  }

  header("Location: login.php?registered=success");
  exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Register</title>
  <link rel="stylesheet" href="./css/registry-styles.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="container">
    <img src="img/logo.png" alt="App Logo" class="logo" />
    <div class="form-box">
      <h2>Create Account</h2>
      <form action="register.php" method="POST">
        <div class="input-container"><i class="fa fa-user"></i>
          <input type="text" name="first_name" placeholder="First Name" required />
        </div>
        <div class="input-container"><i class="fa fa-user"></i>
          <input type="text" name="last_name" placeholder="Surname" required />
        </div>
        <div class="input-container"><i class="fa fa-envelope"></i>
          <input type="email" name="email" placeholder="Email Address" required />
        </div>
        <div class="input-container"><i class="fa fa-phone"></i>
          <input type="tel" name="tel" placeholder="Phone Number" required />
        </div>
        <div class="input-container"><i class="fa fa-lock"></i>
          <input type="password" name="password" placeholder="Password" required />
        </div>
        <div class="input-container"><i class="fa fa-lock"></i>
          <input type="password" name="confirm" placeholder="Confirm Password" required />
        </div>
        <div class="input-container"><i class="fa fa-user-tag"></i>
          <select name="role" id="role" required onchange="toggleOutletSelect()">
            <option value="" disabled selected>Select Role</option>
            <option value="driver">Driver</option>
            <option value="outlet_manager">Outlet Manager</option>
          </select>
        </div>
        <div class="input-container" id="outlet-select-container" style="display:none;">
          <i class="fa fa-store"></i>
          <select name="outlet_id" id="outlet_id">
            <option value="" disabled selected>Select Outlet</option>
            <?php foreach ($outlets as $outlet): ?>
              <option value="<?= htmlspecialchars($outlet['id']) ?>"><?= htmlspecialchars($outlet['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="input-container">
          <i class="fa fa-language"></i>
          <select name="language" id="language" required>
            <option value="en" selected>English</option>
            <option value="fr">French</option>
            <option value="es">Spanish</option>
            <!-- Add more languages as needed -->
          </select>
        </div>

        <button type="submit" class="btn">Register</button>
        <div>or</div>
        <button type="button" class="google-btn">
          <img src="icons/google.png" />Register with Google
        </button>
        <p class="switch-link">
          Already have an account? <a href="login.php">Login</a>
        </p>
      </form>
    </div>
  </div>

<script>
function toggleOutletSelect() {
  const roleSelect = document.getElementById('role');
  const outletContainer = document.getElementById('outlet-select-container');
  const outletIdField = document.getElementById('outlet_id');
  if (roleSelect.value === 'outlet_manager') {
    outletContainer.style.display = 'block';
    outletIdField.setAttribute('required', 'required');
  } else {
    outletContainer.style.display = 'none';
    outletIdField.removeAttribute('required');
  }
}
</script>

</body>
</html>
