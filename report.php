<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_name = getUserName();
$user_type = getUserType();

$reported_user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$reported_username = '';

if ($reported_user_id) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(bp.full_name, sp.full_name) as full_name, u.user_type
        FROM nexus_users u
        LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
        LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$reported_user_id]);
    $reported = $stmt->fetch();
    if ($reported) {
        $reported_username = $reported['full_name'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reported_user = $_POST['reported_user_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    $other_reason = $_POST['other_reason'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $block_user = isset($_POST['block_user']) ? 1 : 0;
    
    $errors = [];
    
    if (!$reported_user) {
        $errors[] = "Please specify which user you are reporting";
    }
    
    if (empty($reason)) {
        $errors[] = "Please select a reason for reporting";
    }
    
    if ($reason === 'other' && empty($other_reason)) {
        $errors[] = "Please specify the reason";
    }
    
    if (empty($description)) {
        $errors[] = "Please provide details about what happened";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
 
            $final_reason = ($reason === 'other') ? $other_reason : $reason;
            $stmt = $pdo->prepare("
                INSERT INTO user_reports (reporter_id, reported_id, reason, description, status, created_at) 
                VALUES (?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$user_id, $reported_user, $final_reason, $description]);
            $report_id = $pdo->lastInsertId();

            if (!empty($_FILES['evidence'])) {
                $upload_dir = 'uploads/reports/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                $max_file_size = 10 * 1024 * 1024; 
                
                foreach ($_FILES['evidence']['tmp_name'] as $index => $tmp_name) {
                    if ($_FILES['evidence']['error'][$index] === UPLOAD_ERR_OK) {
                        $file_type = $_FILES['evidence']['type'][$index];
                        $file_size = $_FILES['evidence']['size'][$index];
                        
                        if (in_array($file_type, $allowed_types) && $file_size <= $max_file_size) {
                            $file_ext = pathinfo($_FILES['evidence']['name'][$index], PATHINFO_EXTENSION);
                            $file_name = time() . '_' . $report_id . '_' . uniqid() . '.' . $file_ext;
                            $file_path = $upload_dir . $file_name;
                            
                            if (move_uploaded_file($tmp_name, $file_path)) {
                                $stmt = $pdo->prepare("INSERT INTO report_attachments (report_id, file_name, file_path, file_type) VALUES (?, ?, ?, ?)");
                                $stmt->execute([$report_id, $_FILES['evidence']['name'][$index], $file_path, $file_type]);
                            }
                        }
                    }
                }
            }

            if ($block_user) {
                $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $reported_user]);
            }
            
            $pdo->commit();
            
            $success = "Thank you for your report. Our safety team will review it within 24-48 hours.";
            if ($block_user) {
                $success .= " You have also blocked this user.";
            }

            $_POST = array();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to submit report. Please try again later.";
            error_log("Report error: " . $e->getMessage());
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

$reporting_user_id = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$reporting_username = '';
if ($reporting_user_id) {
    $stmt = $pdo->prepare("
        SELECT COALESCE(bp.full_name, sp.full_name) as full_name
        FROM nexus_users u
        LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
        LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$reporting_user_id]);
    $reported_user_data = $stmt->fetch();
    if ($reported_user_data) {
        $reporting_username = $reported_user_data['full_name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Report User</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.report-page {
				padding-top: 50px;
				padding-bottom: 80px;
			}

			.report-container {
				max-width: 900px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.report-header {
				text-align: center;
				margin-bottom: 32px;
			}

			.report-header h1 {
				font-size: 36px;
				color: #ff4444;
				margin-bottom: 12px;
				display: flex;
				justify-content: center;
				align-items: center;
				gap: 12px;
			}

			.report-header p {
				color: #888;
				font-size: 15px;
			}

			.username-highlight {
				color: #8ff5ff;
				font-weight: 600;
				background: rgba(143, 245, 255, 0.1);
				padding: 8px 20px;
				border-radius: 40px;
				display: inline-block;
				font-size: 16px;
			}

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
				text-align: center;
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

			.form-section {
				margin-bottom: 32px;
				padding-bottom: 8px;
				border-bottom: 1px solid rgba(255, 255, 255, 0.05);
			}

			.section-title {
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 10px;
				margin-bottom: 20px;
				font-size: 18px;
				font-weight: 600;
				color: #fff;
			}

			.section-title i {
				color: #8ff5ff;
				font-size: 20px;
			}

			.required {
				color: #ff4444;
				font-size: 12px;
			}

			.radio-group {
				display: flex;
				flex-direction: column;
				gap: 12px;
				align-items: center;
			}

			.radio-option {
				display: flex;
				align-items: center;
				gap: 14px;
				padding: 14px 18px;
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				cursor: pointer;
				transition: all 0.2s;
				width: 100%;
				max-width: 500px;
			}

			.radio-option:hover {
				background: #1a1a1a;
				border-color: #8ff5ff;
			}

			.radio-option input[type="radio"] {
				width: 18px;
				height: 18px;
				cursor: pointer;
				accent-color: #8ff5ff;
			}

			.radio-option span {
				flex: 1;
				cursor: pointer;
				color: #e5e5e5;
			}

			.other-reason {
				margin-top: 12px;
				display: none;
				text-align: center;
			}

			.other-reason.show {
				display: block;
			}

			.other-reason input {
				width: 100%;
				max-width: 470px;
				padding: 12px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 10px;
				color: #e5e5e5;
				font-size: 14px;
			}

			.other-reason input:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			textarea {
				width: 100%;
				max-width: 600px;
				padding: 14px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				color: #e5e5e5;
				font-family: inherit;
				font-size: 14px;
				resize: vertical;
				min-height: 120px;
				display: block;
				margin: 0 auto;
			}

			textarea:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			.file-upload {
				border: 2px dashed #2a2a2a;
				border-radius: 16px;
				padding: 32px;
				text-align: center;
				cursor: pointer;
				transition: all 0.2s;
				background: #0e0e10;
				max-width: 500px;
				margin: 0 auto;
			}

			.file-upload:hover {
				border-color: #8ff5ff;
				background: #1a1a1a;
			}

			.file-upload i {
				font-size: 48px;
				color: #8ff5ff;
				margin-bottom: 12px;
			}

			.file-upload p {
				color: #888;
				font-size: 13px;
			}

			.file-upload .small {
				font-size: 11px;
				margin-top: 6px;
			}

			.file-upload input {
				display: none;
			}

			.file-list {
				margin-top: 16px;
				display: flex;
				flex-wrap: wrap;
				gap: 10px;
				justify-content: center;
			}

			.file-tag {
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 6px 14px;
				font-size: 12px;
				display: inline-flex;
				align-items: center;
				gap: 8px;
				color: #e5e5e5;
			}

			.file-tag button {
				background: none;
				border: none;
				color: #ff4444;
				cursor: pointer;
				font-size: 12px;
			}

			.checkbox-option {
				display: flex;
				align-items: flex-start;
				gap: 12px;
				cursor: pointer;
				padding: 12px 0;
				width: fit-content;
				margin: 0 auto;
			}

			.checkbox-option input {
				width: 18px;
				height: 18px;
				margin-top: 2px;
				accent-color: #8ff5ff;
			}

			.checkbox-option span {
				flex: 1;
				color: #e5e5e5;
			}

			.checkbox-hint {
				font-size: 12px;
				color: #666;
				margin-top: 4px;
				text-align: center;
			}

			.emergency-section {
				background: #1a1a1a;
				border: 1px solid #ff4444;
				border-radius: 16px;
				padding: 24px 28px;
				margin: 32px auto;
				max-width: 500px;
			}

			.emergency-section h3 {
				color: #ff4444;
				margin-bottom: 12px;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 10px;
				font-size: 18px;
			}

			.emergency-section p {
				color: #aaa;
				font-size: 14px;
				margin-bottom: 16px;
				text-align: center;
			}

			.emergency-buttons {
				display: flex;
				gap: 16px;
				flex-wrap: wrap;
				justify-content: center;
			}

			.emergency-btn {
				display: inline-flex;
				align-items: center;
				gap: 10px;
				padding: 10px 24px;
				border-radius: 40px;
				text-decoration: none;
				font-weight: 500;
				font-size: 14px;
				transition: all 0.2s;
			}

			.emergency-btn.email {
				background: transparent;
				border: 1px solid #ff4444;
				color: #ff4444;
			}

			.emergency-btn.call {
				background: #ff4444;
				color: white;
			}

			.emergency-btn:hover {
				transform: translateY(-2px);
			}

			.submit-btn {
				background: #ff4444;
				color: white;
				border: none;
				border-radius: 40px;
				padding: 14px 32px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
				margin-top: 16px;
				display: flex;
				align-items: center;
				justify-content: center;
				gap: 10px;
				width: fit-content;
				margin-left: auto;
				margin-right: auto;
			}

			.submit-btn:hover {
				background: #cc0000;
				transform: translateY(-2px);
			}

			@media (max-width: 768px) {
				.report-page {
					padding-top: 100px;
				}
				.report-header h1 {
					font-size: 28px;
				}
				.radio-option {
					padding: 12px 16px;
				}
				.emergency-section {
					padding: 20px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="report-page">
			<div class="report-container">
				<div class="report-header">
					<h1><i class="fas fa-flag"></i> Report User</h1>
					<p>Help keep the Nexus community safe by reporting inappropriate behaviour</p>
				</div>

				<?php if (isset($error)): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<?php if (isset($success)): ?>
					<div class="alert alert-success"><?php echo $success; ?></div>
				<?php endif; ?>

				<form id="reportForm" method="post" action="" enctype="multipart/form-data">
					<input type="hidden" name="reported_user_id" value="<?php echo $reporting_user_id; ?>">

					<div style="text-align: center; margin-bottom: 24px;">
						<p style="margin-bottom: 8px; color: #888;">You are reporting:</p>
						<span class="username-highlight">
							<i class="fas fa-user"></i> <?php echo htmlspecialchars($reporting_username ?: 'User'); ?>
						</span>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-exclamation-triangle"></i>
							<span>Reason for reporting <span class="required">*</span></span>
						</div>
						<div class="radio-group" id="reasonOptions">
							<label class="radio-option">
								<input type="radio" name="reason" value="sexual-misconduct">
								<span>Sexual Misconduct</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="fraudulent-activity">
								<span>Fraudulent Activity</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="false-advertising">
								<span>False Advertising / Misleading Listing</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="harassment">
								<span>Harassment or Bullying</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="hate-speech">
								<span>Hate Speech / Discrimination</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="scam">
								<span>Scam or Phishing Attempt</span>
							</label>
							<label class="radio-option">
								<input type="radio" name="reason" value="other" id="otherRadio">
								<span>Other</span>
							</label>
						</div>
						
						<div class="other-reason" id="otherReasonBox">
							<input type="text" name="other_reason" id="otherReason" placeholder="Please specify the reason...">
						</div>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-comment-dots"></i>
							<span>What happened? <span class="required">*</span></span>
						</div>
						<textarea name="description" id="description" placeholder="Please describe the incident in detail. Include dates, message content, listing IDs, or any other relevant information..."></textarea>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-image"></i>
							<span>Upload Evidence (If available)</span>
						</div>
						<div class="file-upload" id="fileUploadArea">
							<i class="fas fa-cloud-upload-alt"></i>
							<p>Click or drag files here to upload</p>
							<p class="small">PNG, JPG, PDF up to 10MB</p>
							<input type="file" name="evidence[]" id="fileInput" multiple accept="image/*,application/pdf">
						</div>
						<div class="file-list" id="fileList"></div>
					</div>

					<div class="form-section">
						<div class="section-title">
							<i class="fas fa-ban"></i>
							<span>Block User</span>
						</div>
						<label class="checkbox-option">
							<input type="checkbox" name="block_user" id="blockUser">
							<span>Also block this user</span>
						</label>
						<p class="checkbox-hint">Blocking will prevent this user from contacting you or viewing your profile.</p>
					</div>

					<div class="emergency-section">
						<h3><i class="fas fa-exclamation-triangle"></i> Immediate Emergency?</h3>
						<p>If this is an urgent safety concern, please contact us immediately:</p>
						<div class="emergency-buttons">
							<a href="mailto:safety@nexusmarketplace.co.za?subject=URGENT%20Safety%20Report" class="emergency-btn email">
								<i class="fas fa-envelope"></i> Send Email
							</a>
							<a href="tel:+27123456789" class="emergency-btn call">
								<i class="fas fa-phone"></i> Call Us
							</a>
						</div>
					</div>

					<button type="submit" class="submit-btn">
						<i class="fas fa-paper-plane"></i> Submit Report
					</button>
				</form>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			const otherRadio = document.getElementById('otherRadio');
			const otherReasonBox = document.getElementById('otherReasonBox');
			
			if (otherRadio) {
				otherRadio.addEventListener('change', function() {
					otherReasonBox.classList.toggle('show', this.checked);
				});
			}

			const fileInput = document.getElementById('fileInput');
			const fileUploadArea = document.getElementById('fileUploadArea');
			const fileList = document.getElementById('fileList');
			let selectedFiles = [];

			if (fileUploadArea) {
				fileUploadArea.addEventListener('click', () => fileInput.click());
				
				fileUploadArea.addEventListener('dragover', (e) => {
					e.preventDefault();
					fileUploadArea.style.borderColor = '#8ff5ff';
				});
				
				fileUploadArea.addEventListener('dragleave', () => {
					fileUploadArea.style.borderColor = '#2a2a2a';
				});
				
				fileUploadArea.addEventListener('drop', (e) => {
					e.preventDefault();
					fileUploadArea.style.borderColor = '#2a2a2a';
					handleFiles(e.dataTransfer.files);
				});
			}
			
			if (fileInput) {
				fileInput.addEventListener('change', (e) => handleFiles(e.target.files));
			}

			function handleFiles(files) {
				for (let file of files) {
					if (file.size <= 10 * 1024 * 1024) {
						selectedFiles.push(file);
						addFileTag(file.name);
					} else {
						alert(`${file.name} exceeds 10MB limit`);
					}
				}
			}

			function addFileTag(fileName) {
				const tag = document.createElement('div');
				tag.className = 'file-tag';
				tag.innerHTML = `
					<i class="fas fa-file"></i>
					<span>${fileName.substring(0, 20)}${fileName.length > 20 ? '...' : ''}</span>
					<button type="button" onclick="this.parentElement.remove(); removeFile('${fileName}')"><i class="fas fa-times"></i></button>
				`;
				fileList.appendChild(tag);
			}
			
			function removeFile(fileName) {
				selectedFiles = selectedFiles.filter(f => f.name !== fileName);
			}

			const reportForm = document.getElementById('reportForm');
			
			if (reportForm) {
				reportForm.addEventListener('submit', (e) => {
					let hasError = false;

					const reasonRadios = document.querySelectorAll('input[name="reason"]');
					let reasonSelected = false;
					for (let radio of reasonRadios) {
						if (radio.checked) {
							reasonSelected = true;
							break;
						}
					}
					
					if (!reasonSelected) {
						alert('Please select a reason for reporting');
						e.preventDefault();
						return;
					}

					if (otherRadio && otherRadio.checked) {
						const otherReason = document.getElementById('otherReason');
						if (!otherReason.value.trim()) {
							alert('Please specify the reason');
							e.preventDefault();
							return;
						}
					}

					const description = document.getElementById('description');
					if (!description.value.trim()) {
						alert('Please provide details about what happened');
						e.preventDefault();
						return;
					}
				});
			}
		</script>
	</body>
</html>