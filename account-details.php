<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_type = getUserType();
$error = '';
$success = '';

$stmt = $pdo->prepare("
    SELECT u.*, 
           bp.full_name as buyer_full_name, bp.phone_number as buyer_phone, 
           bp.id_passport_number, bp.residential_address, bp.city_town, bp.province, bp.postal_code,
           sp.full_name as seller_full_name, sp.phone_number as seller_phone,
           sp.store_name, sp.selling_description, sp.verification_status, sp.application_documents,
           u.email, u.user_type
    FROM nexus_users u
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$full_name = $user['buyer_full_name'] ?? $user['seller_full_name'] ?? '';
$phone = $user['buyer_phone'] ?? $user['seller_phone'] ?? '';
$address = $user['residential_address'] ?? '';
$city = $user['city_town'] ?? '';
$province = $user['province'] ?? '';
$postal_code = $user['postal_code'] ?? '';
$id_number = $user['id_passport_number'] ?? '';
$store_name = $user['store_name'] ?? '';
$selling_description = $user['selling_description'] ?? '';
$verification_status = $user['verification_status'] ?? 'pending';
$email = $user['email'] ?? '';
$user_type_display = ($user_type === 'seller') ? 'Seller' : 'Buyer';

// Check if there's a pending application
$has_pending_application = ($user_type === 'seller' && $verification_status === 'pending');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';
    
    if ($action === 'update') {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $province = $_POST['province'] ?? '';
    $postal_code = trim($_POST['postal_code'] ?? '');
    
    // Banking details
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_holder_name = trim($_POST['account_holder_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $branch_code = trim($_POST['branch_code'] ?? '');
    $account_type = $_POST['account_type'] ?? 'cheque';
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    }
    
    if (empty($errors)) {
        try {
            if ($user_type === 'buyer') {
                $stmt = $pdo->prepare("UPDATE buyer_profiles SET 
                    full_name = ?, phone_number = ?, residential_address = ?, 
                    city_town = ?, province = ?, postal_code = ?
                    WHERE user_id = ?");
                $stmt->execute([$full_name, $phone, $address, $city, $province, $postal_code, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE seller_profiles SET 
                    full_name = ?, phone_number = ?, residential_address = ?, 
                    city_town = ?, province = ?, postal_code = ?, 
                    store_name = ?, selling_description = ?,
                    bank_name = ?, account_holder_name = ?, account_number = ?, 
                    branch_code = ?, account_type = ?
                    WHERE user_id = ?");
                $stmt->execute([$full_name, $phone, $address, $city, $province, $postal_code, 
                    $store_name, $selling_description, $bank_name, $account_holder_name, 
                    $account_number, $branch_code, $account_type, $user_id]);
            }
            
            $_SESSION['full_name'] = $full_name;
            $_SESSION['phone'] = $phone;
            
            $success = "Account details updated successfully!";
            
            header("Location: account-details.php?success=1");
            exit;
            
        } catch (PDOException $e) {
            $error = "Failed to update account. Please try again.";
            error_log("Account update error: " . $e->getMessage());
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
    
    if ($action === 'submit_seller_upgrade') {
        $store_name = trim($_POST['store_name'] ?? '');
        $selling_description = trim($_POST['selling_description'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        
        $errors = [];
        if (empty($selling_description)) {
            $errors[] = "Please describe what you aim to sell";
        }
         if (empty($id_number)) {
			$errors[] = "ID number is required";
		} elseif (!preg_match('/^[0-9]{13}$/', $id_number)) {
			$errors[] = "ID number must be 13 digits";
		} else {
			$age = getAgeFromSAID($id_number);
			if ($age === null) {
				$errors[] = "Invalid ID number. Please check and try again.";
			} elseif ($age < 16) {
				$errors[] = "You must be at least 16 years old to become a seller.";
			}
		}

        $idDocumentPath = '';
        $proofDocumentPath = '';
        
        $uploadDir = 'uploads/documents/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $maxFileSize = 5 * 1024 * 1024;

        if (isset($_FILES['idDocument']) && $_FILES['idDocument']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['idDocument']['size'] > $maxFileSize) {
                $errors[] = "ID document must be 5MB or smaller";
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
                        $errors[] = "Failed to upload ID document";
                    }
                } else {
                    $errors[] = "ID document must be PDF, JPG, or PNG";
                }
            }
        } else {
            $errors[] = "ID document is required";
        }

        if (isset($_FILES['proofOfResidence']) && $_FILES['proofOfResidence']['error'] === UPLOAD_ERR_OK) {
            if ($_FILES['proofOfResidence']['size'] > $maxFileSize) {
                $errors[] = "Proof of residence must be 5MB or smaller";
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
                        $errors[] = "Failed to upload proof of residence";
                    }
                } else {
                    $errors[] = "Proof of residence must be PDF, JPG, or PNG";
                }
            }
        } else {
            $errors[] = "Proof of residence is required";
        }
        
        // Store document paths as JSON
        $documents = json_encode([
            'id_document' => $idDocumentPath,
            'proof_of_residence' => $proofDocumentPath,
            'applied_at' => date('Y-m-d H:i:s')
        ]);
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $existing_seller = $stmt->fetch();
                
                if ($existing_seller) {
                    $stmt = $pdo->prepare("
                        UPDATE seller_profiles 
                        SET store_name = ?, 
                            selling_description = ?, 
                            id_passport_number = ?,
                            verification_status = 'pending',
                            application_documents = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$store_name, $selling_description, $id_number, $documents, $user_id]);
                } else {
                    $stmt = $pdo->prepare("
                        SELECT full_name, phone_number, residential_address, city_town, province, postal_code 
                        FROM buyer_profiles WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $buyer = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO seller_profiles 
                        (user_id, full_name, phone_number, id_passport_number, residential_address, 
                         city_town, province, postal_code, store_name, selling_description, 
                         verification_status, application_documents) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        $buyer['full_name'], 
                        $buyer['phone_number'], 
                        $id_number,
                        $buyer['residential_address'],
                        $buyer['city_town'],
                        $buyer['province'],
                        $buyer['postal_code'],
                        $store_name, 
                        $selling_description,
                        $documents
                    ]);
                }
                
                $stmt = $pdo->prepare("UPDATE nexus_users SET user_type = 'seller' WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $pdo->commit();

                $_SESSION['user_type'] = 'seller';
                
                $success = "Your seller application has been submitted! You will be notified once verified.";
                
                header("Location: account-details.php?application_submitted=1");
                exit;
                
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to submit application. Please try again.";
                error_log("Upgrade application error: " . $e->getMessage());
            }
        } else {
            $error = implode("<br>", $errors);
        }
    }
    
    if ($action === 'delete_account') {
        try {
            $stmt = $pdo->prepare("UPDATE nexus_users SET status = 'deleted' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            session_destroy();
            header("Location: index.php?account_deleted=1");
            exit;
        } catch (PDOException $e) {
            $error = "Failed to delete account. Please contact support.";
            error_log("Account deletion error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Account Details</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.account-page {
				padding-top: 110px;
				padding-bottom: 80px;
			}

			.account-container {
				max-width: 800px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.account-header {
				text-align: center;
				margin-bottom: 48px;
			}

			.account-header h1 {
				font-size: 42px;
				margin-bottom: 12px;
				color: #8ff5ff;
			}

			.account-header p {
				font-size: 16px;
				color: #adaaad;
			}

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
			}

			.alert-error {
				background: rgba(255, 107, 107, 0.1);
				border: 1px solid #ff6b6b;
				color: #ff6b6b;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}

			.profile-section {
				display: flex;
				flex-direction: column;
				align-items: center;
				margin-bottom: 40px;
			}

			.avatar {
				width: 140px;
				height: 140px;
				background: #1a1a1a;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 56px;
				color: #8ff5ff;
				margin-bottom: 16px;
				border: 3px solid #2a2a2a;
				transition: all 0.3s;
				cursor: pointer;
				overflow: hidden;
			}

			.avatar:hover {
				border-color: #8ff5ff;
				transform: scale(1.02);
			}

			.avatar img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}

			.change-photo-btn {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #aaa;
				padding: 8px 24px;
				border-radius: 30px;
				font-size: 14px;
				cursor: pointer;
				transition: all 0.2s;
			}

			.change-photo-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			.form-section {
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 10px;
				padding: 32px;
				margin-bottom: 28px;
			}

			.form-section:hover {
				border-color: rgba(143, 245, 255, 0.2);
			}

			.section-title {
				font-size: 20px;
				font-weight: 600;
				margin-bottom: 24px;
				padding-bottom: 16px;
				border-bottom: 1px solid #2a2a2a;
				display: flex;
				align-items: center;
				gap: 12px;
				color: #f9f5f8;
			}

			.section-title i {
				color: #8ff5ff;
				font-size: 22px;
			}

			.form-group {
				margin-bottom: 20px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				font-size: 14px;
				font-weight: 500;
				color: #aaa;
			}

			.form-group input,
			.form-group select,
			.form-group textarea {
				width: 100%;
				padding: 12px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				color: #e5e5e5;
				font-size: 15px;
				transition: all 0.2s;
			}

			.form-group input:focus,
			.form-group select:focus,
			.form-group textarea:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			.form-group input:disabled {
				opacity: 0.6;
				cursor: not-allowed;
				background: #1a1a1a;
			}

			.form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 20px;
			}

			.notification-options {
				display: flex;
				flex-direction: column;
				gap: 16px;
			}

			.checkbox-item {
				display: flex;
				align-items: center;
				gap: 14px;
				cursor: pointer;
				padding: 8px 0;
			}

			.checkbox-item:hover {
				transform: translateX(4px);
			}

			.checkbox-item input {
				width: 18px;
				height: 18px;
				accent-color: #8ff5ff;
				cursor: pointer;
			}

			.checkbox-item span {
				color: #d4d4d8;
				font-size: 14px;
			}

			.upgrade-section {
				background: #131315;
				border: 1px solid rgba(143, 245, 255, 0.2);
				border-radius: 16px;
				padding: 28px;
				text-align: center;
			}

			.upgrade-section h3 {
				color: #8ff5ff;
				font-size: 20px;
				margin-bottom: 8px;
			}

			.upgrade-section p {
				font-size: 14px;
				margin-bottom: 16px;
				color: #adaaad;
			}

			.upgrade-btn {
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				padding: 10px 28px;
				border-radius: 30px;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
			}

			.upgrade-btn:hover {
				transform: translateY(-2px);
				background: #7de0ea;
			}

			.seller-badge {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				background: rgba(143, 245, 255, 0.1);
				color: #8ff5ff;
				padding: 10px 20px;
				border-radius: 40px;
				font-size: 14px;
				font-weight: 500;
			}

			.verification-badge {
				display: inline-block;
				padding: 6px 14px;
				border-radius: 20px;
				font-size: 13px;
				font-weight: 500;
			}

			.verification-approved {
				background: rgba(76, 175, 80, 0.2);
				color: #4caf50;
				border: 1px solid #4caf50;
			}

			.verification-pending {
				background: rgba(255, 193, 7, 0.2);
				color: #ffc107;
				border: 1px solid #ffc107;
			}

			.verification-rejected {
				background: rgba(255, 68, 68, 0.2);
				color: #ff4444;
				border: 1px solid #ff4444;
			}

			.pending-application {
				background: rgba(255, 193, 7, 0.1);
				border: 1px solid #ffc107;
				border-radius: 12px;
				padding: 20px;
				text-align: center;
			}

			.pending-application i {
				color: #ffc107;
				font-size: 32px;
				margin-bottom: 12px;
				display: block;
			}

			.action-buttons {
				display: flex;
				gap: 16px;
				justify-content: flex-end;
				margin-top: 32px;
			}

			.save-btn {
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				padding: 12px 32px;
				border-radius: 40px;
				font-size: 15px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
			}

			.save-btn:hover {
				background: #7de0ea;
				transform: translateY(-2px);
			}

			.cancel-btn {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #aaa;
				padding: 12px 32px;
				border-radius: 40px;
				font-size: 15px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
			}

			.cancel-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			.delete-zone {
				border-top: 1px solid #2a2a2a;
				padding-top: 28px;
				margin-top: 24px;
				text-align: center;
			}

			.delete-btn {
				background: transparent;
				border: none;
				color: #e5e5e5;
				font-size: 14px;
				cursor: pointer;
				padding: 8px 16px;
				transition: all 0.2s;
			}

			.delete-btn:hover {
				color: #ff4444;
			}

			.upgrade-modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.95);
				z-index: 1000;
				align-items: center;
				justify-content: center;
			}
			
			.upgrade-modal.show {
				display: flex;
			}
			
			.upgrade-modal-content {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 32px;
				max-width: 600px;
				width: 90%;
				max-height: 85vh;
				overflow-y: auto;
			}
			
			.upgrade-modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 24px;
			}
			
			.upgrade-modal-header h2 {
				color: #8ff5ff;
				font-size: 24px;
			}
			
			.close-upgrade-modal {
				background: none;
				border: none;
				color: #888;
				font-size: 28px;
				cursor: pointer;
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
			
			.password-hint {
				font-size: 11px;
				color: #666;
				margin-top: 5px;
			}

			.modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.9);
				z-index: 200;
				align-items: center;
				justify-content: center;
			}

			.modal.show {
				display: flex;
			}

			.modal-content {
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 32px;
				max-width: 400px;
				width: 90%;
				text-align: center;
			}

			.modal-content h3 {
				font-size: 22px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}

			.modal-content p {
				color: #e5e5e5;
				font-size: 14px;
				margin-bottom: 24px;
			}

			.modal-buttons {
				display: flex;
				gap: 16px;
				justify-content: center;
			}

			.modal-buttons button {
				padding: 10px 24px;
				border-radius: 30px;
				font-size: 14px;
				cursor: pointer;
				transition: all 0.2s;
			}

			.confirm-delete {
				background: #ff4444;
				border: none;
				color: white;
			}

			.confirm-delete:hover {
				background: #cc0000;
			}

			.cancel-delete {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #e5e5e5;
			}

			.cancel-delete:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			@media (max-width: 768px) {
				.account-page {
					padding-top: 80px;
					padding-bottom: 40px;
				}
				.account-header h1 {
					font-size: 28px;
				}
				.account-header p {
					font-size: 14px;
				}
				.avatar {
					width: 100px;
					height: 100px;
					font-size: 40px;
				}
				.form-section {
					padding: 16px;
				}
				.section-title {
					font-size: 18px;
				}
				.form-group input,
				.form-group select,
				.form-group textarea {
					padding: 10px 14px;
					font-size: 14px;
				}
				.upgrade-modal-content {
					padding: 20px;
				}
				.modal-content {
					padding: 24px;
				}
			}

			@media (max-width: 360px) {
				.account-header h1 {
					font-size: 24px;
				}
				.form-section {
					padding: 14px;
				}
				.save-btn, .cancel-btn {
					padding: 10px 24px;
					font-size: 14px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="account-page">
			<div class="account-container">
				<div class="account-header">
					<h1>Account Details</h1>
					<p>Manage your personal information and account settings</p>
				</div>
				
				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<?php if ($success): ?>
					<div class="alert alert-success"><?php echo $success; ?></div>
				<?php endif; ?>
				
				<?php if (isset($_GET['application_submitted'])): ?>
					<div class="alert alert-success">Your seller application has been submitted successfully! You will be notified once verified.</div>
				<?php endif; ?>
				
				<?php if (isset($_GET['success'])): ?>
					<div class="alert alert-success">Account details updated successfully!</div>
				<?php endif; ?>

				<form id="accountForm" method="post" action="">
					<input type="hidden" name="action" value="update">

					<div class="profile-section">
						<div class="avatar" id="avatar">
							<i class="fas fa-user"></i>
						</div>
						<button type="button" class="change-photo-btn" id="changePhotoBtn">Change Photo</button>
						<input type="file" id="profilePhoto" accept="image/*" style="display: none;">
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-user"></i>
							<span>Personal Information</span>
						</div>
						
						<div class="form-group">
							<label>Full Name <span class="required">*</span></label>
							<input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>" required>
						</div>

						<div class="form-group">
							<label>Email Address</label>
							<input type="email" value="<?php echo htmlspecialchars($email); ?>" disabled>
							<small style="color: #666;">Email cannot be changed. Contact support for assistance.</small>
						</div>

						<div class="form-group">
							<label>Phone Number</label>
							<input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
						</div>

						<?php if ($user_type === 'seller'): ?>
							<div class="form-group">
								<label>Store Name <span style="color: #666; font-size: 12px;">(optional)</span></label>
								<input type="text" name="store_name" placeholder="e.g., John's Vintage Shop">
							</div>
							
							<div class="form-group">
								<label>Store Description</label>
								<textarea id="selling_description" name="selling_description" rows="3" placeholder="Tell buyers about your store..."><?php echo htmlspecialchars($selling_description); ?></textarea>
							</div>
							<?php if ($user_type === 'seller'): ?>
<div class="form-section">
    <div class="section-title">
        <i class="fas fa-university"></i>
        <span>Banking Details</span>
    </div>
    
    <div class="form-group">
        <label>Bank Name</label>
        <input type="text" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($user['bank_name'] ?? ''); ?>" placeholder="e.g., Standard Bank, FNB, Capitec">
    </div>
    
    <div class="form-group">
        <label>Account Holder Name</label>
        <input type="text" id="account_holder_name" name="account_holder_name" value="<?php echo htmlspecialchars($user['account_holder_name'] ?? ''); ?>" placeholder="Name as it appears on the account">
    </div>
    
    <div class="form-row">
        <div class="form-group">
            <label>Account Number</label>
            <input type="text" id="account_number" name="account_number" value="<?php echo htmlspecialchars($user['account_number'] ?? ''); ?>" placeholder="Account number">
        </div>
        <div class="form-group">
            <label>Branch Code</label>
            <input type="text" id="branch_code" name="branch_code" value="<?php echo htmlspecialchars($user['branch_code'] ?? ''); ?>" placeholder="Branch code">
        </div>
    </div>
    
    <div class="form-group">
        <label>Account Type</label>
        <select id="account_type" name="account_type">
            <option value="cheque" <?php echo (($user['account_type'] ?? 'cheque') == 'cheque') ? 'selected' : ''; ?>>Cheque Account</option>
            <option value="savings" <?php echo (($user['account_type'] ?? '') == 'savings') ? 'selected' : ''; ?>>Savings Account</option>
            <option value="transmission" <?php echo (($user['account_type'] ?? '') == 'transmission') ? 'selected' : ''; ?>>Transmission Account</option>
        </select>
    </div>
    
    <div class="password-hint" style="margin-top: 10px;">
        <i class="fas fa-info-circle"></i> Your banking details are required to receive payouts from your sales.
    </div>
</div>
<?php endif; ?>
							<div class="form-group">
								<label>Verification Status</label>
								<div>
									<?php if ($verification_status === 'approved'): ?>
										<span class="verification-badge verification-approved">
											<i class="fas fa-check-circle"></i> Verified Seller
										</span>
									<?php elseif ($verification_status === 'pending'): ?>
										<span class="verification-badge verification-pending">
											<i class="fas fa-clock"></i> Verification Pending
										</span>
										<p style="margin-top: 8px; font-size: 12px; color: #ffc107;">
											Your documents are being reviewed. You will be notified once verified.
										</p>
									<?php elseif ($verification_status === 'rejected'): ?>
										<span class="verification-badge verification-rejected">
											<i class="fas fa-times-circle"></i> Verification Rejected
										</span>
									<?php endif; ?>
								</div>
							</div>
						<?php endif; ?>
					</div>

					<!-- Location -->
					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-map-marker-alt"></i>
							<span>Location</span>
						</div>

						<div class="form-group">
							<label>Address</label>
							<input type="text" id="address" name="address" value="<?php echo htmlspecialchars($address); ?>">
						</div>

						<div class="form-row">
							<div class="form-group">
								<label>City</label>
								<input type="text" id="city" name="city" value="<?php echo htmlspecialchars($city); ?>">
							</div>
							<div class="form-group">
								<label>Province</label>
								<select id="province" name="province">
									<option value="Western Cape" <?php echo $province == 'Western Cape' ? 'selected' : ''; ?>>Western Cape</option>
									<option value="Gauteng" <?php echo $province == 'Gauteng' ? 'selected' : ''; ?>>Gauteng</option>
									<option value="KwaZulu-Natal" <?php echo $province == 'KwaZulu-Natal' ? 'selected' : ''; ?>>KwaZulu-Natal</option>
									<option value="Eastern Cape" <?php echo $province == 'Eastern Cape' ? 'selected' : ''; ?>>Eastern Cape</option>
									<option value="Free State" <?php echo $province == 'Free State' ? 'selected' : ''; ?>>Free State</option>
									<option value="Mpumalanga" <?php echo $province == 'Mpumalanga' ? 'selected' : ''; ?>>Mpumalanga</option>
									<option value="Limpopo" <?php echo $province == 'Limpopo' ? 'selected' : ''; ?>>Limpopo</option>
									<option value="North West" <?php echo $province == 'North West' ? 'selected' : ''; ?>>North West</option>
									<option value="Northern Cape" <?php echo $province == 'Northern Cape' ? 'selected' : ''; ?>>Northern Cape</option>
								</select>
							</div>
						</div>

						<div class="form-group">
							<label>Postal Code</label>
							<input type="text" id="postal_code" name="postal_code" value="<?php echo htmlspecialchars($postal_code); ?>">
						</div>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-tag"></i>
							<span>Account Type</span>
						</div>

						<div class="form-group">
							<label>Current Account</label>
							<input type="text" value="<?php echo $user_type_display; ?>" disabled>
						</div>

						<?php if ($user_type !== 'seller'): ?>
							<div id="upgradeSection" class="upgrade-section">
								<h3>Become a Seller</h3>
								<p>Start selling your items on Nexus Marketplace</p>
								<button type="button" class="upgrade-btn" id="upgradeBtn">Apply to Become a Seller</button>
							</div>
						<?php else: ?>
							<div id="sellerBadge" style="text-align: center;">
								<?php if ($verification_status === 'approved'): ?>
									<span class="seller-badge"><i class="fas fa-check-circle"></i> Seller Account</span>
								<?php elseif ($verification_status === 'pending'): ?>
									<div class="pending-application">
										<i class="fas fa-clock"></i>
										<h3>Application Pending Review</h3>
										<p>Your seller application is currently being reviewed by our team. You will be notified once approved.</p>
									</div>
								<?php else: ?>
									<span class="seller-badge" style="background: rgba(255, 68, 68, 0.1); color: #ff4444;">
										<i class="fas fa-times-circle"></i> Verification Required
									</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-bell"></i>
							<span>Notification Preferences</span>
						</div>

						<div class="notification-options">
							<label class="checkbox-item">
								<input type="checkbox" id="email_notifications" name="email_notifications" checked>
								<span>Email notifications for new messages</span>
							</label>
							<label class="checkbox-item">
								<input type="checkbox" id="order_updates" name="order_updates" checked>
								<span>Order updates and shipping notifications</span>
							</label>
							<label class="checkbox-item">
								<input type="checkbox" id="promo_emails" name="promo_emails">
								<span>Promotional emails and deals</span>
							</label>
						</div>
					</div>

					<!-- Action Buttons -->
					<div class="action-buttons">
						<button type="button" class="cancel-btn" id="cancelBtn">Cancel</button>
						<button type="submit" class="save-btn">Save Changes</button>
					</div>

					<div class="delete-zone">
						<button type="button" class="delete-btn" id="deleteAccountBtn">Delete Account</button>
					</div>
				</form>
			</div>
		</main>

		<div class="upgrade-modal" id="upgradeModal">
			<div class="upgrade-modal-content">
				<div class="upgrade-modal-header">
					<h2><i class="fas fa-store"></i> Apply to Become a Seller</h2>
					<button class="close-upgrade-modal" id="closeUpgradeModalBtn">&times;</button>
				</div>
				
				<form id="sellerUpgradeForm" method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="submit_seller_upgrade">
					
					<div class="form-group">
						<label>Store Name <span style="color: #666; font-size: 12px;">(optional)</span></label>
						<input type="text" name="store_name" placeholder="The name of your store">
					</div>
					
					<div class="form-group">
						<label>What do you aim to sell? <span class="required">*</span></label>
						<textarea name="selling_description" rows="3" placeholder="Describe the types of items you plan to sell..." required></textarea>
					</div>
					
					<div class="form-group">
						<label>ID Number <span class="required">*</span></label>
						<input type="text" name="id_number" id="id_number" maxlength="13" placeholder="13-digit South African ID" value="<?php echo htmlspecialchars($id_number); ?>" required>
					</div>
					
					<div class="form-group">
						<label>Upload ID Document/Passport Copy <span class="required">*</span></label>
						<div class="file-upload-group">
							<label class="file-upload-label">
								<i class="fas fa-cloud-upload-alt"></i> Choose File
								<input type="file" name="idDocument" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png" required>
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
								<input type="file" name="proofOfResidence" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png" required>
							</label>
							<span class="file-name" id="proofFileName">No file chosen</span>
						</div>
						<p class="password-hint">Accepted formats: PDF, JPG, PNG. (Utility bill, bank statement, etc.)</p>
					</div>
					
					<div class="action-buttons" style="margin-top: 20px;">
						<button type="button" class="cancel-btn" id="cancelUpgradeBtn">Cancel</button>
						<button type="submit" class="save-btn">Submit Application</button>
					</div>
				</form>
			</div>
		</div>

		<div class="modal" id="deleteModal">
			<div class="modal-content">
				<h3>Delete Account</h3>
				<p>Are you sure? This action cannot be undone. All your data will be permanently removed.</p>
				<div class="modal-buttons">
					<button class="cancel-delete" id="cancelDeleteBtn">Cancel</button>
					<button class="confirm-delete" id="confirmDeleteBtn">Delete</button>
				</div>
			</div>
		</div>
		
		<form id="deleteForm" method="post" action="" style="display: none;">
			<input type="hidden" name="action" value="delete_account">
		</form>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			const changePhotoBtn = document.getElementById('changePhotoBtn');
			const profilePhotoInput = document.getElementById('profilePhoto');
			const avatar = document.getElementById('avatar');
			
			if (changePhotoBtn) {
				changePhotoBtn.addEventListener('click', () => profilePhotoInput.click());
			}
			
			if (profilePhotoInput) {
				profilePhotoInput.addEventListener('change', (e) => {
					const file = e.target.files[0];
					if (file) {
						const reader = new FileReader();
						reader.onload = (event) => {
							avatar.innerHTML = `<img src="${event.target.result}" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">`;
						};
						reader.readAsDataURL(file);
					}
				});
			}

			const cancelBtn = document.getElementById('cancelBtn');
			if (cancelBtn) {
				cancelBtn.addEventListener('click', () => {
					if (confirm('Discard unsaved changes?')) {
						location.reload();
					}
				});
			}

			const upgradeBtn = document.getElementById('upgradeBtn');
			const upgradeModal = document.getElementById('upgradeModal');
			const closeUpgradeModalBtn = document.getElementById('closeUpgradeModalBtn');
			const cancelUpgradeBtn = document.getElementById('cancelUpgradeBtn');
			
			if (upgradeBtn) {
				upgradeBtn.addEventListener('click', () => {
					upgradeModal.classList.add('show');
				});
			}
			
			if (closeUpgradeModalBtn) {
				closeUpgradeModalBtn.addEventListener('click', () => {
					upgradeModal.classList.remove('show');
				});
			}
			
			if (cancelUpgradeBtn) {
				cancelUpgradeBtn.addEventListener('click', () => {
					upgradeModal.classList.remove('show');
				});
			}
			
			if (upgradeModal) {
				upgradeModal.addEventListener('click', (e) => {
					if (e.target === upgradeModal) {
						upgradeModal.classList.remove('show');
					}
				});
			}
			
			const idDocument = document.querySelector('input[name="idDocument"]');
			const proofDocument = document.querySelector('input[name="proofOfResidence"]');
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

			const deleteModal = document.getElementById('deleteModal');
			const deleteAccountBtn = document.getElementById('deleteAccountBtn');
			const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
			const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
			
			if (deleteAccountBtn) {
				deleteAccountBtn.addEventListener('click', () => {
					deleteModal.classList.add('show');
				});
			}
			
			if (cancelDeleteBtn) {
				cancelDeleteBtn.addEventListener('click', () => {
					deleteModal.classList.remove('show');
				});
			}
			
			if (confirmDeleteBtn) {
				confirmDeleteBtn.addEventListener('click', () => {
					if (confirm('WARNING: This will permanently delete your account and all associated data. Are you absolutely sure?')) {
						document.getElementById('deleteForm').submit();
					}
				});
			}
			
			if (deleteModal) {
				deleteModal.addEventListener('click', (e) => {
					if (e.target === deleteModal) {
						deleteModal.classList.remove('show');
					}
				});
			}
			
			const accountForm = document.getElementById('accountForm');
			if (accountForm) {
				accountForm.addEventListener('submit', (e) => {
					const fullName = document.getElementById('full_name');
					if (!fullName.value.trim()) {
						e.preventDefault();
						alert('Full name is required');
						fullName.focus();
					}
				});
			}
			
			// Seller upgrade form validation
			const sellerUpgradeForm = document.getElementById('sellerUpgradeForm');
			if (sellerUpgradeForm) {
				sellerUpgradeForm.addEventListener('submit', (e) => {
					const description = sellerUpgradeForm.querySelector('textarea[name="selling_description"]');
					const idNumber = sellerUpgradeForm.querySelector('input[name="id_number"]');
					const idDoc = sellerUpgradeForm.querySelector('input[name="idDocument"]');
					const proofDoc = sellerUpgradeForm.querySelector('input[name="proofOfResidence"]');
					
					if (!description.value.trim()) {
						e.preventDefault();
						alert('Please describe what you aim to sell');
						description.focus();
						return;
					}
					
					if (!idNumber.value.trim() || !/^\d{13}$/.test(idNumber.value.trim())) {
						e.preventDefault();
						alert('Please enter a valid 13-digit ID number');
						idNumber.focus();
						return;
					}

					const idValue = idNumber.value.trim();
					const year = parseInt(idValue.substring(0, 2));
					const month = parseInt(idValue.substring(2, 4));
					const day = parseInt(idValue.substring(4, 6));
					const currentYear = new Date().getFullYear();
					const fullYear = year <= parseInt(String(currentYear).substring(2)) ? 2000 + year : 1900 + year;
					const birthDate = new Date(fullYear, month - 1, day);
					const today = new Date();
					let age = today.getFullYear() - birthDate.getFullYear();
					if (today.getMonth() < birthDate.getMonth() || 
						(today.getMonth() === birthDate.getMonth() && today.getDate() < birthDate.getDate())) {
						age--;
					}
					
					if (age < 16) {
						e.preventDefault();
						alert('You must be at least 16 years old to become a seller.');
						return;
					}
					
					if (!idDoc.files.length) {
						e.preventDefault();
						alert('Please upload your ID document');
						return;
					}
					
					if (!proofDoc.files.length) {
						e.preventDefault();
						alert('Please upload proof of residence');
						return;
					}
				});
			}
		</script>
	</body>
</html>