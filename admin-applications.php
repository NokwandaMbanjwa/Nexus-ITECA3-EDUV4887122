<?php
require_once 'admin-auth.php';
require_once 'mail-config.php';

// Only super admin or verification department can access
if (!$is_super_admin && $admin_role !== 'verification') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $application_id = $_POST['application_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    $admin_id = getUserId();
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("
            SELECT a.*, u.email, u.user_id 
            FROM admin_applications a
            JOIN nexus_users u ON a.user_id = u.user_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
        
        if ($application) {
            // Generate unique access code
            $access_code = 'NEX' . strtoupper(substr($application['requested_department'], 0, 3)) . rand(100, 999);
            
            $stmt = $pdo->prepare("UPDATE nexus_users SET admin_role = ?, admin_approved = 1, admin_access_code = ? WHERE user_id = ?");
            $stmt->execute([$application['requested_department'], $access_code, $application['user_id']]);
            
            // Update application status
            $stmt = $pdo->prepare("UPDATE admin_applications SET application_status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
            $stmt->execute([$admin_id, $application_id]);
            
            // Send message to applicant via messaging system
            $user_id = $application['user_id'];
            
            $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)");
            $stmt->execute([$admin_id, $user_id, $user_id, $admin_id]);
            $conv = $stmt->fetch();
            
            if ($conv) {
                $conversation_id = $conv['conversation_id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
                $stmt->execute([$admin_id, $user_id]);
                $conversation_id = $pdo->lastInsertId();
            }
            
            $msg = "Congratulations! Your admin application for " . ucfirst(str_replace('_', ' ', $application['requested_department'])) . " has been approved.\n\nYour access code is: " . $access_code . "\n\nUse this code at admin-login.php along with your email and password to access the admin panel.";
            
            $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversation_id, $admin_id, $user_id, $msg]);
            
            $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
            $stmt->execute([$msg, $conversation_id]);
            
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'admin_application', 'Admin Application Approved', ?, ?)");
            $stmt->execute([$user_id, "Your admin application has been approved! Check your messages for your access code.", $application_id]);
            
            $user_email = $application['email'];
            $subject = "NEXUS - Admin Application Approved";
            $body = "
                <h2>Congratulations!</h2>
                <p>Your admin application for <strong>" . ucfirst(str_replace('_', ' ', $application['requested_department'])) . " Department</strong> has been approved.</p>
                <p><strong>Your Access Code:</strong> <span style='font-size: 24px; color: #875692;'>$access_code</span></p>
                <p>Use this code along with your email and password to login at the admin portal:</p>
                <p><a href='https://nexus.infinityfree.me/admin-login.php'>Admin Login Portal</a></p>
                <hr>
                <p style='color: #888; font-size: 12px;'>NEXUS Marketplace - Administrative Team</p>
            ";
            sendEmail($user_email, $subject, $body);
            
            $success = "Application approved! Access code sent to user via email and messages.";
        }
        
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("
            SELECT a.*, u.user_id 
            FROM admin_applications a
            JOIN nexus_users u ON a.user_id = u.user_id
            WHERE a.application_id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch();
        
        if ($application) {
            $stmt = $pdo->prepare("UPDATE admin_applications SET application_status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
            $stmt->execute([$reason, $admin_id, $application_id]);
            
            $stmt = $pdo->prepare("UPDATE nexus_users SET admin_role = NULL, admin_approved = 0, admin_access_code = NULL WHERE user_id = ?");
            $stmt->execute([$application['user_id']]);
            
            $user_id = $application['user_id'];
            
            $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)");
            $stmt->execute([$admin_id, $user_id, $user_id, $admin_id]);
            $conv = $stmt->fetch();
            
            if ($conv) {
                $conversation_id = $conv['conversation_id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
                $stmt->execute([$admin_id, $user_id]);
                $conversation_id = $pdo->lastInsertId();
            }
            
            $msg = "Your admin application for " . ucfirst(str_replace('_', ' ', $application['requested_department'])) . " has been declined.\n\nReason: " . $reason . "\n\nIf you have questions, please contact support.";
            
            $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversation_id, $admin_id, $user_id, $msg]);
            
            $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
            $stmt->execute([$msg, $conversation_id]);

            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'admin_application', 'Admin Application Declined', ?, ?)");
            $stmt->execute([$user_id, "Your admin application has been declined. Check your messages for details.", $application_id]);
            
            $success = "Application rejected. User notified via messages.";
        }
    }
}

