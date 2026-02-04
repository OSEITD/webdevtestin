<?php
session_start();

$valid_username = "manager"; 
$valid_password = "password"; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    
    if ($username === $valid_username && $password === $valid_password) {
        $_SESSION['outlet_manager_name'] = $username; 
        header("Location: ../outlet_dashboard.php"); 
        exit;
    } else {
        echo "Invalid credentials. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
</head>
<body>
    <h2>Login</h2>
    <form method="POST" action="">
        <label for="username">Username:</label>
        <input type="text" id="username" name="username" required>
        <br>
        <label for="password">Password:</label>
        <div class="input-container" style="margin-bottom:1.25rem;">
            <i class="fas fa-lock" aria-hidden="true"></i>
            <input type="password" id="password" name="password" required>
            <button type="button" id="togglePassword" class="toggle-password" aria-label="Show password" title="Show password">
                <svg id="iconEye" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4b5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
                <svg id="iconEyeOff" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#4b5563" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:none;">
                    <path d="M17.94 17.94A10.06 10.06 0 0 1 12 20c-7 0-11-8-11-8a21.84 21.84 0 0 1 5.06-6.16"></path>
                    <path d="M1 1l22 22"></path>
                    <path d="M9.88 9.88A3 3 0 0 0 14.12 14.12"></path>
                </svg>
            </button>
        </div>
        <br>
        <button type="submit">Login</button>
    </form>

    <script>
    // Toggle password visibility
    (function(){
        const toggle = document.getElementById('togglePassword');
        const pwd = document.getElementById('password');
        if (!toggle || !pwd) return;
        toggle.addEventListener('click', function(){
            if (pwd.type === 'password') {
                pwd.type = 'text';
                toggle.setAttribute('aria-label', 'Hide password');
                toggle.title = 'Hide password';
                toggle.setAttribute('aria-pressed', 'true');
                document.getElementById('iconEye').style.display = 'none';
                document.getElementById('iconEyeOff').style.display = 'inline-block';
            } else {
                pwd.type = 'password';
                toggle.setAttribute('aria-label', 'Show password');
                toggle.title = 'Show password';
                toggle.setAttribute('aria-pressed', 'false');
                document.getElementById('iconEye').style.display = 'inline-block';
                document.getElementById('iconEyeOff').style.display = 'none';
            }
        });
    })();
    </script>
</body>
</html>
