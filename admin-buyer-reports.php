<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'admin-auth.php';

if (!$is_super_admin && $admin_role !== 'safety_support') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

$admin_id = getUserId();
$success = '';
$error = '';

// Handle removing reported item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_item'])) {
    $product_id = $_POST['product_id'] ?? 0;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
$stmt->execute([$product_id]);
$success = "Item deleted successfully!";
    } catch (Exception $e) {
        $error = "Failed to remove item: " . $e->getMessage();
    }
}

// Handle sending message to reported user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_reported'])) {
    $report_id = $_POST['report_id'] ?? 0;
    $reported_id = $_POST['reported_id'] ?? 0;
    $reporter_id = $_POST['reporter_id'] ?? 0;
    $case_number = $_POST['case_number'] ?? '';
    
    $msg_reported = "CASE #" . $case_number . "\n\nWe have received a report regarding your account. You have been flagged in our system. If you receive two more reports, your account will be permanently removed from NEXUS.\n\nPlease review our Terms of Service and Community Guidelines. If you believe this report was made in error, you may contact us.\n\n- NEXUS Safety Team";
    
    // Create/get conversation with reported user
    $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)");
    $stmt->execute([$admin_id, $reported_id, $reported_id, $admin_id]);
    $conv = $stmt->fetch();
    
    if ($conv) {
        $conv_id = $conv['conversation_id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
        $stmt->execute([$admin_id, $reported_id]);
        $conv_id = $pdo->lastInsertId();
    }
    
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$conv_id, $admin_id, $reported_id, $msg_reported]);
    $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
    $stmt->execute([$msg_reported, $conv_id]);
    
    // Message to reporter
    $msg_reporter = "CASE #" . $case_number . "\n\nThank you for your report. We have reviewed it and the user has been flagged in our system. We apologize for any inconvenience caused.\n\nIf you experience further issues, please do not hesitate to contact us.\n\n- NEXUS Safety Team";
    
    $stmt = $pdo->prepare("SELECT conversation_id FROM conversations WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)");
    $stmt->execute([$admin_id, $reporter_id, $reporter_id, $admin_id]);
    $conv2 = $stmt->fetch();
    
    if ($conv2) {
        $conv_id2 = $conv2['conversation_id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
        $stmt->execute([$admin_id, $reporter_id]);
        $conv_id2 = $pdo->lastInsertId();
    }
    
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
    $stmt->execute([$conv_id2, $admin_id, $reporter_id, $msg_reporter]);
    $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
    $stmt->execute([$msg_reporter, $conv_id2]);
    
    // Update report status
    $stmt = $pdo->prepare("UPDATE user_reports SET status = 'reviewed', assigned_to = ?, resolved_by = ?, resolved_at = NOW() WHERE report_id = ?");
    $stmt->execute([$admin_id, $admin_id, $report_id]);
    
    $success = "Case #" . $case_number . " processed. Both parties notified.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss'])) {
    $report_id = $_POST['report_id'] ?? 0;
    $stmt = $pdo->prepare("UPDATE user_reports SET status = 'dismissed', resolved_by = ?, resolved_at = NOW() WHERE report_id = ?");
    $stmt->execute([$admin_id, $report_id]);
    $success = "Report dismissed.";
}

// Get reported items
$reported_items = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.*, sp.store_name
        FROM products p
        JOIN seller_profiles sp ON p.seller_id = sp.profile_id
        WHERE p.is_reported = 1 AND p.listing_status = 'active'
        ORDER BY p.reported_at DESC
    ");
    $stmt->execute();
    $reported_items = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading reported items: " . $e->getMessage());
}

