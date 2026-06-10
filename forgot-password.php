<?php
require_once 'config.php';
require_once 'mail-config.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Please enter your email address";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format";
    } else {
        $stmt = $pdo->prepare("SELECT user_id, email FROM nexus_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $stmt = $pdo->prepare("UPDATE nexus_users SET reset_token = ?, reset_expires = ? WHERE user_id = ?");
            $stmt->execute([$token, $expires, $user['user_id']]);

            $reset_link = "https://nexus.infinityfree.me/reset-password.php?token=$token";
            
            $subject = "NEXUS - Password Reset";
            $body = "
                <h2>Password Reset Request</h2>
                <p>Click the link below to reset your password:</p>
                <p><a href='$reset_link'>$reset_link</a></p>
                <p>This link expires in 1 hour.</p>
                <p>If you didn't request this, ignore this email.</p>
            ";

            sendEmail($email, $subject, $body);
        }
        
        $message = "If an account exists with this email, a password reset link has been sent. Please check your inbox.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NEXUS | Forgot Password</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="basestyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

</head>
<body>
    <?php include 'header.php'; ?>
    
    <main class="page-content">
        <div class="container">
            <div class="login-wrapper" style="max-width: 450px; margin: 0 auto;">
                <div class="login-container">
                    <header class="login-header">
                        <h1 class="page-title">RESET PASSWORD</h1>
                        <p>Enter your email to receive reset instructions</p>
                    </header>
                    
                    <?php if ($error): ?>
                        <div class="error-message"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($message): ?>
                        <div class="success-message"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <button type="submit" class="btn-primary-transparent">Send Reset Link</button>
                        
                        <div class="register-prompt">
                            <a href="login.php" style="color: #00FFFF; text-decoration: underline;">Back to Login</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>
</body>
</html>