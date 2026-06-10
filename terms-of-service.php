<?php
require_once 'config.php';

$page_title = "Terms of Service";
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Terms of Service</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.terms-content {
				padding: 60px 0 80px 0;
				background-color: #0e0e10;
			}

			.terms-wrapper {
				max-width: 900px;
				margin: 0 auto;
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 12px;
				padding: 48px;
			}

			.terms-section {
				margin-bottom: 40px;
				padding-bottom: 30px;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}

			.terms-section:last-of-type {
				border-bottom: none;
				margin-bottom: 0;
				padding-bottom: 0;
			}

			.terms-section h2 {
				font-size: 24px;
				color: #8ff5ff;
				margin-bottom: 16px;
			}

			.terms-section h3 {
				font-size: 18px;
				color: #c180ff;
				margin: 20px 0 12px 0;
			}

			.terms-section p {
				color: #adaaad;
				line-height: 1.8;
				margin-bottom: 16px;
				font-size: 15px;
			}

			.terms-section ul {
				margin: 16px 0;
				padding-left: 24px;
			}

			.terms-section li {
				color: #adaaad;
				line-height: 1.8;
				margin-bottom: 8px;
				font-size: 15px;
			}

			.terms-section a {
				color: #8ff5ff;
				text-decoration: none;
			}

			.terms-section a:hover {
				text-decoration: underline;
			}

			.terms-acceptance {
				margin-top: 40px;
				padding-top: 30px;
				border-top: 2px solid rgba(143, 245, 255, 0.3);
				text-align: center;
			}

			.terms-acceptance p {
				color: #e5e5e5;
				font-weight: 500;
				font-size: 16px;
			}

			.last-updated {
				display: inline-block;
				background: rgba(143, 245, 255, 0.1);
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				color: #8ff5ff;
				margin-top: 8px;
			}

			@media (max-width: 768px) {
				.terms-content {
					padding: 100px 0 60px;
				}
				.terms-wrapper {
					padding: 24px;
					margin: 0 20px;
				}
				.terms-section h2 {
					font-size: 20px;
				}
				.terms-section h3 {
					font-size: 16px;
				}
				.terms-section p,
				.terms-section li {
					font-size: 14px;
				}
				.terms-section ul {
					padding-left: 20px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="terms-page">
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">TERMS OF SERVICE</h1>
					<p class="section-hero-subtitle">Last updated: <?php echo date('F Y'); ?></p>
				</div>
			</section>
			
			<div class="terms-content">
				<div class="container">
					<div class="terms-wrapper">
						<section class="terms-section">
							<h2>1. Introduction</h2>
							<p>Welcome to Nexus. By accessing or using our marketplace platform, you agree to comply with and be bound by these Terms of Service. Please read them carefully.</p>
							<p>Nexus is a Consumer-to-Consumer (C2C) marketplace connecting buyers and sellers across South Africa. These terms govern your use of our website, services, and applications.</p>
						</section>
						
						<section class="terms-section">
							<h2>2. Eligibility</h2>
							<p>To use Nexus, you must:</p>
							<ul>
								<li>Be at least 16 years old</li>
								<li>Have the legal capacity to enter into binding contracts</li>
								<li>Provide accurate and complete registration information</li>
								<li>Not have previously been suspended or removed from our platform</li>
								<li>Comply with all applicable South African laws and regulations</li>
							</ul>
						</section>

						<section class="terms-section">
							<h2>3. Account Registration</h2>
							<p>When creating an account, you agree to:</p>
							<ul>
								<li>Provide truthful, accurate, and complete information</li>
								<li>Maintain and update your information as needed</li>
								<li>Keep your login credentials confidential and secure</li>
								<li>Notify us immediately of any unauthorized account access</li>
								<li>Take responsibility for all activities under your account</li>
							</ul>
							<p>We reserve the right to suspend or terminate accounts that violate these terms or contain false information.</p>
						</section>
						
						<section class="terms-section">
							<h2>4. Buying and Selling</h2>
							
							<h3>For Buyers:</h3>
							<ul>
								<li>You agree to pay the listed price plus any applicable shipping fees</li>
								<li>You are responsible for reviewing item descriptions and photos carefully</li>
								<li>Once an order is confirmed, you enter into a binding purchase agreement</li>
								<li>You may cancel an order within 1 hour of purchase if not yet shipped</li>
							</ul>
							
							<h3>For Sellers:</h3>
							<ul>
								<li>You agree to accurately describe your items including condition and defects</li>
								<li>You must ship items within the timeframe stated in your listing</li>
								<li>You are responsible for providing tracking information to buyers</li>
								<li>You must respond to buyer inquiries promptly and professionally</li>
								<li>A 10% fee will be deducted for every sale made throught the platform</li>
							</ul>
						</section>
						
						<section class="terms-section">
							<h2>5. Fees and Payments</h2>
							<p>Nexus operates on a commission-based fee structure:</p>
							<ul>
								<li>Listing items on Nexus is completely free</li>
								<li>When an item sells, we charge a 10% commission fee</li>
								<li>The remaining 90% is deposited into your Nexus wallet</li>
								<li>Withdrawals to your bank account may incur processing fees</li>
								<li>All fees are displayed clearly before you confirm a transaction</li>
							</ul>
							<p>We reserve the right to modify our fee structure with 30 days advance notice.</p>
						</section>
						
						<section class="terms-section">
							<h2>6. Prohibited Items</h2>
							<p>The following items are strictly prohibited on Nexus:</p>
							<ul>
								<li>Illegal drugs, paraphernalia, or controlled substances</li>
								<li>Weapons, firearms, ammunition, or explosive materials</li>
								<li>Stolen goods or items with removed serial numbers</li>
								<li>Counterfeit products or intellectual property infringements</li>
								<li>Hazardous materials or dangerous goods</li>
								<li>Live animals or endangered species products</li>
								<li>Adult content or sexually explicit materials</li>
								<li>Services involving illegal activities</li>
							</ul>
							<p>Violation of this policy will result in immediate account suspension and potential legal action.</p>
						</section>
						
						<section class="terms-section">
							<h2>7. User Conduct</h2>
							<p>You agree not to:</p>
							<ul>
								<li>Harass, abuse, or threaten other users</li>
								<li>Post false, misleading, or deceptive information</li>
								<li>Manipulate reviews, ratings, or feedback systems</li>
								<li>Attempt to complete transactions outside our platform</li>
								<li>Use automated systems to scrape or collect user data</li>
								<li>Interfere with the proper functioning of our services</li>
								<li>Violate any applicable laws or third-party rights</li>
							</ul>
						</section>
						
						<section class="terms-section">
							<h2>8. Dispute Resolution</h2>
							<p>If a dispute arises between buyers and sellers:</p>
							<ul>
								<li>Both parties should first attempt to resolve the issue directly</li>
								<li>If unresolved, open a dispute through our Resolution Center within 14 days</li>
								<li>Our team will review evidence and make a fair determination</li>
								<li>Decisions made by Nexus are final and binding</li>
								<li>We reserve the right to suspend accounts found violating policies</li>
							</ul>
						</section>
						
						<section class="terms-section">
							<h2>9. Account Termination</h2>
							<p>We may suspend or terminate your account for:</p>
							<ul>
								<li>Violation of these Terms of Service</li>
								<li>Fraudulent or illegal activities</li>
								<li>Repeated disputes or chargebacks</li>
								<li>Abusive behavior toward users or staff</li>
								<li>Inactivity for more than 24 months</li>
							</ul>
							<p>Upon termination, you lose access to your account and any pending funds may be forfeited if obtained through violation.</p>
						</section>
						
						<section class="terms-section">
							<h2>10. Limitation of Liability</h2>
							<p>To the maximum extent permitted by law, Nexus shall not be liable for:</p>
							<ul>
								<li>Indirect, incidental, or consequential damages</li>
								<li>Loss of profits, data, or business opportunities</li>
								<li>Transactions between users conducted off-platform</li>
								<li>Items that do not meet your expectations</li>
								<li>Delays caused by shipping carriers or force majeure</li>
							</ul>
							<p>Our total liability is limited to the fees paid by you in the 12 months preceding the claim.</p>
						</section>
						
						<section class="terms-section">
							<h2>11. Changes to These Terms</h2>
							<p>We may update these Terms of Service periodically. When changes are made:</p>
							<ul>
								<li>We will post the updated terms on this page</li>
								<li>The "Last updated" date will be revised</li>
								<li>Significant changes will be notified via email or site notification</li>
								<li>Continued use of our platform constitutes acceptance of new terms</li>
							</ul>
						</section>
						
						<section class="terms-section">
							<h2>12. Contact Us</h2>
							<p>If you have any questions about these Terms of Service, please contact us:</p>
							<ul>
								<li><strong>Email:</strong> legal@nexusmarketplace.co.za</li>
								<li><strong>Phone:</strong> +27 (0) 21 123 4567</li>
								<li>Through our <a href="contact-us.php">Contact Page</a></li>
							</ul>
						</section>
						
						<section class="terms-section">
							<h2>13. Governing Law</h2>
							<p>These Terms shall be governed by and construed in accordance with the laws of the Republic of South Africa. Any disputes arising under or in connection with these Terms shall be subject to the exclusive jurisdiction of the courts of South Africa.</p>
						</section>
						
						<div class="terms-acceptance">
							<p><i class="fas fa-check-circle" style="color: #8ff5ff;"></i> By using Nexus, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.</p>
							<p style="margin-top: 16px; font-size: 13px;">Last reviewed: <?php echo date('d F Y'); ?></p>
						</div>
					</div>
				</div>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
	</body>
</html>