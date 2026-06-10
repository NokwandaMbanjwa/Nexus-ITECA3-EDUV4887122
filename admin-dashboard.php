<?php
require_once 'config.php';
require_once 'admin-auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

$stats = [];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM nexus_users WHERE user_type = 'buyer'");
$stats['buyers'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM nexus_users WHERE user_type = 'seller'");
$stats['sellers'] = $stmt->fetch()['count'];

$stmt = $pdo->query("SELECT COUNT(*) as count FROM products WHERE listing_status = 'active'");
$stats['products'] = $stmt->fetch()['count'];

// Count successful sales 
$stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE payment_status = 'paid'");
$stats['sales'] = $stmt->fetch()['count'];

// Calculate total revenue from all paid orders
$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount * 0.10), 0) as revenue FROM orders WHERE payment_status = 'paid'");
$stats['revenue'] = $stmt->fetch()['revenue'];

$stmt = $pdo->query("SELECT p.*, sp.store_name, sp.full_name FROM products p LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id ORDER BY p.created_at DESC LIMIT 10");
$recent_items = $stmt->fetchAll();

$stmt = $pdo->query("SELECT category, COUNT(*) as count FROM products WHERE listing_status = 'active' GROUP BY category ORDER BY count DESC LIMIT 5");
$top_categories = $stmt->fetchAll();

$stmt = $pdo->query("SELECT COUNT(DISTINCT ip_address) as visitors FROM admin_activity_log WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['visitors'] = $stmt->fetch()['visitors'] ?: 0;

$pending_verifications = 0;
if ($admin_role === 'verification' || $is_super_admin) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM seller_profiles WHERE verification_status = 'pending'");
    $pending_verifications = $stmt->fetch()['count'];
}

$pending_reports = 0;
if ($admin_role === 'safety_support' || $is_super_admin) {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_reports WHERE status = 'pending'");
    $pending_reports = $stmt->fetch()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS |Admin Dashboard</title>
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
			
			.dashboard-content {
				padding: 24px;
			}
			
			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 30px;
			}
			
			.stat-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
			}
			
			.stat-card h3 {
				font-size: 28px;
				color: #800080;
				margin-bottom: 8px;
			}
			
			.stat-card p {
				font-size: 14px;
			}
			
			.recent-items-table,
			.categories-table {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				overflow-x: auto;
				margin-bottom: 30px;
			}
			
			table {
				width: 100%;
				border-collapse: collapse;
			}
			
			th, td {
				padding: 12px 16px;
				text-align: left;
				border-bottom: 1px solid #2a2a2a;
			}
			
			th {
				background: #0f0f0f;
				color: #8ff5ff;
			}
			
			@media (max-width: 768px) {
				.admin-sidebar {
					left: -280px;
				}
				.admin-sidebar.open {
					left: 0;
				}
				.admin-main {
					margin-left: 0;
				}
				.hamburger-menu {
					display: block;
				}
				.stats-grid {
					grid-template-columns: repeat(2, 1fr);
					gap: 12px;
				}
				.stat-card {
					padding: 16px;
				}
				.stat-card h3 {
					font-size: 24px;
				}
				.stat-card p {
					font-size: 12px;
				}
				.dashboard-content {
					padding: 16px;
				}
				.recent-items-table,
				.categories-table {
					overflow-x: auto;
				}
				th, td {
					padding: 10px 12px;
					font-size: 13px;
				}
			}

			@media (max-width: 480px) {
				.stats-grid {
					grid-template-columns: 1fr 1fr;
					gap: 10px;
				}
				.stat-card {
					padding: 12px;
				}
				.stat-card h3 {
					font-size: 22px;
				}
				.dashboard-content {
					padding: 12px;
				}
				th, td {
					padding: 8px 10px;
					font-size: 12px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'admin-header.php'; ?>
		<?php include 'admin-sidebar.php'; ?>
			
			<main class="admin-main">
				<h2 class="section-heading">NEXUS 2026 Statistics</h2>
				<div class="dashboard-content">
					<div class="stats-grid">
						<div class="stat-card">
							<h3><?php echo number_format($stats['buyers']); ?></h3>
							<p>Total Buyers</p>
						</div>
						<div class="stat-card">
							<h3><?php echo number_format($stats['sellers']); ?></h3>
							<p>Total Sellers</p>
						</div>
						<div class="stat-card">
							<h3><?php echo number_format($stats['products']); ?></h3>
							<p>Total Items</p>
						</div>
						<div class="stat-card">
							<h3><?php echo number_format($stats['sales']); ?></h3>
							<p>Successful Sales</p>
						</div>
						<div class="stat-card">
							<h3>R<?php echo number_format($stats['revenue'], 2); ?></h3>
							<p>Total Revenue</p>
						</div>
						<div class="stat-card">
							<h3><?php echo number_format($stats['visitors']); ?></h3>
							<p>Website Visitors (30 Days)</p>
						</div>
						<?php if ($admin_role === 'verification' || $is_super_admin): ?>
						<div class="stat-card">
							<h3><?php echo number_format($pending_verifications); ?></h3>
							<p>Pending Verifications</p>
						</div>
						<?php endif; ?>
						<?php if ($admin_role === 'safety_support' || $is_super_admin): ?>
						<div class="stat-card">
							<h3><?php echo number_format($pending_reports); ?></h3>
							<p>Pending Reports</p>
						</div>
						<?php endif; ?>
					</div>
					
					<h2 class="section-heading">Recently Added Items</h2>
					<div class="recent-items-table">
						<table>
							<thead>
								<tr>
									<th>Product Name</th>
									<th>Seller</th>
									<th>Price</th>
									<th>Date Added</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($recent_items as $item): ?>
								<tr>
									<td><?php echo htmlspecialchars($item['product_name']); ?></td>
									<td><?php echo htmlspecialchars($item['store_name'] ?: ($item['full_name']?? 'Seller')); ?></td>
									<td>R<?php echo number_format($item['price'], 2); ?></td>
									<td><?php echo date('d M Y', strtotime($item['created_at'])); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					
					<h2 class="section-heading">Most Visited Categories</h2>
					<div class="categories-table">
						<table>
							<thead>
								<tr>
									<th>Category</th>
									<th>Items Count</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ($top_categories as $cat): ?>
								<tr>
									<td><?php echo ucfirst(htmlspecialchars($cat['category'])); ?></td>
									<td><?php echo number_format($cat['count']); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</div>
			</main>
		</div>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
	</body>
</html>