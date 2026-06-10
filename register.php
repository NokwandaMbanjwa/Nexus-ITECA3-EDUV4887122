<?php
	require_once 'config.php';

	$error = '';
	$success = '';

	$preferred_role = $_GET['role'] ?? $_COOKIE['preferred_role'] ?? 'buyer';
	
	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		
		// Get form data
		$fullName = trim($_POST['fullName'] ?? '');
		$email = trim($_POST['email'] ?? '');
		$phone = trim($_POST['phone'] ?? '');
		$idType = $_POST['idType'] ?? '';
		$idNumber = trim($_POST['idNumber'] ?? '');
		$passportNumber = trim($_POST['passportNumber'] ?? '');
		$address = trim($_POST['address'] ?? '');
		$city = trim($_POST['city'] ?? '');
		$province = $_POST['province'] ?? '';
		$postalCode = trim($_POST['postalCode'] ?? '');
		$password = $_POST['password'] ?? '';
		$confirmPassword = $_POST['confirmPassword'] ?? '';
		$accountType = $_POST['user_type'] ?? 'buyer';
		
		// Seller specific fields
		$storeName = trim($_POST['storeName'] ?? '');
		$sellerDescription = trim($_POST['sellerDescription'] ?? '');
		
		$errors = [];
		
		if (empty($fullName)) {
			$errors[] = "Full name is required";
		} elseif (strlen($fullName) < 3) {
			$errors[] = "Full name must be at least 3 characters";
		}

		if (empty($email)) {
			$errors[] = "Email is required";
		} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			$errors[] = "Invalid email format";
		} else {
			$stmt = $pdo->prepare("SELECT user_id FROM nexus_users WHERE email = ?");
			$stmt->execute([$email]);
			if ($stmt->fetch()) {
				$errors[] = "Email already registered. Please login instead.";
			}
		}
		
		// Check if email is blacklisted
		$stmt = $pdo->prepare("SELECT id FROM blacklisted_emails WHERE email = ?");
		$stmt->execute([$email]);
		if ($stmt->fetch()) {
			$errors[] = "This email address has been banned from Nexus. Registration is not allowed.";
		}

		// Also check phone number if provided
		if (!empty($phone)) {
			$stmt = $pdo->prepare("SELECT id FROM blacklisted_emails WHERE phone = ?");
			$stmt->execute([$phone]);
			if ($stmt->fetch()) {
				$errors[] = "This phone number has been banned from Nexus. Registration is not allowed.";
			}
		}

		// Validate ID type selection
		if (empty($idType)) {
			$errors[] = "Please select identification type";
		}

		// Validate based on ID type
		$storedIdNumber = '';
		if ($idType === 'id') {
			if (empty($idNumber)) {
				$errors[] = "ID number is required";
			} elseif (!preg_match('/^[0-9]{13}$/', $idNumber)) {
				$errors[] = "ID number must be 13 digits";
			} else {
				// Check blacklist for ID number
				$stmt = $pdo->prepare("SELECT id FROM blacklisted_emails WHERE id_number = ?");
				$stmt->execute([$idNumber]);
				if ($stmt->fetch()) {
					$errors[] = "This ID number has been banned from Nexus. Registration is not allowed.";
				}
				
				// Age validation for ID
				$year = substr($idNumber, 0, 2);
				$month = substr($idNumber, 2, 2);
				$day = substr($idNumber, 4, 2);
				$currentYear = date('Y');
				$birthYear = ($year <= (date('y') + 1)) ? 2000 + $year : 1900 + $year;
				$age = $currentYear - $birthYear;
				
				// Adjust age if birthday hasn't occurred yet this year
				if (date('md') < $month . $day) {
					$age--;
				}
				
				if ($age < 16) {
					$errors[] = "You must be at least 16 years old to register. Your age: $age years";
				}
			}
			$storedIdNumber = $idNumber;
		} elseif ($idType === 'passport') {
			if (empty($passportNumber)) {
				$errors[] = "Passport number is required";
			} elseif (strlen($passportNumber) < 8 || strlen($passportNumber) > 10) {
				$errors[] = "Passport number must be between 8 and 10 characters";
			} elseif (!preg_match('/^[A-Za-z0-9]+$/', $passportNumber)) {
				$errors[] = "Passport number must contain only letters and numbers";
			}
			$storedIdNumber = $passportNumber;
			$age = null;
		} else {
			$errors[] = "Invalid identification type selected";
		}

		if (empty($address)) $errors[] = "Address is required";
		if (empty($city)) $errors[] = "City is required";
		if (empty($province)) $errors[] = "Province is required";
		if (empty($postalCode)) $errors[] = "Postal code is required";

		if (empty($password)) {
			$errors[] = "Password is required";
		} elseif (strlen($password) < 8) {
			$errors[] = "Password must be at least 8 characters";
		} elseif (!preg_match('/\d/', $password)) {
			$errors[] = "Password must contain at least one number";
		}
		
		if ($password !== $confirmPassword) {
			$errors[] = "Passwords do not match";
		}

		if ($accountType === 'seller') {
			
			if (empty($sellerDescription)) {
				$errors[] = "Please describe what you aim to sell";
			}

			$uploadErrors = [];
			$idDocumentPath = '';
			$proofDocumentPath = '';

			$uploadDir = 'uploads/documents/';
			if (!file_exists($uploadDir)) {
				if (!mkdir($uploadDir, 0777, true) && !is_dir($uploadDir)) {
					$errors[] = "Failed to create upload directory";
				}
			}
			$maxFileSize = 5 * 1024 * 1024; 
                        if (isset($_FILES['idDocument']) && $_FILES['idDocument']['error'] === UPLOAD_ERR_OK) {
                                if ($_FILES['idDocument']['size'] > $maxFileSize) {
                                        $uploadErrors[] = "ID document must be 5MB or smaller";
                                } else {
                                        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                                        $fileType = $_FILES['idDocument']['type'];

                                        if (in_array($fileType, $allowedTypes)) {
                                                $fileExt = pathinfo($_FILES['idDocument']['name'], PATHINFO_EXTENSION);
                                                $fileName = time() . '_id_' . preg_replace('/[^a-zA-Z0-9]/', '', $email) . '.' . $fileExt;
                                                $filePath = $uploadDir . $fileName;

                                                if (move_uploaded_file($_FILES['idDocument']['tmp_name'], $filePath)) {
                                                        $idDocumentPath = $filePath;
                                                } else {
                                                        $uploadErrors[] = "Failed to upload ID document";
                                                }
                                        } else {
                                                $uploadErrors[] = "ID document must be PDF, JPG, or PNG";
                                        }
                                }
                        } else {
                                $uploadErrors[] = "ID document is required";
                        }

                        if (isset($_FILES['proofOfResidence']) && $_FILES['proofOfResidence']['error'] === UPLOAD_ERR_OK) {
                                if ($_FILES['proofOfResidence']['size'] > $maxFileSize) {
                                        $uploadErrors[] = "Proof of residence must be 5MB or smaller";
                                } else {
                                        $allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                                        $fileType = $_FILES['proofOfResidence']['type'];

                                        if (in_array($fileType, $allowedTypes)) {
                                                $fileExt = pathinfo($_FILES['proofOfResidence']['name'], PATHINFO_EXTENSION);
                                                $fileName = time() . '_proof_' . preg_replace('/[^a-zA-Z0-9]/', '', $email) . '.' . $fileExt;
                                                $filePath = $uploadDir . $fileName;

                                                if (move_uploaded_file($_FILES['proofOfResidence']['tmp_name'], $filePath)) {
                                                        $proofDocumentPath = $filePath;
                                                } else {
                                                        $uploadErrors[] = "Failed to upload proof of residence";
                                                }
                                        } else {
                                                $uploadErrors[] = "Proof of residence must be PDF, JPG, or PNG";
                                        }
                                }
                        } else {
                                $uploadErrors[] = "Proof of residence is required";
                        }

                        $errors = array_merge($errors, $uploadErrors);
                }

		// If no errors, save to database
		if (empty($errors)) {
			try {
				$pdo->beginTransaction();
				
				// Hash password
				$passwordHash = password_hash($password, PASSWORD_DEFAULT);
				
				// Insert into nexus_users table
				$stmt = $pdo->prepare("INSERT INTO nexus_users (email, password, user_type) VALUES (?, ?, ?)");
				$stmt->execute([$email, $passwordHash, $accountType]);
				$userId = $pdo->lastInsertId();
				
				if ($accountType === 'buyer') {
					$stmt = $pdo->prepare("INSERT INTO buyer_profiles 
						(user_id, full_name, phone_number, id_passport_number, residential_address, city_town, province, postal_code) 
						VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
					$stmt->execute([$userId, $fullName, $phone, $storedIdNumber, $address, $city, $province, $postalCode]);
					
					$_SESSION['user_id'] = $userId;
					$_SESSION['email'] = $email;
					$_SESSION['user_type'] = $accountType;
					$_SESSION['full_name'] = $fullName;
					
					$success = "Account created successfully!";
					
				} else {
					$stmt = $pdo->prepare("INSERT INTO seller_profiles 
						(user_id, full_name, phone_number, id_passport_number, residential_address, city_town, province, postal_code, selling_description, store_name) 
						VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
					$stmt->execute([$userId, $fullName, $phone, $storedIdNumber, $address, $city, $province, $postalCode, $sellerDescription, $storeName]);
					
					$sellerId = $pdo->lastInsertId();

					if ($idDocumentPath) {
						$stmt = $pdo->prepare("INSERT INTO seller_documents (seller_id, document_type, file_name, file_path, verification_status) 
							VALUES (?, 'id_copy', ?, ?, 'pending')");
						$stmt->execute([$sellerId, basename($idDocumentPath), $idDocumentPath]);
					}
					
					if ($proofDocumentPath) {
						$stmt = $pdo->prepare("INSERT INTO seller_documents (seller_id, document_type, file_name, file_path, verification_status) 
							VALUES (?, 'proof_of_residence', ?, ?, 'pending')");
						$stmt->execute([$sellerId, basename($proofDocumentPath), $proofDocumentPath]);
					}
					
					$_SESSION['user_id'] = $userId;
					$_SESSION['email'] = $email;
					$_SESSION['user_type'] = $accountType;
					$_SESSION['full_name'] = $fullName;
					$_SESSION['store_name'] = $storeName;
					
					$success = "Seller account created! Your documents will be reviewed.";
				}
				
				$pdo->commit();
				
				session_write_close();
				header("Location: explore-feed.php");
				exit;
				
			} catch (Exception $e) {
				$pdo->rollBack();
				$error = "Database error: " . $e->getMessage();
			}
		} else {
			$error = implode("<br>", $errors);
		}
	}

	$page_title = "NEXUS| Register";
?>
<!DOCTYPE html>
	<html lang="en">
	<head>
		<title>NEXUS | Register</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.register-wrapper {
				max-width: 800px;
				margin: 0 auto;
			}
			.register-header {
				text-align: center;
				margin-bottom: 48px;
			}
			.register-header .page-title {
				font-size: 48px;
				background: #8ff5ff;
				-webkit-background-clip: text;
				background-clip: text;
				color: transparent;
				margin-bottom: 12px;
			}
			.register-header p {
				font-size: 18px;
				color: #adaaad;
			}
			.register-form-container {
				background-color: #19191c;
				padding: 40px;
				border-radius: 24px;
				border: 1px solid rgba(118, 117, 119, 0.1);
			}
			.account-type {
				margin-bottom: 32px;
				padding-bottom: 24px;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}
			.account-type h3 {
				margin-bottom: 16px;
				font-size: 18px;
				color: #d4d4d8;
			}
			.type-buttons {
				display: flex;
				gap: 16px;
			}
			.type-btn {
				flex: 1;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 12px;
				padding: 12px 24px;
				background-color: #0e0e10;
				border: 1px solid rgba(118, 117, 119, 0.3);
				border-radius: 12px;
				color: #adaaad;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
			}
			.type-btn i {
				font-size: 18px;
			}
			.type-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}
			.type-btn.active {
				background: #16161d;
				border-color: #8ff5ff;
				color: #8ff5ff;
			}
			.register-form {
				margin-top: 24px;
			}
			.password-hint {
				display: block;
				font-size: 12px;
				color: #9ca3af;
				margin-top: 6px;
			}
			.file-upload-group {
				margin-top: 8px;
			}
			.file-upload-label {
				display: inline-block;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				padding: 10px 16px;
				cursor: pointer;
				transition: all 0.2s;
				font-size: 13px;
				color: #8ff5ff;
			}
			.file-upload-label:hover {
				border-color: #8ff5ff;
			}
			.file-upload-input {
				display: none;
			}
			.file-name {
				font-size: 12px;
				color: #9ca3af;
				margin-top: 6px;
				display: block;
			}
			.seller-fields {
				margin-top: 32px;
				padding-top: 24px;
				border-top: 1px solid rgba(118, 117, 119, 0.1);
			}
			.seller-fields h3 {
				margin-bottom: 20px;
				font-size: 20px;
				color: #c180ff;
			}
			.register-submit {
				width: 100%;
				background: #8ff5ff;
				color: #131315;
				font-weight: bold;
				font-size: 18px;
				padding: 16px;
				border-radius: 12px;
				border: none;
				cursor: pointer;
				transition: all 0.3s;
				margin-top: 16px;
			}
			.register-submit:hover {
				box-shadow: 0 0 5px #8ff5ff;
				transform: translateY(-2px);
			}
			.login-prompt {
				text-align: center;
				margin-top: 24px;
				color: #adaaad;
			}
			.login-prompt a {
				color: #8ff5ff;
				text-decoration: none;
				font-weight: 600;
			}
			.error-message {
				background: rgba(255, 68, 68, 0.1);
				border: 1px solid #ff4444;
				color: #ff4444;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			.success-message {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			@media (max-width: 768px) {
				.register-form-container {
					padding: 24px;
				}
				.type-buttons {
					flex-direction: column;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="page-content">
			<div class="container">
				<div class="register-wrapper">
					<header class="register-header">
						<h1 class="page-title">CREATE ACCOUNT</h1>
						<p>Join the Nexus community and start your online shopping journey today</p>
					</header>
					
					<div class="register-form-container">
						<?php if ($error): ?>
							<div class="error-message"><?php echo $error; ?></div>
						<?php endif; ?>
						
						<?php if ($success): ?>
							<div class="success-message"><?php echo $success; ?></div>
						<?php endif; ?>
						
						<form id="registerForm" class="register-form" method="post" action="" enctype="multipart/form-data">
							<section class="account-type">
								<h3>I want to join as a:</h3>
								<div class="type-buttons">
									<button type="button" class="type-btn <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] == 'buyer') ? 'active' : ''; ?>" data-type="buyer">
										<i class="fas fa-shopping-cart"></i>
										<span>Buyer</span>
									</button>
									<button type="button" class="type-btn <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'seller') ? 'active' : ''; ?>" data-type="seller">
										<i class="fas fa-store"></i>
										<span>Seller</span>
									</button>
								</div>
								<input type="hidden" name="user_type" id="user_type" value="<?php echo isset($_POST['user_type']) ? $_POST['user_type'] : 'buyer'; ?>">
							</section>
							
							<div class="form-row">
								<div class="form-group">
									<label for="fullName">Full Name <span class="required">*</span></label>
									<input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($_POST['fullName'] ?? ''); ?>" required>
								</div>
								
								<div class="form-group">
									<label for="email">Email Address <span class="required">*</span></label>
									<input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
								</div>
							</div>
							
							<div class="form-row">
								<div class="form-group">
									<label for="phone">Phone Number</label>
									<input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
								</div>
								
								<div class="form-group">
									<label>Identification Type <span class="required">*</span></label>
									<select id="idType" name="idType" required>
										<option value="">Select identification type</option>
										<option value="id" <?php echo (isset($_POST['idType']) && $_POST['idType'] == 'id') ? 'selected' : ''; ?>>South African ID</option>
										<option value="passport" <?php echo (isset($_POST['idType']) && $_POST['idType'] == 'passport') ? 'selected' : ''; ?>>Passport</option>
									</select>
								</div>

								<div class="form-group" id="idNumberGroup" style="display: <?php echo (!isset($_POST['idType']) || $_POST['idType'] == 'id') ? 'block' : 'none'; ?>;">
									<label for="idNumber">ID Number <span class="required">*</span></label>
									<input type="text" id="idNumber" name="idNumber" maxlength="13" placeholder="13-digit South African ID" value="<?php echo htmlspecialchars($_POST['idNumber'] ?? ''); ?>">
									<span class="password-hint">13-digit South African ID number</span>
								</div>

								<div class="form-group" id="passportNumberGroup" style="display: <?php echo (isset($_POST['idType']) && $_POST['idType'] == 'passport') ? 'block' : 'none'; ?>;">
									<label for="passportNumber">Passport Number <span class="required">*</span></label>
									<input type="text" id="passportNumber" name="passportNumber" placeholder="South African passport number" value="<?php echo htmlspecialchars($_POST['passportNumber'] ?? ''); ?>">
									<span class="password-hint">South African passport number (8-10 characters)</span>
								</div>
							</div>
							
							<div class="form-group">
								<label for="address">Residential Address <span class="required">*</span></label>
								<input type="text" id="address" name="address" value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" required>
							</div>
							
							<div class="form-row">
								<div class="form-group">
									<label for="city">City/Town <span class="required">*</span></label>
									<input type="text" id="city" name="city" value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" required>
								</div>
								
								<div class="form-group">
									<label for="province">Province <span class="required">*</span></label>
									<select id="province" name="province" required>
										<option value="">Select your province</option>
										<option value="Gauteng" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Gauteng') ? 'selected' : ''; ?>>Gauteng</option>
										<option value="Western Cape" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Western Cape') ? 'selected' : ''; ?>>Western Cape</option>
										<option value="KwaZulu-Natal" <?php echo (isset($_POST['province']) && $_POST['province'] == 'KwaZulu-Natal') ? 'selected' : ''; ?>>KwaZulu-Natal</option>
										<option value="Eastern Cape" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Eastern Cape') ? 'selected' : ''; ?>>Eastern Cape</option>
										<option value="Free State" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Free State') ? 'selected' : ''; ?>>Free State</option>
										<option value="Mpumalanga" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Mpumalanga') ? 'selected' : ''; ?>>Mpumalanga</option>
										<option value="Limpopo" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Limpopo') ? 'selected' : ''; ?>>Limpopo</option>
										<option value="North West" <?php echo (isset($_POST['province']) && $_POST['province'] == 'North West') ? 'selected' : ''; ?>>North West</option>
										<option value="Northern Cape" <?php echo (isset($_POST['province']) && $_POST['province'] == 'Northern Cape') ? 'selected' : ''; ?>>Northern Cape</option>
									</select>
								</div>
							</div>
							
							<div class="form-group">
								<label for="postalCode">Postal Code <span class="required">*</span></label>
								<input type="text" id="postalCode" name="postalCode" value="<?php echo htmlspecialchars($_POST['postalCode'] ?? ''); ?>" required>
							</div>
							
							<div class="form-row">
								<div class="form-group">
									<label for="password">Password <span class="required">*</span></label>
									<input type="password" id="password" name="password" required>
									<span class="password-hint">Minimum 8 characters with at least one number</span>
								</div>
								
								<div class="form-group">
									<label for="confirmPassword">Confirm Password <span class="required">*</span></label>
									<input type="password" id="confirmPassword" name="confirmPassword" required>
								</div>
							</div>
							
							<div id="sellerFields" class="seller-fields" style="display: <?php echo (isset($_POST['user_type']) && $_POST['user_type'] == 'seller') ? 'block' : 'none'; ?>;">
								<h3>Seller Information</h3>
								
								<div class="form-group">
									<label for="storeName">Store Name</label>
									<input type="text" id="storeName" name="storeName" value="<?php echo htmlspecialchars($_POST['storeName'] ?? ''); ?>">
								</div>
								
								<div class="form-group">
									<label for="sellerDescription">What do you aim to sell on Nexus? <span class="required">*</span></label>
									<textarea id="sellerDescription" name="sellerDescription" rows="3"><?php echo htmlspecialchars($_POST['sellerDescription'] ?? ''); ?></textarea>
								</div>
								
								<div class="form-group">
									<label>Upload ID Document/Passport Copy <span class="required">*</span></label>
									<div class="file-upload-group">
										<label class="file-upload-label">
											<i class="fas fa-cloud-upload-alt"></i> Choose File
											<input type="file" id="idDocument" name="idDocument" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png">
										</label>
										<span class="file-name" id="idFileName">No file chosen</span>
									</div>
									<p class="password-hint">Accepted formats: PDF, JPG, PNG. Max 5MB.</p>
								</div>
								
								<div class="form-group">
									<label>Upload Proof of Residence <span class="required">*</span></label>
									<div class="file-upload-group">
										<label class="file-upload-label">
											<i class="fas fa-cloud-upload-alt"></i> Choose File
											<input type="file" id="proofOfResidence" name="proofOfResidence" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png">
										</label>
										<span class="file-name" id="proofFileName">No file chosen</span>
									</div>
									<p class="password-hint">Accepted formats: PDF, JPG, PNG. (Utility bill, bank statement, etc.)</p>
								</div>
							</div>
							
							<div class="form-group checkbox-group">
								<label class="checkbox-label">
									<input type="checkbox" id="termsCheckbox" name="terms" required>
									<span>I agree to the <a href="terms-of-service.php" target="_blank">Terms of Service</a> and <a href="privacy-policy.php" target="_blank">Privacy Policy</a></span>
								</label>
							</div>
							
							<button type="submit" class="register-submit">Create Account</button>
							
							<div class="login-prompt">
								Already have an account? <a href="login.php">Sign in here</a>
							</div>
						</form>
					</div>
				</div>
			</div>
		</main>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
		// Check URL parameter for preferred role
		function getPreferredRole() {
			const urlParams = new URLSearchParams(window.location.search);
			const roleFromUrl = urlParams.get('role');
			
			if (roleFromUrl === 'seller') {
				return 'seller';
			} else if (roleFromUrl === 'buyer') {
				return 'buyer';
			}
			
			const localStorageRole = localStorage.getItem('preferredAccountType');
			if (localStorageRole) {
				localStorage.removeItem('preferredAccountType');
				return localStorageRole;
			}
			
			return 'buyer'; 
		}
		
		const buyerBtn = document.querySelector('.type-btn[data-type="buyer"]');
		const sellerBtn = document.querySelector('.type-btn[data-type="seller"]');
		const sellerFields = document.getElementById('sellerFields');
		const accountTypeInput = document.getElementById('user_type');
		
		function showBuyer() {
			sellerFields.style.display = 'none';
			buyerBtn.classList.add('active');
			sellerBtn.classList.remove('active');
			accountTypeInput.value = 'buyer';
		}
		
		function showSeller() {
			sellerFields.style.display = 'block';
			sellerBtn.classList.add('active');
			buyerBtn.classList.remove('active');
			accountTypeInput.value = 'seller';
		}
		
		const preferredRole = getPreferredRole();
		if (preferredRole === 'seller') {
			showSeller();
		} else {
			showBuyer();
		}
		
		buyerBtn.addEventListener('click', showBuyer);
		sellerBtn.addEventListener('click', showSeller);
		
		const idTypeSelect = document.getElementById('idType');
		const idNumberGroup = document.getElementById('idNumberGroup');
		const passportNumberGroup = document.getElementById('passportNumberGroup');

		if (idTypeSelect) {
			idTypeSelect.addEventListener('change', function() {
				if (this.value === 'id') {
					idNumberGroup.style.display = 'block';
					passportNumberGroup.style.display = 'none';
					document.getElementById('idNumber').required = true;
					document.getElementById('passportNumber').required = false;
				} else if (this.value === 'passport') {
					idNumberGroup.style.display = 'none';
					passportNumberGroup.style.display = 'block';
					document.getElementById('idNumber').required = false;
					document.getElementById('passportNumber').required = true;
				} else {
					idNumberGroup.style.display = 'block';
					passportNumberGroup.style.display = 'none';
					document.getElementById('idNumber').required = false;
					document.getElementById('passportNumber').required = false;
				}
			});
		}

		const idDocument = document.getElementById('idDocument');
		const proofDocument = document.getElementById('proofOfResidence');
		const idFileName = document.getElementById('idFileName');
		const proofFileName = document.getElementById('proofFileName');
		
		if (idDocument) {
			idDocument.addEventListener('change', function(e) {
				idFileName.textContent = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
			});
		}
		
		if (proofDocument) {
			proofDocument.addEventListener('change', function(e) {
				proofFileName.textContent = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
			});
		}
	</script>
	</body>
</html>