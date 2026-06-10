<?php
require_once 'admin-auth.php';

if (!$is_super_admin && $admin_role !== 'verification') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $seller_id = $_POST['seller_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE seller_profiles SET verification_status = 'approved', rejection_reason = NULL WHERE profile_id = ?");
        $stmt->execute([$seller_id]);
        
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM seller_profiles WHERE profile_id = ?");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'seller_verification', 'Seller Verification Approved', 'Congratulations! Your seller account has been verified. You can now list items for sale.', ?)");
        $stmt->execute([$seller['user_id'], $seller_id]);
        
        $success = "Seller verified successfully!";
        
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE seller_profiles SET verification_status = 'rejected', rejection_reason = ? WHERE profile_id = ?");
        $stmt->execute([$reason, $seller_id]);
 
        $stmt = $pdo->prepare("SELECT user_id, full_name FROM seller_profiles WHERE profile_id = ?");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch();
        
        // Send rejection notification
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'seller_verification', 'Seller Verification Declined', 'Your seller verification has been declined. Reason: ' || ?, ?)");
        $stmt->execute([$seller['user_id'], $reason, $seller_id]);
        
        $success = "Seller verification declined.";
        
    } elseif ($action === 'review_appeal') {
        $appeal_action = $_POST['appeal_action'] ?? '';
        $appeal_reason = $_POST['appeal_reason'] ?? '';
        
        if ($appeal_action === 'approve_appeal') {
            $stmt = $pdo->prepare("UPDATE seller_profiles SET verification_status = 'approved', appeal_status = 'approved', rejection_reason = NULL WHERE profile_id = ?");
            $stmt->execute([$seller_id]);
            
            $message = "Your appeal has been approved! Your seller account is now verified.";
        } else {
            $stmt = $pdo->prepare("UPDATE seller_profiles SET appeal_status = 'rejected', rejection_reason = ? WHERE profile_id = ?");
            $stmt->execute([$appeal_reason, $seller_id]);
            
            $message = "Your appeal has been reviewed and declined. Reason: " . $appeal_reason;
        }

        $stmt = $pdo->prepare("SELECT user_id FROM seller_profiles WHERE profile_id = ?");
        $stmt->execute([$seller_id]);
        $seller = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'appeal', 'Appeal Review Complete', ?, ?)");
        $stmt->execute([$seller['user_id'], $message, $seller_id]);
        
        $success = "Appeal reviewed successfully!";
    }
}

