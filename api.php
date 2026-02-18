<?php
require_once 'config.php';

// Set headers for API responses
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get request data
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestPath = isset($_GET['path']) ? $_GET['path'] : '';
$pathParts = explode('/', trim($requestPath, '/'));
$resource = $pathParts[0] ?? '';
$id = $pathParts[1] ?? null;

// Get request body for POST/PUT requests
$requestBody = json_decode(file_get_contents('php://input'), true);

// Route the request to the appropriate handler
try {
    switch ($resource) {
        case 'auth':
            handleAuth($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'users':
            handleUsers($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'services':
            handleServices($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'appointments':
            handleAppointments($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'gallery':
            handleGallery($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'messages':
            handleMessages($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'payments':
            handlePayments($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'availability':
            handleAvailability($requestMethod, $id, $requestBody, $pdo);
            break;
        case 'reviews':
            handleReviews($requestMethod, $id, $requestBody, $pdo);
            break;
        default:
            send_error_response('Invalid endpoint', 404);
    }
} catch (Exception $e) {
    send_error_response('Server error: ' . $e->getMessage(), 500);
}

// Authentication handlers
function handleAuth($method, $id, $data, $pdo) {
    switch ($method) {
        case 'POST':
            if ($id === 'login') {
                loginUser($data, $pdo);
            } elseif ($id === 'register') {
                registerUser($data, $pdo);
            } elseif ($id === 'logout') {
                logoutUser();
            } else {
                send_error_response('Invalid auth endpoint', 404);
            }
            break;
        case 'GET':
            if ($id === 'check') {
                checkAuthStatus();
            } else {
                send_error_response('Invalid auth endpoint', 404);
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function loginUser($data, $pdo) {
    if (!isset($data['email']) || !isset($data['password'])) {
        send_error_response('Email and password are required');
    }

    $stmt = $pdo->prepare("SELECT user_id, email, password, first_name, last_name, role FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    $user = $stmt->fetch();

    if ($user && password_verify($data['password'], $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name'] = $user['last_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['login_time'] = time();

        unset($user['password']); // Don't send password back
        send_success_response($user, 'Login successful');
    } else {
        send_error_response('Invalid email or password', 401);
    }
}

function registerUser($data, $pdo) {
    $required_fields = ['email', 'password', 'first_name', 'last_name', 'role'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        send_error_response('Email already exists');
    }

    // Validate role
    if (!in_array($data['role'], ['client', 'professional'])) {
        send_error_response('Invalid role');
    }

    // Hash password
    $hashed_password = password_hash($data['password'], PASSWORD_DEFAULT);

    // Insert user
    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, phone, bio) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['email'],
        $hashed_password,
        $data['first_name'],
        $data['last_name'],
        $data['role'],
        $data['phone'] ?? null,
        $data['bio'] ?? null
    ]);

    $user_id = $pdo->lastInsertId();
    
    // Auto-login after registration
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $data['email'];
    $_SESSION['first_name'] = $data['first_name'];
    $_SESSION['last_name'] = $data['last_name'];
    $_SESSION['role'] = $data['role'];
    $_SESSION['login_time'] = time();

    send_success_response(['user_id' => $user_id], 'Registration successful');
}

function logoutUser() {
    session_destroy();
    send_success_response([], 'Logout successful');
}

function checkAuthStatus() {
    if (is_logged_in()) {
        send_success_response([
            'user_id' => $_SESSION['user_id'],
            'email' => $_SESSION['email'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'role' => $_SESSION['role']
        ], 'User is authenticated');
    } else {
        send_error_response('User not authenticated', 401);
    }
}

// Users handlers
function handleUsers($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getUserById($id, $pdo);
            } else {
                getAllUsers($pdo);
            }
            break;
        case 'PUT':
            if ($id) {
                updateUser($id, $data, $pdo);
            } else {
                send_error_response('User ID required for update');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllUsers($pdo) {
    $role_filter = $_GET['role'] ?? null;
    $search = $_GET['search'] ?? null;
    
    $sql = "SELECT user_id, email, first_name, last_name, role, phone, bio, profile_image, created_at FROM users WHERE 1=1";
    $params = [];
    
    if ($role_filter) {
        $sql .= " AND role = ?";
        $params[] = $role_filter;
    }
    
    if ($search) {
        $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
    
    $sql .= " ORDER BY first_name, last_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    send_success_response($users);
}

function getUserById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT user_id, email, first_name, last_name, role, phone, bio, profile_image, address, created_at FROM users WHERE user_id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if ($user) {
        send_success_response($user);
    } else {
        send_error_response('User not found', 404);
    }
}

function updateUser($id, $data, $pdo) {
    // Check if user exists and user has permission to update
    if (!is_logged_in() || ($_SESSION['user_id'] != $id && !is_admin())) {
        send_error_response('Unauthorized', 403);
    }
    
    $allowed_fields = ['first_name', 'last_name', 'phone', 'bio', 'address'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        send_error_response('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE user_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    send_success_response([], 'User updated successfully');
}

// Services handlers
function handleServices($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getServiceById($id, $pdo);
            } else {
                getAllServices($pdo);
            }
            break;
        case 'POST':
            createService($data, $pdo);
            break;
        case 'PUT':
            if ($id) {
                updateService($id, $data, $pdo);
            } else {
                send_error_response('Service ID required for update');
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteService($id, $pdo);
            } else {
                send_error_response('Service ID required for deletion');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllServices($pdo) {
    $professional_id = $_GET['professional_id'] ?? null;
    
    $sql = "SELECT s.*, u.first_name, u.last_name FROM services s 
            LEFT JOIN users u ON s.professional_id = u.user_id 
            WHERE 1=1";
    $params = [];
    
    if ($professional_id) {
        $sql .= " AND s.professional_id = ?";
        $params[] = $professional_id;
    }
    
    $sql .= " ORDER BY s.name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
    
    send_success_response($services);
}

function getServiceById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT s.*, u.first_name, u.last_name FROM services s 
                          LEFT JOIN users u ON s.professional_id = u.user_id 
                          WHERE s.service_id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
    
    if ($service) {
        send_success_response($service);
    } else {
        send_error_response('Service not found', 404);
    }
}

function createService($data, $pdo) {
    if (!is_logged_in() || !is_professional()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['name', 'duration', 'price'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO services (professional_id, name, description, duration, price) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['name'],
        $data['description'] ?? null,
        $data['duration'],
        $data['price']
    ]);
    
    $service_id = $pdo->lastInsertId();
    send_success_response(['service_id' => $service_id], 'Service created successfully');
}

function updateService($id, $data, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if service belongs to current user (if professional)
    if (is_professional()) {
        $stmt = $pdo->prepare("SELECT professional_id FROM services WHERE service_id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        
        if (!$service || $service['professional_id'] != $_SESSION['user_id']) {
            send_error_response('Unauthorized', 403);
        }
    }
    
    $allowed_fields = ['name', 'description', 'duration', 'price'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        send_error_response('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE services SET " . implode(', ', $update_fields) . " WHERE service_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    send_success_response([], 'Service updated successfully');
}

function deleteService($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if service belongs to current user (if professional)
    if (is_professional()) {
        $stmt = $pdo->prepare("SELECT professional_id FROM services WHERE service_id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        
        if (!$service || $service['professional_id'] != $_SESSION['user_id']) {
            send_error_response('Unauthorized', 403);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
    $stmt->execute([$id]);
    
    send_success_response([], 'Service deleted successfully');
}

// Appointments handlers
function handleAppointments($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getAppointmentById($id, $pdo);
            } else {
                getAllAppointments($pdo);
            }
            break;
        case 'POST':
            createAppointment($data, $pdo);
            break;
        case 'PUT':
            if ($id) {
                updateAppointment($id, $data, $pdo);
            } else {
                send_error_response('Appointment ID required for update');
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteAppointment($id, $pdo);
            } else {
                send_error_response('Appointment ID required for deletion');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllAppointments($pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if ($role === 'client') {
        $sql = "SELECT a.*, s.name as service_name, s.price, 
                       p.first_name as professional_first_name, p.last_name as professional_last_name
                FROM appointments a 
                JOIN services s ON a.service_id = s.service_id 
                JOIN users p ON a.professional_id = p.user_id 
                WHERE a.client_id = ? 
                ORDER BY a.appointment_date DESC, a.start_time DESC";
        $params = [$user_id];
    } elseif ($role === 'professional') {
        $sql = "SELECT a.*, s.name as service_name, s.price, 
                       c.first_name as client_first_name, c.last_name as client_last_name
                FROM appointments a 
                JOIN services s ON a.service_id = s.service_id 
                JOIN users c ON a.client_id = c.user_id 
                WHERE a.professional_id = ? 
                ORDER BY a.appointment_date DESC, a.start_time DESC";
        $params = [$user_id];
    } else {
        // Admin can see all appointments
        $sql = "SELECT a.*, s.name as service_name, s.price, 
                       c.first_name as client_first_name, c.last_name as client_last_name,
                       p.first_name as professional_first_name, p.last_name as professional_last_name
                FROM appointments a 
                JOIN services s ON a.service_id = s.service_id 
                JOIN users c ON a.client_id = c.user_id 
                JOIN users p ON a.professional_id = p.user_id 
                ORDER BY a.appointment_date DESC, a.start_time DESC";
        $params = [];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
    
    send_success_response($appointments);
}

function getAppointmentById($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("SELECT a.*, s.name as service_name, s.price, 
                          c.first_name as client_first_name, c.last_name as client_last_name,
                          p.first_name as professional_first_name, p.last_name as professional_last_name
                          FROM appointments a 
                          JOIN services s ON a.service_id = s.service_id 
                          JOIN users c ON a.client_id = c.user_id 
                          JOIN users p ON a.professional_id = p.user_id 
                          WHERE a.appointment_id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        // Check if user has permission to view this appointment
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        
        if ($role === 'client' && $appointment['client_id'] != $user_id) {
            send_error_response('Unauthorized', 403);
        } elseif ($role === 'professional' && $appointment['professional_id'] != $user_id) {
            send_error_response('Unauthorized', 403);
        }
        
        send_success_response($appointment);
    } else {
        send_error_response('Appointment not found', 404);
    }
}

function createAppointment($data, $pdo) {
    if (!is_logged_in() || !is_client()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['professional_id', 'service_id', 'appointment_date', 'start_time'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    // Get service details to calculate end time
    $stmt = $pdo->prepare("SELECT duration FROM services WHERE service_id = ?");
    $stmt->execute([$data['service_id']]);
    $service = $stmt->fetch();
    
    if (!$service) {
        send_error_response('Service not found', 404);
    }
    
    // Calculate end time
    $start_time = $data['start_time'];
    $end_time = date('H:i:s', strtotime($start_time) + ($service['duration'] * 60));
    
    // Check for conflicts
    $stmt = $pdo->prepare("SELECT appointment_id FROM appointments 
                          WHERE professional_id = ? AND appointment_date = ? 
                          AND status NOT IN ('cancelled') 
                          AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))");
    $stmt->execute([
        $data['professional_id'], 
        $data['appointment_date'], 
        $start_time, $start_time, 
        $end_time, $end_time
    ]);
    
    if ($stmt->fetch()) {
        send_error_response('Time slot is not available');
    }
    
    // Create appointment
    $stmt = $pdo->prepare("INSERT INTO appointments (client_id, professional_id, service_id, appointment_date, start_time, end_time, notes) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['professional_id'],
        $data['service_id'],
        $data['appointment_date'],
        $start_time,
        $end_time,
        $data['notes'] ?? null
    ]);
    
    $appointment_id = $pdo->lastInsertId();
    send_success_response(['appointment_id' => $appointment_id], 'Appointment created successfully');
}

function updateAppointment($id, $data, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Get appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        send_error_response('Appointment not found', 404);
    }
    
    // Check permissions
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if ($role === 'client' && $appointment['client_id'] != $user_id) {
        send_error_response('Unauthorized', 403);
    } elseif ($role === 'professional' && $appointment['professional_id'] != $user_id) {
        send_error_response('Unauthorized', 403);
    }
    
    $allowed_fields = ['status', 'notes'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        send_error_response('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE appointments SET " . implode(', ', $update_fields) . " WHERE appointment_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    send_success_response([], 'Appointment updated successfully');
}

function deleteAppointment($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Get appointment details
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE appointment_id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();
    
    if (!$appointment) {
        send_error_response('Appointment not found', 404);
    }
    
    // Check permissions
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if ($role === 'client' && $appointment['client_id'] != $user_id) {
        send_error_response('Unauthorized', 403);
    } elseif ($role === 'professional' && $appointment['professional_id'] != $user_id) {
        send_error_response('Unauthorized', 403);
    }
    
    // Instead of deleting, mark as cancelled
    $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
    $stmt->execute([$id]);
    
    send_success_response([], 'Appointment cancelled successfully');
}

// Gallery handlers
function handleGallery($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getGalleryItemById($id, $pdo);
            } else {
                getAllGalleryItems($pdo);
            }
            break;
        case 'POST':
            createGalleryItem($data, $pdo);
            break;
        case 'DELETE':
            if ($id) {
                deleteGalleryItem($id, $pdo);
            } else {
                send_error_response('Gallery item ID required for deletion');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllGalleryItems($pdo) {
    $professional_id = $_GET['professional_id'] ?? null;
    
    $sql = "SELECT g.*, u.first_name, u.last_name FROM gallery g 
            JOIN users u ON g.professional_id = u.user_id 
            WHERE 1=1";
    $params = [];
    
    if ($professional_id) {
        $sql .= " AND g.professional_id = ?";
        $params[] = $professional_id;
    }
    
    $sql .= " ORDER BY g.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $gallery = $stmt->fetchAll();
    
    send_success_response($gallery);
}

function getGalleryItemById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT g.*, u.first_name, u.last_name FROM gallery g 
                          JOIN users u ON g.professional_id = u.user_id 
                          WHERE g.image_id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
    
    if ($item) {
        send_success_response($item);
    } else {
        send_error_response('Gallery item not found', 404);
    }
}

function createGalleryItem($data, $pdo) {
    if (!is_logged_in() || !is_professional()) {
        send_error_response('Unauthorized', 403);
    }
    
    if (!isset($data['image_path']) || empty($data['image_path'])) {
        send_error_response('Image path is required');
    }
    
    $stmt = $pdo->prepare("INSERT INTO gallery (professional_id, image_path, title, description, category) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['image_path'],
        $data['title'] ?? null,
        $data['description'] ?? null,
        $data['category'] ?? null
    ]);
    
    $image_id = $pdo->lastInsertId();
    send_success_response(['image_id' => $image_id], 'Gallery item created successfully');
}

function deleteGalleryItem($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if gallery item belongs to current user (if professional)
    if (is_professional()) {
        $stmt = $pdo->prepare("SELECT professional_id FROM gallery WHERE image_id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        
        if (!$item || $item['professional_id'] != $_SESSION['user_id']) {
            send_error_response('Unauthorized', 403);
        }
    }
    
    $stmt = $pdo->prepare("DELETE FROM gallery WHERE image_id = ?");
    $stmt->execute([$id]);
    
    send_success_response([], 'Gallery item deleted successfully');
}

// Messages handlers
function handleMessages($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getMessageById($id, $pdo);
            } else {
                getAllMessages($pdo);
            }
            break;
        case 'POST':
            createMessage($data, $pdo);
            break;
        case 'PUT':
            if ($id) {
                markMessageAsRead($id, $pdo);
            } else {
                send_error_response('Message ID required for update');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllMessages($pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $user_id = $_SESSION['user_id'];
    $conversation_with = $_GET['conversation_with'] ?? null;
    
    if ($conversation_with) {
        // Get messages between current user and specific user
        $sql = "SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name
                FROM messages m 
                JOIN users s ON m.sender_id = s.user_id 
                JOIN users r ON m.receiver_id = r.user_id 
                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.created_at ASC";
        $params = [$user_id, $conversation_with, $conversation_with, $user_id];
    } else {
        // Get all conversations for current user
        $sql = "SELECT m.*, 
                       s.first_name as sender_first_name, s.last_name as sender_last_name,
                       r.first_name as receiver_first_name, r.last_name as receiver_last_name
                FROM messages m 
                JOIN users s ON m.sender_id = s.user_id 
                JOIN users r ON m.receiver_id = r.user_id 
                WHERE m.sender_id = ? OR m.receiver_id = ?
                ORDER BY m.created_at DESC";
        $params = [$user_id, $user_id];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $messages = $stmt->fetchAll();
    
    send_success_response($messages);
}

function getMessageById($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("SELECT m.*, 
                          s.first_name as sender_first_name, s.last_name as sender_last_name,
                          r.first_name as receiver_first_name, r.last_name as receiver_last_name
                          FROM messages m 
                          JOIN users s ON m.sender_id = s.user_id 
                          JOIN users r ON m.receiver_id = r.user_id 
                          WHERE m.message_id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if ($message) {
        // Check if user has permission to view this message
        $user_id = $_SESSION['user_id'];
        
        if ($message['sender_id'] != $user_id && $message['receiver_id'] != $user_id) {
            send_error_response('Unauthorized', 403);
        }
        
        send_success_response($message);
    } else {
        send_error_response('Message not found', 404);
    }
}

function createMessage($data, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['receiver_id', 'content'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, content) VALUES (?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['receiver_id'],
        $data['content']
    ]);
    
    $message_id = $pdo->lastInsertId();
    send_success_response(['message_id' => $message_id], 'Message sent successfully');
}

function markMessageAsRead($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if user is the receiver of this message
    $stmt = $pdo->prepare("SELECT receiver_id FROM messages WHERE message_id = ?");
    $stmt->execute([$id]);
    $message = $stmt->fetch();
    
    if (!$message) {
        send_error_response('Message not found', 404);
    }
    
    if ($message['receiver_id'] != $_SESSION['user_id']) {
        send_error_response('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("UPDATE messages SET read_status = TRUE WHERE message_id = ?");
    $stmt->execute([$id]);
    
    send_success_response([], 'Message marked as read');
}

// Payments handlers
function handlePayments($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getPaymentById($id, $pdo);
            } else {
                getAllPayments($pdo);
            }
            break;
        case 'POST':
            createPayment($data, $pdo);
            break;
        case 'PUT':
            if ($id) {
                updatePayment($id, $data, $pdo);
            } else {
                send_error_response('Payment ID required for update');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllPayments($pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $user_id = $_SESSION['user_id'];
    $role = $_SESSION['role'];
    
    if ($role === 'client') {
        $sql = "SELECT p.*, a.appointment_date, a.start_time, s.name as service_name
                FROM payments p 
                JOIN appointments a ON p.appointment_id = a.appointment_id 
                JOIN services s ON a.service_id = s.service_id 
                WHERE a.client_id = ? 
                ORDER BY p.created_at DESC";
        $params = [$user_id];
    } elseif ($role === 'professional') {
        $sql = "SELECT p.*, a.appointment_date, a.start_time, s.name as service_name,
                       c.first_name as client_first_name, c.last_name as client_last_name
                FROM payments p 
                JOIN appointments a ON p.appointment_id = a.appointment_id 
                JOIN services s ON a.service_id = s.service_id 
                JOIN users c ON a.client_id = c.user_id 
                WHERE a.professional_id = ? 
                ORDER BY p.created_at DESC";
        $params = [$user_id];
    } else {
        // Admin can see all payments
        $sql = "SELECT p.*, a.appointment_date, a.start_time, s.name as service_name,
                       c.first_name as client_first_name, c.last_name as client_last_name,
                       pr.first_name as professional_first_name, pr.last_name as professional_last_name
                FROM payments p 
                JOIN appointments a ON p.appointment_id = a.appointment_id 
                JOIN services s ON a.service_id = s.service_id 
                JOIN users c ON a.client_id = c.user_id 
                JOIN users pr ON a.professional_id = pr.user_id 
                ORDER BY p.created_at DESC";
        $params = [];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    send_success_response($payments);
}

function getPaymentById($id, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("SELECT p.*, a.appointment_date, a.start_time, s.name as service_name,
                          c.first_name as client_first_name, c.last_name as client_last_name,
                          pr.first_name as professional_first_name, pr.last_name as professional_last_name
                          FROM payments p 
                          JOIN appointments a ON p.appointment_id = a.appointment_id 
                          JOIN services s ON a.service_id = s.service_id 
                          JOIN users c ON a.client_id = c.user_id 
                          JOIN users pr ON a.professional_id = pr.user_id 
                          WHERE p.payment_id = ?");
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    
    if ($payment) {
        // Check if user has permission to view this payment
        $user_id = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        
        if ($role === 'client' && $payment['client_id'] != $user_id) {
            send_error_response('Unauthorized', 403);
        } elseif ($role === 'professional' && $payment['professional_id'] != $user_id) {
            send_error_response('Unauthorized', 403);
        }
        
        send_success_response($payment);
    } else {
        send_error_response('Payment not found', 404);
    }
}

function createPayment($data, $pdo) {
    if (!is_logged_in()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['appointment_id', 'amount'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO payments (appointment_id, amount, payment_method, transaction_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([
        $data['appointment_id'],
        $data['amount'],
        $data['payment_method'] ?? 'card',
        $data['transaction_id'] ?? null
    ]);
    
    $payment_id = $pdo->lastInsertId();
    send_success_response(['payment_id' => $payment_id], 'Payment created successfully');
}

function updatePayment($id, $data, $pdo) {
    if (!is_logged_in() || !is_admin()) {
        send_error_response('Unauthorized', 403);
    }
    
    $allowed_fields = ['status', 'transaction_id'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        send_error_response('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE payments SET " . implode(', ', $update_fields) . " WHERE payment_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    send_success_response([], 'Payment updated successfully');
}

// Availability handlers
function handleAvailability($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getAvailabilityById($id, $pdo);
            } else {
                getAllAvailability($pdo);
            }
            break;
        case 'POST':
            createAvailability($data, $pdo);
            break;
        case 'PUT':
            if ($id) {
                updateAvailability($id, $data, $pdo);
            } else {
                send_error_response('Availability ID required for update');
            }
            break;
        case 'DELETE':
            if ($id) {
                deleteAvailability($id, $pdo);
            } else {
                send_error_response('Availability ID required for deletion');
            }
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllAvailability($pdo) {
    $professional_id = $_GET['professional_id'] ?? null;
    
    if (!$professional_id) {
        send_error_response('Professional ID is required');
    }
    
    $sql = "SELECT a.*, u.first_name, u.last_name FROM availability a 
            JOIN users u ON a.professional_id = u.user_id 
            WHERE a.professional_id = ? 
            ORDER BY a.day_of_week, a.start_time";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$professional_id]);
    $availability = $stmt->fetchAll();
    
    send_success_response($availability);
}

function getAvailabilityById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM availability a 
                          JOIN users u ON a.professional_id = u.user_id 
                          WHERE a.availability_id = ?");
    $stmt->execute([$id]);
    $availability = $stmt->fetch();
    
    if ($availability) {
        send_success_response($availability);
    } else {
        send_error_response('Availability not found', 404);
    }
}

function createAvailability($data, $pdo) {
    if (!is_logged_in() || !is_professional()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['day_of_week', 'start_time', 'end_time'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO availability (professional_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['day_of_week'],
        $data['start_time'],
        $data['end_time'],
        $data['is_available'] ?? true
    ]);
    
    $availability_id = $pdo->lastInsertId();
    send_success_response(['availability_id' => $availability_id], 'Availability created successfully');
}

function updateAvailability($id, $data, $pdo) {
    if (!is_logged_in() || !is_professional()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if availability belongs to current user
    $stmt = $pdo->prepare("SELECT professional_id FROM availability WHERE availability_id = ?");
    $stmt->execute([$id]);
    $availability = $stmt->fetch();
    
    if (!$availability || $availability['professional_id'] != $_SESSION['user_id']) {
        send_error_response('Unauthorized', 403);
    }
    
    $allowed_fields = ['day_of_week', 'start_time', 'end_time', 'is_available'];
    $update_fields = [];
    $params = [];
    
    foreach ($allowed_fields as $field) {
        if (isset($data[$field])) {
            $update_fields[] = "$field = ?";
            $params[] = $data[$field];
        }
    }
    
    if (empty($update_fields)) {
        send_error_response('No valid fields to update');
    }
    
    $params[] = $id;
    $sql = "UPDATE availability SET " . implode(', ', $update_fields) . " WHERE availability_id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    send_success_response([], 'Availability updated successfully');
}

function deleteAvailability($id, $pdo) {
    if (!is_logged_in() || !is_professional()) {
        send_error_response('Unauthorized', 403);
    }
    
    // Check if availability belongs to current user
    $stmt = $pdo->prepare("SELECT professional_id FROM availability WHERE availability_id = ?");
    $stmt->execute([$id]);
    $availability = $stmt->fetch();
    
    if (!$availability || $availability['professional_id'] != $_SESSION['user_id']) {
        send_error_response('Unauthorized', 403);
    }
    
    $stmt = $pdo->prepare("DELETE FROM availability WHERE availability_id = ?");
    $stmt->execute([$id]);
    
    send_success_response([], 'Availability deleted successfully');
}

// Reviews handlers
function handleReviews($method, $id, $data, $pdo) {
    switch ($method) {
        case 'GET':
            if ($id) {
                getReviewById($id, $pdo);
            } else {
                getAllReviews($pdo);
            }
            break;
        case 'POST':
            createReview($data, $pdo);
            break;
        default:
            send_error_response('Method not allowed', 405);
    }
}

function getAllReviews($pdo) {
    $professional_id = $_GET['professional_id'] ?? null;
    
    $sql = "SELECT r.*, c.first_name as client_first_name, c.last_name as client_last_name,
                   p.first_name as professional_first_name, p.last_name as professional_last_name
            FROM reviews r 
            JOIN users c ON r.client_id = c.user_id 
            JOIN users p ON r.professional_id = p.user_id 
            WHERE 1=1";
    $params = [];
    
    if ($professional_id) {
        $sql .= " AND r.professional_id = ?";
        $params[] = $professional_id;
    }
    
    $sql .= " ORDER BY r.created_at DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $reviews = $stmt->fetchAll();
    
    send_success_response($reviews);
}

function getReviewById($id, $pdo) {
    $stmt = $pdo->prepare("SELECT r.*, c.first_name as client_first_name, c.last_name as client_last_name,
                          p.first_name as professional_first_name, p.last_name as professional_last_name
                          FROM reviews r 
                          JOIN users c ON r.client_id = c.user_id 
                          JOIN users p ON r.professional_id = p.user_id 
                          WHERE r.review_id = ?");
    $stmt->execute([$id]);
    $review = $stmt->fetch();
    
    if ($review) {
        send_success_response($review);
    } else {
        send_error_response('Review not found', 404);
    }
}

function createReview($data, $pdo) {
    if (!is_logged_in() || !is_client()) {
        send_error_response('Unauthorized', 403);
    }
    
    $required_fields = ['professional_id', 'rating'];
    foreach ($required_fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            send_error_response("$field is required");
        }
    }
    
    // Validate rating
    if ($data['rating'] < 1 || $data['rating'] > 5) {
        send_error_response('Rating must be between 1 and 5');
    }
    
    $stmt = $pdo->prepare("INSERT INTO reviews (client_id, professional_id, appointment_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([
        $_SESSION['user_id'],
        $data['professional_id'],
        $data['appointment_id'] ?? null,
        $data['rating'],
        $data['comment'] ?? null
    ]);
    
    $review_id = $pdo->lastInsertId();
    send_success_response(['review_id' => $review_id], 'Review created successfully');
}
?>

