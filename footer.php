<?php
$user_type = getUserType(); 
?>

<style>
	.footer {
		background-color: #131315;
		padding-top: 60px;
		padding-bottom: 48px;
		margin-top: 0;
	}

	.footer-divider {
		width: 90%;
		height: 1px;
		background: rgba(118, 117, 119, 0.2);
		margin: 0 auto;
	}

	.footer-container {
		max-width: 1280px;
		margin: 0 auto;
		padding: 0 24px;
	}

	.footer-grid {
		display: grid;
		grid-template-columns: 2fr 1fr 1fr;
		gap: 48px;
		margin-bottom: 60px;
	}

	.footer-logo {
		font-size: 30px;
		font-weight: 800;
		font-family: 'Manrope', sans-serif;
		letter-spacing: -0.02em;
		margin-bottom: 24px;
		color: #9d00ff;
	}

	.footer-about p {
		color: #e5e5e5;
		max-width: 320px;
		margin-bottom: 32px;
	}

	.social-links {
		display: flex;
		gap: 16px;
	}

	.social-icon {
		width: 40px;
		height: 40px;
		background-color: #1f1f22;
		border-radius: 9999px;
		display: flex;
		align-items: center;
		justify-content: center;
		text-decoration: none;
		color: #d4d4d8;
		transition: all 0.3s;
	}

	.social-icon i {
		font-size: 18px;
	}

	.social-icon:hover {
		color: #8ff5ff;
		transform: translateY(-2px);
	}

	.footer-links h4 {
		font-size: 18px;
		font-weight: bold;
		margin-bottom: 24px;
	}

	.footer-links ul {
		list-style: none;
	}

	.footer-links ul li {
		margin-bottom: 16px;
	}

	.footer-links ul li a {
		color: #e5e5e5;
		text-decoration: none;
		transition: color 0.3s;
	}

	.footer-links ul li a:hover {
		color: #8ff5ff;
	}

	.footer-bottom {
		border-top: 1px solid rgba(118, 117, 119, 0.1);
		padding-top: 48px;
		display: flex;
		justify-content: space-between;
		align-items: center;
		flex-wrap: wrap;
		gap: 24px;
	}

	.footer-bottom p {
		color: #e5e5e5;
		font-size: 14px;
	}

    .scroll-to-top {
        position: fixed;
        bottom: 30px;
        right: 30px;
        width: 50px;
        height: 50px;
        background: #a9d1d7;
        border: none;
        border-radius: 50%;
        color: #0e0e10;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(143, 245, 255, 0.3);
    }
    
    .scroll-to-top.show {
        opacity: 1;
        visibility: visible;
    }
    
    .scroll-to-top:hover {
        transform: translateY(-5px);
        box-shadow: 0 3px 5px #a9d1d7;
    }
    
    .scroll-to-top:active {
        transform: translateY(0);
    }
    
    .scroll-to-top .scroll-text {
        font-size: 10px;
        font-weight: bold;
    }
    
    .mobile-footer {
        display: none;
        background-color: #131315;
        padding: 40px 24px 30px;
        margin-top: 40px;
        border-top: 1px solid rgba(118, 117, 119, 0.1);
        text-align: center;
    }
    
    .mobile-footer-links {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .mobile-footer-links a {
        color: #d4d4d8;
        text-decoration: none;
        font-size: 14px;
        transition: color 0.3s;
    }
    
    .mobile-footer-links a:hover {
        color: #8ff5ff;
    }
    
    .mobile-social-icons {
        display: flex;
        justify-content: center;
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .mobile-social-icon {
        width: 40px;
        height: 40px;
        background-color: #1f1f22;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #d4d4d8;
        text-decoration: none;
        transition: all 0.3s;
    }
    
    .mobile-social-icon:hover {
        color: #8ff5ff;
        transform: translateY(-2px);
    }
    
    .mobile-copyright p {
        color: #adaaad;
        font-size: 12px;
        margin-bottom: 8px;
    }
    
    .mobile-seller-note {
        margin-top: 16px;
        padding-top: 16px;
        border-top: 1px solid rgba(118, 117, 119, 0.1);
    }
    
    .mobile-seller-note a {
        color: #8ff5ff;
        text-decoration: none;
        font-size: 13px;
    }
    
    /* Responsive */
    @media only screen and (max-width: 768px) {
        .footer {
            display: none;
        }
        .mobile-footer {
            display: block;
        }
        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
        .footer-grid {
            grid-template-columns: 1fr;
            gap: 32px;
            text-align: center;
        }
        .footer-about p {
            margin-left: auto;
            margin-right: auto;
        }
        .social-links {
            justify-content: center;
        }
        .footer-links ul li {
            text-align: center;
        }
    }
    
    @media (max-width: 480px) {
        .scroll-to-top {
            bottom: 15px;
            right: 15px;
            width: 40px;
            height: 40px;
        }
    }
</style>

<hr class="footer-divider" aria-hidden="true">
<footer class="footer">
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-about">
                <div class="footer-logo" aria-label="NEXUS">NEXUS</div>
                    <p>The premier destination for modern e-commerce. Built for the next generation of digital-first traders.</p>
                
                <div class="social-links">
                    <a href="#" class="social-icon" aria-label="Share on social media">
                        <i class="fas fa-share-alt" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="social-icon" aria-label="Follow our Facebook Page">
                        <i class="fa-brands fa-facebook" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="social-icon" aria-label="Follow our Instagram Page">
                        <i class="fa-brands fa-instagram" aria-hidden="true"></i>
                    </a>
                    <a href="#" class="social-icon" aria-label="Follow our LinkedIn Page">
                        <i class="fa-brands fa-linkedin" aria-hidden="true"></i>
                    </a>
                </div>
            </div>
            
            <nav class="footer-links" aria-label="Marketplace links">
                <h4>Marketplace</h4>
                <ul>
                    <li><a href="about-us.php">About Us</a></li>
                    <li><a href="terms-of-service.php">Terms of Service</a></li>
                    <li><a href="privacy-policy.php">Privacy Policy</a></li>
                    <?php if ($user_type === 'seller'): ?>
                        <li><a href="certifications.php">Expand Your Business</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
            
            <nav class="footer-links" aria-label="Support links">
                <h4>Support</h4>
                <ul>
                    <li><a href="safety-tips.php">Safety Tips</a></li>
                    <li><a href="faqs.php">Help Center</a></li>
                    <li><a href="contact-us.php">Contact Us</a></li>
                    <?php if ($user_type === 'seller' || $user_type === 'admin'): ?>
                        <li><a href="certifications.php">Get Certified</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> NEXUS C2C Marketplace. All rights reserved.</p>
        </div>
    </div>
	<button id="scrollToTopBtn" class="scroll-to-top" aria-label="Scroll to top">
		<i class="fas fa-chevron-up"></i>
	</button>
</footer>

<div class="mobile-footer">
    <div class="mobile-footer-content">
        <div class="mobile-footer-links">
            <a href="about-us.php">About</a>
            <a href="faqs.php">Help</a>
            <a href="contact-us.php">Contact</a>
            <a href="terms-of-service.php">Terms</a>
            <a href="privacy-policy.php">Privacy</a>
            <?php if ($user_type === 'seller'): ?>
                <a href="certifications.php">Get Certified</a>
            <?php endif; ?>
        </div>
        <div class="mobile-social-icons">
            <a href="#" class="mobile-social-icon"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="mobile-social-icon"><i class="fab fa-twitter"></i></a>
            <a href="#" class="mobile-social-icon"><i class="fab fa-instagram"></i></a>
            <a href="#" class="mobile-social-icon"><i class="fab fa-linkedin-in"></i></a>
        </div>
        <div class="mobile-copyright">
            <p>&copy; <?php echo date('Y'); ?> NEXUS</p>
        </div>
    </div>
</div>

<script>
    (function() {
        var scrollBtn = document.getElementById('scrollToTopBtn');

        function checkScrollPosition() {
            if (!scrollBtn) return;
        
            if (window.pageYOffset > 300) {
                scrollBtn.classList.add('show');
            } else {
                scrollBtn.classList.remove('show');
            }
        }
        
        function scrollToTop() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

            var scrollStep = function() {
                if (window.pageYOffset > 0) {
                    window.scrollBy(0, -50);
                    setTimeout(scrollStep, 10);
                }
            };
            
            if (!('scrollBehavior' in document.documentElement.style)) {
                scrollStep();
            }
        }
        
        if (scrollBtn) {
            window.addEventListener('scroll', checkScrollPosition);
            scrollBtn.addEventListener('click', scrollToTop);
            
            checkScrollPosition();
        }
    })();
</script>