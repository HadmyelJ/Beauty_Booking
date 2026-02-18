<?php
require_once 'config.php';

// Get search parameters
$search = $_GET['search'] ?? '';
$specialization = $_GET['specialization'] ?? '';
$location = $_GET['location'] ?? '';

// Build the query
$sql = "SELECT u.user_id, u.first_name, u.last_name, u.profile_image, u.bio, u.phone,
               AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count,
               GROUP_CONCAT(DISTINCT s.name SEPARATOR ', ') as services
        FROM users u 
        LEFT JOIN reviews r ON u.user_id = r.professional_id 
        LEFT JOIN services s ON u.user_id = s.professional_id
        WHERE u.role = 'professional'";

$params = [];

if (!empty($search)) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.bio LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if (!empty($specialization)) {
    $sql .= " AND s.name LIKE ?";
    $params[] = "%$specialization%";
}

$sql .= " GROUP BY u.user_id ORDER BY avg_rating DESC, review_count DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$professionals = $stmt->fetchAll();

// Get all unique specializations for filter
$stmt = $pdo->prepare("SELECT DISTINCT name FROM services ORDER BY name");
$stmt->execute();
$all_specializations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Professionals - BeautyBook</title>
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
                    <?php if (is_logged_in()): ?>
                        <div class="user-links">
                            <a href="appointments.php" class="nav-link">Appointments</a>
                            <a href="messages.php" class="nav-link">Messages</a>
                            <?php if (is_professional()): ?>
                                <a href="professional-dashboard.php" class="nav-link">Dashboard</a>
                            <?php else: ?>
                                <a href="client-dashboard.php" class="nav-link">Dashboard</a>
                            <?php endif; ?>
                            <a href="profile.php" class="nav-link">Profile</a>
                            <a href="logout.php" class="nav-link">Logout</a>
                        </div>
                    <?php else: ?>
                        <div class="auth-links">
                            <a href="login.php" class="nav-link">Login</a>
                            <a href="register.php" class="btn">Sign Up</a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="nav-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Search Section -->
    <section class="search-section">
        <div class="container">
            <div class="search-header">
                <h1>Find Your Perfect Beauty Professional</h1>
                <p>Discover talented professionals in your area</p>
            </div>
            
            <form class="search-form" method="GET" action="">
                <div class="search-row">
                    <div class="search-field">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name or description..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="search-field">
                        <i class="fas fa-cut"></i>
                        <select name="specialization">
                            <option value="">All Services</option>
                            <?php foreach ($all_specializations as $spec): ?>
                                <option value="<?php echo htmlspecialchars($spec['name']); ?>" <?php echo ($specialization === $spec['name']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($spec['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="search-field">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" name="location" placeholder="Location" value="<?php echo htmlspecialchars($location); ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Search</button>
                </div>
            </form>
        </div>
    </section>

    <!-- Professionals Grid -->
    <section class="professionals-section">
        <div class="container">
            <div class="section-header">
                <h2>Beauty Professionals</h2>
                <p><?php echo count($professionals); ?> professionals found</p>
            </div>

            <?php if (count($professionals) > 0): ?>
                <div class="professionals-grid">
                    <?php foreach ($professionals as $professional): ?>
                        <div class="professional-card">
                            <div class="professional-image">
                                <img src="<?php echo $professional['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80'; ?>" alt="<?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?>">
                                <div class="professional-overlay">
                                    <a href="professional-profile.php?id=<?php echo $professional['user_id']; ?>" class="btn btn-white">View Profile</a>
                                </div>
                            </div>
                            <div class="professional-info">
                                <h3><?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?></h3>
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
                                    <span class="rating-text"><?php echo number_format($rating, 1); ?> (<?php echo $professional['review_count']; ?> reviews)</span>
                                </div>
                                <p class="professional-bio"><?php echo htmlspecialchars(substr($professional['bio'] ?: 'Professional beauty services', 0, 120)); ?><?php echo strlen($professional['bio'] ?: '') > 120 ? '...' : ''; ?></p>
                                <?php if ($professional['services']): ?>
                                    <div class="services-tags">
                                        <?php 
                                        $services = explode(', ', $professional['services']);
                                        $displayed_services = array_slice($services, 0, 3);
                                        foreach ($displayed_services as $service): ?>
                                            <span class="service-tag"><?php echo htmlspecialchars($service); ?></span>
                                        <?php endforeach; ?>
                                        <?php if (count($services) > 3): ?>
                                            <span class="service-tag more">+<?php echo count($services) - 3; ?> more</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="professional-actions">
                                    <a href="professional-profile.php?id=<?php echo $professional['user_id']; ?>" class="btn btn-outline">View Profile</a>
                                    <?php if (is_logged_in() && is_client()): ?>
                                        <a href="new-appointment.php?professional_id=<?php echo $professional['user_id']; ?>" class="btn btn-primary">Book Now</a>
                                    <?php else: ?>
                                        <a href="register.php?role=client" class="btn btn-primary">Book Now</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-search"></i>
                    <h3>No professionals found</h3>
                    <p>Try adjusting your search criteria or browse all professionals</p>
                    <a href="browse-professionals.php" class="btn btn-primary">View All Professionals</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CTA Section -->
    <?php if (!is_logged_in()): ?>
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Book Your Beauty Appointment?</h2>
                <p>Join BeautyBook today and connect with amazing beauty professionals</p>
                <div class="cta-buttons">
                    <a href="register.php?role=client" class="btn btn-primary">Sign Up as Client</a>
                    <a href="register.php?role=professional" class="btn btn-secondary">Join as Professional</a>
                </div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>BeautyBook</h3>
                    <p>Your trusted platform for beauty services and professional connections.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-facebook"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                    </div>
                </div>
                <div class="footer-section">
                    <h4>For Clients</h4>
                    <ul>
                        <li><a href="browse-professionals.php">Find Professionals</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="gallery.php">Gallery</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>For Professionals</h4>
                    <ul>
                        <li><a href="register.php?role=professional">Join Us</a></li>
                        <li><a href="professional-dashboard.php">Dashboard</a></li>
                        <li><a href="gallery-upload.php">Upload Work</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h4>Support</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Contact</a></li>
                        <li><a href="help.php">Help Center</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 BeautyBook. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        // Mobile navigation toggle
        document.addEventListener('DOMContentLoaded', function() {
            const navToggle = document.querySelector('.nav-toggle');
            const navMenu = document.querySelector('.nav-menu');

            navToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
        });
    </script>
</body>
</html>

