<?php
require_once 'admin-auth.php';

$admin_role = $_SESSION['admin_role'];
$admin_id = getUserId();
$is_super_admin = ($admin_role === 'super_admin');
$can_message_users = ($admin_role === 'verification' || $admin_role === 'safety_support' || $is_super_admin);

$prefill_user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$prefill_product = isset($_GET['product']) ? $_GET['product'] : '';
$prefill_reason = isset($_GET['reason']) ? $_GET['reason'] : '';

$prefill_name = '';
if ($prefill_user_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM seller_profiles WHERE user_id = ?");
    $stmt->execute([$prefill_user_id]);
    $seller = $stmt->fetch();
    if ($seller) {
        $prefill_name = $seller['full_name'];
    }
}

$stmt = $pdo->prepare("SELECT u.user_id, u.email, u.admin_role, COALESCE(bp.full_name, sp.full_name) as full_name 
                       FROM nexus_users u
                       LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
                       LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
                       WHERE u.user_type = 'admin' AND u.user_id != ?");
$stmt->execute([$admin_id]);
$admins = $stmt->fetchAll();

// Admin-to-admin messages
$stmt = $pdo->prepare("
    SELECT m.*, 
           CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as direction,
           COALESCE(bp.full_name, sp.full_name) as other_name
    FROM admin_messages m
    LEFT JOIN buyer_profiles bp ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = bp.user_id
    LEFT JOIN seller_profiles sp ON (CASE WHEN m.sender_id = ? THEN m.receiver_id ELSE m.sender_id END) = sp.user_id
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY m.created_at DESC
");
$stmt->execute([$admin_id, $admin_id, $admin_id, $admin_id, $admin_id]);
$messages = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $receiver_id = (int)$_POST['receiver_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if ($subject && $message) {
        // Determine receiver type
        $stmt = $pdo->prepare("SELECT user_type FROM nexus_users WHERE user_id = ?");
        $stmt->execute([$receiver_id]);
        $receiver = $stmt->fetch();
        
        if ($receiver && $receiver['user_type'] === 'seller') {
            // Send to seller using regular messaging system
            $stmt = $pdo->prepare("
                SELECT conversation_id FROM conversations 
                WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
            ");
            $stmt->execute([$admin_id, $receiver_id, $receiver_id, $admin_id]);
            $conv = $stmt->fetch();
            
            if ($conv) {
                $conversation_id = $conv['conversation_id'];
            } else {
                $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
                $stmt->execute([$admin_id, $receiver_id]);
                $conversation_id = $pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$conversation_id, $admin_id, $receiver_id, $message]);
            
            // Update conversation last message
            $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
            $stmt->execute([$message, $conversation_id]);
            
            $success = "Message sent to seller successfully!";
        } else {
            // Admin to admin using admin_messages table
            $stmt = $pdo->prepare("INSERT INTO admin_messages (sender_id, receiver_id, subject, message) VALUES (?, ?, ?, ?)");
            $stmt->execute([$admin_id, $receiver_id, $subject, $message]);
            $success = "Message sent successfully!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS |Admin Messages</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

		<style>
			.admin-wrapper {
				display: flex;
				min-height: 100vh;
			}

			.admin-main {
				flex: 1;
				margin-left: 280px;
			}

			.user-dropdown {
				position: relative;
			}

			.user-dropdown-btn {
				display: flex;
				align-items: center;
				gap: 8px;
				background: none;
				border: none;
				color: #e5e5e5;
				cursor: pointer;
				padding: 8px;
			}

			.user-dropdown-content {
				display: none;
				position: absolute;
				right: 0;
				top: 100%;
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				min-width: 180px;
			}

			.user-dropdown:hover .user-dropdown-content {
				display: block;
			}

			.user-dropdown-content a {
				display: block;
				padding: 10px 16px;
				text-decoration: none;
			}

			.user-dropdown-content a:hover {
				background: #2a2a2a;
				color: #b026ff;
			}

			.dashboard-content {
				padding: 24px;
			}

			.messages-container {
				display: flex;
				gap: 30px;
				flex-wrap: wrap;
			}

			.compose-section {
				flex: 1;
				min-width: 300px;
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
			}

			.messages-section {
				flex: 2;
				min-width: 400px;
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
			}

			.form-group {
				margin-bottom: 16px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				color: #aaa;
				font-size: 13px;
			}

			.form-group input,
			.form-group select,
			.form-group textarea {
				width: 100%;
				padding: 10px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
			}

			.btn-send {
				background: #6f2da8;
				color: #ffffff;
				border: none;
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
			}

			.message-item {
				padding: 15px;
				border-bottom: 1px solid #2a2a2a;
			}

			.message-item:last-child {
				border-bottom: none;
			}

			.message-subject {
				font-weight: 600;
				color: #b026ff;
				margin-bottom: 5px;
			}

			.message-meta {
				font-size: 12px;
				margin-bottom: 8px;
			}

			.message-content {
				font-size: 13px;
			}

			.badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 12px;
				font-size: 10px;
				margin-left: 10px;
			}

			.badge-sent {
				background: #2a2a2a;
			}

			.badge-received {
				background: #9a4eae;
				color: #0e0e10;
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

			@media (max-width: 768px) {
				.admin-main {
					margin-left: 0;
				}

				.messages-container {
					flex-direction: column;
				}
				.admin-main {
					margin-left: 0;
				}
				.messages-container {
					flex-direction: column;
				}
				.compose-section,
				.messages-section {
					min-width: 100% !important;
					padding: 16px;
				}
				.dashboard-content {
					padding: 16px;
				}
				.btn-send {
					width: 100%;
				}
			}
			@media (max-width: 480px) {
				.dashboard-content {
					padding: 12px;
				}
				.compose-section,
				.messages-section {
					padding: 12px;
				}
				.form-group input,
				.form-group select,
				.form-group textarea {
					padding: 8px;
					font-size: 14px;
				}
				.message-item {
					padding: 12px;
				}
				.message-subject {
					font-size: 14px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'admin-header.php'; ?>
		<?php include 'admin-sidebar.php'; ?>
		
		<div class="admin-wrapper">
			<main class="admin-main">
				<div class="dashboard-content">
					<div class="messages-container">
						<div class="compose-section">
							<h3 style="margin-bottom: 20px; color: #b026ff;">Compose Message</h3>
							<?php if (isset($success)): ?>
								<div class="alert alert-success"><?php echo $success; ?></div>
							<?php endif; ?>
							<form method="post">
								<div class="form-group">
									<label>To:</label>
									<select name="receiver_id" required>
										<option value="">Select Recipient</option>
										<?php if ($prefill_user_id && $prefill_name): ?>
											<option value="<?php echo $prefill_user_id; ?>" selected>
												<?php echo htmlspecialchars($prefill_name); ?> (Seller)
											</option>
										<?php endif; ?>
										<optgroup label="Administrators">
											<?php foreach ($admins as $admin): ?>
												<option value="<?php echo $admin['user_id']; ?>">
													<?php echo htmlspecialchars($admin['full_name'] ?? $admin['email']); ?> 
													(<?php echo strtoupper($admin['admin_role']); ?>)
												</option>
											<?php endforeach; ?>
										</optgroup>
									</select>
								</div>
								<div class="form-group">
									<label>Subject:</label>
									<input type="text" name="subject" required value="<?php echo $prefill_product ? 'Product Rejection: ' . htmlspecialchars($prefill_product) : ''; ?>">
								</div>
								<div class="form-group">
									<label>Message:</label>
									<textarea name="message" rows="5" required><?php echo $prefill_reason ? "Your product has been declined for the following reason:\n\n" . htmlspecialchars($prefill_reason) . "\n\nIf you have any questions, please contact support." : ''; ?></textarea>
								</div>
								<button type="submit" name="send_message" class="btn-send">Send Message</button>
							</form>
						</div>
						
						<div class="messages-section">
							<h3 style="margin-bottom: 20px; color: #c3b1e1;">Messages</h3>
							<?php if (empty($messages)): ?>
								<p style="text-align: center; padding: 40px;">No messages yet</p>
							<?php else: ?>
								<?php foreach ($messages as $msg): ?>
									<div class="message-item">
										<div class="message-subject">
											<?php echo htmlspecialchars($msg['subject']); ?>
											<span class="badge badge-<?php echo $msg['direction']; ?>">
												<?php echo ucfirst($msg['direction']); ?>
											</span>
										</div>
										<div class="message-meta">
											<?php echo htmlspecialchars($msg['other_name'] ?? 'Admin'); ?> • 
											<?php echo date('d M Y H:i', strtotime($msg['created_at'])); ?>
										</div>
										<div class="message-content">
											<?php echo nl2br(htmlspecialchars($msg['message'])); ?>
										</div>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>
						</div>
					</div>
				</div>
			</main>
		</div>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
	</body>
</html>