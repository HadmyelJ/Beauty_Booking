<?php
require_once 'config.php';

// Check if user is logged in and is a professional
if (!is_logged_in() || !is_professional()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Add new service
    if (isset($_POST['add_service'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $category = trim($_POST['category']);
        
        $name_err = $price_err = $duration_err = "";
        
        // Validate inputs
        if (empty($name)) {
            $name_err = "Please enter a service name.";
        }
        
        if ($price <= 0) {
            $price_err = "Please enter a valid price.";
        }
        
        if ($duration <= 0) {
            $duration_err = "Please enter a valid duration.";
        }
        
        // Check input errors before inserting in database
        if (empty($name_err) && empty($price_err) && empty($duration_err)) {
            $stmt = $pdo->prepare("INSERT INTO services (professional_id, name, description, price, duration, category) VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$user_id, $name, $description, $price, $duration, $category])) {
                $success_msg = "Service added successfully!";
            } else {
                $error_msg = "Something went wrong. Please try again.";
            }
        }
    }
    
    // Edit service
    if (isset($_POST['edit_service'])) {
        $service_id = intval($_POST['service_id']);
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $duration = intval($_POST['duration']);
        $category = trim($_POST['category']);
        
        // Verify service belongs to user
        $stmt = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND professional_id = ?");
        $stmt->execute([$service_id, $user_id]);
        
        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("UPDATE services SET name = ?, description = ?, price = ?, duration = ?, category = ? WHERE service_id = ?");
            
            if ($stmt->execute([$name, $description, $price, $duration, $category, $service_id])) {
                $success_msg = "Service updated successfully!";
            } else {
                $error_msg = "Something went wrong. Please try again.";
            }
        }
    }
    
    // Delete service
    if (isset($_POST['delete_service'])) {
        $service_id = intval($_POST['service_id']);
        
        // Check if service has any appointments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ? AND status NOT IN ('cancelled')");
        $stmt->execute([$service_id]);
        $appointment_count = $stmt->fetchColumn();
        
        if ($appointment_count > 0) {
            $error_msg = "Cannot delete service with active appointments.";
        } else {
            // Verify service belongs to user
            $stmt = $pdo->prepare("SELECT service_id FROM services WHERE service_id = ? AND professional_id = ?");
            $stmt->execute([$service_id, $user_id]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
                
                if ($stmt->execute([$service_id])) {
                    $success_msg = "Service deleted successfully!";
                } else {
                    $error_msg = "Something went wrong. Please try again.";
                }
            }
        }
    }
}

// Get user's services
$stmt = $pdo->prepare("SELECT * FROM services WHERE professional_id = ? ORDER BY name");
$stmt->execute([$user_id]);
$services = $stmt->fetchAll();

// Get service categories for dropdown
$categories = [
    'makeup' => 'Makeup',
    'hair' => 'Hair',
    'nails' => 'Nails',
    'skincare' => 'Skincare',
    'eyebrows' => 'Eyebrows',
    'lashes' => 'Lashes',
    'massage' => 'Massage',
    'facial' => 'Facial',
    'waxing' => 'Waxing',
    'other' => 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - BeautyBook</title>
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
                        <a href="professional-dashboard.php" class="nav-link">Dashboard</a>
                        <a href="services.php" class="nav-link active">Services</a>
                        <a href="gallery.php" class="nav-link">Gallery</a>
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

    <!-- Services Management Section -->
    <section class="services-section">
        <div class="container">
            <div class="services-header">
                <h1>Manage Your Services</h1>
                <p>Add, edit, and organize the services you offer to clients</p>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="services-container">
                <!-- Add New Service Form -->
                <div class="add-service-section">
                    <div class="section-header">
                        <h2>Add New Service</h2>
                        <button class="btn btn-primary" onclick="toggleAddForm()">
                            <i class="fas fa-plus"></i> Add Service
                        </button>
                    </div>

                    <div id="addServiceForm" class="service-form" style="display: none;">
                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name">Service Name *</label>
                                    <input type="text" id="name" name="name" class="form-control" required placeholder="e.g., Bridal Makeup">
                                </div>
                                <div class="form-group">
                                    <label for="category">Category</label>
                                    <select name="category" id="category" class="form-control">
                                        <option value="">Select Category</option>
                                        <?php foreach ($categories as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="price">Price ($) *</label>
                                    <input type="number" id="price" name="price" class="form-control" step="0.01" min="0" required placeholder="0.00">
                                </div>
                                <div class="form-group">
                                    <label for="duration">Duration (minutes) *</label>
                                    <input type="number" id="duration" name="duration" class="form-control" min="15" step="15" required placeholder="60">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="3" placeholder="Describe your service, what's included, any special techniques..."></textarea>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="add_service" class="btn btn-primary">Add Service</button>
                                <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Services List -->
                <div class="services-list-section">
                    <div class="section-header">
                        <h2>Your Services (<?php echo count($services); ?>)</h2>
                    </div>

                    <?php if (count($services) > 0): ?>
                        <div class="services-grid">
                            <?php foreach ($services as $service): ?>
                                <div class="service-card">
                                    <div class="service-header">
                                        <div class="service-info">
                                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                                            <?php if ($service['category']): ?>
                                                <span class="service-category"><?php echo htmlspecialchars($categories[$service['category']] ?? ucfirst($service['category'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="service-actions">
                                            <button class="btn btn-sm btn-outline" onclick="editService(<?php echo $service['service_id']; ?>)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="deleteService(<?php echo $service['service_id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>

                                    <div class="service-details">
                                        <div class="service-pricing">
                                            <div class="price">
                                                <i class="fas fa-dollar-sign"></i>
                                                <span><?php echo number_format($service['price'], 2); ?></span>
                                            </div>
                                            <div class="duration">
                                                <i class="fas fa-clock"></i>
                                                <span><?php echo $service['duration']; ?> min</span>
                                            </div>
                                        </div>

                                        <?php if ($service['description']): ?>
                                            <div class="service-description">
                                                <p><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <div class="service-stats">
                                            <?php
                                            // Get appointment count for this service
                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE service_id = ?");
                                            $stmt->execute([$service['service_id']]);
                                            $appointment_count = $stmt->fetchColumn();
                                            ?>
                                            <span class="stat">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo $appointment_count; ?> bookings
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Hidden Edit Form -->
                                    <div id="editForm<?php echo $service['service_id']; ?>" class="edit-form" style="display: none;">
                                        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                                            <input type="hidden" name="service_id" value="<?php echo $service['service_id']; ?>">
                                            
                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Service Name *</label>
                                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($service['name']); ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Category</label>
                                                    <select name="category" class="form-control">
                                                        <option value="">Select Category</option>
                                                        <?php foreach ($categories as $value => $label): ?>
                                                            <option value="<?php echo $value; ?>" <?php echo ($service['category'] === $value) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="form-row">
                                                <div class="form-group">
                                                    <label>Price ($) *</label>
                                                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="<?php echo $service['price']; ?>" required>
                                                </div>
                                                <div class="form-group">
                                                    <label>Duration (minutes) *</label>
                                                    <input type="number" name="duration" class="form-control" min="15" step="15" value="<?php echo $service['duration']; ?>" required>
                                                </div>
                                            </div>

                                            <div class="form-group">
                                                <label>Description</label>
                                                <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($service['description']); ?></textarea>
                                            </div>

                                            <div class="form-actions">
                                                <button type="submit" name="edit_service" class="btn btn-primary">Update Service</button>
                                                <button type="button" class="btn btn-secondary" onclick="cancelEdit(<?php echo $service['service_id']; ?>)">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-concierge-bell"></i>
                            <h3>No services added yet</h3>
                            <p>Start by adding your first service to attract clients</p>
                            <button class="btn btn-primary" onclick="toggleAddForm()">Add Your First Service</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <span class="modal-close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete "<span id="deleteServiceName"></span>"?</p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                    <input type="hidden" name="service_id" id="deleteServiceId">
                    <button type="submit" name="delete_service" class="btn btn-danger">Delete Service</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                </form>
            </div>
        </div>
    </div>

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
        function toggleAddForm() {
            const form = document.getElementById('addServiceForm');
            if (form.style.display === 'none' || form.style.display === '') {
                form.style.display = 'block';
                form.scrollIntoView({ behavior: 'smooth' });
            } else {
                form.style.display = 'none';
                // Reset form
                form.querySelector('form').reset();
            }
        }

        function editService(serviceId) {
            // Hide all edit forms
            const editForms = document.querySelectorAll('.edit-form');
            editForms.forEach(form => form.style.display = 'none');
            
            // Show all service cards
            const serviceCards = document.querySelectorAll('.service-card .service-header, .service-card .service-details');
            serviceCards.forEach(card => card.style.display = 'block');
            
            // Hide the specific service content and show edit form
            const serviceCard = document.querySelector(`#editForm${serviceId}`).closest('.service-card');
            serviceCard.querySelector('.service-header').style.display = 'none';
            serviceCard.querySelector('.service-details').style.display = 'none';
            
            // Show edit form
            document.getElementById(`editForm${serviceId}`).style.display = 'block';
        }

        function cancelEdit(serviceId) {
            // Hide edit form
            document.getElementById(`editForm${serviceId}`).style.display = 'none';
            
            // Show service content
            const serviceCard = document.querySelector(`#editForm${serviceId}`).closest('.service-card');
            serviceCard.querySelector('.service-header').style.display = 'block';
            serviceCard.querySelector('.service-details').style.display = 'block';
        }

        function deleteService(serviceId, serviceName) {
            document.getElementById('deleteServiceId').value = serviceId;
            document.getElementById('deleteServiceName').textContent = serviceName;
            document.getElementById('deleteModal').style.display = 'flex';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>

    <style>
        .services-section {
            padding: 120px 0 80px;
            background-color: #f8f9fa;
        }

        .services-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .services-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .add-service-section {
            background: white;
            padding: 2rem;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 3rem;
        }

        .service-form {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e0e0e0;
        }

        .services-grid {
            display: grid;
            gap: 2rem;
        }

        .service-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-2px);
        }

        .service-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .service-info h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }

        .service-category {
            background: #e91e63;
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
        }

        .service-actions {
            display: flex;
            gap: 0.5rem;
        }

        .service-pricing {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .price, .duration {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
        }

        .price i, .duration i {
            color: #e91e63;
        }

        .service-description {
            margin-bottom: 1rem;
            color: #666;
        }

        .service-stats {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #666;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .stat i {
            color: #e91e63;
        }

        .edit-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e0e0e0;
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #e0e0e0;
        }

        .modal-header h3 {
            margin: 0;
        }

        .modal-close {
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .warning-text {
            color: #f44336;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        @media (max-width: 768px) {
            .service-header {
                flex-direction: column;
                gap: 1rem;
            }

            .service-actions {
                align-self: flex-start;
            }

            .service-pricing {
                flex-direction: column;
                gap: 1rem;
            }
        }
    </style>
</body>
</html>

