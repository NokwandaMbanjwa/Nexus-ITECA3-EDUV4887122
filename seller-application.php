<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$error = '';
$success = '';
$submitted = false;

$stmt = $pdo->prepare("
    SELECT bp.id_passport_number, bp.full_name, bp.phone_number,
           bp.residential_address, bp.city_town, bp.province, bp.postal_code,
           sp.verification_status
    FROM nexus_users u
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$id_number = $user['id_passport_number'] ?? '';
$verification_status = $user['verification_status'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
                $fileName = time() . '_id_' . $user_id . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($_FILES['idDocument']['tmp_name'], $filePath)) {
                    $errors[] = "Failed to upload ID document";
                } else {
                    $idDocumentPath = $filePath;
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
                $fileName = time() . '_proof_' . $user_id . '.' . $fileExt;
                $filePath = $uploadDir . $fileName;
                
                if (!move_uploaded_file($_FILES['proofOfResidence']['tmp_name'], $filePath)) {
                    $errors[] = "Failed to upload proof of residence";
                } else {
                    $proofDocumentPath = $filePath;
                }
            } else {
                $errors[] = "Proof of residence must be PDF, JPG, or PNG";
            }
        }
    } else {
        $errors[] = "Proof of residence is required";
    }
    
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
                    $buyer['full_name'] ?? '', 
                    $buyer['phone_number'] ?? '', 
                    $id_number,
                    $buyer['residential_address'] ?? '',
                    $buyer['city_town'] ?? '',
                    $buyer['province'] ?? '',
                    $buyer['postal_code'] ?? '',
                    $store_name, 
                    $selling_description,
                    $documents
                ]);
            }
            
            $stmt = $pdo->prepare("UPDATE nexus_users SET user_type = 'seller' WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            $pdo->commit();

            $_SESSION['user_type'] = 'seller';
            $submitted = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Failed to submit application. Please try again.";
            error_log("Seller upgrade error: " . $e->getMessage());
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Apply to Sell</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

		<style>
			.apply-page {
				padding: 50px 0 40px;
				min-height: 100vh;
				display: flex;
				align-items: center;
				justify-content: center;
			}
			
			.apply-container {
				max-width: 600px;
				margin: 0 auto;
				padding: 0 24px;
				width: 100%;
			}
			
			.apply-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 40px 32px;
			}
			
			.apply-header {
				text-align: center;
				margin-bottom: 32px;
			}
			
			.apply-header i {
				font-size: 48px;
				color: #8ff5ff;
				margin-bottom: 16px;
				display: block;
			}
			
			.apply-header h1 {
				font-size: 28px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}
			
			.apply-header p {
				color: #888;
				font-size: 14px;
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
			
			.form-group {
				margin-bottom: 20px;
			}
			
			.form-group label {
				display: block;
				margin-bottom: 8px;
				font-size: 14px;
				color: #aaa;
			}
			
			.form-group input,
			.form-group textarea {
				width: 100%;
				padding: 12px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				color: #e5e5e5;
				font-size: 15px;
				box-sizing: border-box;
			}
			
			.form-group input:focus,
			.form-group textarea:focus {
				outline: none;
				border-color: #8ff5ff;
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
			
			.hint {
				font-size: 11px;
				color: #666;
				margin-top: 5px;
			}
			
			.action-buttons {
				display: flex;
				gap: 12px;
				margin-top: 24px;
			}
			
			.submit-btn {
				flex: 1;
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				padding: 14px;
				border-radius: 12px;
				font-size: 15px;
				font-weight: 600;
				cursor: pointer;
			}
			
			.submit-btn:hover {
				background: #7de0ea;
			}
			
			.back-btn {
				flex:1;
				padding: 14px 24px;
				background: transparent;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				color: #aaa;
				font-size: 15px;
				cursor: pointer;
				text-decoration: none;
				display: inline-flex;
				align-items: center;
				justify-content: center;
				gap: 8px;
			}
			
			.back-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}
			
			.pending-notice {
				text-align: center;
				padding: 40px;
			}
			
			.pending-notice i {
				font-size: 64px;
				color: #ffc107;
				margin-bottom: 16px;
				display: block;
			}
			
			.pending-notice h2 {
				color: #ffc107;
				margin-bottom: 8px;
			}
			
			.pending-notice p {
				color: #888;
				margin-bottom: 24px;
			}
			
			.required {
				color: #ff6b6b;
			}
			.success-notice {
				text-align: center;
				padding: 40px 20px;
			}

			.success-notice i {
				font-size: 64px;
				color: #4caf50;
				margin-bottom: 16px;
				display: block;
			}

			.success-notice h2 {
				color: #4caf50;
				margin-bottom: 8px;
				font-size: 24px;
			}

			.success-notice p {
				color: #888;
				margin-bottom: 24px;
				font-size: 14px;
				line-height: 1.6;
			}

			.btn-spinner {
				color: #0a0a0a;
			}
			@media (max-width: 768px) {
				.apply-page {
					padding: 40px 0;
				}
				.apply-card {
					padding: 24px 16px;
				}
				.apply-header h1 {
					font-size: 24px;
				}
				.action-buttons {
					flex-direction: column;
				}
				.back-btn {
					justify-content: center;
				}
			}
		</style>
	</head>
	<body>
		<?php include 'header.php'; ?>
		
		<main class="apply-page">
			<div class="apply-container">
				<?php if ($verification_status === 'pending' || $submitted): ?>
					<div class="apply-card">
						<div class="success-notice">
							<i class="fas fa-check-circle"></i>
							<h2>Application Submitted!</h2>
							<p>Your seller application has been submitted successfully and is pending review. You will be notified once it's approved.</p>
							<a href="<?php echo htmlspecialchars($_GET['redirect'] ?? 'explore-feed.php'); ?>" class="back-btn" style="display: inline-flex; margin-top: 16px;"> Back </a>
						</div>
					</div>
				<?php else: ?>
					<div class="apply-card">
						<div class="apply-header">
							<i class="fas fa-store"></i>
							<h1>Become a Seller</h1>
							<p>Start selling your items on NEXUS Marketplace</p>
						</div>
						
						<?php if ($error): ?>
							<div class="alert alert-error"><?php echo $error; ?></div>
						<?php endif; ?>
						
						<form method="post" enctype="multipart/form-data" id="sellerForm">
							<input type="hidden" name="action" value="submit_seller_upgrade">
							<input type="hidden" name="redirect" value="<?php echo htmlspecialchars($_GET['redirect'] ?? 'account-details.php'); ?>">
							
							<div class="form-group">
								<label>Store Name <span style="color: #666; font-size: 12px;">(optional)</span></label>
								<input type="text" name="store_name" placeholder="e.g., John's Vintage Shop">
							</div>
							
							<div class="form-group">
								<label>What do you aim to sell? <span class="required">*</span></label>
								<textarea name="selling_description" rows="3" placeholder="Describe the types of items you plan to sell..." required></textarea>
							</div>
							
							<div class="form-group">
								<label>ID Number <span class="required">*</span></label>
								<input type="text" name="id_number" id="id_number" maxlength="13" 
									   placeholder="13-digit South African ID" 
									   value="<?php echo htmlspecialchars($id_number); ?>" required>
							</div>
							
							<div class="form-group">
								<label>Upload ID Document <span class="required">*</span></label>
								<div class="file-upload-group">
									<label class="file-upload-label">
										<i class="fas fa-cloud-upload-alt"></i> Choose File
										<input type="file" name="idDocument" class="file-upload-input" accept=".pdf,.jpg,.jpeg,.png" required>
									</label>
									<span class="file-name" id="idFileName">No file chosen</span>
								</div>
								<p class="hint">Accepted: PDF, JPG, PNG. Max 5MB.</p>
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
								<p class="hint">Utility bill, bank statement, etc. PDF, JPG, PNG. Max 5MB.</p>
							</div>
							
							<div class="action-buttons">
								<a href="<?php echo htmlspecialchars($_GET['redirect'] ?? 'account-details.php'); ?>" class="back-btn">
									<i class="fas fa-arrow-left"></i> Cancel
								</a>
								<button type="submit" class="submit-btn" id="submitBtn">
									<span class="btn-text">Submit Application</span>
									<span class="btn-spinner" style="display: none;">
										<i class="fas fa-spinner fa-spin"></i> Submitting...
									</span>
								</button>
							</div>
						</form>
					</div>
				<?php endif; ?>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script>
			// Show selected file names
			document.querySelector('input[name="idDocument"]').addEventListener('change', function(e) {
				document.getElementById('idFileName').textContent = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
			});
			
			document.querySelector('input[name="proofOfResidence"]').addEventListener('change', function(e) {
				document.getElementById('proofFileName').textContent = e.target.files[0] ? e.target.files[0].name : 'No file chosen';
			});
			
			// Form validation with loading state
			document.getElementById('sellerForm').addEventListener('submit', function(e) {
				const description = this.querySelector('textarea[name="selling_description"]');
				const idNumber = this.querySelector('input[name="id_number"]');
				const idDoc = this.querySelector('input[name="idDocument"]');
				const proofDoc = this.querySelector('input[name="proofOfResidence"]');
				
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
				
				// Show loading state
				document.querySelector('.btn-text').style.display = 'none';
				document.querySelector('.btn-spinner').style.display = 'inline';
				document.getElementById('submitBtn').disabled = true;
			});
		</script>
	</body>
</html>