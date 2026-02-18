<?php
require_once 'config.php';

// Check if user is logged in and is a professional
if (!is_logged_in() || !is_professional()) {
    redirect('login.php');
}

$title = $description = $category = "";
$title_err = $image_err = "";

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate title
    if (empty(trim($_POST["title"]))) {
        $title_err = "Please enter a title.";
    } else {
        $title = trim($_POST["title"]);
    }
    
    // Get optional fields
    $description = trim($_POST["description"]);
    $category = trim($_POST["category"]);
    
    // Handle file upload
    if (isset($_FILES["image"]) && $_FILES["image"]["error"] == 0) {
        $allowed_types = array("jpg" => "image/jpg", "jpeg" => "image/jpeg", "gif" => "image/gif", "png" => "image/png");
        $filename = $_FILES["image"]["name"];
        $filetype = $_FILES["image"]["type"];
        $filesize = $_FILES["image"]["size"];
        
        // Verify file extension
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        if (!array_key_exists($ext, $allowed_types)) {
            $image_err = "Please select a valid image format (JPG, JPEG, PNG, GIF).";
        }
        
        // Verify file size - 5MB maximum
        $maxsize = 5 * 1024 * 1024;
        if ($filesize > $maxsize) {
            $image_err = "File size is larger than the allowed limit (5MB).";
        }
        
        // Verify MYME type of the file
        if (in_array($filetype, $allowed_types)) {
            // Check whether file exists before uploading it
            $upload_dir = "uploads/gallery/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = uniqid() . "_" . $filename;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["image"]["tmp_name"], $upload_path)) {
                $image_path = $upload_path;
            } else {
                $image_err = "Sorry, there was an error uploading your file.";
            }
        } else {
            $image_err = "File type not allowed.";
        }
    } else {
        $image_err = "Please select an image to upload.";
    }
    
    // Check input errors before inserting in database
    if (empty($title_err) && empty($image_err)) {
        
        // Insert gallery item
        $stmt = $pdo->prepare("INSERT INTO gallery (professional_id, title, description, category, image_path) VALUES (?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$_SESSION['user_id'], $title, $description, $category, $image_path])) {
            $success_msg = "Your work has been uploaded successfully!";
            // Reset form
            $title = $description = $category = "";
        } else {
            $error_msg = "Oops! Something went wrong. Please try again later.";
        }
    }
}

