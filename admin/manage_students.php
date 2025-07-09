<?php
// Start session first
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Admin authentication check
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['username'])) {
    header('Location: login.php?error=Please login to access admin panel');
    exit();
}

// Include database connection
require_once '../includes/db.php';

$message = '';
$message_type = '';
$errors = [];

// Handle student registration
if (isset($_POST['register_student'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    
    // Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $checkStmt = $conn->prepare("SELECT username, email FROM users WHERE username = ? OR email = ?");
            $checkStmt->execute([$username, $email]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                if ($existing['username'] === $username) {
                    $errors[] = "Username already exists.";
                }
                if ($existing['email'] === $email) {
                    $errors[] = "Email already exists.";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    // If no errors, register the student
    if (empty($errors)) {
        try {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            $insertStmt = $conn->prepare("
                INSERT INTO users (username, password, first_name, last_name, email, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            if ($insertStmt->execute([$username, $password_hash, $first_name, $last_name, $email])) {
                $message = "Student registered successfully!";
                $message_type = "success";
                
                // Clear form data on success
                $username = $password = $confirm_password = $first_name = $last_name = $email = '';
            } else {
                $errors[] = "Failed to register student.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Handle student deletion
if (isset($_POST['delete_student']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    
    try {
        // Check if student has borrowed books
        $borrowCheck = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE user_id = ? AND status = 'borrowed'");
        $borrowCheck->execute([$user_id]);
        $borrowedCount = $borrowCheck->fetch()['count'];
        
        if ($borrowedCount > 0) {
            $message = "Cannot delete student. They have borrowed books that need to be returned first.";
            $message_type = "error";
        } else {
            // Get student name for confirmation message
            $nameStmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
            $nameStmt->execute([$user_id]);
            $student = $nameStmt->fetch();
            
            if ($student) {
                // Delete the student
                $deleteStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                if ($deleteStmt->execute([$user_id])) {
                    $message = "Student {$student['first_name']} {$student['last_name']} deleted successfully.";
                    $message_type = "success";
                } else {
                    $message = "Failed to delete student.";
                    $message_type = "error";
                }
            } else {
                $message = "Student not found.";
                $message_type = "error";
            }
        }
    } catch (PDOException $e) {
        $message = "Error: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build the query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR username LIKE ? OR email LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Valid sort columns
$valid_sorts = ['first_name', 'last_name', 'username', 'email', 'created_at'];
if (!in_array($sort_by, $valid_sorts)) {
    $sort_by = 'created_at';
}

$valid_orders = ['ASC', 'DESC'];
if (!in_array(strtoupper($sort_order), $valid_orders)) {
    $sort_order = 'DESC';
}

try {
    // Get students (simplified query without borrowing stats)
    $query = "
        SELECT u.*
        FROM users u
        $where_clause
        ORDER BY $sort_by $sort_order
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll();
    
    // Get total count
    $totalStudents = count($students);
    
    // Get overall statistics (simplified)
    $statsQuery = "
        SELECT 
            COUNT(DISTINCT u.user_id) as total_students,
            COUNT(DISTINCT CASE WHEN bb.status = 'borrowed' THEN u.user_id END) as active_borrowers,
            COUNT(CASE WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN 1 END) as total_overdue
        FROM users u
        LEFT JOIN borrowed_books bb ON u.user_id = bb.user_id
    ";
    $stats = $conn->query($statsQuery)->fetch();

} catch (PDOException $e) {
    $students = [];
    $totalStudents = 0;
    $stats = ['total_students' => 0, 'active_borrowers' => 0, 'total_overdue' => 0];
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function to build URL with current parameters
function buildUrl($newParams = []) {
    $currentParams = $_GET;
    $params = array_merge($currentParams, $newParams);
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_manage_students.css">
    <?php include '../includes/favicon.php'; ?>

</head>
<body>
<div class="admin-container">
        <!-- Sidebar -->
        <aside class="admin-sidebar">
            <div class="sidebar-logo">
                <img src="../assets/images/logo.png" alt="Logo" class="banner-logo-dashboard">
                <div class="sidebar-title">The Cat-alog<br>Library</div>
            </div>
            
            
            <nav>
                <ul class="sidebar-nav">
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="manage_books.php">Manage Books</a></li>
                    <li><a href="manage_borrowed.php">Borrowed Books</a></li>
                    <li><a href="manage_students.php" class="active">Students</a></li>
                    <li><a href="archive_books.php">Archive</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <h1 class="page-title">👥 Manage Students</h1>
                <div class="admin-info">
                <div class="admin-avatar">
                        <img src="../assets/images/admin-icon.jpg" alt="Admin Icon" class="admin-avatar">
                    </div>                    
                    <div class="admin-details">
                        <h3><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></h3>
                        <p>Library Administrator</p>
                    </div>
                </div>
            </header>

            <!-- Statistics -->
            <div class="stats-row">
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['total_students']; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['active_borrowers']; ?></div>
                    <div class="stat-label">Active Borrowers</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['total_overdue']; ?></div>
                    <div class="stat-label">Overdue Books</div>
                </div>
            </div>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php if ($message_type === 'success'): ?>
                        ✅ <?php echo htmlspecialchars($message); ?>
                    <?php else: ?>
                        ❌ There were some errors:
                        <ul class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
                
            <!-- Toggle Registration Section -->
            <div class="toggle-section">
                <button type="button" class="toggle-btn" onclick="toggleRegistration()">
                    ➕ Register New Student
                </button>
            </div>

            <!-- Student Registration Section -->
            <div class="registration-section hidden" id="registrationSection">
                <h3>👤 Register New Student</h3>
                <form method="POST" class="registration-form">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required 
                               value="<?php echo htmlspecialchars($username ?? ''); ?>"
                               placeholder="e.g., student123">
                    </div>
                    
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
                               placeholder="First name">
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
                               placeholder="Last name">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required 
                               value="<?php echo htmlspecialchars($email ?? ''); ?>"
                               placeholder="student@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Minimum 6 characters">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Repeat password">
                    </div>

                    <div class="form-group" style="display: flex; align-items: end;">
                        <button type="submit" name="register_student" class="action-btn" style="width: 100%;">
                            👤 Register Student
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search Students</label>
                            <input type="text" id="search" name="search" placeholder="Search by name, username, or email..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="action-btn">
                                🔍 Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="students-table">
                <div class="table-header">
                    <div>
                        <h2>📋 Students List (<?php echo $totalStudents; ?>)</h2>
                        <p>Sort by: 
                            <a href="<?php echo buildUrl(['sort' => 'first_name', 'order' => $sort_by === 'first_name' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Name</a> | 
                            <a href="<?php echo buildUrl(['sort' => 'username', 'order' => $sort_by === 'username' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Username</a> | 
                            <a href="<?php echo buildUrl(['sort' => 'created_at', 'order' => $sort_by === 'created_at' && $sort_order === 'ASC' ? 'DESC' : 'ASC']); ?>">Join Date</a>
                        </p>
                    </div>
                    <a href="?" class="action-btn" style="background: white; color: var(--orange);">
                        🔄 Clear Filters
                    </a>
                </div>

                <?php if (empty($students)): ?>
                    <div class="empty-state">
                        <h3>👥 No students found</h3>
                        <p>No students match your search criteria.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Contact</th>
                                <th>Join Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
                                            </div>
                                            <div class="student-details">
                                                <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                                                <p>@<?php echo htmlspecialchars($student['username']); ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($student['email']); ?></div>
                                        <small style="color: #666;">ID: <?php echo $student['user_id']; ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" 
                                              onsubmit="return confirm('Are you sure you want to delete this student? This action cannot be undone.');">
                                            <input type="hidden" name="user_id" value="<?php echo $student['user_id']; ?>">
                                            <button type="submit" name="delete_student" class="btn-delete">
                                                🗑️ Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        function toggleRegistration() {
            const section = document.getElementById('registrationSection');
            const button = document.querySelector('.toggle-btn');
            
            if (section.classList.contains('hidden')) {
                section.classList.remove('hidden');
                button.textContent = '➖ Hide Registration Form';
            } else {
                section.classList.add('hidden');
                button.textContent = '➕ Register New Student';
            }
        }

        // Show registration form if there are errors
        <?php if (!empty($errors) && isset($_POST['register_student'])): ?>
        document.addEventListener('DOMContentLoaded', function() {
            toggleRegistration();
        });
        <?php endif; ?>

        // Auto-submit search form
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search');
            let searchTimeout;
            
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.form.submit();
                }, 500);
            });
        });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>