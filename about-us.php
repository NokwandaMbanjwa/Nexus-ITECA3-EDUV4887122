<?php
require_once 'config.php';
$user_type = getUserType();
?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<title> NEXUS| About Us </title>
		<meta charset = "UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.motivation-section,
			.mission-section,
			.values-section {
				padding: 60px 0;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}

			.motivation-section {
				background-color: #0e0e10;
			}

			.mission-section {
				background-color: #131315;
			}

			.values-section {
				background-color: #0e0e10;
				border-bottom: none;
			}

			.section-content {
				max-width: 800px;
				margin: 0 auto;
			}

			.section-content p {
				text-align: center;
			}

			.mission-list {
				display: flex;
				justify-content: center;
				gap: 40px;
				flex-wrap: wrap;
				margin-top: 30px;
			}

			.mission-item {
				display: flex;
				align-items: center;
				gap: 10px;
			}

			.mission-item i {
				color: #8ff5ff;
				font-size: 18px;
			}

			.mission-item span {
				color: #e5e5e5;
				font-size: 14px;
			}

			.values-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
				gap: 30px;
				max-width: 1200px;
				margin: 0 auto;
			}

			.value-card {
				background-color: #19191c;
				padding: 32px 24px;
				border-radius: 20px;
				text-align: center;
				border: 1px solid rgba(118, 117, 119, 0.1);
				transition: all 0.3s;
			}

			.value-card:hover {
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.value-card i {
				font-size: 40px;
				color: #8ff5ff;
				margin-bottom: 20px;
			}

			.value-card h3 {
				font-size: 20px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}

			.value-card p {
				color: #e5e5e5;
				line-height: 1.6;
				font-size: 14px;
			}

			/* CTA Section */
			.cta-section {
				padding: 80px 0;
				background: #131315;
			}

			.cta-content {
				text-align: center;
				max-width: 700px;
				margin: 0 auto;
			}

			.cta-content h2 {
				font-size: 32px;
				margin-bottom: 16px;
				color: #f9f5f8;
			}

			.cta-content p {
				color: #adaaad;
				margin-bottom: 32px;
			}

			.cta-buttons {
				display: flex;
				gap: 20px;
				justify-content: center;
			}

			.btn-secondary-cyan {
				padding: 14px 32px;
			}

			@media (max-width: 768px) {
				.about-title {
					font-size: 36px;
				}

				.section-title {
					font-size: 28px;
				}

				.section-content p {
					font-size: 14px;
					text-align: left;
				}

				.mission-list {
					flex-direction: column;
					align-items: flex-start;
					gap: 15px;
				}

				.cta-buttons {
					flex-direction: column;
					gap: 16px;
				}

				.values-grid {
					grid-template-columns: 1fr;
				}
			}

			@media (max-width: 480px) {
				.motivation-section,
				.mission-section,
				.values-section {
					padding: 40px 0;
				}

				.section-heading {
					font-size: 24px;
				}

				.section-content p {
					font-size: 13px;
					text-align: left;
					padding: 0 8px;
				}

				.value-card {
					padding: 24px 16px;
				}

				.value-card i {
					font-size: 32px;
				}

				.value-card h3 {
					font-size: 18px;
				}

				.cta-content h2 {
					font-size: 24px;
				}

				.cta-section {
					padding: 50px 0;
				}
			}
		</style>
	</head>
	
	<body>
		<?php include 'header.php'; ?>

		<main>
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">ABOUT NEXUS</h1>
					<p class="section-hero-subtitle">Building South Africa's most trusted C2C marketplace</p>
				</div>
			</section>
			
			<section class="motivation-section">
				<div class="container">
					<h2 class="section-heading">Our Motivation</h2>
					<div class="section-content">
						<p>Nexus is a Latin term for a connection between a certain number of entities. 
						That is exactly what this platform aims to facilitate: a connection between buyers and sellers. 
						Nexus aims to help entrepreneurs start out and expand their businesses country-wide while providing customers with ease and security from fraudulent activity and other safety concerns while shopping online.</p>
					</div>
				</div>
			</section>
			
			<section class="mission-section">
				<div class="container">
					<h2 class="section-heading">Our Mission</h2>
					<div class="section-content">
						<p>To empower local trade, enrich communities, and foster economic growth by providing a secure, accessible, and transparent C2C marketplace for all South Africans.</p>
						<p>We believe in breaking down barriers between buyers and sellers, removing unnecessary middlemen, and creating direct connections that benefit everyone involved.</p>
						
						<div class="mission-list">
							<div class="mission-item">
								<i class="fas fa-check-circle"></i>
								<span>Empower local entrepreneurs</span>
							</div>
							<div class="mission-item">
								<i class="fas fa-check-circle"></i>
								<span>Build trust through verification</span>
							</div>
							<div class="mission-item">
								<i class="fas fa-check-circle"></i>
								<span>Keep fees fair and transparent</span>
							</div>
						</div>
					</div>
				</div>
			</section>
			
			<section class="values-section">
				<div class="container">
					<h2 class="section-heading">Our Core Values</h2>
					<div class="values-grid">
						<article class="value-card">
							<i class="fas fa-shield-alt"></i>
							<h3>Trust & Security</h3>
							<p>We prioritize safety through rigorous verification processes and secure transactions, ensuring peace of mind for every user.</p>
						</article>
						
						<article class="value-card">
							<i class="fas fa-hand-holding-heart"></i>
							<h3>Community First</h3>
							<p>We're building more than a marketplace - we're creating a community where South Africans support each other's growth.</p>
						</article>
						
						<article class="value-card">
							<i class="fas fa-chart-line"></i>
							<h3>Empowerment</h3>
							<p>We provide tools and insights that help sellers grow their businesses and buyers make informed decisions.</p>
						</article>
						
						<article class="value-card">
							<i class="fas fa-gem"></i>
							<h3>Transparency</h3>
							<p>Clear pricing, honest policies, and open communication are at the heart of everything we do.</p>
						</article>
					</div>
				</div>
			</section>
			
			<!-- Dynamic CTA Section based on user type -->
			<?php if ($user_type === 'guest'): ?>
			<section class="cta-section">
				<div class="container">
					<div class="cta-content">
						<h2>Ready to join the Nexus community?</h2>
						<p>Whether you're buying or selling, start your journey with us today.</p>
						<div class="cta-buttons">
							<a href="Register.php" class="btn-secondary-cyan">Create Account</a>
						</div>
					</div>
				</div>
			</section>
			<?php elseif ($user_type === 'buyer'): ?>
			<section class="cta-section">
				<div class="container">
					<div class="cta-content">
						<h2>Have something to sell?</h2>
						<p>Turn your items into cash. Join thousands of successful sellers on Nexus.</p>
						<div class="cta-buttons">
							<a href="Register.php?role=seller" class="btn-secondary-cyan">Start Selling Today</a>
						</div>
					</div>
				</div>
			</section>
			<?php endif; ?>
		</main>

		<?php include 'footer.php'; ?>
		<script type = "text/javascript" src = "utilities.js" ></script>
	</body>
</html>