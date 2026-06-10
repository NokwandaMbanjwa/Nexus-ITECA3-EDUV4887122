<?php
require_once 'admin-auth.php';

if (!$is_super_admin && $admin_role !== 'safety_support') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

$admin_id = getUserId();
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_replied'])) {
    $message_id = $_POST['message_id'] ?? 0;
    $notes = trim($_POST['admin_notes'] ?? '');
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied', admin_notes = ?, updated_at = NOW() WHERE message_id = ?");
    $stmt->execute([$notes, $message_id]);
    $success = "Message marked as replied.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read'])) {
    $message_id = $_POST['message_id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read', updated_at = NOW() WHERE message_id = ?");
    $stmt->execute([$message_id]);
    $success = "Message marked as read.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['archive'])) {
    $message_id = $_POST['message_id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'archived', updated_at = NOW() WHERE message_id = ?");
    $stmt->execute([$message_id]);
    $success = "Message archived.";
}

$filter = $_GET['filter'] ?? 'unread';

$sql = "
    SELECT cm.*, u.user_type, u.email as user_email,
           COALESCE(bp.full_name, sp.full_name) as profile_name
    FROM contact_messages cm
    LEFT JOIN nexus_users u ON cm.user_id = u.user_id
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE 1=1
";
$params = [];

if ($filter !== 'all') {
    $sql .= " AND cm.status = ?";
    $params[] = $filter;
}

$sql .= " ORDER BY cm.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$messages = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'unread'");
$unread_count = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'read'");
$read_count = $stmt->fetchColumn();
$stmt = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status = 'replied'");
$replied_count = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | Contact Messages</title>
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
				max-width: 1200px;
				margin: 0 auto;
			}

			.page-header {
				margin-bottom: 30px;
			}

			.page-header h1 {
				color: #b026ff;
				font-size: 32px;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
			}

			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
				gap: 16px;
				margin-bottom: 30px;
			}

			.stat-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
				text-align: center;
				cursor: pointer;
				transition: all 0.2s;
				text-decoration: none;
				color: inherit;
				display: block;
			}

			.stat-card:hover {
				border-color: #c3b1e1;
			}

			.stat-card h3 {
				font-size: 32px;
				color: #c3b1e1;
				margin-bottom: 5px;
			}

			.stat-card.unread h3 {
				color: #7851a9;
			}

			.stat-card.replied h3 {
				color: #524f81;
			}

			.filter-bar {
				display: flex;
				gap: 10px;
				margin-bottom: 24px;
				flex-wrap: wrap;
			}

			.filter-bar a {
				padding: 8px 16px;
				border-radius: 20px;
				text-decoration: none;
				font-size: 13px;
				background: #1a1a1a;
				color: #888;
				border: 1px solid #2a2a2a;
			}

			.filter-bar a.active {
				background: #6f2da8;
				color: #ffffff;
				border-color: #6f2da8;
			}

			.message-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 16px;
				overflow: hidden;
			}

			.message-card.unread {
				border-left: 3px solid #6f2da8;
			}

			.message-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}

			.message-header h3 {
				color: #fff;
				margin-bottom: 5px;
				font-size: 16px;
			}

			.message-header p {
				color: #888;
				font-size: 13px;
			}

			.badge {
				padding: 3px 10px;
				border-radius: 12px;
				font-size: 11px;
				font-weight: 500;
			}

			.badge-unread {
				background: rgba(255, 68, 68, 0.15);
				color: #ff4444;
			}

			.badge-read {
				background: rgba(255, 193, 7, 0.15);
				color: #ffc107;
			}

			.badge-replied {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
			}

			.badge-archived {
				background: rgba(158, 158, 158, 0.15);
				color: #9e9e9e;
			}

			.badge-guest {
				background: rgba(158, 158, 158, 0.15);
				color: #9e9e9e;
			}

			.badge-buyer {
				background: rgba(143, 245, 255, 0.15);
				color: #8ff5ff;
			}

			.badge-seller {
				background: rgba(193, 128, 255, 0.15);
				color: #c180ff;
			}

			.message-body {
				padding: 20px;
			}

			.info-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 15px;
				margin-bottom: 15px;
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

			.action-buttons {
				display: flex;
				gap: 10px;
				margin-top: 15px;
				flex-wrap: wrap;
			}

			.btn-sm {
				padding: 8px 16px;
				border-radius: 6px;
				font-size: 12px;
				cursor: pointer;
				border: none;
				font-weight: 500;
			}

			.btn-reply {
				background: #4caf50;
				color: white;
			}

			.btn-read {
				background: #ffc107;
				color: #0e0e10;
			}

			.btn-archive {
				background: #666;
				color: white;
			}

			.notes-area {
				width: 100%;
				padding: 10px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				margin-top: 10px;
				font-size: 12px;
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
				.message-header {
					flex-direction: column;
					align-items: flex-start;
				}
				.action-buttons {
					flex-direction: column;
				}
				.btn-sm {
					width: 100%;
					text-align: center;
				}
				.stats-grid {
					grid-template-columns: repeat(2, 1fr);
					gap: 10px;
				}
				.stat-card {
					padding: 14px;
				}
				.stat-card h3 {
					font-size: 26px;
				}
				.filter-bar {
					gap: 6px;
				}
				.filter-bar a {
					padding: 6px 12px;
					font-size: 12px;
				}
			}

			@media (max-width: 480px) {
				.admin-container {
					padding: 70px 12px 30px;
				}
				.message-body {
					padding: 14px;
				}
				.info-item {
					padding: 10px;
				}
				.info-item .value {
					font-size: 13px;
				}
				.message-header h3 {
					font-size: 14px;
				}
				.empty-state {
					padding: 40px 20px;
				}
				.filter-bar a {
					padding: 5px 10px;
					font-size: 11px;
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
					<h1><i class="fas fa-comment-dots"></i> Contact Messages</h1>
					<p>Messages from the Contact Us page</p>
				</div>
				
				<?php if ($success): ?>
					<div class="alert-success"><?php echo $success; ?></div>
				<?php endif; ?>
				
				<div class="stats-grid">
					<a href="?filter=unread" class="stat-card unread"><h3><?php echo $unread_count; ?></h3><p>Unread</p></a>
					<a href="?filter=read" class="stat-card"><h3><?php echo $read_count; ?></h3><p>Read</p></a>
					<a href="?filter=replied" class="stat-card replied"><h3><?php echo $replied_count; ?></h3><p>Replied</p></a>
					<a href="?filter=all" class="stat-card"><h3><?php echo count($messages); ?></h3><p>Showing</p></a>
				</div>
				
				<div class="filter-bar">
					<a href="?filter=unread" class="<?php echo $filter === 'unread' ? 'active' : ''; ?>">Unread</a>
					<a href="?filter=read" class="<?php echo $filter === 'read' ? 'active' : ''; ?>">Read</a>
					<a href="?filter=replied" class="<?php echo $filter === 'replied' ? 'active' : ''; ?>">Replied</a>
					<a href="?filter=archived" class="<?php echo $filter === 'archived' ? 'active' : ''; ?>">Archived</a>
					<a href="?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">All</a>
				</div>
				
				<?php if (empty($messages)): ?>
					<div class="empty-state"><i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 16px;"></i><p>No messages found</p></div>
				<?php else: ?>
					<?php foreach ($messages as $msg): 
						$user_type_label = $msg['user_type'] ?? 'guest';
					?>
						<div class="message-card <?php echo $msg['status']; ?>">
							<div class="message-header">
								<div>
									<h3><?php echo htmlspecialchars($msg['subject']); ?></h3>
									<p>
										From: <?php echo htmlspecialchars($msg['full_name']); ?> 
										(<?php echo htmlspecialchars($msg['email']); ?>)
										<?php if ($msg['phone']): ?> | Phone: <?php echo htmlspecialchars($msg['phone']); ?><?php endif; ?>
									</p>
								</div>
								<div style="display: flex; gap: 8px; align-items: center;">
									<span class="badge badge-<?php echo $user_type_label; ?>"><?php echo ucfirst($user_type_label); ?></span>
									<span class="badge badge-<?php echo $msg['status']; ?>"><?php echo ucfirst($msg['status']); ?></span>
								</div>
							</div>
							<div class="message-body">
								<div class="info-grid">
									<div class="info-item" style="grid-column: 1 / -1;">
										<label>Message</label>
										<div class="value"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
									</div>
									<div class="info-item">
										<label>Date</label>
										<div class="value"><?php echo date('d M Y H:i', strtotime($msg['created_at'])); ?></div>
									</div>
									<?php if ($msg['profile_name']): ?>
									<div class="info-item">
										<label>Nexus Profile</label>
										<div class="value"><?php echo htmlspecialchars($msg['profile_name']); ?></div>
									</div>
									<?php endif; ?>
									<?php if ($msg['admin_notes']): ?>
									<div class="info-item">
										<label>Admin Notes</label>
										<div class="value"><?php echo nl2br(htmlspecialchars($msg['admin_notes'])); ?></div>
									</div>
									<?php endif; ?>
								</div>
								<div class="action-buttons">
									<?php if ($msg['status'] === 'unread'): ?>
									<form method="post" style="display: inline;">
										<input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
										<button type="submit" name="mark_read" class="btn-sm btn-read"><i class="fas fa-check"></i> Mark Read</button>
									</form>
									<?php endif; ?>
									<?php if ($msg['status'] !== 'replied'): ?>
									<form method="post" style="display: inline; flex: 1;">
										<input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
										<textarea name="admin_notes" class="notes-area" rows="2" placeholder="Add notes..."></textarea>
										<button type="submit" name="mark_replied" class="btn-sm btn-reply" style="margin-top: 5px;"><i class="fas fa-reply"></i> Mark Replied</button>
									</form>
									<?php endif; ?>
									<form method="post" style="display: inline;">
										<input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
										<button type="submit" name="archive" class="btn-sm btn-archive"><i class="fas fa-archive"></i> Archive</button>
									</form>
									<?php if ($msg['user_id']): ?>
									<a href="user-profile.php?id=<?php echo $msg['user_id']; ?>" target="_blank" class="btn-sm" style="background: #8ff5ff; color: #0e0e10; text-decoration: none;">
										<i class="fas fa-user"></i> View Profile
									</a>
									<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</main>
	</body>
</html>