$stmt = $pdo->prepare("
    SELECT a.*, u.email, 
           COALESCE(bp.full_name, sp.full_name) as full_name,
           COALESCE(bp.phone_number, sp.phone_number) as phone
    FROM admin_applications a
    JOIN nexus_users u ON a.user_id = u.user_id
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE a.application_status = 'pending'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$applications = $stmt->fetchAll();

// Get recently reviewed applications
$stmt = $pdo->prepare("
    SELECT a.*, u.email, 
           COALESCE(bp.full_name, sp.full_name) as full_name,
           COALESCE(bp.phone_number, sp.phone_number) as phone,
           (SELECT full_name FROM nexus_users WHERE user_id = a.reviewed_by) as reviewer_name
    FROM admin_applications a
    JOIN nexus_users u ON a.user_id = u.user_id
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE a.application_status IN ('approved', 'rejected')
    ORDER BY a.reviewed_at DESC
    LIMIT 20
");
$stmt->execute();
$reviewed_applications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin |Admin Applications</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		
		<style>
			.admin-container {
				padding: 40px 24px 60px;
				max-width: 1200px;
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
			
			.application-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 20px;
				overflow: hidden;
			}
			
			.app-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}
			
			.app-header h3 {
				color: #fff;
				margin-bottom: 5px;
			}
			
			.app-header p {
				color: #888;
				font-size: 13px;
			}
			
			.app-body {
				padding: 20px;
			}
			
			.info-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 15px;
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
			
			@media (max-width: 768px) {
				.admin-container {
					padding: 80px 16px 40px;
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
				.app-header {
					flex-direction: column;
					align-items: flex-start;
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
				.app-body {
					padding: 14px;
				}
				.info-item {
					padding: 10px;
				}
				.info-item .value {
					font-size: 13px;
				}
				.app-header h3 {
					font-size: 16px;
				}
				.empty-state {
					padding: 40px 20px;
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
					<h1><i class="fas fa-users"></i> Review Admin Applications</h1>
					<p>Review and approve new administrator applications</p>
				</div>
				
				<div class="stats-grid">
					<div class="stat-card">
						<h3><?php echo count($applications); ?></h3>
						<p>Pending Applications</p>
					</div>
					<div class="stat-card">
						<h3><?php echo count($reviewed_applications); ?></h3>
						<p>Recently Reviewed</p>
					</div>
				</div>
				
				<h2 class="section-title">Pending Applications</h2>
				<?php if (empty($applications)): ?>
					<div class="empty-state">
						<i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
						<p>No pending admin applications</p>
					</div>
				<?php else: ?>
					<?php foreach ($applications as $app): ?>
						<div class="application-card">
							<div class="app-header">
								<div>
									<h3><?php echo htmlspecialchars($app['full_name']); ?></h3>
									<p><?php echo htmlspecialchars($app['email']); ?> | Applied: <?php echo date('d M Y H:i', strtotime($app['created_at'])); ?></p>
								</div>
								<span class="status-badge status-pending">Pending Review</span>
							</div>
							<div class="app-body">
								<div class="info-grid">
									<div class="info-item">
										<label>Requested Department</label>
										<div class="value"><?php echo ucfirst(str_replace('_', ' ', $app['requested_department'])); ?></div>
									</div>
									<div class="info-item">
										<label>Current Role on Nexus</label>
										<div class="value"><?php echo ucfirst($app['applicant_role']); ?></div>
									</div>
									<div class="info-item">
										<label>ID Number</label>
										<div class="value"><?php echo substr($app['id_number'], 0, 6) . '******'; ?></div>
									</div>
									<div class="info-item">
										<label>Age</label>
										<div class="value"><?php echo $app['age']; ?> years old</div>
									</div>
								</div>
								
								<div class="action-buttons">
									<button class="btn-approve" onclick="approveApplication(<?php echo $app['application_id']; ?>)">✓ Approve Application</button>
									<button class="btn-reject" onclick="showRejectForm(<?php echo $app['application_id']; ?>)">✗ Reject</button>
								</div>
								<div class="reject-form" id="reject-form-<?php echo $app['application_id']; ?>">
									<textarea id="reason-<?php echo $app['application_id']; ?>" rows="3" placeholder="Reason for rejection..."></textarea>
									<div>
										<button class="btn-reject" onclick="rejectApplication(<?php echo $app['application_id']; ?>)">Submit Rejection</button>
										<button class="btn-approve" style="background: #666;" onclick="hideRejectForm(<?php echo $app['application_id']; ?>)">Cancel</button>
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
			<input type="hidden" name="application_id" id="applicationId">
			<input type="hidden" name="reason" id="reasonText">
		</form>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			function approveApplication(id) {
				if (confirm('Approve this admin application? The user will receive an access code via email.')) {
					document.getElementById('actionType').value = 'approve';
					document.getElementById('applicationId').value = id;
					document.getElementById('actionForm').submit();
				}
			}
			
			function showRejectForm(id) {
				document.getElementById('reject-form-' + id).style.display = 'block';
			}
			
			function hideRejectForm(id) {
				document.getElementById('reject-form-' + id).style.display = 'none';
			}
			
			function rejectApplication(id) {
				const reason = document.getElementById('reason-' + id).value;
				if (!reason) {
					alert('Please provide a reason for rejection');
					return;
				}
				if (confirm('Reject this application?')) {
					document.getElementById('actionType').value = 'reject';
					document.getElementById('applicationId').value = id;
					document.getElementById('reasonText').value = reason;
					document.getElementById('actionForm').submit();
				}
			}
		</script>
	</body>
</html>