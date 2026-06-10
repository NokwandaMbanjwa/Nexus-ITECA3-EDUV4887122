<?php
require_once 'config.php';

$user_type = getUserType();

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$is_seller = ($user_type === 'seller');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Certifications</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>	
			.certification-page {
				padding-top: 70px;
				padding-bottom: 80px;
				width: 100%;
				max-width: 100%;
				overflow-x: hidden;
			}

			.certification-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
				width: 100%;
				box-sizing: border-box;
			}

			.section {
				margin-bottom: 48px;
				width: 100%;
				max-width: 100%;
			}
			
			.section-heading {
				font-size: 22px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 20px;
			}
			
			.benefits-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 20px;
				width: 100%;
			}

			.benefit-card {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 20px;
				padding: 24px;
				transition: all 0.3s;
				text-align: center;
				width: 100%;
				box-sizing: border-box;
			}

			.benefit-card:hover {
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.benefit-icon {
				font-size: 36px;
				color: #8ff5ff;
				margin-bottom: 16px;
			}

			.benefit-card h3 {
				font-size: 18px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 8px;
			}

			.benefit-card p {
				color: #e5e5e5;
				font-size: 14px;
				line-height: 1.6;
			}

			.ways-list {
				display: flex;
				flex-direction: column;
				gap: 16px;
				width: 100%;
			}

			.way-item {
				display: flex;
				align-items: flex-start;
				gap: 16px;
				padding: 16px;
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 20px;
				transition: all 0.3s;
				width: 100%;
				box-sizing: border-box;
			}

			.way-item:hover {
				transform: translateY(-3px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.way-number {
				width: 36px;
				height: 36px;
				min-width: 36px;
				background: rgba(143, 245, 255, 0.1);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 16px;
				font-weight: 600;
				color: #8ff5ff;
				flex-shrink: 0;
			}

			.way-content {
				flex: 1;
				min-width: 0;
			}

			.way-content h3 {
				font-size: 18px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 4px;
				word-wrap: break-word;
			}

			.way-content p {
				color: #e5e5e5;
				font-size: 14px;
				line-height: 1.6;
				word-wrap: break-word;
			}

			.category-grid {
				display: grid;
				grid-template-columns: repeat(4, 1fr);
				gap: 12px;
				width: 100%;
			}

			.category-link {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 12px;
				padding: 14px 12px;
				text-align: center;
				text-decoration: none;
				transition: all 0.3s;
				width: 100%;
				box-sizing: border-box;
				overflow: hidden;
			}

			.category-link:hover {
				transform: translateY(-3px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.category-link i {
				font-size: 24px;
				color: #8ff5ff;
				margin-bottom: 8px;
				display: block;
			}

			.category-link span {
				font-size: 13px;
				color: #e5e5e5;
				word-wrap: break-word;
			}

			.category-link:hover span {
				color: #8ff5ff;
			}

			.external-item {
				display: flex;
				align-items: flex-start;
				gap: 16px;
				padding: 16px;
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 20px;
				transition: all 0.3s;
				width: 100%;
				box-sizing: border-box;
			}

			.external-item:hover {
				transform: translateY(-3px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.external-badge {
				width: 40px;
				height: 40px;
				min-width: 40px;
				background: rgba(143, 245, 255, 0.1);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 18px;
				color: #8ff5ff;
				flex-shrink: 0;
			}

			.external-content {
				flex: 1;
				min-width: 0;
			}

			.external-content h3 {
				font-size: 18px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 4px;
				word-wrap: break-word;
			}

			.external-content p {
				color: #e5e5e5;
				font-size: 14px;
				line-height: 1.6;
				margin-bottom: 8px;
				word-wrap: break-word;
			}

			.external-content a {
				color: #8ff5ff;
				font-size: 13px;
				text-decoration: none;
				word-wrap: break-word;
			}

			.external-content a:hover {
				text-decoration: underline;
			}

			.nexus-pro-section {
				background: linear-gradient(135deg, #131315, #1a1a1f);
				border-radius: 20px;
				padding: 48px;
				text-align: center;
				margin-top: 48px;
				border: 1px solid rgba(143, 245, 255, 0.1);
				width: 100%;
				box-sizing: border-box;
			}

			.pro-icon {
				font-size: 64px;
				color: #cea2fd;
				margin-bottom: 20px;
			}

			.pro-highlight {
				color: #7b00ff;
				font-weight: 600;
			}

			.nexus-pro-section h3 {
				font-size: 28px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 16px;
				word-wrap: break-word;
			}

			.nexus-pro-section p {
				color: #e5e5e5;
				font-size: 15px;
				line-height: 1.6;
				max-width: 700px;
				margin: 0 auto 24px;
				word-wrap: break-word;
			}

			.pro-features {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
				gap: 24px;
				margin-bottom: 32px;
				width: 100%;
			}

			.pro-feature {
				display: flex;
				align-items: center;
				gap: 8px;
				font-size: 13px;
				color: #e5e5e5;
			}

			.pro-feature i {
				color: #cea2fd;
				font-size: 14px;
				flex-shrink: 0;
			}

			.pro-feature span {
				word-wrap: break-word;
			}

			.apply-pro-btn {
				display: inline-block;
				background: #cea2fd;
				color: #0e0e10;
				padding: 14px 32px;
				border-radius: 40px;
				text-decoration: none;
				font-weight: 600;
				transition: all 0.3s;
				white-space: nowrap;
			}

			.apply-pro-btn:hover {
				background: #b880f0;
				transform: translateY(-2px);
			}

			.not-seller-message {
				background: rgba(143, 245, 255, 0.1);
				border: 1px solid #8ff5ff;
				border-radius: 12px;
				padding: 20px;
				text-align: center;
				margin-bottom: 30px;
				width: 100%;
				box-sizing: border-box;
				word-wrap: break-word;
			}

			.not-seller-message p {
				margin-bottom: 16px;
			}

			.become-seller-btn {
				display: inline-block;
				background: #8ff5ff;
				color: #0e0e10;
				padding: 10px 24px;
				border-radius: 8px;
				text-decoration: none;
				font-weight: 600;
				white-space: nowrap;
			}

			@media (max-width: 900px) {
				.benefits-grid {
					grid-template-columns: repeat(2, 1fr);
				}
				.category-grid {
					grid-template-columns: repeat(3, 1fr);
				}
				.nexus-pro-section {
					padding: 36px 28px;
				}
				.nexus-pro-section h3 {
					font-size: 24px;
				}
			}

			@media (max-width: 768px) {
				.section-hero {
					display: none;
				}
				
				.certification-page {
					padding-top: 50px;
					padding-bottom: 60px;
				}
				
				.certification-container {
					padding: 0 16px;
				}
				
				.section {
					margin-bottom: 40px;
				}
				
				.section-heading {
					font-size: 20px;
					margin-bottom: 16px;
				}
				
				.benefits-grid {
					grid-template-columns: 1fr;
					gap: 12px;
				}
				
				.benefit-card {
					display: flex;
					align-items: center;
					gap: 16px;
					padding: 16px;
					text-align: left;
					border-radius: 12px;
				}
				
				.benefit-icon {
					font-size: 28px;
					margin-bottom: 0;
					flex-shrink: 0;
					width: 40px;
					text-align: center;
				}
				
				.benefit-card h3 {
					font-size: 16px;
					margin-bottom: 4px;
				}
				
				.benefit-card p {
					font-size: 13px;
					line-height: 1.4;
				}
				
				.category-grid {
					grid-template-columns: repeat(2, 1fr);
					gap: 10px;
				}
				
				.category-link {
					padding: 16px 12px;
					border-radius: 12px;
					display: flex;
					align-items: center;
					gap: 12px;
					text-align: left;
				}
				
				.category-link i {
					font-size: 22px;
					margin-bottom: 0;
					flex-shrink: 0;
				}
				
				.category-link span {
					font-size: 14px;
				}

				.way-item {
					padding: 14px;
					border-radius: 12px;
					gap: 12px;
				}
				
				.way-number {
					width: 32px;
					height: 32px;
					min-width: 32px;
					font-size: 14px;
				}
				
				.way-content h3 {
					font-size: 16px;
				}
				
				.way-content p {
					font-size: 13px;
				}

				.external-item {
					padding: 14px;
					border-radius: 12px;
					gap: 12px;
				}
				
				.external-badge {
					width: 36px;
					height: 36px;
					min-width: 36px;
					font-size: 16px;
				}
				
				.external-content h3 {
					font-size: 16px;
				}
				
				.external-content p {
					font-size: 13px;
				}

				.nexus-pro-section {
					padding: 32px 20px;
					margin-top: 40px;
					border-radius: 16px;
				}
				
				.pro-icon {
					font-size: 48px;
					margin-bottom: 16px;
				}
				
				.nexus-pro-section h3 {
					font-size: 20px;
					margin-bottom: 12px;
					line-height: 1.4;
				}
				
				.nexus-pro-section p {
					font-size: 14px;
					line-height: 1.5;
					margin-bottom: 20px;
					padding: 0;
				}
				
				.pro-features {
					flex-direction: column;
					align-items: flex-start;
					gap: 14px;
					margin-bottom: 24px;
				}
				
				.pro-feature {
					width: 100%;
					font-size: 14px;
					gap: 12px;
				}
				
				.pro-feature i {
					font-size: 16px;
				}
				
				.apply-pro-btn {
					padding: 14px 28px;
					font-size: 15px;
					width: auto;
					max-width: 100%;
				}
			}

			@media (max-width: 480px) {
				.certification-page {
					padding-top: 50px;
					padding-bottom: 40px;
				}
				
				.certification-container {
					padding: 0 12px;
				}
				
				.section {
					margin-bottom: 32px;
				}
				
				.section-heading {
					font-size: 18px;
				}
				
				.benefit-card {
					padding: 14px;
					gap: 12px;
				}
				
				.benefit-icon {
					font-size: 24px;
					width: 32px;
				}
				
				.benefit-card h3 {
					font-size: 15px;
				}
				
				.benefit-card p {
					font-size: 12px;
				}
				
				.category-grid {
					gap: 8px;
				}
				
				.category-link {
					padding: 14px 10px;
					gap: 10px;
				}
				
				.category-link i {
					font-size: 20px;
				}
				
				.category-link span {
					font-size: 12px;
				}

				.way-item {
					padding: 12px;
					gap: 10px;
				}
				
				.way-number {
					width: 28px;
					height: 28px;
					min-width: 28px;
					font-size: 13px;
				}
				
				.way-content h3 {
					font-size: 15px;
				}
				
				.way-content p {
					font-size: 12px;
				}
				
				.external-item {
					padding: 12px;
					gap: 10px;
				}
				
				.external-badge {
					width: 32px;
					height: 32px;
					min-width: 32px;
					font-size: 14px;
				}
				
				.external-content h3 {
					font-size: 15px;
				}
				
				.external-content p {
					font-size: 12px;
				}
				
				.external-content a {
					font-size: 12px;
				}

				.nexus-pro-section {
					padding: 24px 16px;
					border-radius: 12px;
				}
				
				.pro-icon {
					font-size: 40px;
					margin-bottom: 12px;
				}
				
				.nexus-pro-section h3 {
					font-size: 18px;
					margin-bottom: 10px;
				}
				
				.nexus-pro-section p {
					font-size: 13px;
					margin-bottom: 18px;
				}
				
				.pro-features {
					gap: 12px;
					margin-bottom: 20px;
				}
				
				.pro-feature {
					font-size: 13px;
					gap: 10px;
				}
				
				.pro-feature i {
					font-size: 15px;
				}
				
				.apply-pro-btn {
					padding: 12px 24px;
					font-size: 14px;
				}
			}

			@media (max-width: 360px) {
				.certification-container {
					padding: 0 10px;
				}
				
				.benefit-card {
					padding: 12px;
					gap: 10px;
				}
				
				.benefit-icon {
					font-size: 22px;
					width: 28px;
				}
				
				.benefit-card h3 {
					font-size: 14px;
				}
				
				.benefit-card p {
					font-size: 11px;
				}
				
				.category-link {
					padding: 12px 8px;
				}
				
				.category-link i {
					font-size: 18px;
				}
				
				.category-link span {
					font-size: 11px;
				}
				
				.nexus-pro-section h3 {
					font-size: 16px;
				}
				
				.nexus-pro-section p {
					font-size: 12px;
				}
				
				.pro-feature {
					font-size: 12px;
				}
				
				.apply-pro-btn {
					font-size: 13px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<section class="section-hero">
			<div class="container">
				<h1 class="section-hero-title">Seller Certification</h1>
				<p class="section-hero-subtitle">Get certified to build trust and grow your business on Nexus</p>
			</div>
		</section>
		
		<main class="certification-page">
			<div class="certification-container">
				
				<?php if (!$is_seller): ?>
				<div class="not-seller-message">
					<p><i class="fas fa-store"></i> This page is for sellers who want to get certified and grow their business.</p>
					<a href="register.php?role=seller" class="become-seller-btn">Become a Seller</a>
				</div>
				<?php endif; ?>
				
				<div class="section">
					<h2 class="section-heading">Benefits of certification</h2>
					<div class="benefits-grid">
						<div class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-chart-line"></i></div>
							<div>
								<h3>Higher visibility</h3>
								<p>Certified sellers appear higher in search results and get featured placements.</p>
							</div>
						</div>
						<div class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-hand-holding-heart"></i></div>
							<div>
								<h3>Increased trust</h3>
								<p>Buyers are 3x more likely to purchase from certified sellers.</p>
							</div>
						</div>
						<div class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-tag"></i></div>
							<div>
								<h3>Higher selling prices</h3>
								<p>Certified items typically sell for 15-25% more than non-certified.</p>
							</div>
						</div>
					</div>
				</div>

				<div class="section">
					<h2 class="section-heading">Ways to get certified</h2>
					<div class="ways-list">
						<div class="way-item">
							<div class="way-number">1</div>
							<div class="way-content">
								<h3>Complete seller verification</h3>
								<p>Verify your identity and business information. This is the first step to becoming a certified seller.</p>
							</div>
						</div>
						<div class="way-item">
							<div class="way-number">2</div>
							<div class="way-content">
								<h3>Submit product samples for review</h3>
								<p>For certain categories (e.g., electronics, fashion), submit samples for quality inspection.</p>
							</div>
						</div>
						<div class="way-item">
							<div class="way-number">3</div>
							<div class="way-content">
								<h3>Complete category-specific training</h3>
								<p>Take online courses and pass assessments for your product categories.</p>
							</div>
						</div>
						<div class="way-item">
							<div class="way-number">4</div>
							<div class="way-content">
								<h3>Maintain high seller rating</h3>
								<p>Keep your seller rating above 4.5 stars with at least 50 completed sales.</p>
							</div>
						</div>
						<div class="way-item">
							<div class="way-number">5</div>
							<div class="way-content">
								<h3>Partner with recognized certification bodies</h3>
								<p>Work with external organizations that certify specific product categories.</p>
							</div>
						</div>
					</div>
				</div>

				<div class="section">
					<h2 class="section-heading">Certification resources by category</h2>
					<div class="category-grid">
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Baby & Toddler')"><i class="fas fa-baby-carriage"></i><span>Baby & Toddler</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Beauty')"><i class="fas fa-spa"></i><span>Beauty</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Books')"><i class="fas fa-book"></i><span>Books</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Electronics')"><i class="fas fa-microchip"></i><span>Electronics</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Entertainment')"><i class="fas fa-film"></i><span>Entertainment</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Fashion')"><i class="fas fa-tshirt"></i><span>Fashion</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Gaming')"><i class="fas fa-gamepad"></i><span>Gaming</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Home & Living')"><i class="fas fa-home"></i><span>Home & Living</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Office')"><i class="fas fa-briefcase"></i><span>Office</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Pets')"><i class="fas fa-dog"></i><span>Pets</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Sport')"><i class="fas fa-futbol"></i><span>Sport</span></a>
						<a href="#" class="category-link" onclick="showCertificationInfo(event, 'Other')"><i class="fas fa-ellipsis-h"></i><span>Other</span></a>
					</div>
				</div>

				<div class="section">
					<h2 class="section-heading">External certification bodies</h2>
					<div class="ways-list">
						<div class="external-item">
							<div class="external-badge"><i class="fas fa-leaf"></i></div>
							<div class="external-content">
								<h3>SABS (South African Bureau of Standards)</h3>
								<p>National standards body for product quality and safety certification.</p>
								<a href="https://www.sabs.co.za" target="_blank">Learn more →</a>
							</div>
						</div>
						<div class="external-item">
							<div class="external-badge"><i class="fas fa-certificate"></i></div>
							<div class="external-content">
								<h3>NRCS (National Regulator for Compulsory Specifications)</h3>
								<p>Regulatory body for compulsory product specifications in South Africa.</p>
								<a href="https://www.nrcs.org.za" target="_blank">Learn more →</a>
							</div>
						</div>
						<div class="external-item">
							<div class="external-badge"><i class="fas fa-globe"></i></div>
							<div class="external-content">
								<h3>ISO Certification</h3>
								<p>International standards for quality management and product safety.</p>
								<a href="https://www.iso.org" target="_blank">Learn more →</a>
							</div>
						</div>
					</div>
				</div>

				<div class="nexus-pro-section">
					<div class="pro-icon">
						<i class="fas fa-rocket"></i>
					</div>
					<h3>Take your business to the next level with <span class="pro-highlight">NEXUS Pro</span></h3>
					<p>Once you have your certifications and are ready to start an independent business, NEXUS Pro helps you create a standalone website for your brand. Your products will still be advertised on the NEXUS marketplace, giving you the best of both worlds — your own storefront plus access to our community of buyers.</p>
					<div class="pro-features">
						<div class="pro-feature"><i class="fas fa-check-circle"></i><span>Your own custom website domain</span></div>
						<div class="pro-feature"><i class="fas fa-check-circle"></i><span>Products automatically listed on Nexus</span></div>
						<div class="pro-feature"><i class="fas fa-check-circle"></i><span>Dedicated customer support</span></div>
						<div class="pro-feature"><i class="fas fa-check-circle"></i><span>Analytics and sales insights</span></div>
					</div>
					<a href="https://forms.gle/E7U2h2VXVrBEZKku6" target="_blank" class="apply-pro-btn">Apply for NEXUS Pro</a>
				</div>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type="text/javascript" src="utilities.js"></script>
		<script>
			function showCertificationInfo(event, category) {
				event.preventDefault();
				
				let message = '';
				
				switch(category) {
					case 'Baby & Toddler':
						message = `To sell ${category} products on Nexus, you need:\n\n• SABS safety certification\n• ISO 8124 (toy safety) compliance\n• CE marking for EU standards\n• Product batch testing certificates`;
						break;
					case 'Beauty':
						message = `To sell ${category} products on Nexus, you need:\n\n• SAHPRA registration for cosmetics\n• ISO 22716 (Good Manufacturing Practices)\n• Ingredient safety assessment reports\n• Product labeling compliance`;
						break;
					case 'Electronics':
						message = `To sell ${category} products on Nexus, you need:\n\n• NRCS safety approval\n• CE/FCC electromagnetic compliance\n• ICASA type approval\n• RoHS compliance for hazardous substances`;
						break;
					case 'Fashion':
						message = `To sell ${category} products on Nexus, you need:\n\n• SABS textile quality certification\n• OEKO-TEX Standard 100 (chemical safety)\n• Country of origin verification\n• Fabric composition testing`;
						break;
					case 'Books':
						message = `To sell ${category} products on Nexus, you need:\n\n• Valid ISBN registration\n• Publishing rights verification\n• Copyright clearance\n• No counterfeit copies`;
						break;
					case 'Gaming':
						message = `To sell ${category} products on Nexus, you need:\n\n• Age rating certification (PEGI/ESRB)\n• ICASA approval for wireless devices\n• Product safety testing\n• Anti-counterfeit verification`;
						break;
					case 'Home & Living':
						message = `To sell ${category} products on Nexus, you need:\n\n• SABS product safety certification\n• Flammability testing for textiles\n• Chemical safety compliance\n• Load-bearing certification for furniture`;
						break;
					case 'Pets':
						message = `To sell ${category} products on Nexus, you need:\n\n• Animal safety certification\n• Non-toxic material verification\n• Veterinary health compliance (for pet food)\n• Product durability testing`;
						break;
					default:
						message = `To sell ${category} products on Nexus, please contact our certification team at certifications@nexus.com for specific requirements.`;
				}
				
				alert(message);
			}
		</script>
	</body>
</html>