<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

$admin_role = $_SESSION['admin_role'];
$admin_name = $_SESSION['full_name'] ?? 'Admin';
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		
		<style>
			.admin-navbar {
				position: fixed;
				top: 0;
				width: 100%;
				background: #0e0e10b3;
				backdrop-filter: blur(20px);
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
				z-index: 100;
			}

			.admin-nav-container {
				max-width: 1280px;
				margin: 0 auto;
				padding: 0 24px;
				height: 80px;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}

			.admin-nav-left {
				display: flex;
				align-items: center;
				gap: 48px;
			}

			.admin-logo {
				font-size: 28px;
				font-weight: 800;
				font-family: 'Manrope', sans-serif;
				letter-spacing: -0.02em;
				color: #9d00ff;
				text-shadow: 0 0 10px rgba(106, 78, 155, 0.5), 0 0 20px rgba(106, 78, 155, 0.3);
				text-decoration: none;
				cursor: pointer;
				transition: all 0.3s;
			}

			.admin-logo:hover {
				text-shadow: 0 0 15px rgba(106, 78, 155, 0.8), 0 0 25px rgba(106, 78, 155, 0.5);
				transform: scale(1.02);
			}

			.admin-nav-links {
				display: flex;
				gap: 32px;
			}

			.admin-nav-links a {
				color: #d4d4d8;
				text-decoration: none;
				font-size: 14px;
				font-weight: 500;
				transition: color 0.3s;
			}

			.admin-nav-links a:hover,
			.admin-nav-links a.active {
				color: #b6b5d8;
			}

			.admin-nav-links a.active {
				color: #b6b5d8;
				position: relative;
			}

			.admin-nav-links a.active:after {
				content: '';
				position: absolute;
				bottom: -8px;
				left: 0;
				right: 0;
				height: 2px;
				background: #b6b5d8;
				border-radius: 2px;
			}

			.admin-nav-right {
				display: flex;
				align-items: center;
				gap: 24px;
				margin-left: auto;
			}

			.admin-search-bar {
				display: flex;
				align-items: center;
				background-color: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 50px;
				padding: 12px 20px;
				width: 400px;
			}

			.admin-search-bar i {
				color: #b6b5d8;
				margin-right: 12px;
			}

			.admin-search-bar input {
				flex: 1;
				background: transparent;
				border: none;
				color: #e5e5e5;
				font-size: 15px;
			}

			.admin-search-bar input::placeholder {
				color: #9ca3af;
			}

			.admin-search-bar input:focus {
				outline: none;
			}

			.admin-user-menu {
				position: relative;
			}

			.admin-dropdown-btn {
				background: transparent;
				border: none;
				color: #d4d4d8;
				cursor: pointer;
				display: flex;
				flex-direction: column;
				align-items: center;
				gap: 4px;
				padding: 8px;
				transition: color 0.3s;
			}

			.admin-dropdown-btn i {
				font-size: 20px;
			}

			.admin-dropdown-btn span {
				font-size: 13px;
			}

			.admin-dropdown-btn:hover {
				color: #b6b5d8;
			}

			.admin-dropdown-content {
				display: none;
				position: absolute;
				top: 100%;
				right: 0;
				background-color: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				min-width: 180px;
				z-index: 10;
			}

			.admin-dropdown-content a {
				display: block;
				padding: 10px 16px;
				color: #e5e5e5;
				text-decoration: none;
				transition: all 0.2s;
			}

			.admin-dropdown-content a:hover {
				background-color: #2a2a2a;
				color: #b6b5d8;
			}

			.admin-user-menu:hover .admin-dropdown-content {
				display: block;
			}

			.admin-wrapper {
				display: flex;
				min-height: 100vh;
			}
			
			.admin-sidebar {
				width: 280px;
				background: #0f0f0f;
				border-right: 1px solid #2a2a2a;
				position: fixed;
				left: 0;
				top: 80px;
				bottom: 0;
				overflow-y: auto;
				transition: all 0.3s;
				z-index: 99;
			}
			
			.sidebar-nav {
				padding: 20px 0;
			}
			
			.nav-section {
				margin-bottom: 10px;
			}
			
			.nav-section-title {
				padding: 12px 20px;
				color: #b6b5d8;
				font-size: 13px;
				font-weight: 600;
				text-transform: uppercase;
				letter-spacing: 1px;
				cursor: pointer;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}
			
			.nav-section-title i {
				font-size: 12px;
				transition: transform 0.3s;
			}
			
			.nav-section-title.collapsed i {
				transform: rotate(-90deg);
			}
			
			.nav-section-content {
				display: block;
			}
			
			.nav-section-content.collapsed {
				display: none;
			}
			
			.nav-item {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 10px 20px 10px 45px;
				color: #aaa;
				text-decoration: none;
				transition: all 0.2s;
				font-size: 14px;
			}
			
			.nav-item:hover,
			.nav-item.active {
				background: #1a1a1a;
				color: #b6b5d8;
				border-left: 3px solid #b6b5d8;
			}
			
			.nav-item i {
				width: 20px;
				font-size: 14px;
			}
			
			.admin-main {
				flex: 1;
				margin-left: 280px;
				margin-top: 80px;
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
				color: #b6b5d8;
				margin-bottom: 8px;
			}
			
			.stat-card p {
				color: #888;
				font-size: 13px;
			}
			
			.section-title {
				font-size: 20px;
				margin-bottom: 20px;
				color: #f9f5f8;
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
				color: #b6b5d8;
			}
			
			.hamburger-menu {
				display: none;
				background: #0e0e10;
				border: none;
				color: #e5e5e5;
				font-size: 24px;
				cursor: pointer;
				padding: 8px 12px;
				border-radius: 8px;
			}

			.admin-topnav {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 56px;
				background: rgba(14, 14, 16, 0.98);
				backdrop-filter: blur(12px);
				-webkit-backdrop-filter: blur(12px);
				z-index: 1100;
				align-items: center;
				justify-content: space-between;
				border-bottom: 1px solid rgba(118, 117, 119, 0.2);
				padding: 0 12px;
				box-sizing: border-box;
			}

			.admin-topnav .mobile-hamburger {
				background: transparent;
				border: none;
				color: #e5e5e5;
				font-size: 22px;
				cursor: pointer;
				padding: 8px;
				z-index: 1101;
			}

			.admin-topnav .mobile-logo {
				position: absolute;
				left: 50%;
				top: 50%;
				transform: translate(-50%, -50%);
				font-size: 20px;
				z-index: 1101;
				white-space: nowrap;
			}
			
			.admin-topnav .mobile-logo:hover {
				text-shadow: 0 0 15px rgba(106, 78, 155, 0.8), 0 0 25px rgba(106, 78, 155, 0.5);
				transform: translate(-50%, -50%);
			}

			.admin-topnav .mobile-switch-btn {
				background: transparent;
				border: none;
				color: #8ff5ff;
				font-size: 18px;
				cursor: pointer;
				padding: 8px;
				text-decoration: none;
				z-index: 1101;
			}

			@media only screen and (max-width: 1024px) {
				.admin-navbar {
					display: none !important;
				}
				.admin-topnav {
					display: flex !important;
				}
				.admin-sidebar {
					left: -280px;
					top: 56px;
					z-index: 99;
				}
				.admin-sidebar.open {
					left: 0;
				}
				.admin-main {
					margin-left: 0 !important;
					margin-top: 56px !important;
				}
				.admin-nav-links {
					display: none;
				}
			}

			@media only screen and (max-width: 480px) {
				.admin-topnav {
					height: 50px;
					padding: 0 10px;
				}
				.admin-topnav .mobile-logo {
					font-size: 18px;
				}
				.admin-topnav .mobile-hamburger {
					font-size: 20px;
				}
				.admin-topnav .mobile-switch-btn {
					font-size: 16px;
				}
				.admin-sidebar {
					width: 260px;
					top: 50px;
				}
				.admin-main {
					margin-top: 50px !important;
				}
			}

			@media only screen and (max-width: 360px) {
				.admin-topnav {
					height: 46px;
					padding: 0 8px;
				}
				.admin-topnav .mobile-logo {
					font-size: 16px;
				}
				.admin-sidebar {
					width: 240px;
					top: 46px;
				}
				.admin-main {
					margin-top: 46px !important;
				}
			}
		</style>
	</head>
	<body>

	<nav class="admin-navbar">
		<div class="admin-nav-container">
			<div class="admin-nav-left">
				<a href="admin-dashboard.php" class="admin-logo">NEXUS</a>
				<div class="admin-nav-links">
					<a href="admin-dashboard.php" class="<?php echo ($current_page == 'admin-dashboard.php') ? 'active' : ''; ?>">Dashboard</a>
					<a href="Explore-Feed.php" class="<?php echo ($current_page == 'Explore-Feed.php') ? 'active' : ''; ?>">Explore Feed</a>
					<a href="admin-messages.php" class="<?php echo ($current_page == 'admin-messages.php') ? 'active' : ''; ?>">Messages</a>
				</div>
			</div>
			
			<div class="admin-nav-right">
				<div class="admin-search-bar">
					<i class="fas fa-search"></i>
					<input type="search" placeholder="Search for users, reports, or items...">
				</div>
				
				<div class="admin-user-menu">
					<button class="admin-dropdown-btn">
						<i class="fas fa-user-circle"></i>
						<span><?php echo htmlspecialchars($admin_name); ?></span>
					</button>
					<div class="admin-dropdown-content">
						<a href="admin-profile.php">My Profile</a>
						<a href="admin-settings.php">Settings</a>
						<a href="logout.php?switch=user" style="color: #8ff5ff;">
							<i class="fas fa-exchange-alt"></i> Switch to User Account
						</a>
						<a href="logout.php">Logout</a>
					</div>
				</div>
			</div>
		</div>
	</nav>

	<div class="admin-topnav">
		<button class="mobile-hamburger" id="hamburgerBtn" aria-label="Open menu">
			<i class="fas fa-bars"></i>
		</button>
		<a href="admin-dashboard.php" class="admin-logo mobile-logo">NEXUS</a>
		<a href="logout.php?switch=user" class="mobile-switch-btn" title="Switch to User Account">
			<i class="fas fa-exchange-alt"></i>
		</a>
	</div>

	<<script>
	document.addEventListener('DOMContentLoaded', function() {
		var hamburgerBtn = document.getElementById('hamburgerBtn');
		var adminSidebar = document.getElementById('adminSidebar');

		if (hamburgerBtn && adminSidebar) {
			hamburgerBtn.addEventListener('click', function() {
				adminSidebar.classList.toggle('open');
			});

			document.addEventListener('click', function(e) {
				if (window.innerWidth <= 1024) {
					if (!adminSidebar.contains(e.target) && !hamburgerBtn.contains(e.target)) {
						adminSidebar.classList.remove('open');
					}
				}
			});
		}
	});
	</script>