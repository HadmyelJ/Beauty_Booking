<?php
require_once 'config.php';

// Check if user is logged in and is a client
if (!is_logged_in() || !is_client()) {
    redirect('login.php');
}

$professional_id = $_GET['professional_id'] ?? '';
$service_id = $_GET['service_id'] ?? '';

// Initialize variables
$appointment_date = $appointment_time = $notes = "";
$professional_id_err = $service_id_err = $appointment_date_err = $appointment_time_err = "";

// Get all professionals for dropdown
$stmt = $pdo->prepare("SELECT user_id, first_name, last_name FROM users WHERE role = 'professional' ORDER BY first_name, last_name");
$stmt->execute();
$professionals = $stmt->fetchAll();

// Get services for selected professional
$services = [];
if (!empty($professional_id)) {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE professional_id = ? ORDER BY name");
    $stmt->execute([$professional_id]);
    $services = $stmt->fetchAll();
}

// Get professional details if selected
$professional_details = null;
if (!empty($professional_id)) {
    $stmt = $pdo->prepare("SELECT u.*, AVG(r.rating) as avg_rating, COUNT(r.review_id) as review_count
                           FROM users u 
                           LEFT JOIN reviews r ON u.user_id = r.professional_id 
                           WHERE u.user_id = ? AND u.role = 'professional'
                           GROUP BY u.user_id");
    $stmt->execute([$professional_id]);
    $professional_details = $stmt->fetch();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate professional
    if (empty(trim($_POST["professional_id"]))) {
        $professional_id_err = "Please select a professional.";
    } else {
        $professional_id = trim($_POST["professional_id"]);
    }
    
    // Validate service
    if (empty(trim($_POST["service_id"]))) {
        $service_id_err = "Please select a service.";
    } else {
        $service_id = trim($_POST["service_id"]);
    }
    
    // Validate date
    if (empty(trim($_POST["appointment_date"]))) {
        $appointment_date_err = "Please select a date.";
    } else {
        $appointment_date = trim($_POST["appointment_date"]);
        
        // Check if date is in the future
        if (strtotime($appointment_date) < strtotime(date("Y-m-d"))) {
            $appointment_date_err = "Please select a future date.";
        }
    }
    
    // Validate time
    if (empty(trim($_POST["appointment_time"]))) {
        $appointment_time_err = "Please select a time.";
    } else {
        $appointment_time = trim($_POST["appointment_time"]);
    }
    
    // Get notes
    $notes = trim($_POST["notes"]);
    
    // Check input errors before inserting in database
    if (empty($professional_id_err) && empty($service_id_err) && empty($appointment_date_err) && empty($appointment_time_err)) {
        
        // Get service details
        $stmt = $pdo->prepare("SELECT duration FROM services WHERE service_id = ?");
        $stmt->execute([$service_id]);
        $service = $stmt->fetch();
        
        if ($service) {
            $duration = $service['duration'];
            
            // Calculate end time
            $start_time = $appointment_time;
            $end_time = date('H:i:s', strtotime($start_time) + ($duration * 60));
            
            // Check for conflicts
            $stmt = $pdo->prepare("SELECT appointment_id FROM appointments 
                                  WHERE professional_id = ? AND appointment_date = ? 
                                  AND status NOT IN ('cancelled') 
                                  AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))");
            $stmt->execute([
                $professional_id, 
                $appointment_date, 
                $start_time, $start_time, 
                $end_time, $end_time
            ]);
            
            if ($stmt->fetch()) {
                $appointment_time_err = "This time slot is not available. Please choose another time.";
            } else {
                // Insert appointment
                $stmt = $pdo->prepare("INSERT INTO appointments (client_id, professional_id, service_id, appointment_date, start_time, end_time, notes, status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
                
                if ($stmt->execute([$_SESSION['user_id'], $professional_id, $service_id, $appointment_date, $start_time, $end_time, $notes])) {
                    $appointment_id = $pdo->lastInsertId();
                    
                    // Redirect to appointment confirmation
                    redirect('appointment-confirmation.php?id=' . $appointment_id);
                } else {
                    $error_msg = "Oops! Something went wrong. Please try again later.";
                }
            }
        } else {
            $service_id_err = "Invalid service selected.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book New Appointment - BeautyBook</title>
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
                        <a href="client-dashboard.php" class="nav-link">Dashboard</a>
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

    <!-- Booking Form -->
    <section class="booking-section">
        <div class="container">
            <div class="booking-header">
                <h1>Book New Appointment</h1>
                <p>Schedule your next beauty session</p>
            </div>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="booking-container">
                <div class="booking-form">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="bookingForm">
                        <!-- Professional Selection -->
                        <div class="form-section">
                            <h3>Select Professional</h3>
                            <div class="form-group">
                                <label for="professional_id">Choose a Professional</label>
                                <select name="professional_id" id="professional_id" class="form-control <?php echo (!empty($professional_id_err)) ? 'is-invalid' : ''; ?>" required onchange="loadServices()">
                                    <option value="">-- Select Professional --</option>
                                    <?php foreach ($professionals as $prof): ?>
                                        <option value="<?php echo $prof['user_id']; ?>" <?php echo ($professional_id == $prof['user_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($prof['first_name'] . ' ' . $prof['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $professional_id_err; ?></span>
                            </div>
                        </div>

                        <!-- Professional Details -->
                        <?php if ($professional_details): ?>
                        <div class="professional-preview">
                            <div class="professional-info">
                                <img src="<?php echo $professional_details['profile_image'] ?: 'https://images.unsplash.com/photo-1494790108755-2616b612b786?ixlib=rb-4.0.3&auto=format&fit=crop&w=100&q=80'; ?>" alt="<?php echo htmlspecialchars($professional_details['first_name'] . ' ' . $professional_details['last_name']); ?>" class="professional-avatar">
                                <div>
                                    <h4><?php echo htmlspecialchars($professional_details['first_name'] . ' ' . $professional_details['last_name']); ?></h4>
                                    <div class="rating">
                                        <?php 
                                        $rating = $professional_details['avg_rating'] ?: 0;
                                        for ($i = 1; $i <= 5; $i++): 
                                            if ($i <= $rating): ?>
                                                <i class="fas fa-star"></i>
                                            <?php elseif ($i - 0.5 <= $rating): ?>
                                                <i class="fas fa-star-half-alt"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif;
                                        endfor; ?>
                                        <span><?php echo number_format($rating, 1); ?> (<?php echo $professional_details['review_count']; ?> reviews)</span>
                                    </div>
                                    <p><?php echo htmlspecialchars(substr($professional_details['bio'] ?: 'Professional beauty services', 0, 150)); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Service Selection -->
                        <div class="form-section">
                            <h3>Select Service</h3>
                            <div class="form-group">
                                <label for="service_id">Choose a Service</label>
                                <select name="service_id" id="service_id" class="form-control <?php echo (!empty($service_id_err)) ? 'is-invalid' : ''; ?>" required onchange="updateServiceDetails()" <?php echo empty($professional_id) ? 'disabled' : ''; ?>>
                                    <option value="">-- Select Service --</option>
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?php echo $service['service_id']; ?>" 
                                                data-duration="<?php echo $service['duration']; ?>" 
                                                data-price="<?php echo $service['price']; ?>"
                                                data-description="<?php echo htmlspecialchars($service['description']); ?>"
                                                <?php echo ($service_id == $service['service_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($service['name']); ?> - <?php echo format_currency($service['price']); ?> (<?php echo $service['duration']; ?> min)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <span class="invalid-feedback"><?php echo $service_id_err; ?></span>
                            </div>
                            
                            <div id="service-details" class="service-details" style="display: none;">
                                <div class="service-info">
                                    <h4 id="service-name"></h4>
                                    <p id="service-description"></p>
                                    <div class="service-meta">
                                        <span class="duration"><i class="fas fa-clock"></i> <span id="service-duration"></span> minutes</span>
                                        <span class="price"><i class="fas fa-dollar-sign"></i> <span id="service-price"></span></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Date & Time Selection -->
                        <div class="form-section">
                            <h3>Select Date & Time</h3>
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="appointment_date">Date</label>
                                    <input type="date" name="appointment_date" id="appointment_date" class="form-control <?php echo (!empty($appointment_date_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $appointment_date; ?>" min="<?php echo date('Y-m-d'); ?>" required onchange="loadAvailableTimes()" <?php echo empty($service_id) ? 'disabled' : ''; ?>>
                                    <span class="invalid-feedback"><?php echo $appointment_date_err; ?></span>
                                </div>

                                <div class="form-group">
                                    <label for="appointment_time">Time</label>
                                    <select name="appointment_time" id="appointment_time" class="form-control <?php echo (!empty($appointment_time_err)) ? 'is-invalid' : ''; ?>" required <?php echo empty($appointment_date) ? 'disabled' : ''; ?>>
                                        <option value="">-- Select Time --</option>
                                    </select>
                                    <span class="invalid-feedback"><?php echo $appointment_time_err; ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Notes -->
                        <div class="form-section">
                            <h3>Additional Information</h3>
                            <div class="form-group">
                                <label for="notes">Special Requests or Notes (Optional)</label>
                                <textarea name="notes" id="notes" class="form-control" rows="4" placeholder="Any special requests, allergies, or preferences..."><?php echo $notes; ?></textarea>
                            </div>
                        </div>

                        <!-- Booking Summary -->
                        <div class="booking-summary" id="booking-summary" style="display: none;">
                            <h3>Booking Summary</h3>
                            <div class="summary-content">
                                <div class="summary-item">
                                    <span class="label">Professional:</span>
                                    <span class="value" id="summary-professional"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Service:</span>
                                    <span class="value" id="summary-service"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Date:</span>
                                    <span class="value" id="summary-date"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Time:</span>
                                    <span class="value" id="summary-time"></span>
                                </div>
                                <div class="summary-item">
                                    <span class="label">Duration:</span>
                                    <span class="value" id="summary-duration"></span>
                                </div>
                                <div class="summary-item total">
                                    <span class="label">Total Price:</span>
                                    <span class="value" id="summary-price"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg" id="submit-btn" disabled>Book Appointment</button>
                            <a href="client-dashboard.php" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
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
        function loadServices() {
            const professionalId = document.getElementById('professional_id').value;
            const serviceSelect = document.getElementById('service_id');
            
            if (professionalId) {
                // Enable service select
                serviceSelect.disabled = false;
                
                // Load services via AJAX
                fetch(`get-services.php?professional_id=${professionalId}`)
                    .then(response => response.json())
                    .then(data => {
                        serviceSelect.innerHTML = '<option value="">-- Select Service --</option>';
                        data.forEach(service => {
                            const option = document.createElement('option');
                            option.value = service.service_id;
                            option.textContent = `${service.name} - $${service.price} (${service.duration} min)`;
                            option.setAttribute('data-duration', service.duration);
                            option.setAttribute('data-price', service.price);
                            option.setAttribute('data-description', service.description || '');
                            serviceSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading services:', error);
                    });
            } else {
                // Disable and reset dependent fields
                serviceSelect.disabled = true;
                serviceSelect.innerHTML = '<option value="">-- Select Service --</option>';
                document.getElementById('appointment_date').disabled = true;
                document.getElementById('appointment_time').disabled = true;
                document.getElementById('service-details').style.display = 'none';
                document.getElementById('booking-summary').style.display = 'none';
                document.getElementById('submit-btn').disabled = true;
            }
        }

        function updateServiceDetails() {
            const serviceSelect = document.getElementById('service_id');
            const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
            
            if (selectedOption.value) {
                const serviceName = selectedOption.textContent.split(' - ')[0];
                const duration = selectedOption.getAttribute('data-duration');
                const price = selectedOption.getAttribute('data-price');
                const description = selectedOption.getAttribute('data-description');
                
                document.getElementById('service-name').textContent = serviceName;
                document.getElementById('service-description').textContent = description || 'Professional beauty service';
                document.getElementById('service-duration').textContent = duration;
                document.getElementById('service-price').textContent = `$${price}`;
                
                document.getElementById('service-details').style.display = 'block';
                document.getElementById('appointment_date').disabled = false;
                
                updateSummary();
            } else {
                document.getElementById('service-details').style.display = 'none';
                document.getElementById('appointment_date').disabled = true;
                document.getElementById('appointment_time').disabled = true;
                document.getElementById('booking-summary').style.display = 'none';
                document.getElementById('submit-btn').disabled = true;
            }
        }

        function loadAvailableTimes() {
            const professionalId = document.getElementById('professional_id').value;
            const serviceId = document.getElementById('service_id').value;
            const date = document.getElementById('appointment_date').value;
            const timeSelect = document.getElementById('appointment_time');
            
            if (professionalId && serviceId && date) {
                // Enable time select
                timeSelect.disabled = false;
                
                // Load available times via AJAX
                fetch(`get-available-times.php?professional_id=${professionalId}&service_id=${serviceId}&date=${date}`)
                    .then(response => response.json())
                    .then(data => {
                        timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
                        data.forEach(time => {
                            const option = document.createElement('option');
                            option.value = time.time;
                            option.textContent = time.display;
                            timeSelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading available times:', error);
                    });
            } else {
                timeSelect.disabled = true;
                timeSelect.innerHTML = '<option value="">-- Select Time --</option>';
                document.getElementById('submit-btn').disabled = true;
            }
            
            updateSummary();
        }

        function updateSummary() {
            const professionalSelect = document.getElementById('professional_id');
            const serviceSelect = document.getElementById('service_id');
            const dateInput = document.getElementById('appointment_date');
            const timeSelect = document.getElementById('appointment_time');
            
            const professionalName = professionalSelect.options[professionalSelect.selectedIndex]?.textContent;
            const serviceName = serviceSelect.options[serviceSelect.selectedIndex]?.textContent?.split(' - ')[0];
            const selectedServiceOption = serviceSelect.options[serviceSelect.selectedIndex];
            const duration = selectedServiceOption?.getAttribute('data-duration');
            const price = selectedServiceOption?.getAttribute('data-price');
            const date = dateInput.value;
            const time = timeSelect.value;
            
            if (professionalName && serviceName && date && time && duration && price) {
                document.getElementById('summary-professional').textContent = professionalName;
                document.getElementById('summary-service').textContent = serviceName;
                document.getElementById('summary-date').textContent = new Date(date).toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                document.getElementById('summary-time').textContent = new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit', 
                    hour12: true 
                });
                document.getElementById('summary-duration').textContent = `${duration} minutes`;
                document.getElementById('summary-price').textContent = `$${price}`;
                
                document.getElementById('booking-summary').style.display = 'block';
                document.getElementById('submit-btn').disabled = false;
            } else {
                document.getElementById('booking-summary').style.display = 'none';
                document.getElementById('submit-btn').disabled = true;
            }
        }

        // Add event listeners
        document.getElementById('appointment_time').addEventListener('change', updateSummary);

        // Initialize form state
        document.addEventListener('DOMContentLoaded', function() {
            const professionalId = document.getElementById('professional_id').value;
            if (professionalId) {
                loadServices();
            }
        });
    </script>
</body>
</html>

