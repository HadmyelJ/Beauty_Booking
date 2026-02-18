<?php
require_once 'config.php';

// Get filter parameters
$professional_id = $_GET['professional_id'] ?? '';
$category = $_GET['category'] ?? '';

// Build the query
$sql = "SELECT g.*, u.first_name, u.last_name, u.profile_image as professional_image
        FROM gallery g 
        JOIN users u ON g.professional_id = u.user_id 
        WHERE 1=1";

$params = [];

if (!empty($professional_id)) {
    $sql .= " AND g.professional_id = ?";
    $params[] = $professional_id;
}

if (!empty($category)) {
    $sql .= " AND g.category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY g.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$gallery_items = $stmt->fetchAll();

// Get all professionals for filter
$stmt = $pdo->prepare("SELECT DISTINCT u.user_id, u.first_name, u.last_name 
                       FROM users u 
                       JOIN gallery g ON u.user_id = g.professional_id 
                       WHERE u.role = 'professional' 
                       ORDER BY u.first_name, u.last_name");
$stmt->execute();
$professionals = $stmt->fetchAll();

// Get all categories for filter
$stmt = $pdo->prepare("SELECT DISTINCT category FROM gallery WHERE category IS NOT NULL AND category != '' ORDER BY category");
$stmt->execute();
$categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - BeautyBook</title>
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

    <!-- Gallery Header -->
    <section class="gallery-header">
        <div class="container">
            <div class="gallery-title">
                <h1>Beauty Gallery</h1>
                <p>Discover amazing transformations and get inspired</p>
            </div>
            
            <?php if (is_logged_in() && is_professional()): ?>
                <div class="gallery-actions">
                    <a href="gallery-upload.php" class="btn btn-primary">Upload Your Work</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Gallery Filters -->
    <section class="gallery-filters">
        <div class="container">
            <form class="filter-form" method="GET" action="">
                <div class="filter-row">
                    <div class="filter-field">
                        <label for="professional_id">Professional</label>
                        <select name="professional_id" id="professional_id">
                            <option value="">All Professionals</option>
                            <?php foreach ($professionals as $prof): ?>
                                <option value="<?php echo $prof['user_id']; ?>" <?php echo ($professional_id == $prof['user_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-field">
                        <label for="category">Category</label>
                        <select name="category" id="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo ($category === $cat['category']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(ucfirst($cat['category'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="gallery.php" class="btn btn-outline">Clear</a>
                </div>
            </form>
        </div>
    </section>

    <!-- Gallery Grid -->
    <section class="gallery-section">
        <div class="container">
            <?php if (count($gallery_items) > 0): ?>
                <div class="gallery-grid">
                    <?php foreach ($gallery_items as $item): ?>
                        <div class="gallery-item" onclick="openLightbox('<?php echo htmlspecialchars($item['image_path']); ?>', '<?php echo htmlspecialchars($item['title'] ?: 'Beautiful Work'); ?>', '<?php echo htmlspecialchars($item['description'] ?: ''); ?>', '<?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?>', <?php echo $item['professional_id']; ?>)">
                            <div class="gallery-image">
                                <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?: 'Beauty work'); ?>" loading="lazy">
                                <div class="gallery-overlay">
                                    <div class="gallery-info">
                                        <h4><?php echo htmlspecialchars($item['title'] ?: 'Beautiful Work'); ?></h4>
                                        <p>by <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?></p>
                                        <?php if ($item['category']): ?>
                                            <span class="category-tag"><?php echo htmlspecialchars(ucfirst($item['category'])); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="gallery-actions">
                                        <button class="btn btn-sm btn-white">View</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-images"></i>
                    <h3>No gallery items found</h3>
                    <p>Try adjusting your filters or browse all work</p>
                    <a href="gallery.php" class="btn btn-primary">View All Work</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Lightbox Modal -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <div class="lightbox-content" onclick="event.stopPropagation()">
            <span class="lightbox-close" onclick="closeLightbox()">&times;</span>
            <div class="lightbox-image">
                <img id="lightbox-img" src="" alt="">
            </div>
            <div class="lightbox-info">
                <h3 id="lightbox-title"></h3>
                <p id="lightbox-description"></p>
                <div class="lightbox-professional">
                    <span>by <strong id="lightbox-professional"></strong></span>
                    <div class="lightbox-actions">
                        <a id="lightbox-profile-link" href="#" class="btn btn-sm">View Profile</a>
                        <?php if (is_logged_in() && is_client()): ?>
                            <a id="lightbox-book-link" href="#" class="btn btn-sm btn-primary">Book Appointment</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <?php if (!is_logged_in()): ?>
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Create Your Own Beauty Story?</h2>
                <p>Join BeautyBook and connect with amazing beauty professionals</p>
                <div class="cta-buttons">
                    <a href="register.php?role=client" class="btn btn-primary">Find Professionals</a>
                    <a href="register.php?role=professional" class="btn btn-secondary">Showcase Your Work</a>
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
        function openLightbox(imagePath, title, description, professional, professionalId) {
            document.getElementById('lightbox-img').src = imagePath;
            document.getElementById('lightbox-title').textContent = title;
            document.getElementById('lightbox-description').textContent = description;
            document.getElementById('lightbox-professional').textContent = professional;
            document.getElementById('lightbox-profile-link').href = `professional-profile.php?id=${professionalId}`;
            
            const bookLink = document.getElementById('lightbox-book-link');
            if (bookLink) {
                bookLink.href = `new-appointment.php?professional_id=${professionalId}`;
            }
            
            document.getElementById('lightbox').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        // Close lightbox with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeLightbox();
            }
        });

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

