<?php
require_once 'config.php';

// Get featured professionals
$stmt = $pdo->prepare("SELECT u.user_id, u.first_name, u.last_name, u.profile_image, u.bio, 
                              AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count
                       FROM users u 
                       LEFT JOIN reviews r ON u.user_id = r.professional_id 
                       WHERE u.role = 'professional' 
                       GROUP BY u.user_id 
                       ORDER BY avg_rating DESC, review_count DESC 
                       LIMIT 6");
$stmt->execute();
$featured_professionals = $stmt->fetchAll();

// Get popular services
$stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name, COUNT(a.appointment_id) as booking_count
                       FROM services s 
                       JOIN users u ON s.professional_id = u.user_id 
                       LEFT JOIN appointments a ON s.service_id = a.service_id 
                       GROUP BY s.service_id 
                       ORDER BY booking_count DESC 
                       LIMIT 8");
$stmt->execute();
$popular_services = $stmt->fetchAll();

// Get recent gallery items
$stmt = $pdo->prepare("SELECT g.*, u.first_name, u.last_name 
                       FROM gallery g 
                       JOIN users u ON g.professional_id = u.user_id 
                       ORDER BY g.created_at DESC 
                       LIMIT 12");
$stmt->execute();
$recent_gallery = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BeautyBook - Beauty Professionals Booking Platform</title>
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

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-slider">
            <div class="hero-slide active" style="background-image: url('https://images.unsplash.com/photo-1560066984-138dadb4c035?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80');"></div>
            <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1607779097040-26e80aa78e66?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80');"></div>
            <div class="hero-slide" style="background-image: url('https://images.unsplash.com/photo-1516975080664-ed2fc6a32937?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1740&q=80');"></div>
        </div>
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title">Your Beauty Journey Begins Here</h1>
                <p class="hero-text">Connect with top beauty professionals, book appointments, and showcase your transformation. Whether you're a client seeking services or a professional growing your business, BeautyBook has you covered.</p>
                <div class="hero-cta">
                    <?php if (!is_logged_in()): ?>
                        <a href="register.php?role=client" class="btn btn-primary">Book Now</a>
                        <a href="register.php?role=professional" class="btn btn-secondary">Join as Professional</a>
                    <?php else: ?>
                        <?php if (is_client()): ?>
                            <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                            <a href="client-dashboard.php" class="btn btn-secondary">My Dashboard</a>
                        <?php else: ?>
                            <a href="professional-dashboard.php" class="btn btn-primary">My Dashboard</a>
                            <a href="gallery-upload.php" class="btn btn-secondary">Upload Work</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Why Choose BeautyBook?</h2>
                <p class="section-subtitle">Everything you need for your beauty business and appointments</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3>Easy Scheduling</h3>
                    <p>Book appointments with your favorite beauty professionals in just a few clicks. Real-time availability and instant confirmation.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-route"></i>
                    </div>
                    <h3>Route Optimization</h3>
                    <p>For mobile professionals, get optimized routes to client locations with real-time traffic updates and time estimates.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Direct Messaging</h3>
                    <p>Communicate directly with clients or professionals. Share inspiration, discuss requirements, and stay connected.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <h3>Secure Payments</h3>
                    <p>Safe and secure payment processing with multiple payment options. Automatic invoicing and payment tracking.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-images"></i>
                    </div>
                    <h3>Portfolio Showcase</h3>
                    <p>Professionals can showcase their work with beautiful galleries. Clients can browse and get inspired.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <h3>Reviews & Ratings</h3>
                    <p>Build trust with authentic reviews and ratings. Help others discover the best beauty professionals.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- For Clients Section -->
    <section class="for-clients">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="content">
                        <h2>For Clients</h2>
                        <p>Discover and book with the best beauty professionals in your area. From haircuts to makeup, skincare to nails - find exactly what you need.</p>
                        <ul class="benefits-list">
                            <li><i class="fas fa-check"></i> Browse verified professionals</li>
                            <li><i class="fas fa-check"></i> View portfolios and reviews</li>
                            <li><i class="fas fa-check"></i> Book appointments instantly</li>
                            <li><i class="fas fa-check"></i> Secure payment processing</li>
                            <li><i class="fas fa-check"></i> Direct communication</li>
                        </ul>
                        <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="image-grid">
                        <img src="https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Beauty client" class="grid-img">
                        <img src="https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Beauty treatment" class="grid-img">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- For Professionals Section -->
    <section class="for-professionals">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <div class="image-grid">
                        <img src="https://images.unsplash.com/photo-1562322140-8baeececf3df?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Beauty professional" class="grid-img">
                        <img src="https://images.unsplash.com/photo-1633681926022-84c23e8cb2d6?ixlib=rb-4.0.3&auto=format&fit=crop&w=500&q=80" alt="Professional tools" class="grid-img">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="content">
                        <h2>For Professionals</h2>
                        <p>Grow your beauty business with our comprehensive platform. Manage appointments, showcase your work, and connect with new clients.</p>
                        <ul class="benefits-list">
                            <li><i class="fas fa-check"></i> Manage your calendar and availability</li>
                            <li><i class="fas fa-check"></i> Showcase your portfolio</li>
                            <li><i class="fas fa-check"></i> Get route optimization for mobile services</li>
                            <li><i class="fas fa-check"></i> Secure payment collection</li>
                            <li><i class="fas fa-check"></i> Build client relationships</li>
                        </ul>
                        <a href="register.php?role=professional" class="btn btn-primary">Join as Professional</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Professionals -->
    <?php if (count($featured_professionals) > 0): ?>
    <section class="featured-professionals">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Featured Professionals</h2>
                <p class="section-subtitle">Meet our top-rated beauty professionals</p>
            </div>
            <div class="professionals-grid">
                <?php foreach ($featured_professionals as $professional): ?>
                <div class="professional-card">
                    <div class="professional-image">
                        <img src="<?php echo $professional['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=300&q=80'; ?>" alt="<?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?>">
                    </div>
                    <div class="professional-info">
                        <h3><?php echo htmlspecialchars($professional['first_name'] . ' ' . $professional['last_name']); ?></h3>
                        <p><?php echo htmlspecialchars(substr($professional['bio'] ?: 'Beauty Professional', 0, 100)); ?></p>
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
                            <span>(<?php echo $professional['review_count']; ?> reviews)</span>
                        </div>
                        <a href="professional-profile.php?id=<?php echo $professional['user_id']; ?>" class="btn btn-sm">View Profile</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Popular Services -->
    <?php if (count($popular_services) > 0): ?>
    <section class="popular-services">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Popular Services</h2>
                <p class="section-subtitle">Most booked services on our platform</p>
            </div>
            <div class="services-grid">
                <?php foreach ($popular_services as $service): ?>
                <div class="service-card">
                    <div class="service-info">
                        <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p class="service-provider">by <?php echo htmlspecialchars($service['first_name'] . ' ' . $service['last_name']); ?></p>
                        <p class="service-description"><?php echo htmlspecialchars(substr($service['description'] ?: 'Professional service', 0, 100)); ?></p>
                        <div class="service-details">
                            <span class="duration"><i class="fas fa-clock"></i> <?php echo $service['duration']; ?> min</span>
                            <span class="price"><?php echo format_currency($service['price']); ?></span>
                        </div>
                        <a href="book-appointment.php?service_id=<?php echo $service['service_id']; ?>" class="btn btn-sm">Book Now</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Gallery Preview -->
    <?php if (count($recent_gallery) > 0): ?>
    <section class="gallery-preview">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Recent Work</h2>
                <p class="section-subtitle">Latest transformations from our professionals</p>
            </div>
            <div class="gallery-grid">
                <?php foreach ($recent_gallery as $item): ?>
                <div class="gallery-item">
                    <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?: 'Beauty work'); ?>">
                    <div class="gallery-overlay">
                        <div class="gallery-info">
                            <h4><?php echo htmlspecialchars($item['title'] ?: 'Beautiful Work'); ?></h4>
                            <p>by <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center">
                <a href="gallery.php" class="btn btn-outline">View All Work</a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Transform Your Beauty Experience?</h2>
                <p>Join thousands of satisfied clients and professionals on BeautyBook</p>
                <div class="cta-buttons">
                    <?php if (!is_logged_in()): ?>
                        <a href="register.php?role=client" class="btn btn-primary">Get Started as Client</a>
                        <a href="register.php?role=professional" class="btn btn-secondary">Join as Professional</a>
                    <?php else: ?>
                        <?php if (is_client()): ?>
                            <a href="browse-professionals.php" class="btn btn-primary">Find Professionals</a>
                        <?php else: ?>
                            <a href="professional-dashboard.php" class="btn btn-primary">Go to Dashboard</a>
                        <?php endif; ?>
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
        // Hero slider functionality
        document.addEventListener('DOMContentLoaded', function() {
            const slides = document.querySelectorAll('.hero-slide');
            let currentSlide = 0;

            function nextSlide() {
                slides[currentSlide].classList.remove('active');
                currentSlide = (currentSlide + 1) % slides.length;
                slides[currentSlide].classList.add('active');
            }

            // Auto-advance slides every 5 seconds
            setInterval(nextSlide, 5000);

            // Mobile navigation toggle
            const navToggle = document.querySelector('.nav-toggle');
            const navMenu = document.querySelector('.nav-menu');

            navToggle.addEventListener('click', function() {
                navMenu.classList.toggle('active');
            });
        });
    </script>
</body>
</html>

