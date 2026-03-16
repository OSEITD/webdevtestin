<?php
session_start();
include_once __DIR__ . '/../includes/header.php';

$supabaseUrl = 'https://xerpchdsykqafrsxbqef.supabase.co';
$supabaseKey = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhlcnBjaGRzeWtxYWZyc3hicWVmIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTI3NjQ5NTcsImV4cCI6MjA2ODM0MDk1N30.g2XzfiG0wwgLUS4on2GbSmxnWAog6tW5Am5SvhBHm5E';
$headers = [
  "apikey: $supabaseKey",
  "Content-Type: application/json",
  "Authorization: Bearer $supabaseKey"
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $host = $_SERVER['HTTP_HOST'];
  $subdomain = explode('.', $host)[0];

  $firstName = $_POST['first_name'];
  $lastName = $_POST['last_name'];
  $email = $_POST['email'];
  $password = $_POST['password'];
  $phone = $_POST['tel'];

  // 1. Register User in Supabase Auth
  $authPayload = json_encode(['email' => $email, 'password' => $password]);

  $ch = curl_init("$supabaseUrl/auth/v1/signup");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $authPayload
  ]);
  $response = curl_exec($ch);
  curl_close($ch);
  $userData = json_decode($response, true);

  if (isset($userData['error'])) {
    exit("Auth Error: " . $userData['error']['message']);
  }

  $userId = $userData['user']['id'];

  // 2. Check if company already exists for this subdomain
  $companyCheck = file_get_contents("$supabaseUrl/rest/v1/companies?subdomain=eq.$subdomain", false, stream_context_create([
    'http' => ['method' => 'GET', 'header' => implode("\r\n", $headers)]
  ]));
  $companies = json_decode($companyCheck, true);

  if (empty($companies)) {
    // 3. Create new company
    $companyPayload = json_encode([
      'name' => ucfirst($subdomain) . " Company",
      'subdomain' => $subdomain
    ]);
    $createContext = stream_context_create([
      'http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $companyPayload]
    ]);
    $createdCompany = file_get_contents("$supabaseUrl/rest/v1/companies", false, $createContext);
    $company = json_decode($createdCompany, true)[0];
  } else {
    $company = $companies[0];
  }

  $companyId = $company['id'];

  // 4. Create default outlet
  $outletPayload = json_encode([
    'company_id' => $companyId,
    'name' => 'Main Branch',
    'location' => 'Default location'
  ]);
  file_get_contents("$supabaseUrl/rest/v1/outlets", false, stream_context_create([
    'http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $outletPayload]
  ]));

  // 5. Insert profile record
  $profilePayload = json_encode([
    'id' => $userId,
    'full_name' => "$firstName $lastName",
    'phone' => $phone,
    'role' => 'company_admin',
    'company_id' => $companyId
  ]);
  file_get_contents("$supabaseUrl/rest/v1/profiles", false, stream_context_create([
    'http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $profilePayload]
  ]));

  // 6. Redirect to login
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
  <link rel="manifest" href="manifest.json">
  <meta name="theme-color" content="#2e0b3f">
  <link rel="stylesheet" href="../assets/css/registry-styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;500;700&display=swap" rel="stylesheet" />
</head>
<body>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
      navigator.serviceWorker.register('service-worker.js');
    });
  }
</script>
  <div class="container">
    <img src="../assets/img/logo.png" alt="App Logo" class="logo" />
    <div class="form-box">
      <h2>Create Account</h2>
      <form id="register-form" action="register.php" method="POST">
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
        <div>
          <button type="submit" class="btn">Register</button>
          <div> or </div>
          <button type="button" class="google-btn">
            <img src="../assets/icons/google.png" />Register with Google
          </button>
        </div>
        <p class="switch-link">
          Already have an account? <a href="login.php">Login</a>
        </p>
      </form>
    </div>
  </div>


</body>
</html>
<?php if (!empty($error)) : ?>
  <script>alert("<?= htmlspecialchars($error) ?>");</script>
<?php endif; ?>

<?php if (isset($_GET['registered']) && $_GET['registered'] === 'success') : ?>
  <script>alert('Registered successfully! You can now log in.');</script>
<?php endif; ?>
