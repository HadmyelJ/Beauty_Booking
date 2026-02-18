<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$conversation_with = $_GET['conversation_with'] ?? '';

// Handle sending new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
    $receiver_id = $_POST['receiver_id'];
    $content = trim($_POST['content']);
    
    if (!empty($content) && !empty($receiver_id)) {
        $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
        if ($stmt->execute([$user_id, $receiver_id, $content])) {
            // Redirect to prevent form resubmission
            redirect("messages.php?conversation_with=$receiver_id");
        }
    }
}

// Get all conversations
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN m.sender_id = ? THEN m.receiver_id 
            ELSE m.sender_id 
        END as other_user_id,
        u.first_name, u.last_name, u.profile_image, u.role,
        (SELECT content FROM messages m2 
         WHERE (m2.sender_id = ? AND m2.receiver_id = other_user_id) 
            OR (m2.receiver_id = ? AND m2.sender_id = other_user_id)
         ORDER BY m2.created_at DESC LIMIT 1) as last_message,
        (SELECT created_at FROM messages m2 
         WHERE (m2.sender_id = ? AND m2.receiver_id = other_user_id) 
            OR (m2.receiver_id = ? AND m2.sender_id = other_user_id)
         ORDER BY m2.created_at DESC LIMIT 1) as last_message_time,
        (SELECT COUNT(*) FROM messages m2 
         WHERE m2.sender_id = other_user_id AND m2.receiver_id = ? AND m2.is_read = 0) as unread_count
    FROM messages m
    JOIN users u ON u.user_id = CASE 
        WHEN m.sender_id = ? THEN m.receiver_id 
        ELSE m.sender_id 
    END
    WHERE m.sender_id = ? OR m.receiver_id = ?
    ORDER BY last_message_time DESC
");
$stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id]);
$conversations = $stmt->fetchAll();

