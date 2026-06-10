<?php
require_once 'admin-auth.php';

if (!isLoggedIn() || getUserType() !== 'admin' || $_SESSION['admin_role'] !== 'super_admin') {
    header('Location: admin-login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = $_POST['application_id'] ?? 0;
    $action = $_POST['action'] ?? '';
    $rejection_reason = $_POST['rejection_reason'] ?? '';
    
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
            $stmt = $pdo->prepare("UPDATE nexus_users SET admin_role = ?, admin_approved = 1 WHERE user_id = ?");
            $stmt->execute([$application['requested_department'], $application['user_id']]);

            $stmt = $pdo->prepare("UPDATE admin_applications SET application_status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
            $stmt->execute([getUserId(), $application_id]);
            
            $success = "Application approved! User can now login as admin.";
        }
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE admin_applications SET application_status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW() WHERE application_id = ?");
        $stmt->execute([$rejection_reason, getUserId(), $application_id]);
        $success = "Application rejected.";
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
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Verify Admin Applications | Nexus</title>
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
				padding: 100px 24px 60px;
				max-width: 1200px;
				margin: 0 auto;
			}

			.admin-header {
				margin-bottom: 30px;
			}

			.admin-header h1 {
				color: #8ff5ff;
				font-size: 32px;
			}

			.applications-grid {
				display: grid;
				gap: 20px;
			}

			.application-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
			}

			.app-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				flex-wrap: wrap;
				margin-bottom: 16px;
			}

			.app-name {
				font-size: 18px;
				font-weight: 600;
				color: #fff;
			}

			.app-department {
				background: #8ff5ff;
				color: #0e0e10;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
			}

			.app-details {
				margin-bottom: 16px;
			}

			.detail-row {
				display: flex;
				margin-bottom: 8px;
			}

			.detail-label {
				width: 150px;
				color: #888;
				font-size: 13px;
			}

			.detail-value {
				color: #e5e5e5;
				font-size: 13px;
			}

			.app-actions {
				display: flex;
				gap: 12px;
				margin-top: 16px;
			}

			.btn-approve {
				background: #4caf50;
				color: white;
				border: none;
				padding: 8px 24px;
				border-radius: 6px;
				cursor: pointer;
			}

			.btn-reject {
				background: #ff4444;
				color: white;
				border: none;
				padding: 8px 24px;
				border-radius: 6px;
				cursor: pointer;
			}

			.reject-reason {
				display: none;
				margin-top: 12px;
			}

			.reject-reason textarea {
				width: 100%;
				padding: 8px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 6px;
				color: #e5e5e5;
				margin-bottom: 8px;
			}

			.alert {
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}

			.empty-state {
				text-align: center;
				padding: 60px;
				background: #131315;
				border-radius: 12px;
			}

			@media (max-width: 768px) {
				.admin-container { 
					padding: 80px 16px 40px; 
				}
				.admin-header h1 {
					font-size: 24px;
				}
				.detail-row { 
					flex-direction: column; 
				}
				.detail-label { 
					width: auto; 
					margin-bottom: 4px; 
				}
				.app-header {
					flex-direction: column;
					align-items: flex-start;
					gap: 10px;
				}
				.app-actions {
					flex-direction: column;
				}
				.btn-approve, .btn-reject {
					width: 100%;
					text-align: center;
				}
			}

			@media (max-width: 480px) {
				.admin-container {
					padding: 70px 12px 30px;
				}
				.application-card {
					padding: 16px;
				}
				.app-name {
					font-size: 16px;
				}
				.detail-value {
					font-size: 12px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'admin-sidebar.php'; ?>
		
		<div class="admin-container">
			<div class="admin-header">
				<h1><i class="fas fa-user-check"></i> Admin Applications</h1>
				<p>Review and approve new admin applications</p>
			</div>
			
			<?php if (isset($success)): ?>
				<div class="alert alert-success"><?php echo $success; ?></div>
			<?php endif; ?>
			
			<?php if (empty($applications)): ?>
				<div class="empty-state">
					<i class="fas fa-check-circle" style="font-size: 48px; color: #4caf50; margin-bottom: 16px; display: block;"></i>
					<h3>No Pending Applications</h3>
					<p>All admin applications have been processed.</p>
				</div>
			<?php else: ?>
				<div class="applications-grid">
					<?php foreach ($applications as $app): ?>
						<div class="application-card" data-id="<?php echo $app['application_id']; ?>">
							<div class="app-header">
								<span class="app-name"><?php echo htmlspecialchars($app['full_name']); ?></span>
								<span class="app-department"><?php echo strtoupper(str_replace('_', ' ', $app['requested_department'])); ?></span>
							</div>
							<div class="app-details">
								<div class="detail-row">
									<div class="detail-label">Email:</div>
									<div class="detail-value"><?php echo htmlspecialchars($app['email']); ?></div>
								</div>
								<div class="detail-row">
									<div class="detail-label">Phone:</div>
									<div class="detail-value"><?php echo htmlspecialchars($app['phone']); ?></div>
								</div>
								<div class="detail-row">
									<div class="detail-label">Current Role:</div>
									<div class="detail-value"><?php echo ucfirst($app['applicant_role']); ?></div>
								</div>
								<div class="detail-row">
									<div class="detail-label">ID Number:</div>
									<div class="detail-value"><?php echo substr($app['id_number'], 0, 6) . '******'; ?></div>
								</div>
								<div class="detail-row">
									<div class="detail-label">Age:</div>
									<div class="detail-value"><?php echo $app['age']; ?> years old</div>
								</div>
								<div class="detail-row">
									<div class="detail-label">Applied On:</div>
									<div class="detail-value"><?php echo date('d M Y H:i', strtotime($app['created_at'])); ?></div>
								</div>
							</div>
							<div class="app-actions">
								<button class="btn-approve" onclick="approveApplication(<?php echo $app['application_id']; ?>)">Approve</button>
								<button class="btn-reject" onclick="showRejectForm(<?php echo $app['application_id']; ?>)">Reject</button>
							</div>
							<div class="reject-reason" id="reject-form-<?php echo $app['application_id']; ?>">
								<textarea id="reason-<?php echo $app['application_id']; ?>" rows="2" placeholder="Reason for rejection..."></textarea>
								<button class="btn-reject" onclick="rejectApplication(<?php echo $app['application_id']; ?>)">Confirm Rejection</button>
								<button style="background: #666;" onclick="hideRejectForm(<?php echo $app['application_id']; ?>)">Cancel</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
		
		<form id="actionForm" method="post" style="display: none;">
			<input type="hidden" name="application_id" id="action_app_id">
			<input type="hidden" name="action" id="action_type">
			<input type="hidden" name="rejection_reason" id="action_reason">
		</form>
		
		<script>
			function approveApplication(id) {
				if (confirm('Approve this admin application?')) {
					document.getElementById('action_app_id').value = id;
					document.getElementById('action_type').value = 'approve';
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
					document.getElementById('action_app_id').value = id;
					document.getElementById('action_type').value = 'reject';
					document.getElementById('action_reason').value = reason;
					document.getElementById('actionForm').submit();
				}
			}
		</script>
	</body>
</html>