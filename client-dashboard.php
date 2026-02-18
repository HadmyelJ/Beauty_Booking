<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!is_logged_in() || !is_client()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Get client's statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_appointments FROM appointments WHERE client_id = ?");
$stmt->execute([$user_id]);
$total_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as upcoming_appointments FROM appointments WHERE client_id = ? AND appointment_date >= CURDATE() AND status NOT IN ('cancelled', 'completed')");
$stmt->execute([$user_id]);
$upcoming_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as completed_appointments FROM appointments WHERE client_id = ? AND status = 'completed'");
$stmt->execute([$user_id]);
$completed_appointments = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) as favorite_professionals FROM (SELECT DISTINCT professional_id FROM appointments WHERE client_id = ?) as favs");
$stmt->execute([$user_id]);
$favorite_professionals = $stmt->fetchColumn();

// Get upcoming appointments
$stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, s.duration, 
                              p.first_name as professional_first_name, p.last_name as professional_last_name, p.phone as professional_phone
                       FROM appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       JOIN users p ON a.professional_id = p.user_id 
                       WHERE a.client_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed')
                       ORDER BY a.appointment_date ASC, a.start_time ASC 
                       LIMIT 5");
$stmt->execute([$user_id]);
$upcoming_appointments_list = $stmt->fetchAll();

// Get recent appointments
$stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, 
                              p.first_name as professional_first_name, p.last_name as professional_last_name
                       FROM appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       JOIN users p ON a.professional_id = p.user_id 
                       WHERE a.client_id = ? 
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

// Get recommended professionals (based on popular services)
$stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image, u.bio,
                              AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count
                       FROM users u 
                       LEFT JOIN reviews r ON u.user_id = r.professional_id 
                       WHERE u.role = 'professional' 
                       GROUP BY u.user_id 
                       ORDER BY avg_rating DESC, review_count DESC 
                       LIMIT 4");
$stmt->execute();
$recommended_professionals = $stmt->fetchAll();

// Get next appointment
$stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.duration, 
                              p.first_name as professional_first_name, p.last_name as professional_last_name, p.phone as professional_phone
                       FROM appointments a 
                       JOIN services s ON a.service_id = s.service_id 
                       JOIN users p ON a.professional_id = p.user_id 
                       WHERE a.client_id = ? AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled', 'completed')
                       ORDER BY a.appointment_date ASC, a.start_time ASC 
                       LIMIT 1");
