<?php
require_once 'config.php';

$error = '';
$success = '';

$user_id = getUserId();
$user_name = '';
$user_email = '';

if (isLoggedIn()) {
    $user_name = getUserName();
    $user_email = getUserEmail();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $subject = $_POST['subject'] ?? '';
    $message = trim($_POST['message'] ?? '');
    $privacy_consent = isset($_POST['privacy_consent']);
    
    $errors = [];
    
    if (empty($full_name)) {
        $errors[] = "Full name is required";
    } elseif (strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address";
    }
    
    if (empty($subject)) {
        $errors[] = "Please select a subject";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    } elseif (strlen($message) < 10) {
        $errors[] = "Message must be at least 10 characters";
    }
    
    if (!$privacy_consent) {
        $errors[] = "You must agree to the Privacy Policy";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO contact_messages (user_id, full_name, email, phone, subject, message, status) 
                                    VALUES (?, ?, ?, ?, ?, ?, 'unread')");
            $stmt->execute([$user_id ?: null, $full_name, $email, $phone, $subject, $message]);
            
            $success = "Thank you for contacting us. We will respond within 24-48 hours.";
            
            echo "<script>
                setTimeout(function() {
                    document.getElementById('contactForm').reset();
                }, 100);
            </script>";
            
        } catch (PDOException $e) {
            $error = "Failed to send message. Please try again later.";
            error_log("Contact form error: " . $e->getMessage());
        }
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Contact Us</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>	
			.contact-form-section {
				padding: 60px 0 80px 0;
				background-color: #0e0e10;
				width: 100%;
				max-width: 100%;
				overflow-x: hidden;
			}

			.contact-wrapper {
				display: grid;
				grid-template-columns: 1fr 1.5fr;
				gap: 50px;
				max-width: 1100px;
				margin: 0 auto;
				width: 100%;
				box-sizing: border-box;
			}

			.contact-info {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 24px;
				padding: 32px;
				height: fit-content;
				width: 100%;
				box-sizing: border-box;
			}

			.contact-info h2 {
				font-size: 28px;
				color: #f9f5f8;
				margin-bottom: 16px;
				word-wrap: break-word;
			}

			.contact-info > p {
				color: #adaaad;
				line-height: 1.6;
				margin-bottom: 32px;
				word-wrap: break-word;
			}

			.info-details {
				margin-bottom: 32px;
			}

			.info-item {
				display: flex;
				gap: 16px;
				margin-bottom: 24px;
			}

			.info-item i {
				width: 40px;
				height: 40px;
				min-width: 40px;
				background: #0e0e10;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #8ff5ff;
				font-size: 18px;
			}

			.info-item h3 {
				font-size: 16px;
				color: #f9f5f8;
				margin-bottom: 6px;
			}

			.info-item p {
				color: #adaaad;
				font-size: 14px;
				line-height: 1.5;
				word-wrap: break-word;
			}

			.social-connect h3 {
				font-size: 16px;
				color: #f9f5f8;
				margin-bottom: 16px;
			}

			.social-icons {
				display: flex;
				gap: 12px;
				flex-wrap: wrap;
			}

			.social-link {
				width: 40px;
				height: 40px;
				background-color: #0e0e10;
				border: 1px solid rgba(118, 117, 119, 0.3);
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #adaaad;
				text-decoration: none;
				transition: all 0.3s;
			}

			.social-link:hover {
				background-color: #8ff5ff;
				border-color: #8ff5ff;
				color: #0e0e10;
				transform: translateY(-2px);
			}

			.contact-form-container {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 24px;
				padding: 32px;
				width: 100%;
				box-sizing: border-box;
			}

			.contact-form-container h2 {
				font-size: 28px;
				color: #f9f5f8;
				margin-bottom: 24px;
				word-wrap: break-word;
			}

			.mobile-heading,
			.form-subtitle,
			.mobile-contact-info {
				display: none;
			}

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 20px;
				word-wrap: break-word;
			}

			.alert-error {
				background: rgba(255, 107, 107, 0.1);
				border: 1px solid #ff6b6b;
				color: #ff6b6b;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}

			.btn-primary {
				background: #8ff5ff;
				width: 100%;
				padding: 14px 32px;
				border-radius: 12px;
				border: none;
				font-size: 16px;
				font-weight: bold;
				cursor: pointer;
				transition: all 0.3s;
				color: #0e0e10;
			}

			.btn-primary:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}

			.form-note {
				text-align: center;
				margin-top: 16px;
			}

			.form-note p {
				color: #9ca3af;
				font-size: 12px;
			}

			.form-group {
				margin-bottom: 20px;
				width: 100%;
			}
			
			.form-group label {
				display: block;
				color: #e5e5e5;
				font-size: 14px;
				margin-bottom: 8px;
				font-weight: 500;
			}
			
			.form-group input,
			.form-group select,
			.form-group textarea {
				width: 100%;
				padding: 14px 16px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 10px;
				color: #e5e5e5;
				font-size: 14px;
				font-family: 'Inter', sans-serif;
				transition: border-color 0.2s;
				box-sizing: border-box;
			}
			
			.form-group input:focus,
			.form-group select:focus,
			.form-group textarea:focus {
				outline: none;
				border-color: #8ff5ff;
			}
			
			.form-group textarea {
				resize: vertical;
				min-height: 120px;
			}
			
			.form-group select {
				appearance: none;
				-webkit-appearance: none;
				background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%238ff5ff' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
				background-repeat: no-repeat;
				background-position: right 16px center;
				padding-right: 40px;
				cursor: pointer;
			}
			
			.form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 16px;
			}
			
			.required {
				color: #ff6b6b;
			}
			
			.checkbox-group {
				margin-bottom: 24px;
			}
			
			.checkbox-label {
				display: flex;
				align-items: flex-start;
				gap: 10px;
				font-size: 13px;
				color: #adaaad;
				cursor: pointer;
				line-height: 1.5;
			}
			
			.checkbox-label input[type="checkbox"] {
				width: 18px;
				height: 18px;
				min-width: 18px;
				accent-color: #8ff5ff;
				cursor: pointer;
				margin-top: 2px;
			}
			
			.checkbox-label a {
				color: #8ff5ff;
				text-decoration: none;
			}
			
			.checkbox-label a:hover {
				text-decoration: underline;
			}
			
			.error-message {
				color: #ff6b6b;
				font-size: 12px;
				margin-top: 5px;
				display: block;
			}

			@media (max-width: 1024px) {
				.contact-wrapper {
					grid-template-columns: 1fr;
					gap: 30px;
					padding: 0 20px;
				}
			}

			@media (max-width: 768px) {
				.section-hero {
					display: none;
				}
				
				.contact-info {
					display: none;
				}
				
				.contact-form-section {
					padding: 40px 0 60px 0;
				}
				
				.contact-wrapper {
					padding: 0 16px;
					gap: 0;
				}
				.contact-form-container {
					padding: 28px 20px;
					border-radius: 2px;
				}
				
				.contact-form-container h2 {
					font-size: 24px;
					margin-bottom: 8px;
					color: #8ff5ff;
				}
				
				.form-subtitle {
					display: block;
					color: #adaaad;
					font-size: 14px;
					line-height: 1.6;
					margin-bottom: 28px;
				}
				
				.mobile-contact-info {
					display: flex;
					flex-direction: column;
					gap: 16px;
					margin-bottom: 28px;
					padding-bottom: 24px;
					border-bottom: 1px solid rgba(118, 117, 119, 0.15);
				}
				
				.mobile-contact-info .info-item {
					display: flex;
					gap: 12px;
					align-items: flex-start;
					margin-bottom: 0;
				}
				
				.mobile-contact-info .info-item i {
					width: 36px;
					height: 36px;
					min-width: 36px;
					background: #0e0e10;
					border-radius: 50%;
					display: flex;
					align-items: center;
					justify-content: center;
					color: #8ff5ff;
					font-size: 16px;
				}
				
				.mobile-contact-info .info-item h3 {
					font-size: 14px;
					color: #f9f5f8;
					margin-bottom: 4px;
				}
				
				.mobile-contact-info .info-item p {
					color: #adaaad;
					font-size: 12px;
					line-height: 1.5;
					margin: 0;
				}

				.form-row {
					grid-template-columns: 1fr;
					gap: 0;
				}
				
				.btn-primary {
					padding: 16px;
					font-size: 15px;
				}
				
				.form-group input,
				.form-group select,
				.form-group textarea {
					padding: 12px 14px;
					font-size: 13px;
				}
				
				.form-group label {
					font-size: 13px;
				}
			}

			@media (max-width: 480px) {
				.contact-form-section {
					padding: 40px 0 40px 0;
				}
				
				.contact-wrapper {
					padding: 0 12px;
				}
				
				.contact-form-container {
					padding: 24px 16px;
					border-radius: 2px;
				}
				
				.contact-form-container h2 {
					font-size: 20px;
				}
				
				.form-subtitle {
					font-size: 13px;
					margin-bottom: 24px;
				}
				
				.mobile-contact-info {
					gap: 14px;
					margin-bottom: 24px;
					padding-bottom: 20px;
				}
				
				.mobile-contact-info .info-item i {
					width: 32px;
					height: 32px;
					min-width: 32px;
					font-size: 14px;
				}
				
				.mobile-contact-info .info-item h3 {
					font-size: 13px;
				}
				
				.mobile-contact-info .info-item p {
					font-size: 11px;
				}
				
				.checkbox-label {
					font-size: 12px !important;
				}
				
				.btn-primary {
					padding: 14px;
					font-size: 14px;
				}
				
				.form-note p {
					font-size: 11px;
				}
			}

			@media (max-width: 360px) {
				.contact-wrapper {
					padding: 0 10px;
				}
				
				.contact-form-container {
					padding: 20px 14px;
				}
				
				.contact-form-container h2 {
					font-size: 18px;
				}
				
				.form-subtitle {
					font-size: 12px;
					margin-bottom: 20px;
				}
				
				.mobile-contact-info .info-item i {
					width: 28px;
					height: 28px;
					min-width: 28px;
					font-size: 13px;
				}
				
				.mobile-contact-info .info-item h3 {
					font-size: 12px;
				}
				
				.mobile-contact-info .info-item p {
					font-size: 10px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main>
			<section class="section-hero">
				<div class="container">
					<h1 class="section-hero-title">CONTACT US</h1>
					<p class="section-hero-subtitle">We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
				</div>
			</section>
			
			<section class="contact-form-section">
				<div class="container">
					<div class="contact-wrapper">
						<aside class="contact-info">
							<h2>Get in Touch</h2>
							<p>Have questions, feedback, or need assistance? Fill out the form and our support team will get back to you within 24-48 hours.</p>
							
							<div class="info-details">
								<div class="info-item">
									<i class="fas fa-envelope"></i>
									<div>
										<h3>Email Us</h3>
										<p>safetyandsupport@nexusmarketplace.co.za</p>
										<p>legal@nexusmarketplace.co.za</p>
									</div>
								</div>
								
								<div class="info-item">
									<i class="fas fa-phone-alt"></i>
									<div>
										<h3>Call Us</h3>
										<p>+27 (0) 21 123 4567</p>
										<p>Mon-Fri, 9am - 5pm SAST</p>
									</div>
								</div>
								
								<div class="info-item">
									<i class="fas fa-map-marker-alt"></i>
									<div>
										<h3>Visit Us</h3>
										<p>16 Tyrus Str, Midrand</p>
										<p>1685, South Africa</p>
									</div>
								</div>
							</div>
							
							<div class="social-connect">
								<h3>Connect With Us</h3>
								<div class="social-icons">
									<a href="#" class="social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
									<a href="#" class="social-link" aria-label="Twitter"><i class="fab fa-twitter"></i></a>
									<a href="#" class="social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
									<a href="#" class="social-link" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
								</div>
							</div>
						</aside>
						
						<div class="contact-form-container">
							<h2>Send Us a Message</h2>
							<p class="form-subtitle">We'd love to hear from you!</p>

							<div class="mobile-contact-info">
								<div class="info-item">
									<i class="fas fa-envelope"></i>
									<div>
										<h3>Email Us</h3>
										<p>safetyandsupport@nexusmarketplace.co.za</p>
									</div>
								</div>
								
								<div class="info-item">
									<i class="fas fa-phone-alt"></i>
									<div>
										<h3>Call Us</h3>
										<p>+27 (0) 21 123 4567</p>
									</div>
								</div>
								
								<div class="info-item">
									<i class="fas fa-map-marker-alt"></i>
									<div>
										<h3>Visit Us</h3>
										<p>10 Tyrus Str, Midrand</p>
									</div>
								</div>
							</div>
							
							<?php if ($error): ?>
								<div class="alert alert-error"><?php echo $error; ?></div>
							<?php endif; ?>
							
							<?php if ($success): ?>
								<div class="alert alert-success"><?php echo $success; ?></div>
							<?php endif; ?>
							
							<form id="contactForm" class="contact-form" method="post" action="">
								<div class="form-row">
									<div class="form-group">
										<label for="full_name">Full Name <span class="required">*</span></label>
										<input type="text" id="full_name" name="full_name" placeholder="Enter your full name" value="<?php echo htmlspecialchars($user_name); ?>" required>
									</div>
									
									<div class="form-group">
										<label for="email">Email Address <span class="required">*</span></label>
										<input type="email" id="email" name="email" placeholder="you@example.com" value="<?php echo htmlspecialchars($user_email); ?>" required>
									</div>
								</div>
								
								<div class="form-row">
									<div class="form-group">
										<label for="phone">Phone Number</label>
										<input type="tel" id="phone" name="phone" placeholder="+27 XX XXX XXXX">
									</div>
									
									<div class="form-group">
										<label for="subject">Subject <span class="required">*</span></label>
										<select id="subject" name="subject" required>
											<option value="">Select a subject</option>
											<option value="General Inquiry">General Inquiry</option>
											<option value="Technical Support">Technical Support</option>
											<option value="Account Issues">Account Issues</option>
											<option value="Payment Questions">Payment Questions</option>
											<option value="Shipping Concerns">Shipping Concerns</option>
											<option value="Report a Problem">Report a Problem</option>
											<option value="Partnership Opportunity">Partnership Opportunity</option>
											<option value="Seller Application">Seller Application</option>
											<option value="Certification">Certification Questions</option>
											<option value="Other">Other</option>
										</select>
									</div>
								</div>
								
								<div class="form-group">
									<label for="message">Message <span class="required">*</span></label>
									<textarea id="message" name="message" rows="6" placeholder="Please provide details about your inquiry..." required></textarea>
								</div>
								
								<div class="form-group checkbox-group">
									<label class="checkbox-label">
										<input type="checkbox" id="privacy_consent" name="privacy_consent" required>
										<span>I agree to the <a href="privacy-policy.php" target="_blank">Privacy Policy</a> and consent to Nexus contacting me regarding my inquiry.</span>
									</label>
								</div>
								
								<button type="submit" class="btn-primary">Send Message</button>
								
								<div class="form-note">
									<p><span class="required">*</span> Required fields. We'll respond within 24-48 hours.</p>
								</div>
							</form>
						</div>
					</div>
				</div>
			</section>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type="text/javascript" src="utilities.js"></script>
		<script>
			const contactForm = document.getElementById('contactForm');
			
			function showError(inputElement, message) {
				removeError(inputElement);
				
				const errorSpan = document.createElement('span');
				errorSpan.className = 'error-message';
				errorSpan.style.color = '#ff6b6b';
				errorSpan.style.fontSize = '12px';
				errorSpan.style.marginTop = '5px';
				errorSpan.style.display = 'block';
				errorSpan.textContent = message;
				
				inputElement.style.borderColor = '#ff6b6b';
				inputElement.parentNode.appendChild(errorSpan);
			}
			
			function removeError(inputElement) {
				inputElement.style.borderColor = '';
				const parent = inputElement.parentNode;
				const existingError = parent.querySelector('.error-message');
				if (existingError) {
					parent.removeChild(existingError);
				}
			}
			
			function isValidEmail(email) {
				const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				return emailPattern.test(email);
			}
			
			if (contactForm) {
				contactForm.addEventListener('submit', function(event) {
					let isValid = true;
					
					const fullName = document.getElementById('full_name');
					const email = document.getElementById('email');
					const subject = document.getElementById('subject');
					const message = document.getElementById('message');
					const privacyConsent = document.getElementById('privacy_consent');
					
					if (!fullName.value.trim()) {
						showError(fullName, 'Full name is required');
						isValid = false;
					} else if (fullName.value.trim().length < 3) {
						showError(fullName, 'Full name must be at least 3 characters');
						isValid = false;
					} else {
						removeError(fullName);
					}
					
					if (!email.value.trim()) {
						showError(email, 'Email address is required');
						isValid = false;
					} else if (!isValidEmail(email.value)) {
						showError(email, 'Please enter a valid email address');
						isValid = false;
					} else {
						removeError(email);
					}
					
					if (!subject.value) {
						showError(subject, 'Please select a subject');
						isValid = false;
					} else {
						removeError(subject);
					}
					
					if (!message.value.trim()) {
						showError(message, 'Message is required');
						isValid = false;
					} else if (message.value.trim().length < 10) {
						showError(message, 'Message must be at least 10 characters');
						isValid = false;
					} else {
						removeError(message);
					}
					
					if (!privacyConsent.checked) {
						showError(privacyConsent, 'You must agree to the Privacy Policy');
						isValid = false;
					} else {
						removeError(privacyConsent);
					}
					
					if (!isValid) {
						event.preventDefault();
					}
				});
				
				const inputs = document.querySelectorAll('input, select, textarea');
				inputs.forEach(input => {
					input.addEventListener('input', function() {
						removeError(this);
					});
				});
				
				const privacyCheckbox = document.getElementById('privacy_consent');
				if (privacyCheckbox) {
					privacyCheckbox.addEventListener('change', function() {
						removeError(this);
					});
				}
			}
		</script>
	</body>
</html>