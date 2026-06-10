<?php
require_once 'config.php';

$user_type = getUserType();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Learn More</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0");
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.info-section {
				padding: 60px 0 50px 0;
				background-color: #0c0c14;
			}

			.section-header {
				margin-bottom: 50px;
				display: flex;
				justify-content: space-between;
				align-items: flex-end;
				flex-wrap: wrap;
				gap: 32px;
			}

			.header-content {
				max-width: 700px;
				flex: 1;
			}

			.header-content h2 {
				font-size: 48px;
				margin-bottom: 24px;
				line-height: 1.2;
				text-align: left;
				color: #f9f5f8;
			}

			.header-content p {
				color: #e5e5e5;
				font-size: 18px;
				line-height: 1.6;
				text-align: left;
			}

			.learnmore-link {
				display: inline-flex;
				align-items: center;
				gap: 12px;
				background-color: transparent;
				color: #c180ff;
				font-weight: bold;
				letter-spacing: 0.1em;
				font-size: 20px;
				cursor: pointer;
				padding: 14px 32px;
				border-radius: 12px;
				transition: all 0.3s;
				text-transform: uppercase;
				margin-bottom: 8px;
				border: none;
				text-decoration: none;
			}

			.learnmore-link:hover {
				transform: translateY(-2px);
				text-shadow: 0 0 8px rgba(193, 128, 255, 0.6), 0 0 12px rgba(193, 128, 255, 0.4);
			}

			.learnmore-link span {
				color: #c180ff;
				font-weight: 800;
			}

			.learnmore-link .arrow i {
				transition: transform 0.3s;
				color: #c180ff;
				font-size: 18px;
			}

			.learnmore-link:hover .arrow i {
				transform: translateX(5px);
			}

			.features-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 32px;
				margin-top: 50px;
			}

			.feature-card {
				background-color: #121214;
				padding: 40px;
				border-radius: 16px;
				transition: all 0.5s;
				border: 1px solid rgba(143, 245, 255, 0.08);
			}

			.feature-card:hover {
				background-color: #1a1a20;
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.2);
			}

			.feature-icon {
				margin-bottom: 24px;
			}

			.feature-card:nth-child(1) .feature-icon i {
				color: #8ff5ff;
				font-size: 36px;
			}

			.feature-card:nth-child(2) .feature-icon i {
				color: #c180ff;
				font-size: 36px;
			}

			.feature-card:nth-child(3) .feature-icon i {
				color: #9093ff;
				font-size: 36px;
			}

			.feature-card h3 {
				font-size: 24px;
				font-weight: bold;
				margin-bottom: 16px;
				color: #f9f5f8;
			}

			.feature-card p {
				color: #adaaad;
				font-weight: 300;
				line-height: 1.6;
			}

			.roles-section {
				padding: 60px 0;
				background-color: #0c0c14;
			}

			.roles-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 0;
			}

			.role-card {
				padding: 60px 48px;
				background-color: #121214;
				border: 1px solid rgba(143, 245, 255, 0.08);
			}

			.role-card:first-child {
				border-right: none;
				border-radius: 20px 0 0 20px;
			}

			.role-card:last-child {
				border-radius: 0 20px 20px 0;
			}

			.role-badge {
				font-weight: bold;
				letter-spacing: -0.02em;
				font-size: 14px;
				text-transform: uppercase;
				display: inline-block;
				margin-bottom: 32px;
			}

			.seller-badge {
				color: #9093ff;
			}

			.buyer-badge {
				color: #8ff5ff;
			}

			.role-card h2 {
				font-size: 48px;
				margin-bottom: 32px;
				line-height: 1.2;
				color: #f9f5f8;
			}

			.role-features {
				list-style: none;
				margin-bottom: 48px;
			}

			.role-features li {
				display: flex;
				gap: 16px;
				margin-bottom: 24px;
			}

			.check-icon {
				padding-top: 4px;
				font-size: 20px;
			}

			.seller .check-icon {
				color: #9093ff;
			}

			.buyer .check-icon {
				color: #8ff5ff;
			}

			.role-features li div {
				display: flex;
				flex-direction: column;
			}

			.role-features li strong {
				font-size: 18px;
				margin-bottom: 4px;
				color: #f9f5f8;
			}

			.role-features li span {
				color: #adaaad;
			}
			
			.mobile-benefits{
				display: none;
			}
			
			@media (max-width: 768px) {
				.info-section {
					padding: 60px 0 30px;
				}

				.section-hero {
					display: none;
				}

				.learnmore-link {
					font-size: 16px;
					padding: 5px 10px;
				}

				.learnmore-link .arrow i {
					font-size: 14px;
				}

				.features-grid {
					grid-template-columns: 1fr;
					gap: 16px;
					margin-top: 32px;
				}
				
				.feature-card {
					padding: 24px 20px;
				}

				.feature-card h3 {
					font-size: 17px;
					margin-bottom: 8px;
				}

				.feature-card p {
					font-size: 12px;
					line-height: 1.5;
				}

				.feature-icon {
					margin-bottom: 16px;
				}

				.feature-icon i {
					font-size: 28px !important;
				}

				.roles-section {
					padding: 40px 0;
				}

				.roles-grid {
					grid-template-columns: 1fr;
				}

				.role-card {
					padding: 32px 20px;
				}

				.role-card:first-child {
					border-radius: 16px 16px 0 0;
					border-right: 1px solid rgba(143, 245, 255, 0.08);
				}

				.role-card:last-child {
					border-radius: 0 0 16px 16px;
				}

				.role-card h2 {
					font-size: 24px;
					margin-bottom: 20px;
				}

				.role-badge {
					font-size: 12px;
					margin-bottom: 20px;
				}

				.role-features li {
					margin-bottom: 16px;
					gap: 12px;
				}

				.role-features li strong {
					font-size: 15px;
				}

				.role-features li span {
					font-size: 12px;
				}

				.check-icon {
					font-size: 16px;
				}

				.role-features {
					margin-bottom: 28px;
				}

			@media (max-width: 480px) {
				.role-card h2 {
					font-size: 20px;
				}

				.role-card {
					padding: 24px 16px;
				}

				.feature-card {
					padding: 20px 16px;
				}

				.feature-card h3 {
					font-size: 15px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main>
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">UNDERSTANDING C2C</h1>
					<p class="section-hero-subtitle">How Consumer-to-Consumer marketplaces are changing the way South Africans trade</p>
				</div>
			</section>
			
			<section class="info-section">
				<div class="container">
					<div class="section-header">
						<div class="header-content">
							<h2>REDEFINING C2C TRANSACTIONS</h2>
							<p>Consumer-to-Consumer marketplace platforms remove the middleman, allowing customers to deal directly with entrepreneurs and service providers. 
							Nexus aims to facilitate a connection between buyers and sellers, helping entrepreneurs start out and expand their businesses country-wide while providing customers with ease and security while shopping online.
							There are many ways for users to stay safe when using our services, read more about that on our provided <a href="safety-tips.php" style="color: #00FFFF; text-decoration: none;">Safety Tips</a>.</p>
						</div>
						
						<a href="https://www.sciencedirect.com/science/article/pii/S254392512300030X" class="learnmore-link" target="_blank" rel="noopener noreferrer" id="externalLink">
							<span>LEARN MORE</span>
							<span class="arrow"><i class="fas fa-arrow-right"></i></span>
						</a>
					</div>
					
					<div class="features-grid">
						<article class="feature-card">
							<div class="feature-icon">
								<i class="fas fa-handshake"></i>
							</div>
							<h3>Responsive Communication</h3>
							<p>Our platform prioritizes and facilitates communication between buyers and sellers by providing a secure messaging feature.</p>
						</article>
						
						<article class="feature-card">
							<div class="feature-icon">
								<i class="fas fa-check-circle"></i>
							</div>
							<h3>Guaranteed Security</h3>
							<p>Seller and buyer profiles are verified before officially joining the Nexus community, with reporting and blocking features in place to ensure the safety and contentment of our users. Trust is built into every byte of the platform.</p>
						</article>
						
						<article class="feature-card">
							<div class="feature-icon">
								<i class="fas fa-truck"></i>
							</div>
							<h3>Countrywide Reach</h3>
							<p>Connect with sellers and buyers all around South Africa. With our integrated map system that determines all possible routes, all roads lead to you.</p>
						</article>
					</div>
				</div>
			</section>
			
			<section class="roles-section">
				<div class="container">
					<div class="roles-grid">
						<article class="role-card seller">
							<span class="role-badge seller-badge">For Sellers & Service Providers</span>
							<h2>LIQUIDATE YOUR <br>COLLECTION.</h2>
							<ul class="role-features">
								<li>
									<span class="check-icon" aria-hidden="true">
										<i class="fa-solid fa-circle-check"></i>
									</span>
									<div>
										<strong>Zero Listing Fee</strong>
										<span>Keep 90% of every sale you make. No hidden monthly costs.</span>
									</div>
								</li>
								<li>
									<span class="check-icon" aria-hidden="true">
										<i class="fa-solid fa-circle-check"></i>
									</span>
									<div>
										<strong>Scalability Guaranteed</strong>
										<span>Looking to expand your business? Worry not, services to extend your businesses are offered on the platform.</span>
									</div>
								</li>
							</ul>
							<button onclick="handleSellerAction()" class="btn-secondary-indigo" id="sellerActionBtn">
								<?php
								if ($user_type === 'guest') {
									echo "Become a Seller";
								} elseif ($user_type === 'buyer') {
									echo "Start Selling";
								} elseif ($user_type === 'seller') {
									echo "Post New Item";
								} else {
									echo "Become a Seller";
								}
								?>
							</button>
						</article>
						
						<article class="role-card buyer">
							<span class="role-badge buyer-badge">For Explorers & Buyers</span>
							<h2>DISCOVER THE <br>UNOBTAINABLE.</h2>
							<ul class="role-features">
								<li>
									<span class="check-icon" aria-hidden="true">
										<i class="fa-solid fa-circle-check"></i>
									</span>
									<div>
										<strong>Identity Verification</strong>
										<span>Only shop from verified sellers with proven track records.</span>
									</div>
								</li>
								<li>
									<span class="check-icon" aria-hidden="true">
										<i class="fa-solid fa-circle-check"></i>
									</span>
									<div>
										<strong>Smart Bidding</strong>
										<span>Set your max price and let our automated system win for you.</span>
									</div>
								</li>
							</ul>
							<button onclick="handleBuyerAction()" class="btn-secondary-cyan" id="buyerActionBtn">
								<?php
								if ($user_type === 'guest') {
									echo "Start Exploring";
								} else {
									echo "Start Exploring";
								}
								?>
							</button>
						</article>
					</div>
				</div>
			</section>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			const userType = '<?php echo $user_type; ?>';
			
			function handleSellerAction() {
				if (userType === 'guest') {
					window.location.href = 'register.php?role=seller';
				} else if (userType === 'buyer') {
					window.location.href = 'seller-application.php';
				} else if (userType === 'seller') {
					window.location.href = 'post-items.php';
				} else {
					window.location.href = 'register.php?role=seller';
				}
			}

			function handleBuyerAction() {
				if (userType === 'guest') {
					window.location.href = 'register.php?role=buyer';
				} else {
					window.location.href = 'explore-feed.php';
				}
			}
			
			var learnMoreLink = document.getElementById('externalLink');
			if (learnMoreLink) {
				learnMoreLink.addEventListener('click', function(e) {
					e.preventDefault();
					if (confirm("You are now leaving Nexus. Do you want to continue?")) {
						window.location.href = this.getAttribute('href');
					}
				});
			}
		</script>
	</body>
</html>