$stmt->execute([$user_id]);
$next_appointment = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard - BeautyBook</title>
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
                        <a href="client-dashboard.php" class="nav-link active">Dashboard</a>
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
                <p>Ready for your next beauty appointment?</p>
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
                        <h3><?php echo $upcoming_appointments; ?></h3>
                        <p>Upcoming Appointments</p>
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
                        <i class="fas fa-heart"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $favorite_professionals; ?></h3>
                        <p>Professionals Visited</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2>Quick Actions</h2>
                <div class="actions-grid">
                    <a href="browse-professionals.php" class="action-card">
                        <i class="fas fa-search"></i>
                        <h3>Find Professionals</h3>
                        <p>Discover beauty professionals near you</p>
                    </a>
                    <a href="new-appointment.php" class="action-card">
                        <i class="fas fa-calendar-plus"></i>
                        <h3>Book Appointment</h3>
                        <p>Schedule your next beauty session</p>
                    </a>
                    <a href="gallery.php" class="action-card">
                        <i class="fas fa-images"></i>
                        <h3>Browse Gallery</h3>
                        <p>Get inspired by professional work</p>
                    </a>
                    <a href="services.php" class="action-card">
                        <i class="fas fa-cut"></i>
                        <h3>Explore Services</h3>
                        <p>Discover new beauty services</p>
                    </a>
                </div>
            </div>

            <div class="dashboard-content">
                <div class="dashboard-left">
                    <!-- Next Appointment -->
                    <?php if ($next_appointment): ?>
                    <div class="dashboard-section highlight">
                        <h2>Next Appointment</h2>
                        <div class="next-appointment">
                            <div class="appointment-date">
                                <div class="date-display">
                                    <span class="day"><?php echo date('d', strtotime($next_appointment['appointment_date'])); ?></span>
                                    <span class="month"><?php echo date('M', strtotime($next_appointment['appointment_date'])); ?></span>
                                </div>
                                <div class="time-display">
                                    <span class="time"><?php echo format_time($next_appointment['start_time']); ?></span>
                                    <span class="duration"><?php echo $next_appointment['duration']; ?> min</span>
                                </div>
                            </div>
                            <div class="appointment-details">
                                <h3><?php echo htmlspecialchars($next_appointment['service_name']); ?></h3>
                                <p class="professional">with <?php echo htmlspecialchars($next_appointment['professional_first_name'] . ' ' . $next_appointment['professional_last_name']); ?></p>
                                <?php if ($next_appointment['professional_phone']): ?>
                                    <p class="phone"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($next_appointment['professional_phone']); ?></p>
                                <?php endif; ?>
                                <div class="appointment-actions">
                                    <a href="appointment-details.php?id=<?php echo $next_appointment['appointment_id']; ?>" class="btn btn-sm">View Details</a>
                                    <a href="messages.php?conversation_with=<?php echo $next_appointment['professional_id']; ?>" class="btn btn-sm btn-outline">Message</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Upcoming Appointments -->
                    <div class="dashboard-section">
                        <div class="section-header">
                            <h2>Upcoming Appointments</h2>
                            <a href="appointments.php" class="btn btn-sm">View All</a>
                        </div>
                        <div class="appointments-list">
                            <?php if (count($upcoming_appointments_list) > 0): ?>
                                <?php foreach ($upcoming_appointments_list as $appointment): ?>
                                    <div class="appointment-item">
                                        <div class="appointment-date">
                                            <span class="date"><?php echo format_date($appointment['appointment_date']); ?></span>
                                            <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                        </div>
                                        <div class="appointment-details">
                                            <h4><?php echo htmlspecialchars($appointment['service_name']); ?></h4>
                                            <p>with <?php echo htmlspecialchars($appointment['professional_first_name'] . ' ' . $appointment['professional_last_name']); ?></p>
                                            <p>Duration: <?php echo $appointment['duration']; ?> min | Price: <?php echo format_currency($appointment['price']); ?></p>
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
                                    <p>No upcoming appointments</p>
                                    <a href="browse-professionals.php" class="btn btn-primary">Book Your First Appointment</a>
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
                                            <p>with <?php echo htmlspecialchars($appointment['professional_first_name'] . ' ' . $appointment['professional_last_name']); ?></p>
                                            <p>Price: <?php echo format_currency($appointment['price']); ?></p>
                                        </div>
                                        <div class="appointment-status">
                                            <span class="status status-<?php echo $appointment['status']; ?>">
                                                <?php echo ucfirst($appointment['status']); ?>
                                            </span>
                                            <?php if ($appointment['status'] === 'completed'): ?>
                                                <a href="review.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-xs">Leave Review</a>
                                            <?php endif; ?>
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
                    <!-- Recommended Professionals -->
                    <div class="dashboard-section">
                        <h2>Recommended for You</h2>
                        <div class="professionals-list">
                            <?php foreach ($recommended_professionals as $professional): ?>
                                <div class="professional-item">
                                    <div class="professional-avatar">
                                        <img src="<?php echo $professional['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?>">
                                    </div>
                                    <div class="professional-info">
                                        <h4><?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?></h4>
                                        <div class="rating">
                                            <?php 
                                            $rating = $professional['avg_rating'] ?: 0;
                                            for ($i = 1; $i <= 5; $i++): 
                                                if ($i <= $rating): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php elseif ($i - 0.5 <= $rating): ?>
                                                    <i class="fas fa-star-half-alt"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif;
                                            endfor; ?>
                                            <span>(<?php echo $professional['review_count']; ?>)</span>
                                        </div>
                                        <a href="professional-profile.php?id=<?php echo $professional['user_id']; ?>" class="btn btn-xs">View Profile</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

                    <!-- Beauty Tips -->
                    <div class="dashboard-section">
                        <h2>Beauty Tips</h2>
                        <div class="tips-list">
                            <div class="tip-item">
                                <i class="fas fa-lightbulb"></i>
                                <div>
                                    <h4>Prepare for Your Appointment</h4>
                                    <p>Arrive 10 minutes early and come with clean hair/skin for best results</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-camera"></i>
                                <div>
                                    <h4>Bring Inspiration Photos</h4>
                                    <p>Show your professional exactly what you're looking for with reference images</p>
                                </div>
                            </div>
                            <div class="tip-item">
                                <i class="fas fa-star"></i>
                                <div>
                                    <h4>Leave Reviews</h4>
                                    <p>Help other clients by sharing your experience with professionals</p>
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

