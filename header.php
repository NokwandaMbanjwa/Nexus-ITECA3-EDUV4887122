<?php
	require_once 'config.php';
	
	$user_type = getUserType();
	$user_name = getUserName();
	
	$current_page = basename($_SERVER['PHP_SELF']);
	
	$is_also_admin = false;
	if (isLoggedIn()) {
		$stmt = $pdo->prepare("SELECT admin_approved FROM nexus_users WHERE user_id = ? AND admin_approved = 1");
		$stmt->execute([getUserId()]);
		$is_also_admin = (bool)$stmt->fetch();
	}
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		<link rel="manifest" href="/site.webmanifest">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		
		<style>
			.navbar {
				position: fixed;
				top: 0;
				width: 100%;
				background: #0e0e10b3;
				backdrop-filter: blur(20px);
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
				z-index: 100;
			}

			.nav-container {
				max-width: 1280px;
				margin: 0 auto;
				padding: 0 24px;
				height: 80px;
				display: flex;
				align-items: center;
				justify-content: space-between;
			}

			.nav-left {
				display: flex;
				align-items: center;
				gap: 48px;
			}

			.logo {
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

			.logo:hover {
				text-shadow: 0 0 15px rgba(106, 78, 155, 0.8), 0 0 25px rgba(106, 78, 155, 0.5);
				transform: scale(1.02);
			}

			.nav-links {
				display: flex;
				gap: 32px;
			}

			.nav-links a {
				color: #d4d4d8;
				text-decoration: none;
				font-size: 14px;
				font-weight: 500;
				transition: color 0.3s;
			}

			.nav-links a:hover,
			.nav-links a.active {
				color: #8ff5ff;
			}

			.nav-links a i {
				font-size: 20px;
			}

			.nav-right {
				display: flex;
				align-items: center;
				gap: 24px;
				margin-left: auto;
			}
			
			.nav-links a.active {
				color: #8ff5ff;
				position: relative;
			}

			.nav-links a.active:after {
				content: '';
				position: absolute;
				bottom: -8px;
				left: 0;
				right: 0;
				height: 2px;
				background: #8ff5ff;
				border-radius: 2px;
			}

			.icon-btn.active {
				color: #8ff5ff;
			}

			.navbar .search-bar {
				display: flex;
				align-items: center;
				background-color: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 50px;
				padding: 12px 20px;
				width: 400px;
			}

			.navbar .search-bar i {
				color: #8ff5ff;
				margin-right: 12px;
			}

			.navbar .search-bar input {
				flex: 1;
				background: transparent;
				border: none;
				color: #e5e5e5;
				font-size: 15px;
			}

			.navbar .search-bar input::placeholder {
				color: #9ca3af;
			}

			.navbar .search-bar input:focus {
				outline: none;
			}

			.search-section {
				width: 100%;
				background-color: #0e0e10;
				padding: 20px 32px;
				border-bottom: 1px solid #2a2a2a;
				margin-top: 80px;
			}

			.search-container {
				width: 100%;
				max-width: 1400px;
				margin: 0 auto;
				display: flex;
				gap: 16px;
				align-items: center;
			}

			.search-section .search-bar {
				flex: 1;
				display: flex;
				align-items: center;
				background: #1a1a1a;
				border: 1px solid #333;
				border-radius: 8px;
				padding: 12px 20px;
				width: 100%;
			}

			.search-section .search-bar i {
				color: #8ff5ff;
				margin-right: 12px;
			}

			.search-section .search-bar input {
				flex: 1;
				background: none;
				border: none;
				color: #e0e0e0;
				font-size: 14px;
				width: 100%;
			}

			.search-section .search-bar input::placeholder {
				color: #666;
			}

			.search-section .search-bar input:focus {
				outline: none;
			}

			.category-dropdown {
				position: relative;
				flex-shrink: 0;
			}

			.category-btn {
				background-color: #1a1a1a;
				color: #e5e5e5;
				padding: 12px 20px;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				cursor: pointer;
				font-size: 14px;
				display: flex;
				align-items: center;
				gap: 8px;
				white-space: nowrap;
				transition: all 0.2s;
			}

			.category-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			.category-btn i {
				transition: transform 0.2s;
			}

			.category-btn.active i {
				transform: rotate(180deg);
			}

			.category-dropdown-content {
				display: none;
				position: absolute;
				top: calc(100% + 4px);
				left: 0;
				background-color: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				min-width: 200px;
				z-index: 10;
				max-height: 300px;
				overflow-y: auto;
			}

			.category-dropdown-content.show {
				display: block;
			}

			.category-dropdown-content a {
				display: block;
				padding: 10px 16px;
				color: #e5e5e5;
				text-decoration: none;
				font-size: 13px;
				transition: background 0.2s;
			}

			.category-dropdown-content a:hover {
				background-color: #2a2a2a;
				color: #8ff5ff;
			}

			.icon-btn {
				background: transparent;
				border: none;
				color: #d4d4d8;
				font-size: 20px;
				cursor: pointer;
				padding: 8px;
				transition: color 0.3s;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-decoration: none;
				position: relative;
			}

			.icon-caption {
				font-size: 13px;
			}

			.icon-btn:hover {
				color: #8ff5ff;
			}

			/* --- Badge styles --- */
			.nav-badge {
				position: absolute;
				top: -4px;
				right: -8px;
				background-color: #ff6b6b;
				color: white;
				border-radius: 50%;
				min-width: 18px;
				height: 18px;
				padding: 0 4px;
				font-size: 10px;
				font-weight: bold;
				display: none;
				align-items: center;
				justify-content: center;
				line-height: 1;
				box-sizing: border-box;
			}

			.nav-badge.visible {
				display: flex;
			}

			/* Messages badge in nav-links uses slightly different positioning */
			.nav-links a {
				position: relative;
			}

			.nav-links .nav-badge {
				top: -6px;
				right: -12px;
			}

			.user-menu {
				position: relative;
			}

			.dropdown-btn {
				background: transparent;
				border: none;
				cursor: pointer;
			}

			.dropdown-content {
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

			.dropdown-content a {
				display: block;
				padding: 10px 16px;
				color: #e5e5e5;
				text-decoration: none;
				transition: all 0.2s;
			}

			.dropdown-content a:hover {
				background-color: #2a2a2a;
				color: #8ff5ff;
			}

			.user-menu:hover .dropdown-content {
				display: block;
			}
			
			.topnav {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 64px;
				background: rgba(14, 14, 16, 0.98);
				backdrop-filter: blur(12px);
				-webkit-backdrop-filter: blur(12px);
				z-index: 1100;
				padding: 0 16px;
				align-items: center;
				justify-content: space-between;
				border-bottom: 1px solid rgba(118, 117, 119, 0.2);
				box-sizing: border-box;
			}

			.mobile-left {
				display: flex;
				align-items: center;
				z-index: 1101;
			}

			.mobile-logo {
				position: absolute;
				left: 50%;
				top: 50%;
				transform: translate(-50%, -50%);
				font-size: 36px;
				z-index: 1102;
				white-space: nowrap;
			}
			
			.mobile-logo:hover {
				text-shadow: 0 0 15px rgba(106, 78, 155, 0.8), 0 0 25px rgba(106, 78, 155, 0.5);
				transform: translate(-50%, -50%);
			}

			.mobile-right {
				display: flex;
				align-items: center;
				z-index: 1101;
				margin-left: auto;
			}

			.hamburger-btn {
				background: transparent;
				border: none;
				font-size: 28px;
				color: #d4d4d8;
				cursor: pointer;
				padding: 8px;
				z-index: 1101;
				transition: color 0.3s;
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.hamburger-btn:hover {
				color: #8ff5ff;
			}

			.mobile-account-btn {
				background: transparent;
				border: none;
				color: #d4d4d8;
				font-size: 28px;
				cursor: pointer;
				padding: 8px;
				z-index: 1101;
				text-decoration: none;
				display: flex;
				align-items: center;
				justify-content: center;
				transition: color 0.3s;
			}

			.mobile-account-btn:hover {
				color: #8ff5ff;
			}

			.mobile-menu {
				display: block;
				position: fixed;
				top: 0;
				left: -100%;
				width: 80%;
				max-width: 320px;
				height: 100vh;
				height: 100dvh; 
				background: #131315;
				z-index: 1200;
				padding: 70px 24px 40px;
				transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
				box-shadow: 4px 0 30px rgba(0,0,0,0.6);
				border-right: 1px solid rgba(143, 245, 255, 0.1);
				overflow-y: auto;
				overflow-x: hidden;
			}

			.mobile-menu.open {
				left: 0;
			}

			.mobile-menu a {
				display: flex !important;
				align-items: center;
				color: #e5e5e5;
				text-decoration: none;
				padding: 14px 0;
				font-size: 16px;
				font-weight: 500;
				border-bottom: 1px solid rgba(118, 117, 119, 0.15);
				transition: all 0.2s;
				gap: 12px;
				position: relative;
			}

			.mobile-menu a i {
				width: 20px;
				text-align: center;
				font-size: 16px;
				flex-shrink: 0;
			}

			.mobile-menu a:hover,
			.mobile-menu a:active {
				color: #8ff5ff;
				padding-left: 8px;
			}

			.mobile-menu a:last-child {
				border-bottom: none;
			}

			/* Mobile menu badges */
			.mobile-menu .nav-badge {
				position: static;
				margin-left: auto;
				top: unset;
				right: unset;
			}

			.menu-overlay {
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 100%;
				background: rgba(0,0,0,0.75);
				z-index: 1150;
				display: none;
				backdrop-filter: blur(2px);
				-webkit-backdrop-filter: blur(2px);
			}

			.menu-overlay.show {
				display: block;
			}

			.mobile-search-btn {
				background: transparent;
				border: none;
				color: #d4d4d8;
				font-size: 22px;
				cursor: pointer;
				padding: 8px;
				z-index: 1101;
				transition: color 0.3s;
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.mobile-search-btn:hover {
				color: #8ff5ff;
			}

			.mobile-search-bar {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				width: 100%;
				height: 56px;
				background: rgba(14, 14, 16, 0.98);
				backdrop-filter: blur(12px);
				z-index: 1110;
				padding: 0 16px;
				align-items: center;
				gap: 10px;
				box-sizing: border-box;
			}

			.mobile-search-bar.show {
				display: flex;
			}

			.mobile-search-bar input {
				flex: 1;
				padding: 10px 16px;
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 14px;
			}

			.mobile-search-bar input:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			.mobile-search-close {
				background: transparent;
				border: none;
				color: #888;
				font-size: 18px;
				cursor: pointer;
				padding: 8px;
			}

			.mobile-search-close:hover {
				color: #ff4444;
			}
			@media only screen and (max-width: 1024px) {
				.navbar {
					display: none;
				}
				.topnav {
					display: flex;
				}
				
				.search-section .search-bar {
					display: none;
				}
				.search-section {
					padding: 8px 16px;
					margin-top: 56px;
				}
				.search-container {
					flex-direction: column;
				}
				.category-dropdown {
					width: 100%;
					z-index: 50;
				}
				.category-btn {
					width: 100%;
					justify-content: center;
				}
			}

			@media only screen and (max-width: 768px) {
				.topnav {
					height: 64px;
					padding: 0 12px;
				}
				
				.mobile-logo {
					font-size: 36px;
				}
				
				.mobile-menu {
					width: 80%;
					max-width: 300px;
					padding: 65px 20px 30px;
				}
				
				.mobile-menu a {
					font-size: 15px;
					padding: 13px 0;
				}
			}

			@media only screen and (max-width: 480px) {
				.topnav {
					height: 52px;
					padding: 0 12px;
				}
				
				.mobile-logo {
					font-size: 30px;
				}
				
				.mobile-menu {
					width: 85%;
					max-width: 280px;
					padding: 60px 16px 25px;
				}
				
				.hamburger-btn {
					font-size: 24px;
				}
				
				.mobile-account-btn {
					font-size: 24px;
				}
			}

			@media only screen and (max-width: 360px) {
				.topnav {
					height: 48px;
					padding: 0 10px;
				}
				
				.mobile-logo {
					font-size: 22px;
				}
				
				.mobile-menu {
					width: 90%;
					max-width: 260px;
					padding: 55px 16px 20px;
				}
				
				.mobile-menu a {
					font-size: 14px;
					padding: 12px 0;
				}
				
				.hamburger-btn {
					font-size: 20px;
				}
				
				.mobile-account-btn {
					font-size: 18px;
				}
			}
		</style>
	</head>
	
	<body>
		<header class="navbar">
			<div class="nav-container">
				<div class="nav-left">
					<?php if ($user_type === 'guest'): ?>
						<a href="index.php" class="logo" aria-label="NEXUS Home">NEXUS</a>
					<?php else: ?>
						<a href="explore-feed.php" class="logo" aria-label="NEXUS Home">NEXUS</a>
					<?php endif; ?>
					<nav class="nav-links" aria-label="Main navigation">
						<?php if ($user_type === 'guest'): ?>
							<a href="login.php" class="<?php echo ($current_page == 'login.php') ? 'active' : ''; ?>">Login</a>
							<a href="register.php" class="<?php echo ($current_page == 'register.php') ? 'active' : ''; ?>">Register</a>
							<a href="contact-us.php" class="<?php echo ($current_page == 'contact-us.php') ? 'active' : ''; ?>">Contact Us</a>
							<a href="learn-more.php" class="<?php echo ($current_page == 'learn-more.php') ? 'active' : ''; ?>">Learn More</a>
						<?php else: ?>
							<a href="explore-feed.php" class="<?php echo ($current_page == 'explore-feed.php' || $current_page == 'explore.php') ? 'active' : ''; ?>">Explore</a>
							<a href="messages.php" class="<?php echo ($current_page == 'messages.php') ? 'active' : ''; ?>">
								Messages
								<span class="nav-badge" id="msgBadge"></span>
							</a>
							<a href="faqs.php" class="<?php echo ($current_page == 'faqs.php') ? 'active' : ''; ?>">Help Centre</a>
							<a href="learn-more.php" class="<?php echo ($current_page == 'learn-more.php') ? 'active' : ''; ?>">Learn More</a>
							<a href="contact-us.php" class="<?php echo ($current_page == 'contact-us.php') ? 'active' : ''; ?>">Contact Us</a>
							
							<?php if ($user_type === 'seller'): ?>
								<a href="post-items.php" class="<?php echo ($current_page == 'post-items.php') ? 'active' : ''; ?>">Post Items</a>
							<?php endif; ?>
						<?php endif; ?>
					</nav>
				</div>
				
				<div class="nav-right">
					<?php if ($user_type === 'guest'): ?>
						<div class="search-bar" role="search">
							<i class="fas fa-search"></i>
							<input type="search" id="searchInput" placeholder="Search for items, brands, or sellers...">
						</div>
						<a href="wishlist.php" class="icon-btn <?php echo ($current_page == 'wishlist.php') ? 'active' : ''; ?>" aria-label="Wishlist">
							<i class="fas fa-heart"></i>
							<span class="icon-caption">Wishlist</span>
						</a>
						<a href="safety-tips.php" class="icon-btn <?php echo ($current_page == 'safety-tips.php') ? 'active' : ''; ?>" aria-label="Safety Tips">
							<i class="fa-solid fa-shield-heart"></i>
							<span class="icon-caption">Safety Tips</span>
						</a>
					<?php else: ?>
						<a href="wishlist.php" class="icon-btn <?php echo ($current_page == 'wishlist.php') ? 'active' : ''; ?>" aria-label="Wishlist">
							<i class="fas fa-heart"></i>
							<span class="nav-badge" id="wishlistBadge"></span>
							<span class="icon-caption">Wishlist</span>
						</a>
						<a href="cart.php" class="icon-btn <?php echo ($current_page == 'cart.php') ? 'active' : ''; ?>" aria-label="Cart">
							<i class="fas fa-shopping-cart"></i>
							<span class="nav-badge" id="cartBadge"></span>
							<span class="icon-caption">Cart</span>
						</a>
						
						<div class="user-menu">
							<button class="dropdown-btn icon-btn">
								<i class="fas fa-user-circle"></i>
								<span class="icon-caption"><?php echo htmlspecialchars($user_name); ?></span>
							</button>
							<div class="dropdown-content">
								<a href="account-details.php" class="<?php echo ($current_page == 'account-details.php') ? 'active' : ''; ?>">Account Details</a>
								<a href="purchases.php" class="<?php echo ($current_page == 'purchases.php') ? 'active' : ''; ?>">Purchase History</a>
								<a href="follow-list.php" class="<?php echo ($current_page == 'follow-list.php') ? 'active' : ''; ?>">Follow List</a>

								<?php if ($user_type === 'seller'): ?>
									<a href="orders.php" class="<?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>">Orders</a>
									<a href="my-listings.php" class="<?php echo ($current_page == 'my-listings.php') ? 'active' : ''; ?>">My Listings</a>
									<a href="post-items.php" class="<?php echo ($current_page == 'post-items.php') ? 'active' : ''; ?>">Add Product</a>
								<?php endif; ?>

								<?php if ($user_type === 'buyer'): ?>
									<a href="seller-application.php">Start Selling</a>
								<?php endif; ?>
								
								<?php if ($is_also_admin): ?>
									<a href="admin-login.php" style="color: #b6b5d8;">
										<i class="fas fa-shield-alt"></i> Switch to Admin
									</a>
								<?php endif; ?>
								<a href="logout.php">Logout</a>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</header>

		<?php if ($user_type !== 'guest'): ?>
		<section class="search-section">
			<div class="search-container">
				<div class="category-dropdown">
					<button class="category-btn" id="categoryDropdownBtn">Shop by Category <i class="fas fa-chevron-down"></i></button>
					<div class="category-dropdown-content" id="categoryDropdown">
						<a href="#" data-category="all">All Categories</a>
						<a href="#" data-category="Baby & Toddler">Baby & Toddler</a>
						<a href="#" data-category="Beauty">Beauty</a>
						<a href="#" data-category="Books">Books</a>
						<a href="#" data-category="Electronics">Electronics</a>
						<a href="#" data-category="Entertainment">Entertainment</a>
						<a href="#" data-category="Fashion">Fashion</a>
						<a href="#" data-category="Gaming">Gaming</a>
						<a href="#" data-category="Home & Living">Home & Living</a>
						<a href="#" data-category="Office">Office</a>
						<a href="#" data-category="Pets">Pets</a>
						<a href="#" data-category="Sport">Sport</a>
						<a href="#" data-category="Other">Other</a>
					</div>
				</div>
				<div class="search-bar">
					<i class="fas fa-search"></i>
					<input type="text" id="searchInputLoggedIn" placeholder="Search for items, brands, or sellers...">
				</div>
			</div>
		</section>
		<?php endif; ?>

		<div class="topnav">
			<div class="mobile-left">
				<button class="hamburger-btn" id="hamburgerBtn" aria-label="Open menu">
					<i class="fas fa-bars"></i>
				</button>
			</div>
			
			<a href="<?php echo ($user_type === 'guest') ? 'index.php' : 'explore-feed.php'; ?>" class="logo mobile-logo">NEXUS</a>
			
			<div class="mobile-right">
				<button class="mobile-search-btn" id="mobileSearchBtn" aria-label="Search">
					<i class="fa fa-search" aria-hidden="true"></i>
				</button>
				<?php if ($user_type !== 'guest'): ?>
					<a href="account-details.php" class="mobile-account-btn" aria-label="My Account">
						<i class="fas fa-user-circle"></i>
					</a>
				<?php else: ?>
					<a href="login.php" class="mobile-account-btn" aria-label="Login">
						<i class="fa fa-sign-in" aria-hidden="true"></i>
					</a>
				<?php endif; ?>
			</div>
		</div>
		
		<div class="mobile-search-bar" id="mobileSearchBar">
			<input type="search" id="mobileSearchInput" placeholder="Search for items, brands, or sellers...">
			<button class="mobile-search-close" id="mobileSearchClose">
				<i class="fas fa-times"></i>
			</button>
		</div>

		<div class="mobile-menu" id="mobileMenu">
			<?php if ($user_type!== 'guest'): ?>
				<a href="explore-feed.php"><i class="fas fa-home"></i> Home</a>
			<?php else: ?>
				<a href="index.php"><i class="fas fa-home"></i> Home</a>
			<?php endif; ?>
			<?php if ($user_type !== 'guest'): ?>
				<a href="explore-feed.php"><i class="fas fa-compass"></i> Explore</a>
				<a href="messages.php"><i class="fas fa-comments"></i> Messages <span class="nav-badge" id="msgBadgeMobile"></span></a>
				<a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart <span class="nav-badge" id="cartBadgeMobile"></span></a>
				<a href="purchases.php"><i class="fas fa-receipt"></i> Purchase History</a>
				<a href="follow-list.php"><i class="fas fa-star"></i> Follow List</a>
				<a href="account-details.php"><i class="fas fa-user"></i> My Profile</a>
				<?php if ($is_also_admin): ?>
					<a href="admin-login.php" style="color: #b6b5d8;">
						<i class="fas fa-shield-alt"></i> Switch to Admin
					</a>
				<?php endif; ?>
			<?php endif; ?>
			
			<?php if ($user_type === 'seller'): ?>
				<a href="post-items.php"><i class="fas fa-plus-circle"></i> Post Items</a>
				<a href="my-listings.php"><i class="fas fa-list"></i> My Listings</a>
				<a href="orders.php"><i class="fas fa-box"></i> Orders</a>
			<?php endif; ?>
			
			<?php if ($user_type === 'buyer'): ?>
				<a href="register.php?role=seller"><i class="fas fa-rocket"></i> Start Selling</a>
			<?php endif; ?>
			
			<a href="wishlist.php"><i class="fas fa-heart"></i> Wishlist <span class="nav-badge" id="wishlistBadgeMobile"></span></a>
			<a href="faqs.php"><i class="fas fa-question-circle"></i> Help Centre</a>
			<a href="learn-more.php"><i class="fas fa-info-circle"></i> Learn More</a>
			<a href="contact-us.php"><i class="fas fa-envelope"></i> Contact Us</a>
			<a href="safety-tips.php"><i class="fa-solid fa-shield-heart"></i> Safety Tips</a>
			
			<?php if ($user_type === 'guest'): ?>
				<a href="login.php" style="color: #8ff5ff; font-weight: 600;"><i class="fa fa-sign-in"></i> Login</a>
				<a href="register.php" style="color: #9d00ff; font-weight: 600;"><i class="fas fa-user-plus"></i> Sign Up</a>
			<?php else: ?>
				<a href="logout.php" style="color: #ff6b6b;"><i class="fas fa-sign-out-alt"></i> Logout</a>
			<?php endif; ?>
		</div>

		<div class="menu-overlay" id="menuOverlay"></div>

		<script>
		const hamburgerBtn = document.getElementById('hamburgerBtn');
		const mobileMenu = document.getElementById('mobileMenu');
		const menuOverlay = document.getElementById('menuOverlay');

		function openMobileMenu() {
			mobileMenu.classList.add('open');
			menuOverlay.classList.add('show');
			document.body.style.overflow = 'hidden';
		}

		function closeMobileMenu() {
			mobileMenu.classList.remove('open');
			menuOverlay.classList.remove('show');
			document.body.style.overflow = ''; 
		}

		if (hamburgerBtn) {
			hamburgerBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				if (mobileMenu.classList.contains('open')) {
					closeMobileMenu();
				} else {
					openMobileMenu();
				}
			});
		}

		if (menuOverlay) {
			menuOverlay.addEventListener('click', closeMobileMenu);
		}

		if (mobileMenu) {
			mobileMenu.querySelectorAll('a').forEach(link => {
				link.addEventListener('click', function() {
					setTimeout(closeMobileMenu, 100);
				});
			});
		}

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && mobileMenu && mobileMenu.classList.contains('open')) {
				closeMobileMenu();
			}
		});

		window.addEventListener('resize', function() {
			if (window.innerWidth > 1024 && mobileMenu && mobileMenu.classList.contains('open')) {
				closeMobileMenu();
			}
		});

		const searchInput = document.getElementById('searchInput');
		if (searchInput) {
			searchInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					const searchTerm = this.value.trim();
					if (searchTerm) {
						window.location.href = 'index.php?search=' + encodeURIComponent(searchTerm);
					}
				}
			});
		}

		const searchInputLoggedIn = document.getElementById('searchInputLoggedIn');
		if (searchInputLoggedIn) {
			searchInputLoggedIn.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					const searchTerm = this.value.trim();
					if (searchTerm) {
						window.location.href = `explore-feed.php?search=${encodeURIComponent(searchTerm)}`;
					}
				}
			});
		}

		const categoryBtn = document.getElementById('categoryDropdownBtn');
		const categoryDropdown = document.getElementById('categoryDropdown');

		if (categoryBtn) {
			categoryBtn.addEventListener('click', function(e) {
				e.stopPropagation();
				categoryDropdown.classList.toggle('show');
				this.classList.toggle('active');
			});
			
			document.addEventListener('click', function(e) {
				if (categoryBtn && !categoryBtn.contains(e.target) && categoryDropdown && !categoryDropdown.contains(e.target)) {
					categoryDropdown.classList.remove('show');
					categoryBtn.classList.remove('active');
				}
			});
			
			if (categoryDropdown) {
				categoryDropdown.querySelectorAll('a').forEach(link => {
					link.addEventListener('click', function(e) {
						e.preventDefault();
						const category = this.getAttribute('data-category');
						if (category === 'all') {
							window.location.href = 'explore-feed.php';
						} else {
							window.location.href = `explore-feed.php?category=${encodeURIComponent(category)}`;
						}
					});
				});
			}
		}
		
		var mobileSearchBtn = document.getElementById('mobileSearchBtn');
		var mobileSearchBar = document.getElementById('mobileSearchBar');
		var mobileSearchInput = document.getElementById('mobileSearchInput');
		var mobileSearchClose = document.getElementById('mobileSearchClose');

		if (mobileSearchBtn) {
			mobileSearchBtn.addEventListener('click', function() {
				mobileSearchBar.classList.add('show');
				mobileSearchInput.focus();
			});
		}

		if (mobileSearchClose) {
			mobileSearchClose.addEventListener('click', function() {
				mobileSearchBar.classList.remove('show');
				mobileSearchInput.value = '';
			});
		}

		if (mobileSearchInput) {
			mobileSearchInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					var searchTerm = this.value.trim();
					if (searchTerm) {
						var isGuest = <?php echo ($user_type === 'guest') ? 'true' : 'false'; ?>;
						if (isGuest) {
							window.location.href = 'index.php?search=' + encodeURIComponent(searchTerm);
						} else {
							window.location.href = 'explore-feed.php?search=' + encodeURIComponent(searchTerm);
						}
					}
				}
			});
		}

		<?php if ($user_type !== 'guest'): ?>
		function updateBadge(el, count) {
			if (!el) return;
			if (count > 0) {
				el.textContent = count > 99 ? '99+' : count;
				el.classList.add('visible');
			} else {
				el.textContent = '';
				el.classList.remove('visible');
			}
		}

		function fetchCounts() {
			fetch('get-counts.php')
				.then(function(r) { return r.json(); })
				.then(function(data) {
					updateBadge(document.getElementById('cartBadge'),         data.cart);
					updateBadge(document.getElementById('wishlistBadge'),     data.wishlist);
					updateBadge(document.getElementById('msgBadge'),          data.messages);
					updateBadge(document.getElementById('cartBadgeMobile'),      data.cart);
					updateBadge(document.getElementById('wishlistBadgeMobile'),  data.wishlist);
					updateBadge(document.getElementById('msgBadgeMobile'),       data.messages);
				})
				.catch(function() {});
		}

		// Fetch immediately on load, then every 30 seconds
		fetchCounts();
		setInterval(fetchCounts, 30000);

		window.refreshNavCounts = fetchCounts;
		<?php endif; ?>
		</script>
	</body>
</html>
	