// Get pending reports
$pending_reports = [];
try {
    $stmt = $pdo->prepare("
        SELECT ur.*, 
               COALESCE(bp.full_name, sp.full_name) as reporter_name,
               COALESCE(bp2.full_name, sp2.full_name) as reported_name,
               rd_user.user_id as reported_user_id
        FROM user_reports ur
        JOIN nexus_users u_rep ON ur.reporter_id = u_rep.user_id
        LEFT JOIN buyer_profiles bp ON u_rep.user_id = bp.user_id
        LEFT JOIN seller_profiles sp ON u_rep.user_id = sp.user_id
        JOIN nexus_users rd_user ON ur.reported_id = rd_user.user_id
        LEFT JOIN buyer_profiles bp2 ON rd_user.user_id = bp2.user_id
        LEFT JOIN seller_profiles sp2 ON rd_user.user_id = sp2.user_id
        WHERE u_rep.user_type = 'buyer' AND ur.status = 'pending'
        ORDER BY ur.created_at ASC
    ");
    $stmt->execute();
    $pending_reports = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading pending reports: " . $e->getMessage());
}

// Get reviewed reports
$reviewed_reports = [];
try {
    $stmt = $pdo->prepare("
        SELECT ur.*, 
               COALESCE(bp.full_name, sp.full_name) as reporter_name,
               COALESCE(bp2.full_name, sp2.full_name) as reported_name
        FROM user_reports ur
        JOIN nexus_users u_rep ON ur.reporter_id = u_rep.user_id
        LEFT JOIN buyer_profiles bp ON u_rep.user_id = bp.user_id
        LEFT JOIN seller_profiles sp ON u_rep.user_id = sp.user_id
        JOIN nexus_users rd_user ON ur.reported_id = rd_user.user_id
        LEFT JOIN buyer_profiles bp2 ON rd_user.user_id = bp2.user_id
        LEFT JOIN seller_profiles sp2 ON rd_user.user_id = sp2.user_id
        WHERE ur.status IN ('reviewed', 'dismissed')
        ORDER BY ur.resolved_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $reviewed_reports = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Error loading reviewed reports: " . $e->getMessage());
}

$total_pending = count($pending_reports);
$total_reviewed = count($reviewed_reports);
$total_reported_items = count($reported_items);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Buyer Reports</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="basestyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

    <style>
        .admin-container {
            padding: 40px 24px 60px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            color: #ff6b6b;
            font-size: 32px;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid #4caf50;
            color: #4caf50;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-error {
            background: rgba(255, 68, 68, 0.1);
            border: 1px solid #ff4444;
            color: #ff4444;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: #131315;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-card h3 {
            font-size: 36px;
            color: #ff6b6b;
            margin-bottom: 5px;
        }

        .section-title {
            font-size: 24px;
            color: #fff;
            margin: 30px 0 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #2a2a2a;
        }

        .report-card {
            background: #19191c;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #111;
            border-bottom: 1px solid #2a2a2a;
            flex-wrap: wrap;
            gap: 15px;
        }

        .report-header h3 {
            color: #fff;
            margin-bottom: 5px;
        }

        .report-header p {
            color: #888;
            font-size: 13px;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.15);
            color: #ffc107;
        }

        .status-reviewed {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
        }

        .status-dismissed {
            background: rgba(158, 158, 158, 0.15);
            color: #9e9e9e;
        }

        .report-body {
            padding: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #0e0e10;
            padding: 12px;
            border-radius: 8px;
        }

        .info-item label {
            font-size: 11px;
            color: #888;
            display: block;
            margin-bottom: 5px;
        }

        .info-item .value {
            color: #e5e5e5;
            font-size: 14px;
            word-break: break-word;
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn-notify {
            background: #ffc107;
            color: #0e0e10;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-dismiss {
            background: #666;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-view {
            background: #8ff5ff;
            color: #0e0e10;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-remove {
            background: #ff4444;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-remove:hover {
            background: #cc0000;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: #19191c;
            border-radius: 12px;
            color: #888;
        }

        .reported-item-card {
            background: #19191c;
            border: 1px solid #ff6b6b;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }

        .reported-item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            background: #111;
            border-bottom: 1px solid #ff6b6b;
            flex-wrap: wrap;
            gap: 15px;
        }

        .reported-item-header h3 {
            color: #ff6b6b;
            margin-bottom: 5px;
        }

        .reason-box {
            background: #0e0e10;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            border-left: 3px solid #ff4444;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 80px 16px 40px;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
            .page-header h1 {
                font-size: 24px;
            }
            .section-title {
                font-size: 20px;
            }
            .report-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .action-buttons {
                flex-direction: column;
            }
            .btn-notify, .btn-dismiss, .btn-view, .btn-remove {
                width: 100%;
                text-align: center;
            }
            .stats-grid {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
            .stat-card h3 {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .admin-container {
                padding: 70px 12px 30px;
            }
            .report-body {
                padding: 14px;
            }
            .info-item {
                padding: 10px;
            }
            .info-item .value {
                font-size: 13px;
            }
            .empty-state {
                padding: 40px 20px;
            }
        }
    </style>
</head>
<body>
    <?php include 'admin-header.php'; ?>
    <?php include 'admin-sidebar.php'; ?>
    
    <main class="admin-main">
        <div class="admin-container">
            <div class="page-header">
                <h1><i class="fas fa-user-friends"></i> Buyer Reports</h1>
                <p>Review and manage reports submitted by buyers</p>
            </div>
            
            <?php if ($success): ?>
                <div class="alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <div class="stats-grid">
                <div class="stat-card"><h3><?php echo $total_pending; ?></h3><p>Pending Reports</p></div>
                <div class="stat-card"><h3><?php echo $total_reviewed; ?></h3><p>Recently Reviewed</p></div>
                <div class="stat-card"><h3><?php echo $total_reported_items; ?></h3><p>Reported Items</p></div>
            </div>
            
            <!-- Reported Items Section -->
            <h2 class="section-title">Reported Items</h2>
            <?php if (empty($reported_items)): ?>
                <div class="empty-state"><i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i><p>No reported items</p></div>
            <?php else: ?>
                <?php foreach ($reported_items as $item): ?>
                    <div class="reported-item-card">
                        <div class="reported-item-header">
                            <div>
                                <h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
                                <p>Seller: <?php echo htmlspecialchars($item['store_name']); ?></p>
                            </div>
                        </div>
                        <div class="report-body">
                            <?php if (!empty($item['reported_reason'])): ?>
                                <div class="reason-box">
                                    <strong><i class="fas fa-exclamation-triangle"></i> Reason for Report:</strong>
                                    <p style="margin-top: 8px;"><?php echo nl2br(htmlspecialchars($item['reported_reason'])); ?></p>
                                </div>
                            <?php endif; ?>
                            <div class="action-buttons">
                                <a href="product.php?id=<?php echo $item['product_id']; ?>" class="btn-view" target="_blank">
                                    <i class="fas fa-eye"></i> View Item
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Remove this item from the explore feed? This will hide it from all users.');">
                                    <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                    <button type="submit" name="remove_item" class="btn-remove">
                                        <i class="fas fa-trash"></i> Remove from Feed
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Pending Reports Section -->
            <h2 class="section-title">Pending Reports</h2>
            <?php if (empty($pending_reports)): ?>
                <div class="empty-state"><i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i><p>No pending buyer reports</p></div>
            <?php else: ?>
                <?php foreach ($pending_reports as $report): 
                    $case_number = 'NEX-R' . str_pad($report['report_id'], 6, '0', STR_PAD_LEFT);
                ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div>
                                <h3>Case #<?php echo $case_number; ?></h3>
                                <p>Reported: <?php echo date('d M Y H:i', strtotime($report['created_at'])); ?></p>
                            </div>
                            <span class="status-badge status-pending">Pending</span>
                        </div>
                        <div class="report-body">
                            <div class="info-grid">
                                <div class="info-item"><label>Reporter</label><div class="value"><?php echo htmlspecialchars($report['reporter_name']); ?></div></div>
                                <div class="info-item"><label>Reported User</label><div class="value"><?php echo htmlspecialchars($report['reported_name']); ?> (Buyer)</div></div>
                                <div class="info-item"><label>Reason</label><div class="value"><?php echo ucwords(str_replace('-', ' ', $report['reason'])); ?></div></div>
                                <div class="info-item"><label>Description</label><div class="value"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div></div>
                            </div>
                            <div class="action-buttons">
                                <a href="user-profile.php?id=<?php echo $report['reported_user_id']; ?>" class="btn-view" target="_blank">
                                    <i class="fas fa-user"></i> View Reported Profile
                                </a>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Notify both parties? This will flag the reported user.');">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <input type="hidden" name="reported_id" value="<?php echo $report['reported_id']; ?>">
                                    <input type="hidden" name="reporter_id" value="<?php echo $report['reporter_id']; ?>">
                                    <input type="hidden" name="case_number" value="<?php echo $case_number; ?>">
                                    <button type="submit" name="notify_reported" class="btn-notify">
                                        <i class="fas fa-bell"></i> Notify & Flag User
                                    </button>
                                </form>
                                <form method="post" style="display: inline;" onsubmit="return confirm('Dismiss this report?');">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <button type="submit" name="dismiss" class="btn-dismiss">
                                        <i class="fas fa-times"></i> Dismiss
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- Reviewed Reports Section -->
            <?php if (!empty($reviewed_reports)): ?>
                <h2 class="section-title">Recently Reviewed</h2>
                <?php foreach ($reviewed_reports as $report): 
                    $case_number = 'NEX-R' . str_pad($report['report_id'], 6, '0', STR_PAD_LEFT);
                ?>
                    <div class="report-card">
                        <div class="report-header">
                            <div>
                                <h3>Case #<?php echo $case_number; ?></h3>
                                <p><?php echo htmlspecialchars($report['reporter_name']); ?> reported <?php echo htmlspecialchars($report['reported_name']); ?></p>
                            </div>
                            <span class="status-badge status-<?php echo $report['status']; ?>"><?php echo ucfirst($report['status']); ?></span>
                        </div>
                        <div class="report-body">
                            <div class="info-grid">
                                <div class="info-item"><label>Reason</label><div class="value"><?php echo ucwords(str_replace('-', ' ', $report['reason'])); ?></div></div>
                                <div class="info-item"><label>Description</label><div class="value"><?php echo nl2br(htmlspecialchars($report['description'])); ?></div></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>