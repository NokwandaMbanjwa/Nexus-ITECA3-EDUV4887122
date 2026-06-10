<?php
require_once 'admin-auth.php';

// Only super admin can ban users
if (!$is_super_admin) {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

$success = '';
$error = '';

// Handle user ban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $user_id = $_POST['user_id'] ?? 0;
    $ban_reason = trim($_POST['ban_reason'] ?? '');
    $ban_days = (int)($_POST['ban_days'] ?? 0);
    $permanent_ban = isset($_POST['permanent_ban']);
    
    // Validate ban reason
    if (empty($ban_reason)) {
        $error = "Please provide a reason for the ban.";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Get user details for blacklist
            $stmt = $pdo->prepare("
                SELECT u.email, COALESCE(bp.phone_number, sp.phone_number) as phone, 
                       COALESCE(bp.id_passport_number, sp.id_passport_number) as id_number,
                       u.user_id
                FROM nexus_users u
                LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
                LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new Exception("User not found");
            }
            
            // Calculate ban expiry
            $ban_expires = null;
            if ($permanent_ban) {
                $ban_expires = null;
            } elseif ($ban_days > 0) {
                $ban_expires = date('Y-m-d H:i:s', strtotime("+$ban_days days"));
            }
            
            // Update user as banned
            $stmt = $pdo->prepare("
                UPDATE nexus_users 
                SET is_banned = 1, 
                    banned_at = NOW(), 
                    banned_by = ?, 
                    ban_reason = ?, 
                    ban_expires_at = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$admin_id, $ban_reason, $ban_expires, $user_id]);
            
            // Add to blacklisted emails (prevents re-registration)
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO blacklisted_emails (email, phone, id_number, reason, banned_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$user['email'], $user['phone'], $user['id_number'], $ban_reason, $admin_id]);
            
            // Log the action
            $log_admin_id = getUserId();
            if ($log_admin_id) {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_activity_log (admin_id, action, details, ip_address)
                    VALUES (?, 'banned_user', ?, ?)
                ");
                $stmt->execute([$log_admin_id, "Banned user ID: $user_id. Reason: $ban_reason", $_SERVER['REMOTE_ADDR']]);
            }
            
            $pdo->commit();
            
            $success = "User has been banned successfully!";
            
            // Refresh page to show updated status
            header("Location: admin-user-management.php?success=1");
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to ban user: " . $e->getMessage();
        }
    }
}