// Get messages for selected conversation
$messages = [];
$other_user = null;
if (!empty($conversation_with)) {
    // Get other user details
    $stmt = $pdo->prepare("SELECT user_id, first_name, last_name, profile_image, role FROM users WHERE user_id = ?");
    $stmt->execute([$conversation_with]);
    $other_user = $stmt->fetch();
    
    if ($other_user) {
        // Get messages
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name, u.last_name 
            FROM messages m 
            JOIN users u ON m.sender_id = u.user_id 
            WHERE (m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $conversation_with, $conversation_with, $user_id]);
        $messages = $stmt->fetchAll();
        
        // Mark messages as read
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?");
        $stmt->execute([$conversation_with, $user_id]);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - BeautyBook</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Montserrat:wght@300;400;500;600;700&family=Open+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="index.php" class="logo">BeautyBook</a>
                <div class="nav-menu">
                    <div class="user-links">
                        <a href="appointments.php" class="nav-link">Appointments</a>
                        <a href="messages.php" class="nav-link active">Messages</a>
                        <?php if (is_professional()): ?>
                            <a href="professional-dashboard.php" class="nav-link">Dashboard</a>
                        <?php else: ?>
                            <a href="client-dashboard.php" class="nav-link">Dashboard</a>
                        <?php endif; ?>
                        <a href="profile.php" class="nav-link">Profile</a>
                        <a href="logout.php" class="nav-link">Logout</a>
                    </div>
                </div>
                <div class="nav-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Messages Section -->
    <section class="messages-section">
        <div class="container">
            <div class="messages-container">
                <!-- Conversations List -->
                <div class="conversations-panel">
                    <div class="conversations-header">
                        <h2>Messages</h2>
                        <?php if (is_client()): ?>
                            <a href="browse-professionals.php" class="btn btn-sm">Find Professionals</a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="conversations-list">
                        <?php if (count($conversations) > 0): ?>
                            <?php foreach ($conversations as $conversation): ?>
                                <div class="conversation-item <?php echo ($conversation_with == $conversation['other_user_id']) ? 'active' : ''; ?>" 
                                     onclick="selectConversation(<?php echo $conversation['other_user_id']; ?>)">
                                    <div class="conversation-avatar">
                                        <img src="<?php echo $conversation['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" 
                                             alt="<?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?>">
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-info">
                                        <div class="conversation-header">
                                            <h4><?php echo htmlspecialchars($conversation['first_name'] . ' ' . $conversation['last_name']); ?></h4>
                                            <span class="conversation-time"><?php echo date('M j', strtotime($conversation['last_message_time'])); ?></span>
                                        </div>
                                        <div class="conversation-preview">
                                            <p><?php echo htmlspecialchars(substr($conversation['last_message'] ?: 'No messages yet', 0, 50)); ?><?php echo strlen($conversation['last_message'] ?: '') > 50 ? '...' : ''; ?></p>
                                            <span class="user-role"><?php echo ucfirst($conversation['role']); ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-conversations">
                                <i class="fas fa-comments"></i>
                                <h3>No conversations yet</h3>
                                <p>Start messaging with professionals or clients</p>
                                <?php if (is_client()): ?>
                                    <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="chat-panel">
                    <?php if ($other_user): ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <div class="chat-user-info">
                                <img src="<?php echo $other_user['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" 
                                     alt="<?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?>" 
                                     class="chat-avatar">
                                <div>
                                    <h3><?php echo htmlspecialchars($other_user['first_name'] . ' ' . $other_user['last_name']); ?></h3>
                                    <span class="user-role"><?php echo ucfirst($other_user['role']); ?></span>
                                </div>
                            </div>
                            <div class="chat-actions">
                                <a href="professional-profile.php?id=<?php echo $other_user['user_id']; ?>" class="btn btn-sm btn-outline">View Profile</a>
                                <?php if (is_client() && $other_user['role'] === 'professional'): ?>
                                    <a href="new-appointment.php?professional_id=<?php echo $other_user['user_id']; ?>" class="btn btn-sm btn-primary">Book Appointment</a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Messages -->
                        <div class="chat-messages" id="chatMessages">
                            <?php if (count($messages) > 0): ?>
                                <?php foreach ($messages as $message): ?>
                                    <div class="message <?php echo ($message['sender_id'] == $user_id) ? 'sent' : 'received'; ?>">
                                        <div class="message-content">
                                            <p><?php echo nl2br(htmlspecialchars($message['content'])); ?></p>
                                            <span class="message-time"><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="no-messages">
                                    <i class="fas fa-comment"></i>
                                    <p>No messages yet. Start the conversation!</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Message Input -->
                        <div class="chat-input">
                            <form method="post" class="message-form">
                                <input type="hidden" name="receiver_id" value="<?php echo $other_user['user_id']; ?>">
                                <div class="input-group">
                                    <textarea name="content" placeholder="Type your message..." rows="1" required></textarea>
                                    <button type="submit" name="send_message" class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- No Conversation Selected -->
                        <div class="no-conversation">
                            <i class="fas fa-comments"></i>
                            <h3>Select a conversation</h3>
                            <p>Choose a conversation from the left to start messaging</p>
                            <?php if (is_client()): ?>
                                <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>BeautyBook</h3>
                    <p>Your trusted platform for beauty services and professional connections.</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 BeautyBook. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        function selectConversation(userId) {
            window.location.href = `messages.php?conversation_with=${userId}`;
        }

        // Auto-scroll to bottom of messages
        document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Auto-resize textarea
            const textarea = document.querySelector('.message-form textarea');
            if (textarea) {
                textarea.addEventListener('input', function() {
                    this.style.height = 'auto';
                    this.style.height = Math.min(this.scrollHeight, 120) + 'px';
                });

                // Submit on Enter (but not Shift+Enter)
                textarea.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.closest('form').submit();
                    }
                });
            }
        });

        // Refresh messages periodically (every 30 seconds)
        <?php if ($other_user): ?>
        setInterval(function() {
            // Only refresh if user is still on the same conversation
            const currentUrl = new URL(window.location.href);
            const conversationWith = currentUrl.searchParams.get('conversation_with');
            if (conversationWith === '<?php echo $other_user['user_id']; ?>') {
                // You could implement AJAX refresh here
                // For now, we'll just reload the page if there are new messages
            }
        }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>

