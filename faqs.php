<?php
require_once 'config.php';

$stmt = $pdo->prepare("SELECT * FROM faqs WHERE is_active = TRUE ORDER BY display_order, faq_id");
$stmt->execute();
$all_faqs = $stmt->fetchAll();

$faqs_by_category = [];
foreach ($all_faqs as $faq) {
    $category = $faq['category'];
    if (!isset($faqs_by_category[$category])) {
        $faqs_by_category[$category] = [];
    }
    $faqs_by_category[$category][] = $faq;
}

$category_names = [
    'buying' => 'For Buyers',
    'selling' => 'For Sellers',
    'account' => 'Account Management',
    'payments' => 'Payments & Shipping',
    'safety' => 'Safety & Security'
];

$category_order = ['buying', 'selling', 'account', 'payments', 'safety'];
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | FAQs</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.faqs-page {
				padding: 120px 0 80px 0;
				min-height: 100vh;
			}

			.faqs-hero {
				text-align: center;
				margin-bottom: 48px;
			}

			.faqs-title {
				font-size: 48px;
				color: #8ff5ff;
				margin-bottom: 16px;
			}

			.faqs-subtitle {
				font-size: 18px;
				color: #e5e5e5;
				max-width: 600px;
				margin: 0 auto;
			}

			.categories {
				display: flex;
				flex-wrap: wrap;
				justify-content: center;
				gap: 12px;
				margin-bottom: 48px;
				padding-bottom: 24px;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}

			.cat-btn {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.3);
				border-radius: 9999px;
				padding: 8px 20px;
				color: #adaaad;
				font-size: 14px;
				font-weight: 500;
				cursor: pointer;
				transition: all 0.3s;
			}

			.cat-btn:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			.cat-btn.active {
				background: #8ff5ff;
				border-color: #8ff5ff;
				color: #0e0e10;
				font-weight: bold;
			}

			.faqs-grid {
				max-width: 900px;
				margin: 0 auto;
			}

			.faq-category-section {
				margin-bottom: 48px;
			}

			.category-title {
				font-size: 28px;
				margin-bottom: 24px;
				padding-bottom: 12px;
				border-bottom: 2px solid #30949d;
				display: inline-block;
				color: #f9f5f8;
			}
			

			.faq-item {
				background-color: #19191c;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 16px;
				margin-bottom: 16px;
				overflow: hidden;
				transition: all 0.3s;
			}

			.faq-item:hover {
				border-color: rgba(143, 245, 255, 0.3);
			}

			.faq-question {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px 24px;
				cursor: pointer;
				font-weight: 600;
				color: #f9f5f8;
				transition: background-color 0.3s;
			}

			.faq-question:hover {
				background-color: rgba(143, 245, 255, 0.05);
			}

			.faq-question span {
				font-size: 16px;
				flex: 1;
			}

			.faq-question i {
				color: #8ff5ff;
				transition: transform 0.3s;
				font-size: 14px;
			}

			.faq-item.active .faq-question i {
				transform: rotate(180deg);
			}

			.faq-answer {
				max-height: 0;
				padding: 0 24px;
				overflow: hidden;
				transition: max-height 0.3s ease-out, padding 0.3s ease;
				color: #adaaad;
				line-height: 1.8;
				font-size: 14px;
				border-top: none;
			}

			.faq-item.active .faq-answer {
				max-height: 500px;
				padding: 0 24px 20px 24px;
				border-top: 1px solid rgba(118, 117, 119, 0.1);
			}

			.faq-answer p {
				margin: 0;
			}

			.contact-support {
				text-align: center;
				margin-top: 60px;
				padding: 48px;
				background: #19191c;
				border-radius: 24px;
				border: 1px solid rgba(143, 245, 255, 0.2);
			}

			.contact-support h3 {
				font-size: 24px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}

			.contact-support p {
				margin-bottom: 24px;
				color: #adaaad;
			}

			@media (max-width: 768px) {
				.faqs-title {
					font-size: 32px;
				}
				.faqs-subtitle {
					font-size: 16px;
				}
				.category-title {
					font-size: 24px;
				}
				.faq-question {
					padding: 16px 20px;
				}
				.faq-question span {
					font-size: 14px;
				}
				.contact-support {
					padding: 32px 24px;
				}
				.contact-support h3 {
					font-size: 20px;
				}
				.categories {
					gap: 8px;
				}
				.cat-btn {
					padding: 6px 16px;
					font-size: 12px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="faqs-page">
			<div class="container">
				<section class="faqs-hero">
					<h1 class="faqs-title">FREQUENTLY ASKED QUESTIONS</h1>
					<p class="faqs-subtitle">Find answers to common questions about Nexus</p>
				</section>
				
				<nav class="categories" aria-label="FAQ categories">
					<button class="cat-btn active" data-category="all">All Questions</button>
					<?php foreach ($category_order as $cat): ?>
						<?php if (isset($faqs_by_category[$cat])): ?>
							<button class="cat-btn" data-category="<?php echo $cat; ?>"><?php echo $category_names[$cat]; ?></button>
						<?php endif; ?>
					<?php endforeach; ?>
				</nav>
				
				<div class="faqs-grid">
					<?php foreach ($category_order as $cat): ?>
						<?php if (isset($faqs_by_category[$cat])): ?>
							<section class="faq-category-section" data-category="<?php echo $cat; ?>">
								<h2 class="category-title"><?php echo $category_names[$cat]; ?></h2>
								
								<?php foreach ($faqs_by_category[$cat] as $faq): ?>
									<div class="faq-item">
										<div class="faq-question">
											<span><?php echo htmlspecialchars($faq['question']); ?></span>
											<i class="fas fa-chevron-down"></i>
										</div>
										<div class="faq-answer">
											<p><?php echo nl2br(htmlspecialchars($faq['answer'])); ?></p>
										</div>
									</div>
								<?php endforeach; ?>
							</section>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
				
				<aside class="contact-support">
					<h3>Still have questions?</h3>
					<p>Can't find the answer you're looking for? Our support team is here to help.</p>
					<a href="contact-us.php" class="btn-secondary-indigo">Contact Support</a>
				</aside>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			var faqItems = document.querySelectorAll('.faq-item');

			function closeAllFaqs() {
				for (var i = 0; i < faqItems.length; i++) {
					faqItems[i].classList.remove('active');
				}
			}

			for (var i = 0; i < faqItems.length; i++) {
				var question = faqItems[i].querySelector('.faq-question');
				
				question.addEventListener('click', function() {
					var currentItem = this.parentElement;
					var isOpen = currentItem.classList.contains('active');
					
					closeAllFaqs();
					
					if (!isOpen) {
						currentItem.classList.add('active');
					}
				});
			}

			var categoryBtns = document.querySelectorAll('.cat-btn');
			var faqSections = document.querySelectorAll('.faq-category-section');

			for (var i = 0; i < categoryBtns.length; i++) {
				categoryBtns[i].addEventListener('click', function() {
					var category = this.getAttribute('data-category');

					for (var j = 0; j < categoryBtns.length; j++) {
						categoryBtns[j].classList.remove('active');
					}
					this.classList.add('active');
					
					for (var k = 0; k < faqSections.length; k++) {
						if (category === 'all' || faqSections[k].getAttribute('data-category') === category) {
							faqSections[k].style.display = 'block';
						} else {
							faqSections[k].style.display = 'none';
						}
					}
				});
			}
		</script>
	</body>
</html>