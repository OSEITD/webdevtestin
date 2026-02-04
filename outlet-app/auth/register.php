<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../outlet_dashboard.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Outlet App</title>
    <link rel="stylesheet" href="../css/registry-styles.css">
</head>
<body>
    <div class="container">
        <h2>Outlet Registration</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div class="form-group">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <button type="submit" class="btn">Register</button>
        </form>
        <p>Already have an account? <a href="../login.php">Login here</a></p>
    </div>
</body>
</html>
