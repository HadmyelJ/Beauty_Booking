<?php
require_once 'config.php';

// Check if user is logged in
if (!is_logged_in()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

// Handle appointment status updates
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    $appointment_id = $_POST['appointment_id'];
    $action = $_POST['action'];
    
    // Verify appointment belongs to user
    if ($user_role === 'professional') {
        $stmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND professional_id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_id = ? AND client_id = ?");
    }
    $stmt->execute([$appointment_id, $user_id]);
    
    if ($stmt->fetch()) {
        $new_status = '';
        switch ($action) {
            case 'confirm':
                $new_status = 'confirmed';
                break;
            case 'complete':
                $new_status = 'completed';
                break;
            case 'cancel':
                $new_status = 'cancelled';
                break;
        }
        
        if ($new_status) {
            $stmt = $pdo->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
            $stmt->execute([$new_status, $appointment_id]);
            
            $success_msg = "Appointment " . $action . "ed successfully.";
        }
    }
}

// Get appointments based on user role
if ($user_role === 'professional') {
    $stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, s.duration,
                                  c.first_name as client_first_name, c.last_name as client_last_name, c.phone as client_phone, c.email as client_email
                           FROM appointments a 
                           JOIN services s ON a.service_id = s.service_id 
                           JOIN users c ON a.client_id = c.user_id 
                           WHERE a.professional_id = ? 
                           ORDER BY a.appointment_date DESC, a.start_time DESC");
} else {
    $stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, s.duration,
                                  p.first_name as professional_first_name, p.last_name as professional_last_name, p.phone as professional_phone, p.email as professional_email
                           FROM appointments a 
                           JOIN services s ON a.service_id = s.service_id 
                           JOIN users p ON a.professional_id = p.user_id 
                           WHERE a.client_id = ? 
                           ORDER BY a.appointment_date DESC, a.start_time DESC");
}

$stmt->execute([$user_id]);
$appointments = $stmt->fetchAll();

// Group appointments by status
$upcoming = [];
$past = [];
$cancelled = [];

