<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $applicant_role = $_POST['applicant_role'] ?? '';
    $requested_department = $_POST['requested_department'] ?? '';
    $id_number = trim($_POST['id_number'] ?? '');
    $year_of_birth = (int)($_POST['year_of_birth'] ?? 0);
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required";
    }

    $stmt = $pdo->prepare("SELECT user_id, user_type FROM nexus_users WHERE email = ?");
    $stmt->execute([$email]);
    $existing_user = $stmt->fetch();
    
    if (!$existing_user) {
        $errors[] = "No Nexus account found with this email. Please <a href='Register.php'>create an account</a> first.";
    }
    
    if (empty($applicant_role)) {
        $errors[] = "Please select your current role";
    }
    
    if (empty($requested_department)) {
        $errors[] = "Please select the department you want to join";
    }
    
    if (empty($year_of_birth)) {
        $errors[] = "Year of birth is required";
    } elseif ($year_of_birth < 1920 || $year_of_birth > 2008) {
        $errors[] = "You must be at least 18 years old to apply";
    }
    
    if (empty($id_number)) {
        $errors[] = "ID number is required";
    } elseif (!preg_match('/^[0-9]{13}$/', $id_number)) {
        $errors[] = "ID number must be 13 digits";
    } else {
        $year = (int)substr($id_number, 0, 2);
        $month = (int)substr($id_number, 2, 2);
        $day = (int)substr($id_number, 4, 2);
        
        $current_year = date('Y');
        $century = ($year <= 24) ? 2000 : 1900;
        $birth_year = $century + $year;
        
        if ($year_of_birth !== $birth_year) {
            $errors[] = "Year of birth does not match ID number";
        }
        
        if (checkdate($month, $day, $birth_year)) {
            $birth_date = new DateTime("$birth_year-$month-$day");
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;
            
            if ($age < 20) {
                $errors[] = "You must be at least 20 years old to become an admin. Your age: $age years";
            }
        } else {
            $errors[] = "Invalid date in ID number";
        }
    }
    
    // Check for existing pending/approved application
    $stmt = $pdo->prepare("
        SELECT a.* FROM admin_applications a
        JOIN nexus_users u ON a.user_id = u.user_id
        WHERE u.email = ? AND a.application_status IN ('pending', 'approved')
    ");
    $stmt->execute([$email]);
    $existing_application = $stmt->fetch();
    
    if ($existing_application) {
        $errors[] = "You already have a " . $existing_application['application_status'] . " admin application. Please wait for review.";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $user_id = $existing_user['user_id'];
            
            // Generate unique access code
            $access_code = 'NEX' . strtoupper(substr($requested_department, 0, 3)) . rand(100, 999);
            
            if ($applicant_role === 'buyer' || $applicant_role === 'both') {
                $stmt = $pdo->prepare("SELECT profile_id FROM buyer_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE buyer_profiles SET full_name = ?, phone_number = ?, id_passport_number = ? WHERE user_id = ?");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO buyer_profiles (user_id, full_name, phone_number, id_passport_number) VALUES (?, ?, ?, ?)");
                }
                $stmt->execute([$user_id, $full_name, $phone, $id_number]);
            }
            
            if ($applicant_role === 'seller' || $applicant_role === 'both') {
                $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                if ($stmt->fetch()) {
                    $stmt = $pdo->prepare("UPDATE seller_profiles SET full_name = ?, phone_number = ?, id_passport_number = ? WHERE user_id = ?");
                } else {
                    $stmt = $pdo->prepare("INSERT INTO seller_profiles (user_id, full_name, phone_number, id_passport_number) VALUES (?, ?, ?, ?)");
                }
                $stmt->execute([$user_id, $full_name, $phone, $id_number]);
            }
            
            $stmt = $pdo->prepare("UPDATE nexus_users SET admin_role = ?, admin_approved = 0, admin_access_code = ?, admin_request_date = NOW() WHERE user_id = ?");
            $stmt->execute([$requested_department, $access_code, $user_id]);

            $year = (int)substr($id_number, 0, 2);
            $century = ($year <= 24) ? 2000 : 1900;
            $birth_year = $century + $year;
            $month = (int)substr($id_number, 2, 2);
            $day = (int)substr($id_number, 4, 2);
            $birth_date = new DateTime("$birth_year-$month-$day");
            $today = new DateTime();
            $age = $today->diff($birth_date)->y;

            $stmt = $pdo->prepare("
                INSERT INTO admin_applications (user_id, applicant_role, requested_department, id_number, year_of_birth, age, application_status) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$user_id, $applicant_role, $requested_department, $id_number, $year_of_birth, $age]);
            
            $pdo->commit();
            
            $_POST = array();
            $_SESSION['app_submitted'] = true;
            header('Location: messages.php?applied=1');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to submit application. Please try again later.";
            error_log("Admin signup error: " . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Admin Application</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.signup-page {
				padding: 40px 0 80px;
				min-height: 100vh;
			}

			.signup-container {
				max-width: 700px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.signup-header {
				text-align: center;
				margin-bottom: 32px;
			}

			.signup-header h1 {
				font-size: 36px;
				color: #b026ff;
				margin-bottom: 8px;
			}

			.signup-header p {
				color: #888;
				font-size: 14px;
			}

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
			}

			.alert-error {
				background: rgba(255, 68, 68, 0.1);
				border: 1px solid #ff4444;
				color: #ff4444;
			}

			.form-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 16px;
				padding: 32px;
			}

			.form-group {
				margin-bottom: 20px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				font-size: 13px;
				font-weight: 500;
			}

			.form-group label .required {
				color: #ff4444;
			}

			.form-group input,
			.form-group select {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 14px;
			}

			.form-group input:focus,
			.form-group select:focus {
				outline: none;
				border-color: #6f2da8;
			}

			.form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
			}

			.password-hint {
				font-size: 11px;
				color: #666;
				margin-top: 5px;
			}

			.existing-user-note {
				background: rgba(176, 38, 255, 0.05);
				border: 1px solid #b026ff;
				border-radius: 8px;
				padding: 12px;
				margin-bottom: 20px;
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.existing-user-note i {
				color: #b026ff;
				font-size: 20px;
			}

			.existing-user-note p {
				color: #b026ff;
				font-size: 13px;
				margin: 0;
			}

			.btn-submit {
				width: 100%;
				padding: 14px;
				background: #875692;
				color: #000000;
				border: none;
				border-radius: 8px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
				margin-top: 10px;
			}

			.btn-submit:hover {
				background: #875692;
				transform: translateY(-2px);
			}

			.login-link {
				text-align: center;
				margin-top: 24px;
				font-size: 13px;
			}

			.login-link a {
				color: #b026ff;
				text-decoration: none;
			}

			.id-hint {
				font-size: 11px;
				color: #666;
				margin-top: 5px;
			}

			@media (max-width: 768px) {
				.signup-page {
					padding: 40px 0 60px;
				}

				.form-card {
					padding: 24px;
				}

				.form-row {
					grid-template-columns: 1fr;
					gap: 0;
				}

				.signup-header h1 {
					font-size: 28px;
				}
			}
			@media (max-width: 480px) {
				.signup-page {
					padding: 40px 0 40px;
				}
				.signup-container {
					padding: 0 16px;
				}
				.signup-header h1 {
					font-size: 24px;
				}
				.signup-header p {
					font-size: 13px;
				}
				.form-card {
					padding: 20px 16px;
				}
				.form-group input,
				.form-group select {
					padding: 10px;
					font-size: 13px;
				}
				.existing-user-note {
					padding: 10px;
					gap: 8px;
				}
				.existing-user-note i {
					font-size: 18px;
				}
				.existing-user-note p {
					font-size: 12px;
				}
				.btn-submit {
					padding: 12px;
					font-size: 15px;
				}
			}

			@media (max-width: 360px) {
				.signup-header h1 {
					font-size: 22px;
				}
				.form-card {
					padding: 16px 12px;
				}
			}
	</style>
	<body>
		<?php include 'header.php'; ?>
		<main class="signup-page">
			<div class="signup-container">
				<div class="signup-header">
					<h1><i class="fas fa-shield-alt"></i> Admin Application</h1>
					<p>Apply to join the Nexus administration team</p>
				</div>
				
				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<div class="form-card">
					<div class="existing-user-note">
						<i class="fas fa-info-circle"></i>
						<p>You must have an existing Nexus account to apply. You'll use your current password to log in as admin once approved.</p>
					</div>
					
					
					<form method="post" action="" id="adminSignupForm">
						<div class="form-row">
							<div class="form-group">
								<label>Full Name <span class="required">*</span></label>
								<input type="text" name="full_name" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" required>
							</div>
							<div class="form-group">
								<label>Email Address <span class="required">*</span></label>
								<input type="email" name="email" id="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
								<div class="password-hint" id="emailHint"></div>
							</div>
						</div>
						
						<div class="form-row">
							<div class="form-group">
								<label>Phone Number <span class="required">*</span></label>
								<input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" required>
							</div>
							<div class="form-group">
								<label>ID Number <span class="required">*</span></label>
								<input type="text" name="id_number" id="idNumber" maxlength="13" value="<?php echo htmlspecialchars($_POST['id_number'] ?? ''); ?>" required>
								<div class="id-hint">13-digit South African ID number</div>
							</div>
						</div>
						
						<div class="form-row">
							<div class="form-group">
								<label>Year of Birth <span class="required">*</span></label>
								<input type="number" name="year_of_birth" id="yearOfBirth" placeholder="e.g., 1990" min="1920" max="2008" value="<?php echo htmlspecialchars($_POST['year_of_birth'] ?? ''); ?>" required>
							</div>
							<div class="form-group">
								<label>Current Role on Nexus <span class="required">*</span></label>
								<select name="applicant_role" required>
									<option value="">Select your role</option>
									<option value="buyer" <?php echo (isset($_POST['applicant_role']) && $_POST['applicant_role'] == 'buyer') ? 'selected' : ''; ?>>Buyer</option>
									<option value="seller" <?php echo (isset($_POST['applicant_role']) && $_POST['applicant_role'] == 'seller') ? 'selected' : ''; ?>>Seller</option>
									<option value="both" <?php echo (isset($_POST['applicant_role']) && $_POST['applicant_role'] == 'both') ? 'selected' : ''; ?>>Both Buyer & Seller</option>
								</select>
							</div>
						</div>
						
						<div class="form-group">
							<label>Requested Department <span class="required">*</span></label>
							<select name="requested_department" required>
								<option value="">Select department</option>
								<option value="payments" <?php echo (isset($_POST['requested_department']) && $_POST['requested_department'] == 'payments') ? 'selected' : ''; ?>>Payments Department</option>
								<option value="verification" <?php echo (isset($_POST['requested_department']) && $_POST['requested_department'] == 'verification') ? 'selected' : ''; ?>>Verification Department</option>
								<option value="safety_support" <?php echo (isset($_POST['requested_department']) && $_POST['requested_department'] == 'safety_support') ? 'selected' : ''; ?>>Safety & Support Department</option>
								<option value="legal" <?php echo (isset($_POST['requested_department']) && $_POST['requested_department'] == 'legal') ? 'selected' : ''; ?>>Legal Department</option>
								<option value="social_media" <?php echo (isset($_POST['requested_department']) && $_POST['requested_department'] == 'social_media') ? 'selected' : ''; ?>>Social Media Department</option>
							</select>
						</div>
						
						<button type="submit" class="btn-submit">Submit Application</button>
						
						<div class="login-link">
							Already have an admin account? <a href="admin-login.php">Login here</a>
						</div>
					</form>
				</div>
			</div>
		</main>
		
		<script type="text/javascript" src="utilities.js"></script>
		<script>
			var emailInput = document.getElementById('email');
			var emailHint = document.getElementById('emailHint');
			var emailCheckTimeout;
			
			emailInput.addEventListener('input', function() {
				clearTimeout(emailCheckTimeout);
				emailCheckTimeout = setTimeout(function() {
					var email = emailInput.value.trim();
					if (email.length > 3) {
						fetch('check-user-email.php', {
							method: 'POST',
							headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
							body: 'email=' + encodeURIComponent(email)
						})
						.then(function(response) { return response.json(); })
						.then(function(data) {
							if (data.exists) {
								emailHint.innerHTML = '<span style="color: #4caf50;">✓ Existing Nexus user found</span>';
							} else {
								emailHint.innerHTML = '<span style="color: #ff4444;">✗ No account found. Please register first.</span>';
							}
						});
					}
				}, 500);
			});
			
			var idNumberInput = document.getElementById('idNumber');
			var yearOfBirthInput = document.getElementById('yearOfBirth');
			
			idNumberInput.addEventListener('blur', function() {
				var idNumber = this.value.trim();
				if (idNumber.length === 13 && /^\d+$/.test(idNumber)) {
					var year = parseInt(idNumber.substring(0, 2));
					var month = parseInt(idNumber.substring(2, 4));
					var day = parseInt(idNumber.substring(4, 6));
					var currentYear = new Date().getFullYear();
					var birthYear = year <= (currentYear % 100) ? 2000 + year : 1900 + year;
					
					if (yearOfBirthInput) {
						yearOfBirthInput.value = birthYear;
					}
				}
			});
			
			document.getElementById('adminSignupForm').addEventListener('submit', function(e) {
				var emailHintText = document.getElementById('emailHint').innerHTML;
				if (emailHintText.includes('No account found')) {
					e.preventDefault();
					alert('You must have an existing Nexus account to apply for admin.');
				}
			});
		</script>
	</body>
</html>