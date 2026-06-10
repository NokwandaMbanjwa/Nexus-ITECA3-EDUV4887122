<?php
require_once 'admin-auth.php';

$admin_role = $_SESSION['admin_role'];
$is_super_admin = ($admin_role === 'super_admin');
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="admin-sidebar" id="adminSidebar">
    <nav class="sidebar-nav">
        <div class="nav-section">
            <a href="admin-dashboard.php" class="nav-item <?php echo ($current_page == 'admin-dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Dashboard
            </a>
        </div>
        
        <div class="nav-section">
            <a href="nexus-users.php" class="nav-item <?php echo ($current_page == 'nexus-users.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-list"></i> Nexus Users
            </a>
        </div>
        
        <div class="nav-section">
            <a href="admin-messages.php" class="nav-item <?php echo ($current_page == 'admin-messages.php') ? 'active' : ''; ?>">
                <i class="fas fa-envelope"></i> Messages
            </a>
        </div>
        
		<?php if ($is_super_admin): ?>
		<div class = "nav-section">
			<a href="admin-user-management.php" class="nav-item <?php echo ($current_page == 'admin-user-management.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-address-book"></i> User Management
            </a>
        </div>
	<?php endif; ?>

        <!--Only super admin or legal admin -->
        <?php if ($is_super_admin || $admin_role === 'legal'): ?>
        <div class="nav-section">
            <div class="nav-section-title" data-section="legal">
                Legal <i class="fas fa-chevron-down"></i>
            </div>
            <div class="nav-section-content" id="legal-content">
                <a href="admin-privacy.php" class="nav-item <?php echo ($current_page == 'admin-privacy.php') ? 'active' : ''; ?>">
                    <i class="fas fa-lock"></i> Review Privacy Policy
                </a>
                <a href="admin-terms.php" class="nav-item <?php echo ($current_page == 'admin-terms.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-contract"></i> Review Terms of Service
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Only super admin or payments admin -->
        <?php if ($is_super_admin || $admin_role === 'payments'): ?>
        <div class="nav-section">
            <div class="nav-section-title" data-section="payments">
                Payments <i class="fas fa-chevron-down"></i>
            </div>
            <div class="nav-section-content" id="payments-content">
                <a href="admin-bank-details.php" class="nav-item <?php echo ($current_page == 'admin-bank-details.php') ? 'active' : ''; ?>">
                    <i class="fas fa-university"></i> Bank Account Details
                </a>
                <a href="admin-payment-gateway.php" class="nav-item <?php echo ($current_page == 'admin-payment-gateway.php') ? 'active' : ''; ?>">
                    <i class="fas fa-credit-card"></i> Payment Gateway
                </a>
                <a href="admin-payment-disputes.php" class="nav-item <?php echo ($current_page == 'admin-payment-disputes.php') ? 'active' : ''; ?>">
                    <i class="fas fa-gavel"></i> Payment Disputes
                </a>
                <?php if ($is_super_admin): ?>
                <a href="admin-payment-disbursement.php" class="nav-item <?php echo ($current_page == 'admin-payment-disbursement.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice"></i> NEXUS Payments Site
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Only super admin or support and safety admin -->
        <?php if ($is_super_admin || $admin_role === 'safety_support'): ?>
        <div class="nav-section">
            <div class="nav-section-title" data-section="safety">
                Safety & Support <i class="fas fa-chevron-down"></i>
            </div>
            <div class="nav-section-content" id="safety-content">
                <a href="admin-buyer-reports.php" class="nav-item <?php echo ($current_page == 'admin-buyer-reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-friends"></i> Buyer Reports
                </a>
                <a href="admin-seller-reports.php" class="nav-item <?php echo ($current_page == 'admin-seller-reports.php') ? 'active' : ''; ?>">
                    <i class="fas fa-store"></i> Seller Reports
                </a>
                <a href="admin-general-reports.php" class="nav-item <?php echo ($current_page == 'admin-general-reports.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-comment-dots"></i> User Communications
                </a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Only super admin or verification admin -->
        <?php if ($is_super_admin || $admin_role === 'verification'): ?>
        <div class="nav-section">
            <div class="nav-section-title" data-section="verification">
                Verification <i class="fas fa-chevron-down"></i>
            </div>
            <div class="nav-section-content" id="verification-content">
                <a href="admin-verify-seller.php" class="nav-item <?php echo ($current_page == 'admin-verify-seller.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-check"></i> Verify Seller Account
                </a>
                <a href="admin-verify-submission.php" class="nav-item <?php echo ($current_page == 'admin-verify-submission.php') ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i> Verify Seller Submissions
                </a>
                <?php if ($is_super_admin): ?>
                <a href="admin-applications.php" class="nav-item <?php echo ($current_page == 'admin-applications.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-spinner"></i> Review Admin Applications
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Only super admin or social media admin -->
        <?php if ($is_super_admin || $admin_role === 'social_media'): ?>
        <div class="nav-section">
            <div class="nav-section-title" data-section="social">
                Social Media <i class="fas fa-chevron-down"></i>
            </div>
            <div class="nav-section-content" id="social-content">
                <a href="admin-facebook.php" class="nav-item <?php echo ($current_page == 'admin-facebook.php') ? 'active' : ''; ?>">
                    <i class="fab fa-facebook"></i> Facebook
                </a>
                <a href="admin-twitter.php" class="nav-item <?php echo ($current_page == 'admin-twitter.php') ? 'active' : ''; ?>">
                    <i class="fab fa-twitter"></i> Twitter
                </a>
                <a href="admin-instagram.php" class="nav-item <?php echo ($current_page == 'admin-instagram.php') ? 'active' : ''; ?>">
                    <i class="fab fa-instagram"></i> Instagram
                </a>
                <a href="admin-linkedin.php" class="nav-item <?php echo ($current_page == 'admin-linkedin.php') ? 'active' : ''; ?>">
                    <i class="fab fa-linkedin"></i> LinkedIn
                </a>
            </div>
        </div>
        <?php endif; ?>
    </nav>
</aside>

<script>
    document.querySelectorAll('.nav-section-title').forEach(title => {
        const content = document.getElementById(title.getAttribute('data-section') + '-content');
        if (content) {
            const savedState = localStorage.getItem(title.getAttribute('data-section') + '_collapsed');
            if (savedState === 'true') {
                content.classList.add('collapsed');
                title.classList.add('collapsed');
            }
            
            title.addEventListener('click', () => {
                content.classList.toggle('collapsed');
                title.classList.toggle('collapsed');
                localStorage.setItem(title.getAttribute('data-section') + '_collapsed', content.classList.contains('collapsed'));
            });
        }
    });
</script>