// Get user's existing gallery items
$stmt = $pdo->prepare("SELECT * FROM gallery WHERE professional_id = ? ORDER BY created_at DESC");
$stmt->execute([$_SESSION['user_id']]);
$my_gallery = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Work - BeautyBook</title>
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

    <!-- Upload Section -->
    <section class="upload-section">
        <div class="container">
            <div class="upload-header">
                <h1>Upload Your Work</h1>
                <p>Showcase your talent and attract new clients</p>
            </div>

            <?php if (isset($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>

            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger"><?php echo $error_msg; ?></div>
            <?php endif; ?>

            <div class="upload-container">
                <div class="upload-form">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                        <div class="form-section">
                            <h3>Upload Image</h3>
                            <div class="form-group">
                                <label for="image">Select Image</label>
                                <div class="file-upload">
                                    <input type="file" id="image" name="image" accept="image/*" required onchange="previewImage(this)">
                                    <div class="file-upload-design">
                                        <div class="upload-icon">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                        </div>
                                        <div class="upload-text">
                                            <span class="upload-title">Click to upload image</span>
                                            <span class="upload-subtitle">JPG, PNG, GIF up to 5MB</span>
                                        </div>
                                    </div>
                                </div>
                                <span class="invalid-feedback"><?php echo $image_err; ?></span>
                            </div>
                            
                            <div class="image-preview" id="imagePreview" style="display: none;">
                                <img id="previewImg" src="" alt="Preview">
                                <button type="button" class="remove-preview" onclick="removePreview()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Work Details</h3>
                            <div class="form-group">
                                <label for="title">Title</label>
                                <input type="text" id="title" name="title" class="form-control <?php echo (!empty($title_err)) ? 'is-invalid' : ''; ?>" value="<?php echo $title; ?>" placeholder="e.g., Elegant Bridal Makeup" required>
                                <span class="invalid-feedback"><?php echo $title_err; ?></span>
                            </div>

                            <div class="form-group">
                                <label for="category">Category</label>
                                <select name="category" id="category" class="form-control">
                                    <option value="">Select Category (Optional)</option>
                                    <option value="makeup" <?php echo ($category === 'makeup') ? 'selected' : ''; ?>>Makeup</option>
                                    <option value="hair" <?php echo ($category === 'hair') ? 'selected' : ''; ?>>Hair</option>
                                    <option value="nails" <?php echo ($category === 'nails') ? 'selected' : ''; ?>>Nails</option>
                                    <option value="skincare" <?php echo ($category === 'skincare') ? 'selected' : ''; ?>>Skincare</option>
                                    <option value="eyebrows" <?php echo ($category === 'eyebrows') ? 'selected' : ''; ?>>Eyebrows</option>
                                    <option value="lashes" <?php echo ($category === 'lashes') ? 'selected' : ''; ?>>Lashes</option>
                                    <option value="other" <?php echo ($category === 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea name="description" id="description" class="form-control" rows="4" placeholder="Describe your work, techniques used, or any special details..."><?php echo $description; ?></textarea>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">Upload Work</button>
                            <a href="professional-dashboard.php" class="btn btn-secondary btn-lg">Cancel</a>
                        </div>
                    </form>
                </div>

                <div class="upload-tips">
                    <h3>Tips for Great Photos</h3>
                    <div class="tips-list">
                        <div class="tip-item">
                            <i class="fas fa-lightbulb"></i>
                            <div>
                                <h4>Good Lighting</h4>
                                <p>Use natural light when possible. Avoid harsh shadows and ensure the work is well-lit.</p>
                            </div>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-camera"></i>
                            <div>
                                <h4>High Quality</h4>
                                <p>Upload high-resolution images that showcase the details of your work clearly.</p>
                            </div>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-crop"></i>
                            <div>
                                <h4>Good Composition</h4>
                                <p>Frame your shots well and consider multiple angles to show your work completely.</p>
                            </div>
                        </div>
                        <div class="tip-item">
                            <i class="fas fa-palette"></i>
                            <div>
                                <h4>Show Variety</h4>
                                <p>Upload different styles and techniques to demonstrate your range and skills.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- My Gallery -->
    <?php if (count($my_gallery) > 0): ?>
    <section class="my-gallery-section">
        <div class="container">
            <div class="section-header">
                <h2>My Gallery</h2>
                <p><?php echo count($my_gallery); ?> items uploaded</p>
            </div>
            
            <div class="gallery-grid">
                <?php foreach ($my_gallery as $item): ?>
                    <div class="gallery-item">
                        <div class="gallery-image">
                            <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            <div class="gallery-overlay">
                                <div class="gallery-info">
                                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <?php if ($item['category']): ?>
                                        <span class="category-tag"><?php echo htmlspecialchars(ucfirst($item['category'])); ?></span>
                                    <?php endif; ?>
                                    <p class="upload-date">Uploaded <?php echo date('M j, Y', strtotime($item['created_at'])); ?></p>
                                </div>
                                <div class="gallery-actions">
                                    <a href="edit-gallery-item.php?id=<?php echo $item['gallery_id']; ?>" class="btn btn-sm btn-white">Edit</a>
                                    <a href="delete-gallery-item.php?id=<?php echo $item['gallery_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this item?')">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2024 BeautyBook. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    document.getElementById('previewImg').src = e.target.result;
                    document.getElementById('imagePreview').style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }

        function removePreview() {
            document.getElementById('image').value = '';
            document.getElementById('imagePreview').style.display = 'none';
            document.getElementById('previewImg').src = '';
        }

        // Drag and drop functionality
        const fileUpload = document.querySelector('.file-upload');
        const fileInput = document.getElementById('image');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            fileUpload.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            fileUpload.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            fileUpload.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            fileUpload.classList.add('drag-over');
        }

        function unhighlight(e) {
            fileUpload.classList.remove('drag-over');
        }

        fileUpload.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            
            if (files.length > 0) {
                fileInput.files = files;
                previewImage(fileInput);
            }
        }

        // Click to upload
        fileUpload.addEventListener('click', function() {
            fileInput.click();
        });
    </script>
</body>
</html>

