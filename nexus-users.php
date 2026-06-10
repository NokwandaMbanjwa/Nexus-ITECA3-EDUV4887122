<?php
require_once 'admin-auth.php';

$search = $_GET['search'] ?? '';
$filter_type = $_GET['type'] ?? 'all';
$filter_flagged = $_GET['flagged'] ?? 'all';

$sql = "
    SELECT u.user_id, u.email, u.user_type, u.created_at, u.admin_approved, u.admin_role,
           COALESCE(bp.full_name, sp.full_name) as full_name,
           COALESCE(bp.phone_number, sp.phone_number) as phone,
           sp.store_name,
           (SELECT COUNT(*) FROM products WHERE seller_id = sp.profile_id) as listing_count,
           (SELECT COUNT(*) FROM user_reports WHERE reported_id = u.user_id AND status = 'reviewed') as report_count
    FROM nexus_users u
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.email LIKE ? OR bp.full_name LIKE ? OR sp.full_name LIKE ? OR sp.store_name LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

if ($filter_type !== 'all') {
    $sql .= " AND u.user_type = ?";
    $params[] = $filter_type;
}

if ($filter_flagged === 'flagged') {
    $sql .= " AND (SELECT COUNT(*) FROM user_reports WHERE reported_id = u.user_id AND status = 'reviewed') > 0";
} elseif ($filter_flagged === 'clean') {
    $sql .= " AND (SELECT COUNT(*) FROM user_reports WHERE reported_id = u.user_id AND status = 'reviewed') = 0";
}

