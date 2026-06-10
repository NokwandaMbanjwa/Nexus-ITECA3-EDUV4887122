<?php
require_once 'config.php';

if (isLoggedIn() && isAdmin()) {
    header('Location: admin-dashboard.php');
    exit;
}

$error = '';
$show_success = '';

if (isset($_GET['message']) && $_GET['message'] == 'application_submitted') {
    $access_code = htmlspecialchars($_GET['code'] ?? '');
    $show_success = "Your admin application has been submitted successfully!<br>
                     <strong>Access Code: " . $access_code . "</strong><br>
                     Use this code along with your password to login once approved.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $access_code = trim($_POST['access_code'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } elseif (empty($access_code)) {
        $error = "Please enter your access code";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM nexus_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            if (!$user['admin_approved']) {
                $error = "Your admin application has not been approved yet.";
            } elseif (!$user['admin_access_code']) {
                $error = "No access code assigned. Please contact super admin.";
            } elseif ($user['admin_access_code'] !== $access_code) {
                $error = "Invalid access code. Please check and try again.";
            } else {
                $full_name = $user['full_name'] ?? '';
                if (empty($full_name)) {
                    $stmt = $pdo->prepare("SELECT full_name FROM seller_profiles WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);
                    $profile = $stmt->fetch();
                    if (!$profile) {
                        $stmt = $pdo->prepare("SELECT full_name FROM buyer_profiles WHERE user_id = ?");
                        $stmt->execute([$user['user_id']]);
                        $profile = $stmt->fetch();
                    }
                    $full_name = $profile['full_name'] ?? 'Admin';
                }
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_role'] = $user['admin_role'];
                $_SESSION['full_name'] = $full_name;
                
                $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, ip_address) VALUES (?, 'login', ?)");
                $stmt->execute([$user['user_id'], $_SERVER['REMOTE_ADDR']]);
                
                header('Location: admin-dashboard.php');
                exit;
            }
        } else {
            $error = "Invalid email or password";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Admin Login</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.admin-login-page {
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
				background: #0a0a0a;
				padding: 20px;
			}
			
			.login-card {
				max-width: 450px;
				width: 100%;
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 16px;
				padding: 40px;
			}
			
			.login-header {
				text-align: center;
				margin-bottom: 32px;
			}
			
			.login-header h1 {
				font-size: 28px;
				color: #9457eb;
				margin-bottom: 8px;
			}
			
			.login-header p {
				font-size: 14px;
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
			
			.code-hint {
				font-size: 11px;
				color: #666;
				margin-top: 5px;
			}
			
			.form-group {
				margin-bottom: 20px;
			}
			
			.form-group label {
				display: block;
				margin-bottom: 8px;
				font-size: 13px;
			}
			
			.form-group input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
			}
			
			.form-group input:focus {
				outline: none;
				border-color: #875692;
			}
			
			.btn-primary {
				width: 100%;
				padding: 12px;
				background: #875692;
				color: #000000;
				border: none;
				border-radius: 8px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
			}
			
			.btn-primary:hover {
				box-shadow: 0 0 5px #875692;
				transform: translateY(-2px);
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
			
			@media (max-width: 480px) {
				.admin-login-page {
					padding: 16px;
				}
				.login-card {
					padding: 24px 20px;
				}
				.login-header h1 {
					font-size: 24px;
				}
				.login-header p {
					font-size: 13px;
				}
				.form-group input {
					padding: 10px;
					font-size: 14px;
				}
				.btn-primary {
					padding: 10px;
					font-size: 15px;
				}
			}

			@media (max-width: 360px) {
				.login-card {
					padding: 20px 16px;
				}
				.login-header h1 {
					font-size: 22px;
				}
			}
		</style>
	</head>

	<body>
		<div class="admin-login-page">
			<div class="login-card">
				<div class="login-header">
					<h1><i class="fas fa-shield-alt"></i> Admin Portal</h1>
					<p>Secure access for Nexus administrators</p>
				</div>
				
				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<?php if ($show_success): ?>
					<div class="alert alert-success"><?php echo $show_success; ?></div>
				<?php endif; ?>
				
				<form method="post" action="">
					<div class="form-group">
						<label>Email Address</label>
						<input type="email" name="email" required>
					</div>
					<div class="form-group">
						<label>Password</label>
						<input type="password" name="password" required>
					</div>
					<div class="form-group">
						<label>Access Code</label>
						<input type="text" name="access_code" placeholder="Enter your access code" required>
						<div class="code-hint">You received this code when you were granted admin access</div>
					</div>
					<button type="submit" class="btn-primary">Login to Admin Portal</button>
				</form>
				
				<div class="back-link">
					<a href="explore-feed.php"> Back to Main Site</a>
				</div>
			</div>
		</div>
	</body>
</html>