foreach ($appointments as $appointment) {
    $appointment_datetime = strtotime($appointment['appointment_date'] . ' ' . $appointment['start_time']);
    $now = time();
    
    if ($appointment['status'] === 'cancelled') {
        $cancelled[] = $appointment;
    } elseif ($appointment_datetime >= $now && $appointment['status'] !== 'completed') {
        $upcoming[] = $appointment;
    } else {
        $past[] = $appointment;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointments - BeautyBook</title>
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
                        <a href="appointments.php" class="nav-link active">Appointments</a>
                        <a href="messages.php" class="nav-link">Messages</a>
                        <?php if ($user_role === 'professional'): ?>
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

    <!-- Appointments Content -->
    <section class="appointments-section">
        <div class="container">
            <div class="appointments-header">
                <h1>My Appointments</h1>
                <div class="appointments-actions">
                    <?php if ($user_role === 'client'): ?>
                        <a href="new-appointment.php" class="btn btn-primary">Book New Appointment</a>
                    <?php else: ?>
                        <a href="availability.php" class="btn btn-primary">Manage Availability</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <!-- Appointment Tabs -->
            <div class="appointment-tabs">
                <button class="tab-btn active" onclick="showTab('upcoming')">
                    Upcoming (<?php echo count($upcoming); ?>)
                </button>
                <button class="tab-btn" onclick="showTab('past')">
                    Past (<?php echo count($past); ?>)
                </button>
                <button class="tab-btn" onclick="showTab('cancelled')">
                    Cancelled (<?php echo count($cancelled); ?>)
                </button>
            </div>

            <!-- Upcoming Appointments -->
            <div id="upcoming" class="tab-content active">
                <h2>Upcoming Appointments</h2>
                <?php if (count($upcoming) > 0): ?>
                    <div class="appointments-list">
                        <?php foreach ($upcoming as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-date">
                                    <div class="date-display">
                                        <span class="day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></span>
                                    </div>
                                    <div class="time-display">
                                        <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                        <span class="duration"><?php echo $appointment['duration']; ?> min</span>
                                    </div>
                                </div>
                                
                                <div class="appointment-details">
                                    <h3><?php echo htmlspecialchars($appointment['service_name']); ?></h3>
                                    <?php if ($user_role === 'professional'): ?>
                                        <p class="client-info">
                                            <strong>Client:</strong> <?php echo htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']); ?>
                                        </p>
                                        <?php if ($appointment['client_phone']): ?>
                                            <p class="contact-info">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['client_phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="contact-info">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($appointment['client_email']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="professional-info">
                                            <strong>Professional:</strong> <?php echo htmlspecialchars($appointment['professional_first_name'] . ' ' . $appointment['professional_last_name']); ?>
                                        </p>
                                        <?php if ($appointment['professional_phone']): ?>
                                            <p class="contact-info">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($appointment['professional_phone']); ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <p class="price"><strong>Price:</strong> <?php echo format_currency($appointment['price']); ?></p>
                                    
                                    <?php if ($appointment['notes']): ?>
                                        <p class="notes"><strong>Notes:</strong> <?php echo htmlspecialchars($appointment['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="appointment-status">
                                    <span class="status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="appointment-actions">
                                    <?php if ($user_role === 'professional'): ?>
                                        <?php if ($appointment['status'] === 'pending'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="action" value="confirm">
                                                <button type="submit" class="btn btn-sm btn-success">Confirm</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($appointment['status'] === 'confirmed'): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                                <input type="hidden" name="action" value="complete">
                                                <button type="submit" class="btn btn-sm btn-primary">Mark Complete</button>
                                            </form>
                                        <?php endif; ?>
                                        <a href="messages.php?conversation_with=<?php echo $appointment['client_id']; ?>" class="btn btn-sm btn-outline">Message Client</a>
                                    <?php else: ?>
                                        <a href="messages.php?conversation_with=<?php echo $appointment['professional_id']; ?>" class="btn btn-sm btn-outline">Message Professional</a>
                                    <?php endif; ?>
                                    
                                    <?php if (in_array($appointment['status'], ['pending', 'confirmed'])): ?>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                                            <input type="hidden" name="appointment_id" value="<?php echo $appointment['appointment_id']; ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button type="submit" class="btn btn-sm btn-danger">Cancel</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar"></i>
                        <h3>No upcoming appointments</h3>
                        <?php if ($user_role === 'client'): ?>
                            <p>Ready to book your next beauty session?</p>
                            <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                        <?php else: ?>
                            <p>No upcoming appointments from clients</p>
                            <a href="professional-dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Past Appointments -->
            <div id="past" class="tab-content">
                <h2>Past Appointments</h2>
                <?php if (count($past) > 0): ?>
                    <div class="appointments-list">
                        <?php foreach ($past as $appointment): ?>
                            <div class="appointment-card">
                                <div class="appointment-date">
                                    <div class="date-display">
                                        <span class="day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></span>
                                    </div>
                                    <div class="time-display">
                                        <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                        <span class="duration"><?php echo $appointment['duration']; ?> min</span>
                                    </div>
                                </div>
                                
                                <div class="appointment-details">
                                    <h3><?php echo htmlspecialchars($appointment['service_name']); ?></h3>
                                    <?php if ($user_role === 'professional'): ?>
                                        <p class="client-info">
                                            <strong>Client:</strong> <?php echo htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="professional-info">
                                            <strong>Professional:</strong> <?php echo htmlspecialchars($appointment['professional_first_name'] . ' ' . $appointment['professional_last_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="price"><strong>Price:</strong> <?php echo format_currency($appointment['price']); ?></p>
                                </div>
                                
                                <div class="appointment-status">
                                    <span class="status status-<?php echo $appointment['status']; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="appointment-actions">
                                    <?php if ($user_role === 'client' && $appointment['status'] === 'completed'): ?>
                                        <a href="review.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-primary">Leave Review</a>
                                    <?php endif; ?>
                                    <a href="new-appointment.php<?php echo $user_role === 'client' ? '?professional_id=' . $appointment['professional_id'] : ''; ?>" class="btn btn-sm btn-outline">Book Again</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <h3>No past appointments</h3>
                        <p>Your appointment history will appear here</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cancelled Appointments -->
            <div id="cancelled" class="tab-content">
                <h2>Cancelled Appointments</h2>
                <?php if (count($cancelled) > 0): ?>
                    <div class="appointments-list">
                        <?php foreach ($cancelled as $appointment): ?>
                            <div class="appointment-card cancelled">
                                <div class="appointment-date">
                                    <div class="date-display">
                                        <span class="day"><?php echo date('d', strtotime($appointment['appointment_date'])); ?></span>
                                        <span class="month"><?php echo date('M', strtotime($appointment['appointment_date'])); ?></span>
                                    </div>
                                    <div class="time-display">
                                        <span class="time"><?php echo format_time($appointment['start_time']); ?></span>
                                        <span class="duration"><?php echo $appointment['duration']; ?> min</span>
                                    </div>
                                </div>
                                
                                <div class="appointment-details">
                                    <h3><?php echo htmlspecialchars($appointment['service_name']); ?></h3>
                                    <?php if ($user_role === 'professional'): ?>
                                        <p class="client-info">
                                            <strong>Client:</strong> <?php echo htmlspecialchars($appointment['client_first_name'] . ' ' . $appointment['client_last_name']); ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="professional-info">
                                            <strong>Professional:</strong> <?php echo htmlspecialchars($appointment['professional_first_name'] . ' ' . $appointment['professional_last_name']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="price"><strong>Price:</strong> <?php echo format_currency($appointment['price']); ?></p>
                                </div>
                                
                                <div class="appointment-status">
                                    <span class="status status-cancelled">Cancelled</span>
                                </div>
                                
                                <div class="appointment-actions">
                                    <a href="new-appointment.php<?php echo $user_role === 'client' ? '?professional_id=' . $appointment['professional_id'] : ''; ?>" class="btn btn-sm btn-primary">Book Again</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-ban"></i>
                        <h3>No cancelled appointments</h3>
                        <p>Cancelled appointments will appear here</p>
                    </div>
                <?php endif; ?>
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
        function showTab(tabName) {
            // Hide all tab contents
            const tabContents = document.querySelectorAll('.tab-content');
            tabContents.forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            const tabButtons = document.querySelectorAll('.tab-btn');
            tabButtons.forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }
    </script>
</body>
</html>

