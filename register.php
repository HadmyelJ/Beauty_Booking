<?php
require_once 'config.php';

// If user is already logged in, redirect to appropriate dashboard
if (is_logged_in()) {
    if (is_professional()) {
        redirect('professional-dashboard.php');
    } else {
        redirect('client-dashboard.php');
    }
}

// Define variables and initialize with empty values
$first_name = $last_name = $email = $password = $confirm_password = $role = $phone = $bio = "";
$first_name_err = $last_name_err = $email_err = $password_err = $confirm_password_err = $role_err = "";

// Get role from URL parameter if provided
if (isset($_GET['role']) && in_array($_GET['role'], ['client', 'professional'])) {
    $role = $_GET['role'];
}

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate first name
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }
    
    // Validate last name
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address.";
    } else {
        // Prepare a select statement
        $sql = "SELECT user_id FROM users WHERE email = ?";
        
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(1, $param_email, PDO::PARAM_STR);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                if ($stmt->rowCount() == 1) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";     
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must have at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }
    
    // Validate role
    if (empty(trim($_POST["role"]))) {
        $role_err = "Please select a role.";
    } elseif (!in_array(trim($_POST["role"]), ['client', 'professional'])) {
        $role_err = "Please select a valid role.";
    } else {
        $role = trim($_POST["role"]);
    }
    
    // Get optional fields
    $phone = trim($_POST["phone"]);
    $bio = trim($_POST["bio"]);
    
    // Check input errors before inserting in database
    if (empty($first_name_err) && empty($last_name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err)) {
        
        // Prepare an insert statement
        $sql = "INSERT INTO users (first_name, last_name, email, password, role, phone, bio) VALUES (?, ?, ?, ?, ?, ?, ?)";
         
        if ($stmt = $pdo->prepare($sql)) {
            $stmt->bindParam(1, $param_first_name, PDO::PARAM_STR);
            $stmt->bindParam(2, $param_last_name, PDO::PARAM_STR);
            $stmt->bindParam(3, $param_email, PDO::PARAM_STR);
            $stmt->bindParam(4, $param_password, PDO::PARAM_STR);
            $stmt->bindParam(5, $param_role, PDO::PARAM_STR);
            $stmt->bindParam(6, $param_phone, PDO::PARAM_STR);
            $stmt->bindParam(7, $param_bio, PDO::PARAM_STR);
            
            // Set parameters
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_role = $role;
            $param_phone = $phone;
            $param_bio = $bio;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Get the user ID
                $user_id = $pdo->lastInsertId();
                
                // Auto-login the user
                $_SESSION["loggedin"] = true;
                $_SESSION["user_id"] = $user_id;
                $_SESSION["email"] = $email;
                $_SESSION["first_name"] = $first_name;
                $_SESSION["last_name"] = $last_name;
                $_SESSION["role"] = $role;
                
                // Redirect to appropriate dashboard
                if ($role === 'professional') {
                    redirect('professional-dashboard.php');
                } else {
                    redirect('client-dashboard.php');
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            unset($stmt);
        }
    }
    
    // Close connection
    unset($pdo);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - BeautyBook</title>
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
                    <div class="auth-links">
                        <a href="login.php" class="nav-link">Login</a>
                        <a href="register.php" class="btn active">Sign Up</a>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Registration Form -->
    <section class="auth-section">
        <div class="container">
            <div class="auth-container">
                <div class="auth-form">
                    <div class="auth-header">
                        <h1>Join BeautyBook</h1>
                        <p>Create your account and start your beauty journey</p>
                    </div>

                    <!-- Role Selection -->
                    <div class="role-selection">
                        <div class="role-option <?php echo ($role === 'client') ? 'active' : ''; ?>" onclick="selectRole('client')">
                            <i class="fas fa-user"></i>
                            <h3>I'm a Client</h3>
                            <p>Looking for beauty services</p>
                        </div>
                        <div class="role-option <?php echo ($role === 'professional') ? 'active' : ''; ?>" onclick="selectRole('professional')">
                            <i class="fas fa-cut"></i>
                            <h3>I'm a Professional</h3>
                            <p>Offering beauty services</p>
                        </div>
                    </div>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" id="registerForm">
                        <input type="hidden" name="role" id="roleInput" value="<?php echo $role; ?>">
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" class="form-control <?php echo (!empty($first_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $first_name; ?>" required>
                                <span class="invalid-feedback"><?php echo $first_name_err; ?></span>
                            </div>

                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" class="form-control <?php echo (!empty($last_name_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $last_name; ?>" required>
                                <span class="invalid-feedback"><?php echo $last_name_err; ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-control <?php echo (!empty($email_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $email; ?>" required>
                            <span class="invalid-feedback"><?php echo $email_err; ?></span>
                        </div>

                        <div class="form-group">
                            <label for="phone">Phone Number (Optional)</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo $phone; ?>">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input type="password" id="password" name="password" class="form-control <?php echo (!empty($password_err)) ? 'is-invalid' : ''; ?>" required>
                                <span class="invalid-feedback"><?php echo $password_err; ?></span>
                            </div>

                            <div class="form-group">
                                <label for="confirm_password">Confirm Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" class="form-control <?php echo (!empty($confirm_password_err)) ? 'is-invalid' : ''; ?>" required>
                                <span class="invalid-feedback"><?php echo $confirm_password_err; ?></span>
                            </div>
                        </div>

                        <div class="form-group professional-only" style="display: <?php echo ($role === 'professional') ? 'block' : 'none'; ?>;">
                            <label for="bio">Professional Bio (Optional)</label>
                            <textarea id="bio" name="bio" class="form-control" rows="3" placeholder="Tell clients about your experience and specialties..."><?php echo $bio; ?></textarea>
                        </div>

                        <div class="form-group">
                            <div class="form-check">
                                <input type="checkbox" id="terms" name="terms" class="form-check-input" required>
                                <label for="terms" class="form-check-label">
                                    I agree to the <a href="terms.php" target="_blank">Terms of Service</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                                </label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    </form>

                    <div class="auth-footer">
                        <p>Already have an account? <a href="login.php">Sign in here</a></p>
                    </div>
                </div>

                <div class="auth-image">
                    <img src="https://images.unsplash.com/photo-1607779097040-26e80aa78e66?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80" alt="Beauty professional">
                    <div class="auth-image-overlay">
                        <h2>Start Your Beauty Journey</h2>
                        <p>Whether you're seeking services or offering them, BeautyBook is your perfect platform</p>
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
                <div class="footer-section">
                    <h4>Quick Links</h4>
                    <ul>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="services.php">Services</a></li>
                        <li><a href="contact.php">Contact</a></li>
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
        function selectRole(role) {
            // Update visual selection
            document.querySelectorAll('.role-option').forEach(option => {
                option.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Update hidden input
            document.getElementById('roleInput').value = role;
            
            // Show/hide professional-specific fields
            const professionalFields = document.querySelectorAll('.professional-only');
            professionalFields.forEach(field => {
                field.style.display = role === 'professional' ? 'block' : 'none';
            });
        }

        // Initialize role selection on page load
        document.addEventListener('DOMContentLoaded', function() {
            const currentRole = document.getElementById('roleInput').value;
            if (currentRole) {
                const professionalFields = document.querySelectorAll('.professional-only');
                professionalFields.forEach(field => {
                    field.style.display = currentRole === 'professional' ? 'block' : 'none';
                });
            }
        });
    </script>
</body>
</html>