$sql .= " ORDER BY u.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$flagged_count = 0;
$clean_count = 0;
foreach ($users as $u) {
    if ($u['report_count'] > 0) $flagged_count++;
    else $clean_count++;
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin | All Users</title>
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
					color: #b026ff;
					font-size: 32px;
				}

				.filters-bar {
					display: flex;
					gap: 12px;
					margin-bottom: 24px;
					flex-wrap: wrap;
					align-items: center;
				}

				.filters-bar input,
				.filters-bar select {
					padding: 10px 14px;
					background: #0e0e10;
					border: 1px solid #2a2a2a;
					border-radius: 8px;
					color: #e5e5e5;
					font-size: 13px;
				}

				.filters-bar input {
					flex: 1;
					min-width: 200px;
				}

				.filters-bar input:focus,
				.filters-bar select:focus {
					outline: none;
					border-color: #c3b1e1;
				}

				.stats-grid {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
					gap: 16px;
					margin-bottom: 30px;
				}

				.stat-card {
					background: #131315;
					border: 1px solid #2a2a2a;
					border-radius: 12px;
					padding: 20px;
					text-align: center;
				}

				.stat-card h3 {
					font-size: 32px;
					color: #c3b1e1;
					margin-bottom: 5px;
				}

				.stat-card.flagged h3 {
					color: #ff4444;
				}

				.users-table {
					background: #131315;
					border: 1px solid #2a2a2a;
					border-radius: 12px;
					overflow-x: auto;
				}

				table {
					width: 100%;
					border-collapse: collapse;
				}

				th {
					background: #0f0f0f;
					color: #c3b1e1;
					padding: 14px 16px;
					text-align: left;
					font-size: 12px;
					text-transform: uppercase;
					letter-spacing: 1px;
				}

				td {
					padding: 12px 16px;
					border-bottom: 1px solid #2a2a2a;
					color: #e5e5e5;
					font-size: 13px;
				}

				tr:hover td {
					background: #1a1a1a;
				}

				tr.flagged td {
					background: rgba(255, 68, 68, 0.08);
				}

				tr.flagged:hover td {
					background: rgba(255, 68, 68, 0.15);
				}

				.badge {
					display: inline-block;
					padding: 3px 10px;
					border-radius: 12px;
					font-size: 11px;
					font-weight: 500;
				}

				.badge-buyer {
					background: rgba(143, 245, 255, 0.15);
					color: #8ff5ff;
				}

				.badge-seller {
					background: rgba(193, 128, 255, 0.15);
					color: #c180ff;
				}

				.badge-admin {
					background: rgba(255, 193, 7, 0.15);
					color: #ffc107;
				}

				.badge-flagged {
					background: rgba(255, 68, 68, 0.15);
					color: #ff4444;
				}

				.badge-clean {
					background: rgba(76, 175, 80, 0.15);
					color: #4caf50;
				}

				.empty-state {
					text-align: center;
					padding: 60px;
					color: #888;
				}

				@media (max-width: 768px) {
					.admin-container {
						padding: 40px 16px 20px;
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
					<h1><i class="fas fa-users"></i> All Users</h1>
					<p>Manage and view all registered Nexus users</p>
				</div>
				
				<div class="stats-grid">
					<div class="stat-card"><h3><?php echo count($users); ?></h3><p>Total Users</p></div>
					<div class="stat-card flagged"><h3><?php echo $flagged_count; ?></h3><p>Flagged Users</p></div>
					<div class="stat-card"><h3><?php echo $clean_count; ?></h3><p>Clean Users</p></div>
				</div>
				
				<form class="filters-bar" method="get">
					<input type="text" name="search" placeholder="Search by name or email..." value="<?php echo htmlspecialchars($search); ?>">
					<select name="type">
						<option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
						<option value="buyer" <?php echo $filter_type === 'buyer' ? 'selected' : ''; ?>>Buyer</option>
						<option value="seller" <?php echo $filter_type === 'seller' ? 'selected' : ''; ?>>Seller</option>
					</select>
					<select name="flagged">
						<option value="all" <?php echo $filter_flagged === 'all' ? 'selected' : ''; ?>>All Status</option>
						<option value="flagged" <?php echo $filter_flagged === 'flagged' ? 'selected' : ''; ?>>Flagged Only</option>
						<option value="clean" <?php echo $filter_flagged === 'clean' ? 'selected' : ''; ?>>Clean Only</option>
					</select>
					<button type="submit" style="padding: 10px 20px; background: #c3b1e1; color: #0e0e10; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Filter</button>
				</form>
				
				<?php if (empty($users)): ?>
					<div class="empty-state"><i class="fas fa-users" style="font-size: 48px; margin-bottom: 16px;"></i><p>No users found</p></div>
				<?php else: ?>
					<div class="users-table">
						<table>
							<thead>
								<tr>
									<th>User</th>
									<th>Email</th>
									<th>Type</th>
									<th>Listings</th>
									<th>Reports</th>
									<th>Status</th>
									<th>Joined</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($users as $user): 
									$is_flagged = $user['report_count'] > 0;
								?>
									<tr class="<?php echo $is_flagged ? 'flagged' : ''; ?>">
										<td>
											<strong><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></strong>
											<?php if ($user['store_name']): ?>
												<br><small style="color: #888;"><?php echo htmlspecialchars($user['store_name']); ?></small>
											<?php endif; ?>
										</td>
										<td><?php echo htmlspecialchars($user['email']); ?></td>
										<td>
											<span class="badge badge-<?php echo $user['user_type']; ?>"><?php echo ucfirst($user['user_type']); ?></span>
											<?php if ($user['admin_approved']): ?>
												<br><small style="color: #ffc107;">Admin: <?php echo $user['admin_role']; ?></small>
											<?php endif; ?>
										</td>
										<td><?php echo $user['user_type'] === 'seller' ? $user['listing_count'] : '-'; ?></td>
										<td><?php echo $user['report_count']; ?></td>
										<td>
											<span class="badge <?php echo $is_flagged ? 'badge-flagged' : 'badge-clean'; ?>">
												<?php echo $is_flagged ? 'Flagged' : 'Clean'; ?>
											</span>
										</td>
										<td><?php echo date('d M Y', strtotime($user['created_at'])); ?></td>
										<td>
											<a href="user-profile.php?id=<?php echo $user['user_id']; ?>" target="_blank" style="color: #c3b1e1; text-decoration: none;">
												<i class="fas fa-external-link-alt"></i> View
											</a>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</div>
		</main>
	</body>
</html>