// Handle user unban
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unban_user'])) {
    $user_id = $_POST['user_id'] ?? 0;
    
    try {
        $pdo->beginTransaction();
        
        // Get user email before unbanning
        $stmt = $pdo->prepare("SELECT email FROM nexus_users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        // Update user as unbanned
        $stmt = $pdo->prepare("
            UPDATE nexus_users 
            SET is_banned = 0, 
                banned_at = NULL, 
                banned_by = NULL, 
                ban_reason = NULL, 
                ban_expires_at = NULL
            WHERE user_id = ?
        ");
        $stmt->execute([$user_id]);
        
        $pdo->commit();
        
        $success = "User has been unbanned successfully!";
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to unban user: " . $e->getMessage();
    }
}

// Check for success parameter from redirect
if (isset($_GET['success'])) {
    $success = "User has been banned successfully!";
}

// Get all users with their ban status
$stmt = $pdo->prepare("
    SELECT u.*, 
           COALESCE(bp.full_name, sp.full_name) as full_name,
           COALESCE(bp.phone_number, sp.phone_number) as phone,
           COALESCE(bp.id_passport_number, sp.id_passport_number) as id_number,
           banned_admin.full_name as banned_by_name
    FROM nexus_users u
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    LEFT JOIN nexus_users banned_admin ON u.banned_by = banned_admin.user_id
    WHERE u.user_type IN ('buyer', 'seller')
    ORDER BY u.is_banned DESC, u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll(); 
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | User Management</title>
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
				padding: 30px 24px 60px;
				max-width: 1200px;
				margin: 0 auto;
			}
			
			.user-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 20px;
				overflow: hidden;
			}
			
			.user-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}
			
			.user-name {
				font-size: 18px;
				font-weight: 600;
				color: #fff;
			}
			
			.ban-status {
				padding: 5px 12px;
				border-radius: 20px;
				font-size: 12px;
			}
			
			.status-banned {
				background: rgba(255, 68, 68, 0.15);
				color: #ff4444;
			}
			
			.status-active {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
			}
			
			.user-body {
				padding: 20px;
			}
			
			.ban-modal {
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
			
			.ban-modal.show {
				display: flex;
			}
			
			.ban-modal-content {
				background: #19191c;
				border-radius: 16px;
				padding: 32px;
				max-width: 500px;
				width: 90%;
			}
			
			.form-group {
				margin-bottom: 20px;
			}
			
			.form-group label {
				display: block;
				margin-bottom: 8px;
				color: #aaa;
			}
			
			.form-group input, .form-group select, .form-group textarea {
				width: 100%;
				padding: 10px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
			}
			
			.checkbox-group {
				display: flex;
				align-items: center;
				gap: 10px;
			}
			
			.btn-submit {
				background: #ff4444;
				color: white;
				border: none;
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
			}
			
			.btn-unban {
				background: #4caf50;
				color: white;
				border: none;
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
			}
			
			.btn-dismiss {
				background: #666;
				color: white;
				border: none;
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
			}
			
			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			
			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}
			
			.alert-error {
				background: rgba(255, 68, 68, 0.1);
				border: 1px solid #ff4444;
				color: #ff4444;
			}
			
			/* Mobile Responsive Styles */
			@media (max-width: 768px) {
				.admin-container {
					padding: 30px 16px 40px;
				}
				
				.user-header {
					flex-direction: column;
					align-items: flex-start;
				}
				
				.user-name {
					font-size: 16px;
				}
				
				.user-body {
					padding: 16px;
				}
				
				.user-body p {
					font-size: 13px;
					margin-bottom: 8px;
				}
				
				.btn-submit, .btn-unban, .btn-dismiss {
					width: 100%;
					padding: 12px 20px;
					font-size: 14px;
				}
				
				.ban-modal-content {
					padding: 24px 20px;
					margin: 0 16px;
					width: calc(100% - 32px);
				}
				
				.ban-modal-content h3 {
					font-size: 20px;
				}
				
				.form-group label {
					font-size: 13px;
				}
				
				.form-group input, .form-group select, .form-group textarea {
					padding: 10px 12px;
					font-size: 14px;
				}
				
				.checkbox-group {
					flex-wrap: wrap;
				}
				
				.alert {
					font-size: 13px;
					padding: 10px 12px;
				}
			}
			
			@media (max-width: 480px) {
				.admin-container {
					padding: 30px 12px 30px;
				}
				
				.user-header {
					padding: 16px;
				}
				
				.user-name {
					font-size: 15px;
				}
				
				.ban-status {
					font-size: 10px;
					padding: 4px 10px;
				}
				
				.user-body {
					padding: 14px;
				}
				
				.user-body p {
					font-size: 12px;
				}
				
				.btn-submit, .btn-unban, .btn-dismiss {
					padding: 10px 16px;
					font-size: 13px;
				}
				
				.ban-modal-content {
					padding: 20px 16px;
				}
				
				.ban-modal-content h3 {
					font-size: 18px;
				}
				
				.form-group {
					margin-bottom: 16px;
				}
				
				.form-group label {
					font-size: 12px;
				}
				
				.form-group input, .form-group select, .form-group textarea {
					padding: 8px 12px;
					font-size: 13px;
				}
				
				.alert {
					font-size: 12px;
					padding: 8px 12px;
				}
				
				.alert-success, .alert-error {
					margin-bottom: 16px;
				}
			}
		</style>
	</head>
	<body>
		<?php include 'admin-header.php'; ?>
		<?php include 'admin-sidebar.php'; ?>
		
		<main class="admin-main">
			<div class="admin-container">
				<h1 style = "color: #b6b5d8"><i class="fas fa-users"></i> User Management</h1>
				<p>Manage user accounts, ban or unban users as needed.</p>
				
				<?php if ($success): ?>
					<div class="alert alert-success"><?php echo $success; ?></div>
				<?php endif; ?>
				
				<?php if ($error): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<?php foreach ($users as $user): ?>
					<div class="user-card">
						<div class="user-header">
							<div>
								<span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
								<p style="color: #888; font-size: 13px; margin-top: 4px;"><?php echo htmlspecialchars($user['email']); ?></p>
							</div>
							<div>
								<span class="ban-status <?php echo $user['is_banned'] ? 'status-banned' : 'status-active'; ?>">
									<?php echo $user['is_banned'] ? 'Banned' : 'Active'; ?>
								</span>
							</div>
						</div>
						<div class="user-body">
							<?php if ($user['is_banned']): ?>
								<p><strong>Ban Reason:</strong> <?php echo htmlspecialchars($user['ban_reason']); ?></p>
								<p><strong>Banned By:</strong> <?php echo htmlspecialchars($user['banned_by_name']); ?></p>
								<p><strong>Banned At:</strong> <?php echo date('d M Y H:i', strtotime($user['banned_at'])); ?></p>
								<?php if ($user['ban_expires_at']): ?>
									<p><strong>Expires:</strong> <?php echo date('d M Y H:i', strtotime($user['ban_expires_at'])); ?></p>
								<?php else: ?>
									<p><strong>Permanent Ban</strong></p>
								<?php endif; ?>
								<form method="post" style="margin-top: 15px;">
									<input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
									<button type="submit" name="unban_user" class="btn-unban">Unban User</button>
								</form>
							<?php else: ?>
								<button class="btn-submit" onclick="openBanModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['full_name']); ?>')">
									Ban User
								</button>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</main>
		
		<!-- Ban Modal -->
		<div class="ban-modal" id="banModal">
			<div class="ban-modal-content">
				<h3 style="color: #ff4444; margin-bottom: 20px;">Ban User</h3>
				<form method="post" id="banForm">
					<input type="hidden" name="user_id" id="ban_user_id">
					<div class="form-group">
						<label>Reason for Ban</label>
						<textarea name="ban_reason" rows="3" required placeholder="Enter reason for banning this user..." style="resize: vertical; min-height: 80px;"></textarea>
					</div>
					<div class="form-group checkbox-group">
						<input type="checkbox" name="permanent_ban" id="permanent_ban">
						<label for="permanent_ban">Permanent Ban (User cannot ever re-register)</label>
					</div>
					<div class="form-group" id="temp_ban_days">
						<label>Ban Duration (days) - leave 0 for indefinite</label>
						<input type="number" name="ban_days" value="0" min="0">
					</div>
					<div style="display: flex; gap: 12px; flex-wrap: wrap;">
						<button type="submit" name="ban_user" class="btn-submit">Confirm Ban</button>
						<button type="button" class="btn-dismiss" onclick="closeBanModal()">Cancel</button>
					</div>
				</form>
			</div>
		</div>
		
		<script>
			function openBanModal(userId, userName) {
				document.getElementById('ban_user_id').value = userId;
				document.getElementById('banModal').classList.add('show');
			}
			
			function closeBanModal() {
				document.getElementById('banModal').classList.remove('show');
			}
			
			document.getElementById('permanent_ban').addEventListener('change', function() {
				document.getElementById('temp_ban_days').style.display = this.checked ? 'none' : 'block';
			});
			
			window.addEventListener('click', function(e) {
				if (e.target === document.getElementById('banModal')) {
					closeBanModal();
				}
			});
		</script>
	</body>
</html>