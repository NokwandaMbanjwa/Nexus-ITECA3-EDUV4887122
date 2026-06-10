<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_name = getUserName();
$user_type = getUserType();

// Get user's profile info
$stmt = $pdo->prepare("SELECT * FROM " . ($user_type === 'seller' ? "seller_profiles" : "buyer_profiles") . " WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$avatar_letter = strtoupper(substr($user_name, 0, 1));

// Pusher credentials
$pusher_key = 'a3192cebd4c0ba37141f';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_conversations') {
		$stmt = $pdo->prepare("
			SELECT c.*,
				   CASE 
					   WHEN c.participant1_id = ? THEN c.participant2_id
					   ELSE c.participant1_id
				   END as other_user_id,
				   COALESCE(
					   (SELECT full_name FROM seller_profiles WHERE user_id = CASE WHEN c.participant1_id = ? THEN c.participant2_id ELSE c.participant1_id END),
					   (SELECT full_name FROM buyer_profiles WHERE user_id = CASE WHEN c.participant1_id = ? THEN c.participant2_id ELSE c.participant1_id END)
				   ) as other_user_name,
				   (SELECT user_type FROM nexus_users WHERE user_id = CASE WHEN c.participant1_id = ? THEN c.participant2_id ELSE c.participant1_id END) as other_user_type,
				   (SELECT COUNT(*) FROM messages WHERE conversation_id = c.conversation_id AND receiver_id = ? AND is_read = FALSE) as unread_count
			FROM conversations c
			WHERE c.participant1_id = ? OR c.participant2_id = ?
			ORDER BY c.updated_at DESC
		");
		$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
		$conversations = $stmt->fetchAll();
		
		echo json_encode(['success' => true, 'conversations' => $conversations]);
		exit;
	}
    
    if ($action === 'get_messages') {
        $conversation_id = $_POST['conversation_id'] ?? 0;
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE conversation_id = ? AND receiver_id = ?");
        $stmt->execute([$conversation_id, $user_id]);
        
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type
            FROM messages m
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $conversation_id]);
        $messages = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    if ($action === 'get_new_messages') {
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $last_id = $_POST['last_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   CASE WHEN m.sender_id = ? THEN 'sent' ELSE 'received' END as message_type
            FROM messages m
            WHERE m.conversation_id = ? AND m.message_id > ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $conversation_id, $last_id]);
        $messages = $stmt->fetchAll();
        
        if (!empty($messages)) {
            $stmt = $pdo->prepare("UPDATE messages SET is_read = TRUE, read_at = NOW() WHERE conversation_id = ? AND receiver_id = ?");
            $stmt->execute([$conversation_id, $user_id]);
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit;
    }
    
    if ($action === 'send_message') {
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $message = trim($_POST['message'] ?? '');
        $receiver_id = $_POST['receiver_id'] ?? 0;
        
        if (empty($message)) {
            echo json_encode(['success' => false, 'error' => 'Message cannot be empty']);
            exit;
        }
        
        // Check if blocked
        $stmt = $pdo->prepare("SELECT * FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$receiver_id, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You have been blocked by this user']);
            exit;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM blocked_users WHERE blocker_id = ? AND blocked_id = ?");
        $stmt->execute([$user_id, $receiver_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'error' => 'You have blocked this user']);
            exit;
        }
        
        $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, receiver_id, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$conversation_id, $user_id, $receiver_id, $message]);
        $message_id = $pdo->lastInsertId();
        
        // Update conversation last message
        $stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW(), updated_at = NOW() WHERE conversation_id = ?");
        $stmt->execute([$message, $conversation_id]);
        
        // Trigger Pusher event
        $pusher_data = [
            'message_id' => $message_id,
            'conversation_id' => $conversation_id,
            'sender_id' => $user_id,
            'receiver_id' => $receiver_id,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Make request to Pusher HTTP API
        $pusher_url = "https://api-eu.pusher.com/apps/2156423/events";
        $pusher_secret = "f39249c0cbb7baf5eb07";
        
        $post_data = json_encode([
            'name' => 'new-message',
            'channel' => "private-user-{$receiver_id}",
            'data' => $pusher_data
        ]);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $pusher_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("{$pusher_key}:{$pusher_secret}")
        ]);
        curl_exec($ch);
        curl_close($ch);
        
        echo json_encode(['success' => true, 'message_id' => $message_id]);
        exit;
    }
    
    if ($action === 'start_conversation') {
        $other_user_id = $_POST['other_user_id'] ?? 0;
        
        $stmt = $pdo->prepare("
            SELECT conversation_id FROM conversations 
            WHERE (participant1_id = ? AND participant2_id = ?) OR (participant1_id = ? AND participant2_id = ?)
        ");
        $stmt->execute([$user_id, $other_user_id, $other_user_id, $user_id]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            echo json_encode(['success' => true, 'conversation_id' => $existing['conversation_id'], 'existing' => true]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO conversations (participant1_id, participant2_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $other_user_id]);
            echo json_encode(['success' => true, 'conversation_id' => $pdo->lastInsertId(), 'existing' => false]);
        }
        exit;
    }
    
    if ($action === 'delete_conversation') {
        $conversation_id = $_POST['conversation_id'] ?? 0;
        $stmt = $pdo->prepare("DELETE FROM conversations WHERE conversation_id = ?");
        $stmt->execute([$conversation_id]);
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'rate_user') {
        $rated_id = $_POST['rated_id'] ?? 0;
        $rating = $_POST['rating'] ?? 0;
        $review = $_POST['review'] ?? '';
        $conversation_id = $_POST['conversation_id'] ?? null;
        
        $stmt = $pdo->prepare("INSERT INTO user_ratings (rater_id, rated_id, rating, review, conversation_id) 
                               VALUES (?, ?, ?, ?, ?) 
                               ON DUPLICATE KEY UPDATE rating = ?, review = ?");
        $stmt->execute([$user_id, $rated_id, $rating, $review, $conversation_id, $rating, $review]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'report_user') {
        $reported_id = $_POST['reported_id'] ?? 0;
        $reason = $_POST['reason'] ?? '';
        
        $stmt = $pdo->prepare("INSERT INTO user_reports (reporter_id, reported_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $reported_id, $reason]);
        
        // Also block the user
        $stmt = $pdo->prepare("INSERT IGNORE INTO blocked_users (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $reported_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'search_users') {
        $search = $_POST['search'] ?? '';
        $searchTerm = "%$search%";
        
        $stmt = $pdo->prepare("
            SELECT u.user_id, u.user_type,
                   COALESCE(bp.full_name, sp.full_name) as full_name
            FROM nexus_users u
            LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
            LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
            WHERE u.user_id != ? AND (bp.full_name LIKE ? OR sp.full_name LIKE ?)
            LIMIT 20
        ");
        $stmt->execute([$user_id, $searchTerm, $searchTerm]);
        $users = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'users' => $users]);
        exit;
    }
	
	if ($action === 'get_sellers') {
		$stmt = $pdo->prepare("
			SELECT u.user_id, u.user_type, sp.full_name, sp.store_name
			FROM nexus_users u
			JOIN seller_profiles sp ON u.user_id = sp.user_id
			WHERE u.user_id != ?
			ORDER BY sp.full_name ASC
			LIMIT 50
		");
		$stmt->execute([$user_id]);
		$users = $stmt->fetchAll();
		
		echo json_encode(['success' => true, 'users' => $users]);
		exit;
	}

}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Messages</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
		
		<style>
			.messages-page {
				margin-top: 20px;
				height: calc(100vh - 80px);
				display: flex;
				background: #0e0e10;
			}
			.empty-state {
				flex: 1;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-align: center;
				padding: 40px;
			}
			.empty-icon {
				font-size: 80px;
				color: #8ff5ff;
				margin-bottom: 24px;
				opacity: 0.7;
			}
			.empty-state h2 {
				font-size: 28px;
				color: #fff;
				margin-bottom: 12px;
			}
			.empty-message {
				color: #888;
				margin-bottom: 32px;
				max-width: 400px;
			}
			.new-chat-btn {
				display: inline-flex;
				align-items: center;
				gap: 12px;
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				padding: 12px 28px;
				border-radius: 40px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.2s;
			}
			.search-modal {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.8);
				z-index: 200;
				display: none;
				align-items: center;
				justify-content: center;
				border: none;
			}
			.search-modal[open] {
				display: flex;
			}
			.modal-content {
				background: #1a1a1a;
				border: 1px solid #333;
				border-radius: 16px;
				width: 90%;
				max-width: 500px;
				padding: 24px;
			}
			.modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 20px;
			}
			.modal-header h3 {
				color: #fff;
				font-size: 20px;
			}
			.close-modal {
				background: none;
				border: none;
				color: #888;
				font-size: 24px;
				cursor: pointer;
			}
			.user-search-input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #333;
				border-radius: 8px;
				color: #e5e5e5;
				margin-bottom: 20px;
			}
			.search-results {
				max-height: 300px;
				overflow-y: auto;
			}
			.search-result-item {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 12px;
				cursor: pointer;
				border-radius: 8px;
				transition: background 0.2s;
			}
			.search-result-item:hover {
				background: #2a2a2a;
			}
			.result-avatar {
				width: 40px;
				height: 40px;
				border-radius: 50%;
				background: #8ff5ff;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #0e0e10;
				font-weight: bold;
			}
			.result-info h4 {
				color: #fff;
				font-size: 14px;
			}
			.result-info p {
				color: #888;
				font-size: 12px;
			}
			.messages-container {
				display: flex;
				width: 100%;
				height: 100%;
				background: #0e0e10;
			}
			.conversations-sidebar {
				width: 450px;
				border-right: 1px solid #2a2a2a;
				display: flex;
				flex-direction: column;
				background: #111;
			}
			.sidebar-header {
				padding: 20px;
				border-bottom: 1px solid #2a2a2a;
			}
			.conversation-search {
				width: 100%;
				padding: 10px 16px;
				background: #1a1a1a;
				border: 1px solid #333;
				border-radius: 40px;
				color: #e5e5e5;
			}
			.conversations-list {
				flex: 1;
				overflow-y: auto;
			}
			.conversation-item {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 16px;
				cursor: pointer;
				transition: background 0.2s;
				border-bottom: 1px solid #2a2a2a;
				position: relative;
			}
			.conversation-item:hover {
				background: #1a1a1a;
			}
			.conversation-item.active {
				background: #1a1a1a;
				border-left: 3px solid #8ff5ff;
			}
			.conversation-avatar {
				width: 48px;
				height: 48px;
				border-radius: 50%;
				background: #8ff5ff;
				display: flex;
				align-items: center;
				justify-content: center;
				flex-shrink: 0;
				color: #0e0e10;
				font-weight: bold;
				font-size: 18px;
			}
			.conversation-info {
				flex: 1;
				min-width: 0;
			}
			.conversation-name {
				font-size: 16px;
				font-weight: 600;
				color: #fff;
				margin-bottom: 4px;
			}
			.conversation-preview {
				font-size: 12px;
				color: #888;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			.conversation-time {
				font-size: 10px;
				color: #666;
				flex-shrink: 0;
			}
			.unread-badge {
				position: absolute;
				top: 12px;
				right: 12px;
				background: #8ff5ff;
				color: #0e0e10;
				border-radius: 50%;
				width: 20px;
				height: 20px;
				font-size: 10px;
				display: flex;
				align-items: center;
				justify-content: center;
				font-weight: bold;
			}
			.chat-area {
				flex: 1;
				display: flex;
				flex-direction: column;
				background: #0e0e10;
			}
			.chat-header {
				display: flex;
				align-items: center;
				justify-content: space-between;
				padding: 16px 24px;
				border-bottom: 1px solid #2a2a2a;
				background: #111;
			}
			.chat-header-left {
				display: flex;
				align-items: center;
				gap: 16px;
			}
			.expand-btn {
				background: none;
				border: none;
				color: #888;
				font-size: 20px;
				cursor: pointer;
				padding: 8px;
				border-radius: 50%;
				transition: all 0.2s;
			}
			.expand-btn:hover {
				background: #2a2a2a;
				color: #8ff5ff;
			}
			.chat-contact {
				display: flex;
				align-items: center;
				gap: 12px;
			}
			.chat-avatar {
				width: 40px;
				height: 40px;
				border-radius: 50%;
				background: #8ff5ff;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #0e0e10;
				font-weight: bold;
			}
			.chat-contact-info h3 {
				font-size: 16px;
				color: #fff;
			}
			.chat-actions {
				display: flex;
				gap: 12px;
			}
			.action-circle {
				width: 36px;
				height: 36px;
				background: #1a1a1a;
				border: 1px solid #333;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				transition: all 0.2s;
				color: #888;
			}
			.action-circle:hover {
				background: #2a2a2a;
				border-color: #8ff5ff;
				color: #8ff5ff;
				transform: scale(1.05);
			}
			.action-circle.danger:hover {
				border-color: #ff4444;
				color: #ff4444;
			}
			.messages-display {
				flex: 1;
				overflow-y: auto;
				padding: 24px;
				display: flex;
				flex-direction: column;
				gap: 16px;
			}
			.message {
				max-width: 70%;
				display: flex;
				flex-direction: column;
			}
			.message.received {
				align-self: flex-start;
			}
			.message.sent {
				align-self: flex-end;
			}
			.message-bubble {
				padding: 10px 16px;
				border-radius: 18px;
				font-size: 14px;
				line-height: 1.4;
				word-wrap: break-word;
			}
			.message.received .message-bubble {
				background: #1a1a1a;
				color: #ffffff;
				border-bottom-left-radius: 4px;
				border: 1px solid #333;
			}
			.message.sent .message-bubble {
				background: #008B8B;
				color: #ffffff;
				border-bottom-right-radius: 4px;
			}
			.message-time {
				font-size: 10px;
				color: #666;
				margin-top: 4px;
				padding: 0 8px;
			}
			.message-input-area {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 16px 24px;
				border-top: 1px solid #2a2a2a;
				background: #111;
			}
			.attachment-btn {
				background: none;
				border: none;
				color: #888;
				font-size: 20px;
				cursor: pointer;
				padding: 8px;
				border-radius: 50%;
				transition: all 0.2s;
			}
			.attachment-btn:hover {
				color: #8ff5ff;
			}
			.message-input {
				flex: 1;
				padding: 12px 16px;
				background: #1a1a1a;
				border: 1px solid #333;
				border-radius: 40px;
				color: #e5e5e5;
				font-family: inherit;
			}
			.message-input:focus {
				outline: none;
				border-color: #8ff5ff;
			}
			.send-btn {
				background: #8ff5ff;
				color: #0a0a0a;
				border: none;
				border-radius: 50%;
				width: 40px;
				height: 40px;
				display: flex;
				align-items: center;
				justify-content: center;
				cursor: pointer;
				transition: all 0.2s;
			}
			.send-btn:hover {
				transform: scale(1.05);
			}
			.rating-modal, .report-modal {
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.9);
				z-index: 300;
				display: none;
				align-items: center;
				justify-content: center;
			}
			.rating-modal.show, .report-modal.show {
				display: flex;
			}
			.rating-content {
				background: #1a1a1a;
				border-radius: 16px;
				padding: 24px;
				max-width: 400px;
				width: 90%;
			}
			.rating-stars {
				display: flex;
				gap: 8px;
				justify-content: center;
				margin: 20px 0;
			}
			.rating-star {
				font-size: 32px;
				cursor: pointer;
				color: #555;
				transition: color 0.2s;
			}
			.rating-star:hover, .rating-star.active {
				color: #ffc107;
			}
			.review-text, .report-reason {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #333;
				border-radius: 8px;
				color: #e5e5e5;
				margin: 16px 0;
				resize: vertical;
			}
			.loading {
				text-align: center;
				padding: 40px;
				color: #888;
			}
			.chat-placeholder {
				flex: 1;
				display: flex;
				flex-direction: column;
				align-items: center;
				justify-content: center;
				text-align: center;
				padding: 40px;
			}

			.chat-placeholder .empty-icon {
				font-size: 64px;
				color: #8ff5ff;
				margin-bottom: 20px;
				opacity: 0.5;
			}

			.chat-placeholder h2 {
				font-size: 22px;
				color: #fff;
				margin-bottom: 8px;
			}

			.chat-placeholder .empty-message {
				color: #666;
				font-size: 14px;
			}
			.mobile-back-btn {
				display: none;
			}

			@media (max-width: 768px) {
				.messages-page {
					margin-top: 0;
					height: calc(100vh - 56px);
				}
				
				.empty-state {
					padding: 24px;
				}
				
				.empty-icon {
					font-size: 56px;
				}
				
				.empty-state h2 {
					font-size: 22px;
				}
				
				.empty-message {
					font-size: 13px;
					margin-bottom: 24px;
				}
				
				.new-chat-btn {
					padding: 10px 24px;
					font-size: 14px;
				}

				.messages-container {
					position: relative;
					overflow: hidden;
					width: 100%;
					height: 100%;
				}
				
				.conversations-sidebar {
					width: 100%;
					height: 100%;
					position: absolute;
					top: 0;
					left: 0;
					z-index: 1;
					border-right: none;
				}
				
				.chat-area {
					position: absolute;
					top: 0;
					left: 100%;
					width: 100%;
					height: 100%;
					z-index: 2;
					transition: left 0.3s ease;
					background: #0e0e10;
				}
				
				.chat-area.active {
					left: 0;
				}
				
				.mobile-back-btn {
					display: flex;
					align-items: center;
					justify-content: center;
					background: none;
					border: none;
					color: #8ff5ff;
					font-size: 18px;
					cursor: pointer;
					padding: 8px;
					margin-right: 4px;
				}
				
				.expand-btn {
					display: none;
				}
				
				.chat-header {
					padding: 12px 16px;
				}
				
				.chat-header-left {
					gap: 8px;
				}
				
				.chat-avatar {
					width: 34px;
					height: 34px;
					font-size: 14px;
				}
				
				.chat-contact-info h3 {
					font-size: 14px;
				}
				
				.chat-actions {
					gap: 6px;
				}
				
				.action-circle {
					width: 30px;
					height: 30px;
				}
				
				.messages-display {
					padding: 16px;
					gap: 12px;
				}
				
				.message {
					max-width: 85%;
				}
				
				.message-bubble {
					padding: 8px 14px;
					font-size: 13px;
				}
				
				.message-time {
					font-size: 9px;
				}
				
				.message-input-area {
					padding: 10px 12px;
					gap: 8px;
				}
				
				.message-input {
					padding: 10px 14px;
					font-size: 13px;
				}
				
				.send-btn {
					width: 36px;
					height: 36px;
				}
				
				.attachment-btn {
					font-size: 16px;
					padding: 6px;
				}
				
				.conversation-item {
					padding: 14px;
				}
				
				.conversation-avatar {
					width: 40px;
					height: 40px;
					font-size: 15px;
				}
				
				.conversation-name {
					font-size: 14px;
				}
				
				.conversation-preview {
					font-size: 11px;
				}
				
				.chat-placeholder .empty-icon {
					font-size: 48px;
				}
				
				.chat-placeholder h2 {
					font-size: 18px;
				}
				
				.chat-placeholder .empty-message {
					font-size: 12px;
				}
				
				.sidebar-header {
					padding: 14px;
				}
				
				.conversation-search {
					padding: 8px 14px;
					font-size: 13px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="messages-page">
			<section class="empty-state" id="emptyState">
				<div class="empty-icon" aria-hidden="true">
					<i class="fa-solid fa-envelope"></i>
				</div>
				<h2>Your message box is empty</h2>
				<p class="empty-message">Start a conversation with someone on Nexus</p>
				<button class="new-chat-btn" id="newChatBtn">
					<i class="fas fa-plus" aria-hidden="true"></i>
					New Message
				</button>
			</section>

			<dialog class="search-modal" id="searchModal">
				<div class="modal-content">
					<header class="modal-header">
						<h3>Start a new conversation</h3>
						<button class="close-modal" id="closeModalBtn">
							<i class="fas fa-times"></i>
						</button>
					</header>
					<input type="search" id="userSearch" class="user-search-input" placeholder="Search by name...">
					<section class="search-results" id="searchResults">
						<div class="loading">Type to search for users...</div>
					</section>
				</div>
			</dialog>

			<div class="rating-modal" id="ratingModal">
				<div class="rating-content">
					<h3 style="color: #fff; text-align: center;">Rate this user</h3>
					<div class="rating-stars" id="ratingStars">
						<i class="fas fa-star rating-star" data-rating="1"></i>
						<i class="fas fa-star rating-star" data-rating="2"></i>
						<i class="fas fa-star rating-star" data-rating="3"></i>
						<i class="fas fa-star rating-star" data-rating="4"></i>
						<i class="fas fa-star rating-star" data-rating="5"></i>
					</div>
					<textarea id="reviewText" class="review-text" rows="3" placeholder="Write a review (optional)..."></textarea>
					<div style="display: flex; gap: 12px;">
						<button id="submitRatingBtn" class="new-chat-btn" style="flex: 1;">Submit</button>
						<button id="closeRatingBtn" class="new-chat-btn" style="flex: 1; background: #333;">Cancel</button>
					</div>
				</div>
			</div>

			<div class="messages-container" id="messagesContainer" style="display: none;">
				<aside class="conversations-sidebar" id="conversationsSidebar" style="display: flex; flex-direction: column;">
					<header class="sidebar-header">
						<input type="search" id="conversationSearch" class="conversation-search" placeholder="Search conversations...">
					</header>
					<nav class="conversations-list" id="conversationsList" style="flex: 1;"></nav>
					<div style="padding: 10px 16px 25px; display: flex; justify-content: flex-end;">
						<button id="newChatSidebarBtn" style="background: #8ff5ff; color: #0e0e10; border: none; border-radius: 50%; width: 38px; height: 38px; cursor: pointer; font-size: 15px;" title="New Message">
							<i class="fas fa-plus"></i>
						</button>
					</div>
				</aside>

				<section class="chat-area" id="chatArea">
					<div class="chat-placeholder" id="chatPlaceholder">
						<div class="empty-icon">
							<i class="fa-solid fa-comment-dots"></i>
						</div>
						<h2>Have any new messages?</h2>
						<p class="empty-message">Select a conversation to start chatting</p>
					</div>

					<div class="chat-active" id="chatActive" style="display: none; flex-direction: column; flex: 1;">
						<header class="chat-header">
							<div class="chat-header-left">
								<button class="expand-btn" id="expandSidebarBtn">
									<i class="fas fa-bars"></i>
								</button>
								<button class="mobile-back-btn" id="mobileBackBtn">
									<i class="fas fa-arrow-left"></i>
								</button>
								<div class="chat-contact">
									<div class="chat-avatar" id="chatAvatar"><?php echo $avatar_letter; ?></div>
									<div class="chat-contact-info">
										<h3 id="chatContactName">Select a conversation</h3>
									</div>
								</div>
							</div>
							<nav class="chat-actions">
								<button class="action-circle" id="viewProfileBtn" title="View Profile">
									<i class="fas fa-user-circle"></i>
								</button>
								<button class="action-circle" id="rateUserBtn" title="Rate User">
									<i class="fas fa-star"></i>
								</button>
								<button class="action-circle danger" id="reportUserBtn" title="Report & Block">
									<i class="fas fa-flag"></i>
								</button>
								<button class="action-circle danger" id="deleteChatBtn" title="Delete Chat">
									<i class="fas fa-trash"></i>
								</button>
							</nav>
						</header>
						<section class="messages-display" id="messagesDisplay"></section>
						<footer class="message-input-area">
							<button class="attachment-btn" id="attachImageBtn"><i class="fas fa-image"></i></button>
							<button class="attachment-btn" id="attachFileBtn"><i class="fas fa-paperclip"></i></button>
							<input type="text" id="messageInput" class="message-input" placeholder="Type a message...">
							<button class="send-btn" id="sendMessageBtn"><i class="fas fa-paper-plane"></i></button>
						</footer>
					</div>
				</section>

			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			const currentUserId = <?php echo $user_id; ?>;
			let currentConversationId = null;
			let currentOtherUserId = null;
			let currentOtherUserName = '';
			let lastMessageId = 0;
			
			Pusher.logToConsole = true;
			const pusher = new Pusher('a3192cebd4c0ba37141f', {
				cluster: 'eu',
				encrypted: true,
				authorizer: function(channel) {
					return {
						authorize: function(socketId, callback) {
							fetch('pusher-auth.php', {
								method: 'POST',
								headers: {
									'Content-Type': 'application/x-www-form-urlencoded'
								},
								body: 'channel_name=' + encodeURIComponent(channel.name) + '&socket_id=' + encodeURIComponent(socketId)
							})
							.then(function(response) { return response.json(); })
							.then(function(data) {
								callback(null, data);
							})
							.catch(function(error) {
								callback(error, null);
							});
						}
					};
				}
			});
			
			const channel = pusher.subscribe(`private-user-${currentUserId}`);
			channel.bind('new-message', function(data) {
				console.log('New message received:', data);
				if (data.conversation_id === currentConversationId) {
					loadMessages(currentConversationId);
				}
				loadConversations();
			});
			
			function loadConversations() {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: 'action=get_conversations'
				})
				.then(response => response.json())
				.then(data => {
					const container = document.getElementById('conversationsList');
					const emptyState = document.getElementById('emptyState');
					const messagesContainer = document.getElementById('messagesContainer');
					
					if (data.conversations && data.conversations.length > 0) {
						emptyState.style.display = 'none';
						messagesContainer.style.display = 'flex';
						
						container.innerHTML = data.conversations.map(conv => `
							<div class="conversation-item ${currentConversationId == conv.conversation_id ? 'active' : ''}" 
								 data-conversation-id="${conv.conversation_id}" 
								 data-other-user-id="${conv.other_user_id}"
								 data-other-user-name="${conv.other_user_name}">
								<div class="conversation-avatar">${(conv.other_user_name || 'U').charAt(0).toUpperCase()}</div>
								<div class="conversation-info">
									<h3 class="conversation-name">${escapeHtml(conv.other_user_name || 'User')}</h3>
									<p class="conversation-preview">${escapeHtml(conv.last_message || 'No messages yet')}</p>
								</div>
								<time class="conversation-time">${formatTime(conv.last_message_time)}</time>
								${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
							</div>
						`).join('');
						
						attachConversationClickHandlers();
					} else {
						container.innerHTML = '<div style="padding: 20px; text-align: center; color: #888;">No conversations yet</div>';
					}
				});
			}
			
			var conversationSearchInput = document.getElementById('conversationSearch');
			if (conversationSearchInput) {
				conversationSearchInput.addEventListener('input', function() {
					var searchTerm = this.value.trim().toLowerCase();
					var items = document.querySelectorAll('.conversation-item');
					items.forEach(function(item) {
						var name = item.querySelector('.conversation-name').textContent.toLowerCase();
						var preview = item.querySelector('.conversation-preview').textContent.toLowerCase();
						if (name.includes(searchTerm) || preview.includes(searchTerm)) {
							item.style.display = 'flex';
						} else {
							item.style.display = 'none';
						}
					});
				});
			}
			function loadMessages(conversationId) {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=get_messages&conversation_id=${conversationId}`
				})
				.then(response => response.json())
				.then(data => {
					const container = document.getElementById('messagesDisplay');
					if (data.messages && data.messages.length > 0) {
						lastMessageId = data.messages[data.messages.length - 1].message_id;
						container.innerHTML = data.messages.map(msg => `
							<div class="message ${msg.message_type}">
								<div class="message-bubble">
									<p>${escapeHtml(msg.message)}</p>
								</div>
								<time class="message-time">${formatTime(msg.created_at)}</time>
							</div>
						`).join('');
					} else {
						container.innerHTML = '<div style="text-align: center; padding: 40px; color: #888;">No messages yet. Start the conversation!</div>';
					}
					scrollToBottom();
				});
			}
			
			function sendMessage() {
				const messageInput = document.getElementById('messageInput');
				const message = messageInput.value.trim();
				
				if (!message || !currentConversationId) return;
				
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=send_message&conversation_id=${currentConversationId}&message=${encodeURIComponent(message)}&receiver_id=${currentOtherUserId}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						messageInput.value = '';
						loadMessages(currentConversationId);
						loadConversations();
					} else if (data.error) {
						alert(data.error);
					}
				});
			}
			
			// Start new conversation
			function startConversation(otherUserId, otherUserName) {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=start_conversation&other_user_id=${otherUserId}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						currentConversationId = data.conversation_id;
						currentOtherUserId = otherUserId;
						currentOtherUserName = otherUserName;
						document.getElementById('chatContactName').innerText = otherUserName;
						document.getElementById('chatAvatar').innerText = (otherUserName || 'U').charAt(0).toUpperCase();
						loadMessages(currentConversationId);
						loadConversations();
						document.getElementById('searchModal').removeAttribute('open');
					}
				});
			}

			var userSearchInput = document.getElementById('userSearch');
			var searchTimeout;
			if (userSearchInput) {
				userSearchInput.addEventListener('input', function() {
					clearTimeout(searchTimeout);
					searchTimeout = setTimeout(() => {
						const search = this.value.trim();
						if (search.length < 2) {
							document.getElementById('searchResults').innerHTML = '<div class="loading">Type at least 2 characters to search...</div>';
							return;
						}
						
						fetch(window.location.href, {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
								'X-Requested-With': 'XMLHttpRequest'
							},
							body: `action=search_users&search=${encodeURIComponent(search)}`
						})
						.then(response => response.json())
						.then(data => {
							if (data.users && data.users.length > 0) {
								document.getElementById('searchResults').innerHTML = data.users.map(user => `
									<div class="search-result-item" data-user-id="${user.user_id}" data-user-name="${user.full_name}">
										<div class="result-avatar">${(user.full_name || 'U').charAt(0).toUpperCase()}</div>
										<div class="result-info">
											<h4>${escapeHtml(user.full_name)}</h4>
											<p>${user.user_type === 'seller' ? 'Seller' : 'Buyer'}</p>
										</div>
									</div>
								`).join('');
								
								document.querySelectorAll('.search-result-item').forEach(item => {
									item.addEventListener('click', () => {
										const userId = item.getAttribute('data-user-id');
										const userName = item.getAttribute('data-user-name');
										startConversation(userId, userName);
									});
								});
							} else {
								document.getElementById('searchResults').innerHTML = '<div class="loading">No users found</div>';
							}
						});
					}, 300);
				});
			}
			
			function deleteConversation() {
				if (!currentConversationId) return;
				if (confirm('Delete this conversation? This action cannot be undone.')) {
					fetch(window.location.href, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							'X-Requested-With': 'XMLHttpRequest'
						},
						body: `action=delete_conversation&conversation_id=${currentConversationId}`
					})
					.then(() => {
						currentConversationId = null;
						currentOtherUserId = null;
						document.getElementById('chatPlaceholder').style.display = 'flex';
						document.getElementById('chatActive').style.display = 'none';
						document.getElementById('messagesDisplay').innerHTML = '';
						document.getElementById('conversationsSidebar').classList.remove('slide-out');
						document.getElementById('chatArea').classList.remove('active');
						loadConversations();
					});
				}
			}
			
			let selectedRating = 0;
			function openRatingModal() {
				if (!currentOtherUserId) return;
				selectedRating = 0;
				document.querySelectorAll('.rating-star').forEach(star => {
					star.classList.remove('active');
				});
				document.getElementById('reviewText').value = '';
				document.getElementById('ratingModal').classList.add('show');
			}
			
			function submitRating() {
				if (selectedRating === 0) {
					alert('Please select a rating');
					return;
				}
				
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=rate_user&rated_id=${currentOtherUserId}&rating=${selectedRating}&review=${encodeURIComponent(document.getElementById('reviewText').value)}&conversation_id=${currentConversationId}`
				})
				.then(() => {
					alert('Thank you for your rating!');
					document.getElementById('ratingModal').classList.remove('show');
				});
			}
			
			function openReportModal() {
				if (!currentOtherUserId) return;
				document.getElementById('reportReason').value = '';
				document.getElementById('reportModal').classList.add('show');
			}
			
			function submitReport() {
				const reason = document.getElementById('reportReason').value;
				if (!reason) {
					alert('Please provide a reason for reporting');
					return;
				}
				
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=report_user&reported_id=${currentOtherUserId}&reason=${encodeURIComponent(reason)}`
				})
				.then(() => {
					alert('User has been reported and blocked. This conversation will be closed.');
					deleteConversation();
					document.getElementById('reportModal').classList.remove('show');
				});
			}
			
			// Helper functions
			function escapeHtml(str) {
				if (!str) return '';
				return str.replace(/[&<>]/g, function(m) {
					if (m === '&') return '&amp;';
					if (m === '<') return '&lt;';
					if (m === '>') return '&gt;';
					return m;
				});
			}
			
			function formatTime(timestamp) {
				if (!timestamp) return '';
				const date = new Date(timestamp);
				const now = new Date();
				if (date.toDateString() === now.toDateString()) {
					return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
				}
				return date.toLocaleDateString();
			}
			
			function scrollToBottom() {
				const container = document.getElementById('messagesDisplay');
				if (container) {
					container.scrollTop = container.scrollHeight;
				}
			}
			
			function attachConversationClickHandlers() {
				document.querySelectorAll('.conversation-item').forEach(item => {
					item.addEventListener('click', () => {
						currentConversationId = item.getAttribute('data-conversation-id');
						currentOtherUserId = item.getAttribute('data-other-user-id');
						currentOtherUserName = item.getAttribute('data-other-user-name');
						
						document.getElementById('chatPlaceholder').style.display = 'none';
						document.getElementById('chatActive').style.display = 'flex';
						document.getElementById('chatContactName').innerText = currentOtherUserName;
						document.getElementById('chatAvatar').innerText = (currentOtherUserName || 'U').charAt(0).toUpperCase();
						loadMessages(currentConversationId);
						loadConversations();
						
						if (window.innerWidth <= 768) {
							document.getElementById('chatArea').classList.add('active');
						}
					});
				});
			}
			
			document.getElementById('newChatBtn')?.addEventListener('click', () => {
				document.getElementById('searchModal').setAttribute('open', '');
				loadAllSellers(); // Load sellers immediately
			});

			function loadAllSellers() {
				document.getElementById('searchResults').innerHTML = '<div class="loading">Loading sellers...</div>';
				
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: 'action=get_sellers'
				})
				.then(response => response.json())
				.then(data => {
					if (data.users && data.users.length > 0) {
						document.getElementById('searchResults').innerHTML = data.users.map(user => `
							<div class="search-result-item" data-user-id="${user.user_id}" data-user-name="${user.full_name}">
								<div class="result-avatar">${(user.full_name || 'U').charAt(0).toUpperCase()}</div>
								<div class="result-info">
									<h4>${escapeHtml(user.full_name)}</h4>
									<p>Seller${user.store_name ? ' - ' + escapeHtml(user.store_name) : ''}</p>
								</div>
							</div>
						`).join('');
						
						document.querySelectorAll('.search-result-item').forEach(item => {
							item.addEventListener('click', () => {
								const userId = item.getAttribute('data-user-id');
								const userName = item.getAttribute('data-user-name');
								startConversation(userId, userName);
							});
						});
					} else {
						document.getElementById('searchResults').innerHTML = '<div class="loading">No sellers found</div>';
					}
				});
			}

			userSearchInput.addEventListener('input', function() {
				clearTimeout(searchTimeout);
				const search = this.value.trim();
				
				if (search.length === 0) {
					loadAllSellers(); // Show all sellers when search is cleared
					return;
				}
				
				searchTimeout = setTimeout(() => {
					if (search.length < 2) {
						document.getElementById('searchResults').innerHTML = '<div class="loading">Type at least 2 characters to search...</div>';
						return;
					}
					
					fetch(window.location.href, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							'X-Requested-With': 'XMLHttpRequest'
						},
						body: `action=search_users&search=${encodeURIComponent(search)}`
					})
					.then(response => response.json())
					.then(data => {
						if (data.users && data.users.length > 0) {
							document.getElementById('searchResults').innerHTML = data.users.map(user => `
								<div class="search-result-item" data-user-id="${user.user_id}" data-user-name="${user.full_name}">
									<div class="result-avatar">${(user.full_name || 'U').charAt(0).toUpperCase()}</div>
									<div class="result-info">
										<h4>${escapeHtml(user.full_name)}</h4>
										<p>${user.user_type === 'seller' ? 'Seller' : 'Buyer'}</p>
									</div>
								</div>
							`).join('');
							
							document.querySelectorAll('.search-result-item').forEach(item => {
								item.addEventListener('click', () => {
									const userId = item.getAttribute('data-user-id');
									const userName = item.getAttribute('data-user-name');
									startConversation(userId, userName);
								});
							});
						} else {
							document.getElementById('searchResults').innerHTML = '<div class="loading">No users found</div>';
						}
					});
				}, 300);
			});
			
			document.getElementById('closeModalBtn')?.addEventListener('click', () => {
				document.getElementById('searchModal').removeAttribute('open');
			});
			
			document.getElementById('expandSidebarBtn')?.addEventListener('click', () => {
				document.getElementById('conversationsSidebar').classList.toggle('open');
			});
			
			document.getElementById('sendMessageBtn')?.addEventListener('click', sendMessage);
			document.getElementById('messageInput')?.addEventListener('keypress', (e) => {
				if (e.key === 'Enter') sendMessage();
			});
			
			document.getElementById('deleteChatBtn')?.addEventListener('click', deleteConversation);
			document.getElementById('rateUserBtn')?.addEventListener('click', openRatingModal);
			document.getElementById('reportUserBtn')?.addEventListener('click', function() {
				if (currentOtherUserId) {
					window.location.href = 'report.php?user=' + currentOtherUserId;
				}
			});
			
			document.getElementById('submitRatingBtn')?.addEventListener('click', submitRating);
			document.getElementById('closeRatingBtn')?.addEventListener('click', () => {
				document.getElementById('ratingModal').classList.remove('show');
			});
			
			document.getElementById('submitReportBtn')?.addEventListener('click', submitReport);
			document.getElementById('closeReportBtn')?.addEventListener('click', () => {
				document.getElementById('reportModal').classList.remove('show');
			});
			
			document.getElementById('viewProfileBtn')?.addEventListener('click', () => {
				if (currentOtherUserId) {
					window.location.href = `user-profile.php?id=${currentOtherUserId}`;
				}
			});
			
			document.getElementById('newChatSidebarBtn')?.addEventListener('click', function() {
				document.getElementById('searchModal').setAttribute('open', '');
				document.getElementById('userSearch').value = '';
				loadAllSellers();
			});
			
			document.querySelectorAll('.rating-star').forEach(star => {
				star.addEventListener('click', function() {
					selectedRating = parseInt(this.getAttribute('data-rating'));
					document.querySelectorAll('.rating-star').forEach(s => {
						const rating = parseInt(s.getAttribute('data-rating'));
						if (rating <= selectedRating) {
							s.classList.add('active');
						} else {
							s.classList.remove('active');
						}
					});
				});
			});
			
			document.getElementById('mobileBackBtn')?.addEventListener('click', function() {
				document.getElementById('chatArea').classList.remove('active');
			});
			
			loadConversations();
		</script>
	</body>
</html>