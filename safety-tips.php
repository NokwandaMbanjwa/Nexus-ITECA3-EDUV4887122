<?php
require_once 'config.php';

$page_title = "Safety Tips";
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Safety Tips</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.safety-section {
				padding: 60px 0;
				background-color: #0e0e10;
			}

			.safety-section.alt-bg {
				background-color: #131315;
			}

			.safety-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 30px;
				max-width: 1200px;
				margin: 0 auto;
			}

			.safety-card {
				background-color: #19191c;
				padding: 32px 24px;
				border-radius: 20px;
				text-align: center;
				border: 1px solid rgba(118, 117, 119, 0.1);
				transition: all 0.3s;
			}

			.safety-card:hover {
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.safety-icon {
				width: 70px;
				height: 70px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}

			.safety-icon i {
				font-size: 36px;
				color: #8ff5ff;
			}

			.safety-card h3 {
				font-size: 20px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}

			.safety-card p {
				color: #adaaad;
				line-height: 1.6;
				font-size: 14px;
			}

			.red-flags-section {
				padding: 60px 0;
				background-color: #0e0e10;
			}

			.red-flags-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 24px;
				max-width: 1200px;
				margin: 0 auto;
			}

			.red-flag-card {
				background: #19191c;
				border: 1px solid rgba(255, 100, 100, 0.2);
				border-radius: 16px;
				padding: 24px;
				text-align: center;
				transition: all 0.3s;
			}

			.red-flag-card:hover {
				border-color: rgba(255, 100, 100, 0.4);
				transform: translateY(-3px);
			}

			.red-flag-card i {
				font-size: 36px;
				color: #ff6464;
				margin-bottom: 16px;
			}

			.red-flag-card h3 {
				font-size: 18px;
				margin-bottom: 10px;
				color: #ff6464;
			}

			.red-flag-card p {
				color: #adaaad;
				font-size: 14px;
				line-height: 1.5;
			}

			.what-to-do-section {
				padding: 60px 0;
				background-color: #131315;
			}

			.what-to-do-content {
				max-width: 900px;
				margin: 0 auto;
				text-align: center;
			}

			.what-to-do-content h2 {
				font-size: 32px;
				color: #f9f5f8;
				margin-bottom: 40px;
			}

			.steps-container {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
				gap: 30px;
			}

			.step {
				flex: 1;
				min-width: 180px;
				text-align: center;
			}

			.step-number {
				width: 50px;
				height: 50px;
				background: #8ff5ff;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 16px;
				font-size: 24px;
				font-weight: bold;
				color: #0e0e10;
			}

			.step-content h3 {
				font-size: 18px;
				color: #f9f5f8;
				margin-bottom: 8px;
			}

			.step-content p {
				font-size: 13px;
				line-height: 1.5;
				color: #adaaad;
			}

			@media (max-width: 768px) {
				.section-hero-title {
					font-size: 36px;
				}
				.section-title {
					font-size: 28px;
				}
				.safety-grid,
				.red-flags-grid {
					grid-template-columns: 1fr;
				}
				.steps-container {
					flex-direction: column;
					gap: 24px;
				}
				.what-to-do-content h2 {
					font-size: 28px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="safety-page">
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">SAFETY TIPS</h1>
					<p class="section-hero-subtitle">Your safety is our priority. Follow these guidelines for a secure trading experience.</p>
				</div>
			</section>

			<section class="safety-section">
				<div class="container">
					<h2 class="section-heading">For Buyers</h2>
					
					<div class="safety-grid">
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-user-check"></i>
							</div>
							<h3>Verify Sellers</h3>
							<p>Always check the seller's profile, ratings, and review history before making a purchase. Verified sellers have a badge on their profile.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-lock"></i>
							</div>
							<h3>Use Secure Payments</h3>
							<p>Always make payments through our secure platform. Never transfer money directly to a seller's personal account.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-comment-dots"></i>
							</div>
							<h3>Communicate on Platform</h3>
							<p>Keep all communication within Nexus. This helps us protect you and maintain records if issues arise.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-file-alt"></i>
							</div>
							<h3>Read Listings Carefully</h3>
							<p>Review product descriptions, photos, and terms thoroughly before committing to a purchase.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-flag"></i>
							</div>
							<h3>Report Suspicious Activity</h3>
							<p>If something feels wrong, report the user or listing immediately. Our team investigates all reports.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-camera"></i>
							</div>
							<h3>Document Everything</h3>
							<p>Take screenshots of listings and conversations. Save receipts and tracking numbers for your records.</p>
						</article>
					</div>
				</div>
			</section>

			<section class="safety-section alt-bg">
				<div class="container">
					<h2 class="section-heading">For Sellers</h2>
					
					<div class="safety-grid">
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-id-card"></i>
							</div>
							<h3>Complete Verification</h3>
							<p>Get verified to build trust with buyers. Verified sellers get more views and higher conversion rates.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-camera-retro"></i>
							</div>
							<h3>Use Clear Photos</h3>
							<p>Take honest, high-quality photos of your items from multiple angles. Show any defects clearly.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-tags"></i>
							</div>
							<h3>Be Transparent</h3>
							<p>Provide accurate descriptions including condition, brand, size, and any flaws. Honesty prevents disputes.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-truck"></i>
							</div>
							<h3>Use Tracked Shipping</h3>
							<p>Always use tracked shipping services. Share tracking numbers with buyers immediately after dispatch.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-clock"></i>
							</div>
							<h3>Respond Promptly</h3>
							<p>Quick responses to buyer questions build trust and lead to more sales. Aim to reply within 24 hours.</p>
						</article>
						
						<article class="safety-card">
							<div class="safety-icon">
								<i class="fas fa-star"></i>
							</div>
							<h3>Build Your Reputation</h3>
							<p>Encourage buyers to leave reviews. A strong rating history attracts more customers.</p>
						</article>
					</div>
				</div>
			</section>

			<section class="red-flags-section">
				<div class="container">
					<h2 class="section-heading">Watch Out for These Red Flags</h2>
					
					<div class="red-flags-grid">
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>Too Good To Be True</h3>
							<p>Prices significantly lower than market value often indicate scams.</p>
						</article>
						
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>Pressure to Act Fast</h3>
							<p>Scammers create urgency to prevent you from thinking clearly.</p>
						</article>
						
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>Requests to Move Off-Platform</h3>
							<p>Legitimate users are happy to communicate within Nexus.</p>
						</article>
						
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>Unusual Payment Requests</h3>
							<p>Never send money via gift cards, wire transfers, or cryptocurrency.</p>
						</article>
						
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>Vague or Missing Photos</h3>
							<p>Stock photos or blurry images may hide the item's true condition.</p>
						</article>
						
						<article class="red-flag-card">
							<i class="fas fa-exclamation-triangle"></i>
							<h3>New Account with No History</h3>
							<p>Be extra cautious with brand new accounts that have no reviews.</p>
						</article>
					</div>
				</div>
			</section>

			<section class="what-to-do-section">
				<div class="container">
					<div class="what-to-do-content">
						<h2>What To Do If Something Goes Wrong</h2>
						<div class="steps-container">
							<div class="step">
								<div class="step-number" aria-hidden="true">1</div>
								<div class="step-content">
									<h3>Contact the User</h3>
									<p>Start by messaging the buyer or seller directly to resolve the issue amicably.</p>
								</div>
							</div>
							
							<div class="step">
								<div class="step-number" aria-hidden="true">2</div>
								<div class="step-content">
									<h3>Open a Dispute</h3>
									<p>If unresolved, open a dispute through our Resolution Center within 14 days.</p>
								</div>
							</div>
							
							<div class="step">
								<div class="step-number" aria-hidden="true">3</div>
								<div class="step-content">
									<h3>Report to Nexus</h3>
									<p>Report suspicious users or listings using the report button on their profile.</p>
								</div>
							</div>
							
							<div class="step">
								<div class="step-number" aria-hidden="true">4</div>
								<div class="step-content">
									<h3>Contact Authorities</h3>
									<p>For serious offenses like fraud, report to your local police station.</p>
								</div>
							</div>
						</div>
					</div>
				</div>
			</section>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
	</body>
</html>