$stmt = $pdo->prepare("
    SELECT sp.*, u.email, 
           (SELECT COUNT(*) FROM products WHERE seller_id = sp.profile_id) as total_products
    FROM seller_profiles sp
    JOIN nexus_users u ON sp.user_id = u.user_id
    WHERE sp.verification_status = 'pending'
    ORDER BY sp.created_at ASC
");
$stmt->execute();
$pending_sellers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT sp.*, u.email,
           (SELECT COUNT(*) FROM products WHERE seller_id = sp.profile_id) as total_products
    FROM seller_profiles sp
    JOIN nexus_users u ON sp.user_id = u.user_id
    WHERE sp.appeal_status = 'pending'
    ORDER BY sp.appealed_at ASC
");
$stmt->execute();
$appeal_sellers = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT sp.*, u.email,
           (SELECT COUNT(*) FROM products WHERE seller_id = sp.profile_id) as total_products
    FROM seller_profiles sp
    JOIN nexus_users u ON sp.user_id = u.user_id
    WHERE sp.verification_status = 'approved'
    ORDER BY sp.created_at DESC
    LIMIT 20
");
$stmt->execute();
$verified_sellers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin |Verify Sellers</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.admin-container {
				padding: 40px 24px 60px;
				max-width: 1400px;
				margin: 0 auto;
			}
			
			.page-header {
				margin-bottom: 30px;
			}
			
			.page-header h1 {
				color: #b6b5d8;
				font-size: 32px;
			}
			
			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 40px;
			}
			
			.stat-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
				text-align: center;
			}
			
			.stat-card h3 {
				font-size: 36px;
				color: #b6b5d8;
				margin-bottom: 5px;
			}
			
			.section-title {
				font-size: 24px;
				color: #fff;
				margin: 30px 0 20px;
				padding-bottom: 10px;
				border-bottom: 1px solid #2a2a2a;
			}
			
			.seller-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 20px;
				overflow: hidden;
			}
			
			.seller-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}
			
			.seller-info h3 {
				color: #fff;
				margin-bottom: 5px;
			}
			
			.seller-info p {
				color: #888;
				font-size: 13px;
			}
			
			.status-badge {
				padding: 5px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 500;
			}
			
			.status-pending {
				background: rgba(255, 193, 7, 0.15);
				color: #ffc107;
			}
			
			.status-approved {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
			}
			
			.status-rejected {
				background: rgba(255, 68, 68, 0.15);
				color: #ff4444;
			}
			
			.seller-body {
				padding: 20px;
			}
			
			.info-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 20px;
				margin-bottom: 20px;
			}
			
			.info-item {
				background: #0e0e10;
				padding: 12px;
				border-radius: 8px;
			}
			
			.info-item label {
				font-size: 11px;
				color: #888;
				display: block;
				margin-bottom: 5px;
			}
			
			.info-item .value {
				color: #e5e5e5;
				font-size: 14px;
				word-break: break-word;
			}
			
			.document-link {
				color: #b6b5d8;
				text-decoration: none;
			}
			
			.document-link:hover {
				text-decoration: underline;
			}
			
			.action-buttons {
				display: flex;
				gap: 12px;
				margin-top: 20px;
				flex-wrap: wrap;
			}
			
			.btn-approve {
				background: #4caf50;
				color: white;
				border: none;
				padding: 10px 24px;
				border-radius: 8px;
				cursor: pointer;
			}
			
			.btn-reject {
				background: #ff4444;
				color: white;
				border: none;
				padding: 10px 24px;
				border-radius: 8px;
				cursor: pointer;
			}
			
			.reject-form {
				display: none;
				margin-top: 15px;
				padding: 15px;
				background: #0e0e10;
				border-radius: 8px;
			}
			
			.reject-form textarea {
				width: 100%;
				padding: 10px;
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				margin-bottom: 10px;
			}
			
			.empty-state {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 12px;
				color: #888;
			}
			
			.appeal-section {
				background: rgba(255, 193, 7, 0.05);
				border: 1px solid #ffc107;
				border-radius: 8px;
				padding: 15px;
				margin-top: 15px;
			}
			
			.appeal-section h4 {
				color: #ffc107;
				margin-bottom: 10px;
			}
			
			@media (max-width: 768px) {
				.admin-container {
					padding: 80px 16px 40px;
				}
				.seller-header {
					flex-direction: column;
					align-items: flex-start;
				}
				.info-grid {
					grid-template-columns: 1fr;
				}
				.page-header h1 {
					font-size: 24px;
				}
				.section-title {
					font-size: 20px;
				}
				.action-buttons {
					flex-direction: column;
				}
				.btn-approve, .btn-reject {
					width: 100%;
					text-align: center;
				}
				.stats-grid {
					gap: 12px;
				}
				.stat-card {
					padding: 14px;
				}
				.stat-card h3 {
					font-size: 28px;
				}
			}

			@media (max-width: 480px) {
				.admin-container {
					padding: 70px 12px 30px;
				}
				.seller-body {
					padding: 14px;
				}
				.info-item {
					padding: 10px;
				}
				.info-item .value {
					font-size: 13px;
				}
				.seller-info h3 {
					font-size: 16px;
				}
				.empty-state {
					padding: 40px 20px;
				}
				.appeal-section {
					padding: 12px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'admin-header.php'; ?>
		<?php include 'admin-sidebar.php'; ?>
		
		<main class="admin-main">
			<div class="admin-container">
				<div class="page-header">
					<h1><i class="fas fa-user-check"></i> Verify Seller Accounts</h1>
					<p>Review seller applications and manage verifications</p>
				</div>
				
				<!-- Stats -->
				<div class="stats-grid">
					<div class="stat-card">
						<h3><?php echo count($pending_sellers); ?></h3>
						<p>Pending Verification</p>
					</div>
					<div class="stat-card">
						<h3><?php echo count($appeal_sellers); ?></h3>
						<p>Pending Appeals</p>
					</div>
					<div class="stat-card">
						<h3><?php echo count($verified_sellers); ?></h3>
						<p>Verified Sellers</p>
					</div>
				</div>
				
				<!-- Pending Verifications Section -->
				<h2 class="section-title">Pending Verifications</h2>
				<?php if (empty($pending_sellers)): ?>
					<div class="empty-state">
						<i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
						<p>No pending seller verifications</p>
					</div>
				<?php else: ?>
					<?php foreach ($pending_sellers as $seller): ?>
						<div class="seller-card" id="seller-<?php echo $seller['profile_id']; ?>">
							<div class="seller-header">
								<div class="seller-info">
									<h3><?php echo htmlspecialchars($seller['store_name'] ?: $seller['full_name']); ?></h3>
									<p><?php echo htmlspecialchars($seller['email']); ?> | <?php echo htmlspecialchars($seller['phone_number']); ?></p>
								</div>
								<span class="status-badge status-pending">Pending Review</span>
							</div>
							<div class="seller-body">
								<div class="info-grid">
									<div class="info-item">
										<label>Full Name</label>
										<div class="value"><?php echo htmlspecialchars($seller['full_name']); ?></div>
									</div>
									<div class="info-item">
										<label>ID Number</label>
										<div class="value"><?php echo htmlspecialchars(substr($seller['id_passport_number'], 0, 6) . '******'); ?></div>
									</div>
									<div class="info-item">
										<label>Address</label>
										<div class="value"><?php echo htmlspecialchars($seller['residential_address']); ?>, <?php echo htmlspecialchars($seller['city_town']); ?></div>
									</div>
									<div class="info-item">
										<label>Store Description</label>
										<div class="value"><?php echo nl2br(htmlspecialchars(substr($seller['selling_description'] ?? 'No description', 0, 200))); ?></div>
									</div>
									<?php 
									// Fetch documents from seller_documents table
									$doc_stmt = $pdo->prepare("SELECT * FROM seller_documents WHERE seller_id = ?");
									$doc_stmt->execute([$seller['profile_id']]);
									$seller_docs = $doc_stmt->fetchAll();

									if (!empty($seller_docs)):
									?>
									<div class="info-item">
										<label>Documents</label>
										<div class="value">
											<?php foreach ($seller_docs as $doc): ?>
												<a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="document-link">
													<i class="fas fa-file"></i> 
													<?php echo $doc['document_type'] === 'id_copy' ? 'ID Document' : 'Proof of Residence'; ?>
												</a><br>
											<?php endforeach; ?>
										</div>
									</div>
									<?php endif; ?>
								</div>
								
								<div class="action-buttons">
									<button class="btn-approve" onclick="approveSeller(<?php echo $seller['profile_id']; ?>)">✓ Approve Seller</button>
									<button class="btn-reject" onclick="showRejectForm(<?php echo $seller['profile_id']; ?>)">✗ Reject</button>
								</div>
								<div class="reject-form" id="reject-form-<?php echo $seller['profile_id']; ?>">
									<textarea id="reason-<?php echo $seller['profile_id']; ?>" rows="3" placeholder="Reason for rejection..."></textarea>
									<div>
										<button class="btn-reject" onclick="rejectSeller(<?php echo $seller['profile_id']; ?>)">Submit Rejection</button>
										<button class="btn-approve" style="background: #666;" onclick="hideRejectForm(<?php echo $seller['profile_id']; ?>)">Cancel</button>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
				
				<h2 class="section-title">Pending Appeals</h2>
				<?php if (empty($appeal_sellers)): ?>
					<div class="empty-state">
						<i class="fas fa-gavel" style="font-size: 48px; margin-bottom: 16px;"></i>
						<p>No pending appeals</p>
					</div>
				<?php else: ?>
					<?php foreach ($appeal_sellers as $seller): ?>
						<div class="seller-card">
							<div class="seller-header">
								<div class="seller-info">
									<h3><?php echo htmlspecialchars($seller['store_name'] ?: $seller['full_name']); ?></h3>
									<p><?php echo htmlspecialchars($seller['email']); ?></p>
								</div>
								<span class="status-badge status-pending">Appeal Pending</span>
							</div>
							<div class="seller-body">
								<div class="appeal-section">
									<h4><i class="fas fa-comment"></i> Appeal Reason</h4>
									<p><?php echo nl2br(htmlspecialchars($seller['appeal_reason'])); ?></p>
									<p class="small" style="color: #888; margin-top: 8px;">Appealed on: <?php echo date('d M Y H:i', strtotime($seller['appealed_at'])); ?></p>
								</div>
								<div class="action-buttons">
									<button class="btn-approve" onclick="reviewAppeal(<?php echo $seller['profile_id']; ?>, 'approve')">Approve Appeal</button>
									<button class="btn-reject" onclick="reviewAppeal(<?php echo $seller['profile_id']; ?>, 'reject')">Reject Appeal</button>
								</div>
								<div class="reject-form" id="appeal-reject-form-<?php echo $seller['profile_id']; ?>">
									<textarea id="appeal-reason-<?php echo $seller['profile_id']; ?>" rows="3" placeholder="Reason for rejecting appeal..."></textarea>
									<div>
										<button class="btn-reject" onclick="submitAppealRejection(<?php echo $seller['profile_id']; ?>)">Submit</button>
										<button class="btn-approve" style="background: #666;" onclick="hideAppealRejectForm(<?php echo $seller['profile_id']; ?>)">Cancel</button>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</main>
		
		<form id="actionForm" method="post" style="display: none;">
			<input type="hidden" name="action" id="actionType">
			<input type="hidden" name="seller_id" id="sellerId">
			<input type="hidden" name="reason" id="reasonText">
			<input type="hidden" name="appeal_action" id="appealAction">
		</form>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			function approveSeller(sellerId) {
				if (confirm('Approve this seller\'s account?')) {
					document.getElementById('actionType').value = 'approve';
					document.getElementById('sellerId').value = sellerId;
					document.getElementById('actionForm').submit();
				}
			}
			
			function showRejectForm(sellerId) {
				document.getElementById('reject-form-' + sellerId).style.display = 'block';
			}
			
			function hideRejectForm(sellerId) {
				document.getElementById('reject-form-' + sellerId).style.display = 'none';
			}
			
			function rejectSeller(sellerId) {
				const reason = document.getElementById('reason-' + sellerId).value;
				if (!reason) {
					alert('Please provide a reason for rejection');
					return;
				}
				if (confirm('Reject this seller\'s application?')) {
					document.getElementById('actionType').value = 'reject';
					document.getElementById('sellerId').value = sellerId;
					document.getElementById('reasonText').value = reason;
					document.getElementById('actionForm').submit();
				}
			}
			
			function reviewAppeal(sellerId, action) {
				if (action === 'approve') {
					if (confirm('Approve this seller\'s appeal?')) {
						document.getElementById('actionType').value = 'review_appeal';
						document.getElementById('sellerId').value = sellerId;
						document.getElementById('appealAction').value = 'approve_appeal';
						document.getElementById('actionForm').submit();
					}
				} else {
					document.getElementById('appeal-reject-form-' + sellerId).style.display = 'block';
				}
			}
			
			function submitAppealRejection(sellerId) {
				const reason = document.getElementById('appeal-reason-' + sellerId).value;
				if (!reason) {
					alert('Please provide a reason for rejecting the appeal');
					return;
				}
				if (confirm('Reject this appeal?')) {
					document.getElementById('actionType').value = 'review_appeal';
					document.getElementById('sellerId').value = sellerId;
					document.getElementById('appealAction').value = 'reject_appeal';
					document.getElementById('reasonText').value = reason;
					document.getElementById('actionForm').submit();
				}
			}
			
			function hideAppealRejectForm(sellerId) {
				document.getElementById('appeal-reject-form-' + sellerId).style.display = 'none';
			}
		</script>
	</body>
</html>