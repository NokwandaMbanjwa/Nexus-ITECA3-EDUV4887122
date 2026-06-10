<?php
require_once 'config.php';

$page_title = "Privacy Policy";
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Privacy Policy</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.privacy-content {
				padding: 50px 0 80px 0;
				background-color: #0e0e10;
			}

			.privacy-wrapper {
				max-width: 900px;
				margin: 0 auto;
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 12px;
				padding: 48px;
			}

			.privacy-section {
				margin-bottom: 40px;
				padding-bottom: 30px;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}

			.privacy-section:last-of-type {
				border-bottom: none;
				margin-bottom: 0;
				padding-bottom: 0;
			}

			.privacy-section h2 {
				font-size: 24px;
				color: #8ff5ff;
				margin-bottom: 16px;
			}

			.privacy-section h3 {
				font-size: 18px;
				color: #c180ff;
				margin: 20px 0 12px 0;
			}

			.privacy-section p {
				color: #adaaad;
				line-height: 1.8;
				margin-bottom: 16px;
				font-size: 15px;
			}

			.privacy-section ul {
				margin: 16px 0;
				padding-left: 24px;
			}

			.privacy-section li {
				color: #adaaad;
				line-height: 1.8;
				margin-bottom: 8px;
				font-size: 15px;
			}

			.privacy-section a {
				color: #8ff5ff;
				text-decoration: none;
			}

			.privacy-section a:hover {
				text-decoration: underline;
			}

			.privacy-acceptance {
				margin-top: 40px;
				padding-top: 30px;
				border-top: 2px solid rgba(143, 245, 255, 0.3);
				text-align: center;
			}

			.privacy-acceptance p {
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
				.privacy-content {
					padding: 100px 0 60px;
				}
				.privacy-wrapper {
					padding: 24px;
					margin: 0 20px;
				}
				.privacy-section h2 {
					font-size: 20px;
				}
				.privacy-section h3 {
					font-size: 16px;
				}
				.privacy-section p,
				.privacy-section li {
					font-size: 14px;
				}
				.privacy-section ul {
					padding-left: 20px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main>
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">PRIVACY POLICY</h1>
					<p class="section-hero-subtitle">Last updated: <?php echo date('F Y'); ?></p>
				</div>
			</section>

			<div class="privacy-content">
				<div class="container">
					<div class="privacy-wrapper">
						<section class="privacy-section">
							<h2>1. Introduction</h2>
							<p>At Nexus, we take your privacy seriously. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our marketplace platform.</p>
							<p>We are committed to protecting your personal information and complying with the Protection of Personal Information Act (POPIA) of South Africa.</p>
						</section>

						<section class="privacy-section">
							<h2>2. Information We Collect</h2>
							
							<h3>Personal Information You Provide:</h3>
							<ul>
								<li>Full name and date of birth</li>
								<li>Email address and phone number</li>
								<li>Physical address for shipping and verification</li>
								<li>Payment information (processed securely by our partners)</li>
								<li>Government-issued ID for seller verification</li>
								<li>Profile photo and store information (if applicable)</li>
							</ul>
							
							<h3>Information Automatically Collected:</h3>
							<ul>
								<li>IP address and device information</li>
								<li>Browser type and operating system</li>
								<li>Pages visited and time spent on our platform</li>
								<li>Search queries and browsing history</li>
								<li>Location data (with your consent)</li>
							</ul>
						</section>

						<section class="privacy-section">
							<h2>3. How We Use Your Information</h2>
							<p>We use your information to:</p>
							<ul>
								<li>Create and manage your account</li>
								<li>Process transactions and verify identities</li>
								<li>Facilitate communication between buyers and sellers</li>
								<li>Improve and personalize your experience</li>
								<li>Send order confirmations and shipping updates</li>
								<li>Detect and prevent fraud or suspicious activity</li>
								<li>Comply with legal obligations</li>
								<li>Send marketing communications (with your consent)</li>
							</ul>
						</section>

						<section class="privacy-section">
							<h2>4. Legal Basis for Processing</h2>
							<p>Under South African law (POPIA), we process your information based on:</p>
							<ul>
								<li><strong>Contractual necessity:</strong> To provide our services to you</li>
								<li><strong>Legal obligation:</strong> To comply with applicable laws</li>
								<li><strong>Legitimate interests:</strong> To improve our platform and prevent fraud</li>
								<li><strong>Consent:</strong> For marketing and optional data collection</li>
							</ul>
						</section>

						<section class="privacy-section">
							<h2>5. Information Sharing</h2>
							<p>We may share your information with:</p>
							<ul>
								<li><strong>Other Users:</strong> Your public profile information is visible to buyers and sellers</li>
								<li><strong>Service Providers:</strong> Payment processors, shipping carriers, and verification services</li>
								<li><strong>Law Enforcement:</strong> When required by law or to protect our rights</li>
								<li><strong>Business Transfers:</strong> In the event of a merger or acquisition</li>
							</ul>
							<p>We never sell your personal information to third parties.</p>
						</section>

						<section class="privacy-section">
							<h2>6. Data Security</h2>
							<p>We implement industry-standard security measures to protect your information:</p>
							<ul>
								<li>SSL/TLS encryption for all data transmission</li>
								<li>Secure servers with firewall protection</li>
								<li>Regular security audits and vulnerability testing</li>
								<li>Access controls and employee confidentiality agreements</li>
								<li>Encrypted storage of sensitive information</li>
							</ul>
							<p>While we strive to protect your data, no method of transmission is 100% secure.</p>
						</section>

						<section class="privacy-section">
							<h2>7. Data Retention</h2>
							<p>We retain your information for as long as:</p>
							<ul>
								<li>Your account is active</li>
								<li>Necessary to provide our services</li>
								<li>Required by law (typically 5-7 years for financial records)</li>
								<li>To resolve disputes or enforce agreements</li>
							</ul>
							<p>After your account is closed, we retain certain information for legal and legitimate business purposes.</p>
						</section>

						<section class="privacy-section">
							<h2>8. Your Rights</h2>
							<p>Under POPIA, you have the right to:</p>
							<ul>
								<li>Access the personal information we hold about you</li>
								<li>Correct inaccurate or incomplete information</li>
								<li>Request deletion of your information (subject to legal requirements)</li>
								<li>Object to certain types of processing</li>
								<li>Withdraw consent for marketing communications</li>
								<li>Lodge a complaint with the Information Regulator</li>
							</ul>
							<p>To exercise any of these rights, please <a href="contact-us.php">contact us</a>.</p>
						</section>

						<section class="privacy-section">
							<h2>9. Cookies and Tracking</h2>
							<p>We use cookies and similar technologies to:</p>
							<ul>
								<li>Remember your preferences and login status</li>
								<li>Analyze platform usage and improve performance</li>
								<li>Personalize content and recommendations</li>
								<li>Prevent fraud and enhance security</li>
							</ul>
							<p>You can control cookie settings through your browser preferences.</p>
						</section>

						<section class="privacy-section">
							<h2>10. Third-Party Links</h2>
							<p>Our platform may contain links to third-party websites. We are not responsible for their privacy practices. We encourage you to read their privacy policies before providing any personal information.</p>
						</section>

						<section class="privacy-section">
							<h2>11. Children's Privacy</h2>
							<p>Nexus is not intended for users under 16 years of age. We do not knowingly collect personal information from minors. If we discover we have collected information from a minor, we will delete it immediately.</p>
						</section>
						
						<section class="privacy-section">
							<h2>12. Changes to This Policy</h2>
							<p>We may update this Privacy Policy periodically. When changes are made:</p>
							<ul>
								<li>We will post the updated policy on this page</li>
								<li>The "Last updated" date will be revised</li>
								<li>Significant changes will be notified via email or platform notification</li>
							</ul>
						</section>

						<section class="privacy-section">
							<h2>13. Contact Us</h2>
							<p>If you have questions about this Privacy Policy or your data rights, please contact us:</p>
							<ul>
								<li><strong>Email:</strong> <a href="mailto:privacy@nexusmarketplace.co.za">legal@nexusmarketplace.co.za</a></li>
								<li><strong>Phone:</strong> +27 21 234 5678</li>
								<li><strong>Address:</strong> 10 Tyrus Street, Glen Austin, Midrand, 1685</li>
							</ul>
							<p>You can also reach us through our <a href="contact-us.php">Contact Page</a>.</p>
						</section>

						<section class="privacy-section">
							<h2>14. Information Regulator</h2>
							<p>If you are not satisfied with our response, you have the right to lodge a complaint with the South African Information Regulator:</p>
							<ul>
								<li><strong>Website:</strong> <a href="https://www.justice.gov.za/inforeg" target="_blank">www.justice.gov.za/inforeg</a></li>
								<li><strong>Email:</strong> inforeg@justice.gov.za</li>
								<li><strong>Phone:</strong> +27 (0) 10 023 5200</li>
								<li><strong>Address:</strong> JD House, 27 Stiemens Street, Braamfontein, Johannesburg, 2001</li>
							</ul>
						</section>
						
						<div class="privacy-acceptance">
							<p><i class="fas fa-check-circle" style="color: #8ff5ff;"></i> By using Nexus, you consent to the collection and use of your information as described in this Privacy Policy.</p>
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