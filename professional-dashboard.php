<?php
require_once 'config.php';

// Check if user is logged in and is a professional
if (!is_logged_in() || !is_professional()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get professional's statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE professional_id = ?");
$stmt->execute([$user_id]);
$total_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_appointments FROM appointments WHERE professional_id = ? AND status = 'pending'");
$stmt->execute([$user_id]);
$pending_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as completed_appointments FROM appointments WHERE professional_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as total_services FROM services WHERE professional_id = ?");
$stmt->execute([$user_id]);
$total_services = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as gallery_items FROM gallery WHERE professional_id = ?");
$stmt->execute([$user_id]);
$gallery_items = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE professional_id = ?");
$stmt->execute([$user_id]);
$rating_data = $stmt->fetch();
$avg_rating = $rating_data['avg_rating'] ?: 0;
$review_count = $rating_data['review_count'];

// Get recent appointments
$stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, c.first_name, c.last_name 
                       FROM appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       JOIN users c ON a.client_id = c.user_id 
                       WHERE a.professional_id = ? 
                       ORDER BY a.appointment_date DESC, a.start_time DESC 
                       LIMIT 5");
$stmt->execute([$user_id]);
$recent_appointments = $stmt->fetchAll();

// Get recent messages
$stmt = $pdo->prepare("SELECT m.*, u.first_name, u.last_name 
                       FROM messages m 
                       JOIN users u ON m.sender_id = u.user_id 
                       WHERE m.receiver_id = ? 
                       ORDER BY m.created_at DESC 
                       LIMIT 5");
$stmt->execute([$user_id]);
$recent_messages = $stmt->fetchAll();

// Get today's appointments
$today = date('Y-m-d');
$stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.duration, c.first_name, c.last_name, c.phone 
                       FROM appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       JOIN users c ON a.client_id = c.user_id 
                       WHERE a.professional_id = ? AND a.appointment_date = ? 
                       ORDER BY a.start_time ASC");
$stmt->execute([$user_id, $today]);
$today_appointments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Dashboard - BeautyBook</title>
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
                        <a href="messages.php" class="nav-link">Messages</a>
                        <a href="professional-dashboard.php" class="nav-link active">Dashboard</a>
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

    <!-- Dashboard Content -->
    <section class="dashboard">
        <div class="container">
            <div class="dashboard-header">
                <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['first_name']); ?>!</h1>
                <p>Here's what's happening with your beauty business today</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_appointments; ?></h3>
                        <p>Total Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $pending_appointments; ?></h3>
                        <p>Pending Appointments</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completed_appointments; ?></h3>
                        <p>Completed</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($avg_rating, 1); ?></h3>
                        <p>Average Rating (<?php echo $review_count; ?> reviews)</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="availability.php" class="action-card">
                        <i class="fas fa-calendar-alt"></i>
                        <h3>Manage Availability</h3>
                        <p>Set your working hours and availability</p>
                    </a>
                    <a href="services.php" class="action-card">
                        <i class="fas fa-cut"></i>
                        <h3>Manage Services</h3>
                        <p>Add or edit your services and pricing</p>
                    </a>
                    <a href="gallery-upload.php" class="action-card">
                        <i class="fas fa-images"></i>
                        <h3>Upload Work</h3>
                        <p>Showcase your latest work to clients</p>
                    </a>
                    <a href="route.php" class="action-card">
                        <i class="fas fa-route"></i>
                        <h3>Route Planning</h3>
                        <p>Plan optimal routes to client locations</p>
                    </a>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="dashboard-left">
                    <!-- Today's Appointments -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Today's Appointments</h2>
                            <a href="appointments.php" class="btn btn-sm">View All</a>
                        </div>
                        <div class="appointments-list">
                            <?php if (count($today_appointments) > 0): ?>
                                <?php foreach ($today_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-time">
                                            <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                            <span class="duration"><?php echo $appointment['duration']; ?> min</span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($appointment['service_name']); ?></h4>
                                            <p>Client: <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                                            <?php if ($appointment['phone']): ?>
                                                <p>Phone: <?php echo htmlspecialchars($appointment['phone']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="appointment-status">
                                            <span class="status status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar"></i>
                                    <p>No appointments scheduled for today</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Appointments -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Recent Appointments</h2>
                            <a href="appointments.php" class="btn btn-sm">View All</a>
                        </div>
                        <div class="appointments-list">
                            <?php if (count($recent_appointments) > 0): ?>
                                <?php foreach ($recent_appointments as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <span class="date"><?php echo format_date($appointment['appointment_date']); ?></span>
                                            <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($appointment['service_name']); ?></h4>
                                            <p>Client: <?php echo htmlspecialchars($appointment['first_name'] . ' ' . $appointment['last_name']); ?></p>
                                            <p>Price: <?php echo format_currency($appointment['price']); ?></p>
                                        </div>
                                        <div class="appointment-status">
                                            <span class="status status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-calendar"></i>
                                    <p>No recent appointments</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="dashboard-right">
                    <!-- Business Overview -->
                    <div class="dashboard-section">
                        <h2>Business Overview</h2>
                        <div class="overview-stats">
                            <div class="overview-item">
                                <i class="fas fa-cut"></i>
                                <div>
                                    <h4><?php echo $total_services; ?></h4>
                                    <p>Services Offered</p>
                                </div>
                            </div>
                            <div class="overview-item">
                                <i class="fas fa-images"></i>
                                <div>
                                    <h4><?php echo $gallery_items; ?></h4>
                                    <p>Portfolio Items</p>
                                </div>
                            </div>
                            <div class="overview-item">
                                <i class="fas fa-star"></i>
                                <div>
                                    <h4><?php echo $review_count; ?></h4>
                                    <p>Client Reviews</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Messages -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Recent Messages</h2>
                            <a href="messages.php" class="btn btn-sm">View All</a>
                        </div>
                        <div class="messages-list">
                            <?php if (count($recent_messages) > 0): ?>
                                <?php foreach ($recent_messages as $message): ?>
                                    <div class="message-item">
                                        <div class="message-sender">
                                            <strong><?php echo htmlspecialchars($message['first_name'] . ' ' . $message['last_name']); ?></strong>
                                            <span class="message-time"><?php echo date('M j, g:i A', strtotime($message['created_at'])); ?></span>
                                        </div>
                                        <div class="message-content">
                                            <?php echo htmlspecialchars(substr($message['content'], 0, 100)); ?>
                                            <?php if (strlen($message['content']) > 100): ?>...<?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-comments"></i>
                                    <p>No recent messages</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tips & Resources -->
                    <div class="dashboard-section">
                        <h2>Tips & Resources</h2>
                        <div class="tips-list">
                            <div class="tip-item">
                                <i class="fas fa-lightbulb"></i>
                                <div>
                                    <h4>Complete Your Profile</h4>
                                    <p>Add a professional photo and detailed bio to attract more clients</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-camera"></i>
                                <div>
                                    <h4>Showcase Your Work</h4>
                                    <p>Upload high-quality photos of your work to build trust with potential clients</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h4>Keep Availability Updated</h4>
                                    <p>Regularly update your availability to maximize booking opportunities</p>
                                </div>
                            </div>
                        </div>
                    </div>
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
</body>
</html>

