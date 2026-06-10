<?php
require_once 'config.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

// Verify token
$stmt = $pdo->prepare("SELECT user_id, email FROM nexus_users WHERE reset_token = ? AND reset_expires > NOW()");
$stmt->execute([$token]);
$user = $stmt->fetch();

if (!$user) {
    $error = "Invalid or expired reset link. Please request a new one.";
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match";
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE nexus_users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE user_id = ?");
        $stmt->execute([$hash, $user['user_id']]);
        $success = "Password reset successfully! You can now login.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>NEXUS | Reset Password</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="basestyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

    <style>
        .reset-page {
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			background: #0a0a0a;
			padding: 20px;
		}

		.reset-card {
			max-width: 450px;
			width: 100%;
			background: #131315;
			border: 1px solid #2a2a2a;
			border-radius: 16px;
			padding: 40px;
		}

		.reset-header {
			text-align: center;
			margin-bottom: 32px;
		}

		.reset-header h1 {
			font-size: 28px;
			color: #8ff5ff;
			margin-bottom: 8px;
		}

		.alert {
			padding: 12px;
			border-radius: 8px;
			margin-bottom: 20px;
			font-size: 14px;
		}

		.alert-error {
			background: rgba(255, 68, 68, 0.1);
			border: 1px solid #ff4444;
			color: #ff4444;
		}

		.alert-success {
			background: rgba(76, 175, 80, 0.1);
			border: 1px solid #4caf50;
			color: #4caf50;
		}

		.form-group {
			margin-bottom: 20px;
		}

		.form-group label {
			display: block;
			margin-bottom: 8px;
			color: #aaa;
			font-size: 13px;
		}

		.form-group input {
			width: 100%;
			padding: 12px;
			background: #0e0e10;
			border: 1px solid #2a2a2a;
			border-radius: 8px;
			color: #e5e5e5;
			font-size: 14px;
		}

		.form-group input:focus {
			outline: none;
			border-color: #8ff5ff;
		}

		.btn-submit {
			width: 100%;
			padding: 12px;
			background: #8ff5ff;
			color: #0e0e10;
			border: none;
			border-radius: 8px;
			font-size: 16px;
			font-weight: 600;
			cursor: pointer;
		}

		.back-link {
			text-align: center;
			margin-top: 20px;
		}

		.back-link a {
			color: #8ff5ff;
			text-decoration: none;
			font-size: 13px;
		}
		
		@media (max-width: 768px) {
			.reset-page {
				padding: 16px;
			}

			.reset-card {
				padding: 24px 20px;
				border-radius: 12px;
			}

			.reset-header h1 {
				font-size: 24px;
			}

			.alert {
				padding: 10px;
				font-size: 13px;
			}

			.form-group label {
				font-size: 12px;
			}

			.form-group input {
				padding: 10px;
				font-size: 13px;
			}

			.btn-submit {
				padding: 10px;
				font-size: 15px;
			}

			.back-link a {
				font-size: 12px;
			}
		}

		@media (max-width: 480px) {
			.reset-page {
				padding: 12px;
			}

			.reset-card {
				padding: 20px 16px;
				border-radius: 10px;
			}

			.reset-header {
				margin-bottom: 24px;
			}

			.reset-header h1 {
				font-size: 22px;
			}

			.form-group {
				margin-bottom: 16px;
			}

			.form-group label {
				font-size: 11px;
				margin-bottom: 6px;
			}

			.form-group input {
				padding: 10px;
				font-size: 13px;
			}

			.btn-submit {
				padding: 10px;
				font-size: 14px;
			}
		}

		@media (max-width: 360px) {
			.reset-card {
				padding: 16px 14px;
			}

			.reset-header h1 {
				font-size: 20px;
			}

			.alert {
				padding: 8px;
				font-size: 12px;
			}

			.form-group input {
				padding: 8px;
				font-size: 12px;
			}

			.btn-submit {
				padding: 9px;
				font-size: 13px;
			}

			.back-link a {
				font-size: 11px;
			}
		}
    </style>
</head>
	<body>
		<div class="reset-page">
			<div class="reset-card">
				<div class="reset-header">
					<h1><i class="fas fa-key"></i> Reset Password</h1>
				</div>
				
				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				<?php if ($success): ?>
					<div class="alert alert-success"><?php echo $success; ?> <a href="login.php" style="color: #8ff5ff;">Login here</a></div>
				<?php elseif ($user): ?>
					<form method="post">
						<div class="form-group"><label>New Password</label><input type="password" name="password" required></div>
						<div class="form-group"><label>Confirm Password</label><input type="password" name="confirm_password" required></div>
						<button type="submit" class="btn-submit">Reset Password</button>
					</form>
				<?php endif; ?>
				
				<div class="back-link"><a href="login.php"><i class="fas fa-arrow-left"></i> Back to Login</a></div>
			</div>
		</div>
	</body>
</html>