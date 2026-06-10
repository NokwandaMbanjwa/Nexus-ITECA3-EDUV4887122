<?php
require_once 'config.php';

if (isLoggedIn()) {
    header('Location: explore-feed.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['login_input'] ?? $_POST['email'] ?? $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    if (empty($login_input) || empty($password)) {
        $error = "Please enter both email/phone and password";
    } else {
        // Determine if input is email or phone
        $is_email = filter_var($login_input, FILTER_VALIDATE_EMAIL);
        
        try {
            if ($is_email) {
                $stmt = $pdo->prepare("
                    SELECT u.*, 
                           COALESCE(bp.full_name, sp.full_name) as full_name,
                           COALESCE(bp.phone_number, sp.phone_number) as phone_number
                    FROM nexus_users u
                    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
                    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
                    WHERE u.email = ?
                ");
                $stmt->execute([$login_input]);
            } else {
                $stmt = $pdo->prepare("
                    SELECT u.*, 
                           COALESCE(bp.full_name, sp.full_name) as full_name,
                           COALESCE(bp.phone_number, sp.phone_number) as phone_number
                    FROM nexus_users u
                    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
                    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
                    WHERE bp.phone_number = ? OR sp.phone_number = ?
                ");
                $stmt->execute([$login_input, $login_input]);
            }
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // BAN CHECK - Added this section
                // Check if account is banned
                if ($user['is_banned'] == 1) {
                    // Check if ban has expired
                    if ($user['ban_expires_at'] && strtotime($user['ban_expires_at']) > time()) {
                        $error = "Your account has been banned until " . date('d M Y', strtotime($user['ban_expires_at'])) . ".<br>Reason: " . htmlspecialchars($user['ban_reason']);
                        // Log failed login attempt
                        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
                        $stmt->execute([$login_input, $_SERVER['REMOTE_ADDR']]);
                        // Don't proceed with login
                        $user = null;
                    } elseif ($user['ban_expires_at'] && strtotime($user['ban_expires_at']) <= time()) {
                        // Ban has expired - automatically unban
                        $stmt = $pdo->prepare("UPDATE nexus_users SET is_banned = 0, banned_at = NULL, banned_by = NULL, ban_reason = NULL, ban_expires_at = NULL WHERE user_id = ?");
                        $stmt->execute([$user['user_id']]);
                        // Continue with login (user is now unbanned)
                    } else {
                        // Permanent ban
                        $error = "Your account has been permanently banned.<br>Reason: " . htmlspecialchars($user['ban_reason']);
                        // Log failed login attempt
                        $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 0)");
                        $stmt->execute([$login_input, $_SERVER['REMOTE_ADDR']]);
                        // Don't proceed with login
                        $user = null;
                    }
                }
                
                // Only proceed if user is not banned
                if ($user) {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['phone'] = $user['phone_number'];
                    
                    // Set remember me cookie if checked (30 days)
                    if ($remember_me) {
                        $token = bin2hex(random_bytes(32));
                        $_SESSION['remember_token'] = $token;
                        setcookie('remember_token', $token, time() + (86400 * 30), "/");
                       
                        $stmt = $pdo->prepare("UPDATE nexus_users SET remember_token = ? WHERE user_id = ?");
                        $stmt->execute([$token, $user['user_id']]);
                    }

                    $_SESSION['sync_wishlist'] = true;
                    
                    // Log successful login
                    $stmt = $pdo->prepare("INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, 1)");
                    $stmt->execute([$login_input, $_SERVER['REMOTE_ADDR']]);
                    
                    header('Location: explore-feed.php');
                    exit;
                }
            } else {
                if ($is_email) {
                    $stmt = $pdo->prepare("SELECT user_id FROM nexus_users WHERE email = ?");
                    $stmt->execute([$login_input]);
                    if (!$stmt->fetch()) {
                        $error = "No account found with this email. Please <a href='register.php'>register</a> first.";
                    } else {
                        $error = "Incorrect password. Please try again.";
                    }
                } else {
                    $error = "Invalid phone number or password. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "Login failed. Please try again later.";
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Login</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.login-wrapper {
				display: flex;
				justify-content: center;
				align-items: center;
				min-height: calc(100vh - 80px);
			}

			.login-container {
				width: 100%;
				max-width: 450px;
				background-color: #19191c;
				padding: 48px 40px;
				border-radius: 24px;
				border: 1px solid rgba(118, 117, 119, 0.1);
			}

			.login-header {
				text-align: center;
				margin-bottom: 32px;
			}

			.login-header .page-title {
				font-size: 36px;
				margin-bottom: 12px;
			}

			.login-header p {
				font-size: 16px;
			}

			.login-method-toggle {
				display: flex;
				gap: 12px;
				margin-bottom: 32px;
				background-color: #0e0e10;
				padding: 4px;
				border-radius: 16px;
			}

			.method-btn {
				flex: 1;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
				padding: 12px 16px;
				background: transparent;
				border: none;
				border-radius: 12px;
				color: #adaaad;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
			}

			.method-btn i {
				font-size: 16px;
			}

			.method-btn:hover {
				color: #8ff5ff;
			}

			.method-btn.active {
				background-color: #8ff5ff;
				color: #0e0e10;
			}

			.login-form .form-group {
				margin-bottom: 24px;
			}

			.form-options {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 32px;
			}

			.forgot-password {
				color: #8ff5ff;
				text-decoration: none;
				font-size: 14px;
				transition: color 0.3s;
			}

			.forgot-password:hover {
				text-decoration: underline;
			}

			.login-submit {
				width: 100%;
				background: #8ff5ff;
				color: #0e0e10;
				font-weight: bold;
				font-size: 18px;
				padding: 14px;
				border-radius: 12px;
				border: none;
				cursor: pointer;
				transition: all 0.3s;
				margin-bottom: 24px;
			}

			.login-submit:hover {
				box-shadow: 0 0 5px #8ff5ff;
				transform: translateY(-2px);
			}

			.login-submit:active {
				transform: translateY(1px);
			}

			.register-prompt {
				text-align: center;
				color: #adaaad;
				font-size: 14px;
			}

			.register-prompt a {
				color: #8ff5ff;
				text-decoration: none;
				font-weight: 600;
			}

			.register-prompt a:hover {
				text-decoration: underline;
			}
			
			.admin-signin {
				text-align: center;
				color: #adaaad;
				font-size: 14px;
			}

			.admin-signin a {
				color: #b6b5d8;
				text-decoration: none;
				font-weight: 600;
			}

			.admin-signin a:hover {
				text-decoration: underline;
			}

			.error-message {
				background: rgba(255, 107, 107, 0.1);
				border: 1px solid #ff6b6b;
				color: #ff6b6b;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
				font-size: 14px;
				text-align: center;
			}

			.success-message {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
				font-size: 14px;
				text-align: center;
			}

			@media (max-width: 768px) {
				.login-container {
					padding: 32px 24px;
					margin: 0 16px;
				}
				.login-header .page-title {
					font-size: 28px;
				}
				.form-options {
					flex-direction: column;
					gap: 16px;
					align-items: flex-start;
				}
				.method-btn span {
					display: none;
				}
				.method-btn i {
					font-size: 20px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="page-content">
			<div class="container">
				<div class="login-wrapper">
					<div class="login-container">
						<header class="login-header">
							<h1 class="page-title">WELCOME BACK</h1>
							<p>Sign in to your Nexus account</p>
						</header>
						
						<?php if ($error): ?>
							<div class="error-message"><?php echo $error; ?></div>
						<?php endif; ?>
						
						<?php if ($success): ?>
							<div class="success-message"><?php echo $success; ?></div>
						<?php endif; ?>
						
						<form id="loginForm" class="login-form" method="post" action="">
							<!-- Login Method Toggle -->
							<div class="login-method-toggle" role="group" aria-label="Login method selection">
								<button type="button" class="method-btn active" data-method="email" aria-pressed="true">
									<i class="fas fa-envelope"></i>
									<span>Email</span>
								</button>
								<button type="button" class="method-btn" data-method="phone" aria-pressed="false">
									<i class="fas fa-phone"></i>
									<span>Phone</span>
								</button>
							</div>
							
							<div class="form-group" id="emailField">
								<label for="login_input_email">Email Address</label>
								<input type="email" id="email" name="email" placeholder="you@example.com" required>
							</div>
							
							<div class="form-group" id="phoneField" style="display: none;">
								<label for="login_input_phone">Phone Number</label>
								<input type="tel" id="phone" name="phone" placeholder="+27 XX XXX XXXX">
							</div>
							
							<div class="form-group">
								<label for="password">Password</label>
								<input type="password" id="password" name="password" placeholder="Enter your password" required>
							</div>
							
							<div class="form-options">
								<label class="checkbox-label">
									<input type="checkbox" name="remember_me" id="rememberMe">
									<span>Remember me</span>
								</label>
								<a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
							</div>
							
							<button type="submit" class="login-submit">Sign In</button>
							
							<div class="register-prompt">
								Don't have an account? <a href="register.php">Create one now</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</main>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
        // Toggle between email and phone login
        const emailMethodBtn = document.querySelector('.method-btn[data-method="email"]');
        const phoneMethodBtn = document.querySelector('.method-btn[data-method="phone"]');
        const emailField = document.getElementById('emailField');
        const phoneField = document.getElementById('phoneField');
        const emailInput = document.getElementById('email');
        const phoneInput = document.getElementById('phone');
        
        function switchLoginMethod(method) {
            if (method === 'phone') {
                // Show phone field, hide email field
                emailField.style.display = 'none';
                phoneField.style.display = 'block';
                
                phoneMethodBtn.classList.add('active');
                emailMethodBtn.classList.remove('active');
                
                phoneMethodBtn.setAttribute('aria-pressed', 'true');
                emailMethodBtn.setAttribute('aria-pressed', 'false');
                
                emailInput.removeAttribute('required');
                phoneInput.setAttribute('required', 'required');
            } else {
                // Show email field, hide phone field
                emailField.style.display = 'block';
                phoneField.style.display = 'none';

                emailMethodBtn.classList.add('active');
                phoneMethodBtn.classList.remove('active');
                
                emailMethodBtn.setAttribute('aria-pressed', 'true');
                phoneMethodBtn.setAttribute('aria-pressed', 'false');
                
                phoneInput.removeAttribute('required');
                emailInput.setAttribute('required', 'required');
            }
        }
        
        if (emailMethodBtn) {
            emailMethodBtn.addEventListener('click', () => switchLoginMethod('email'));
        }
        
        if (phoneMethodBtn) {
            phoneMethodBtn.addEventListener('click', () => switchLoginMethod('phone'));
        }
        
        // Sync local wishlist with database after login
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            localStorage.setItem('sync_wishlist_on_login', 'true');
        });
        
        if (localStorage.getItem('sync_wishlist_on_login') === 'true') {
            const localWishlist = loadWishlist();
            if (localWishlist.length > 0) {
                sessionStorage.setItem('pending_wishlist_sync', JSON.stringify(localWishlist));
            }
            localStorage.removeItem('sync_wishlist_on_login');
        }
        
        // Remove error styles when user starts typing
        const inputs = document.querySelectorAll('input');
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
                const errorMsg = this.parentNode.querySelector('.error-message');
                if (errorMsg) errorMsg.remove();
            });
        });
    </script> 
	</body>
</html>