<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
try {
    require_once '../includes/db.php';
    if (!isset($conn) || !$conn) {
        throw new Exception("Database connection not established");
    }
} catch (Exception $e) {
    die("Database connection error: " . $e->getMessage());
}

// Try to include helper functions with better error handling
$useHelperFunctions = false;
$helperError = '';
try {
    $bookFunctionsPath = '../includes/book_functions.php';
    if (file_exists($bookFunctionsPath)) {
        require_once $bookFunctionsPath;
        // Test if the function exists
        if (function_exists('addBookCopy')) {
            $useHelperFunctions = true;
        } else {
            $helperError = "addBookCopy function not found in book_functions.php";
        }
    } else {
        $helperError = "book_functions.php file not found at: " . $bookFunctionsPath;
    }
} catch (Exception $e) {
    error_log("Helper functions failed to load: " . $e->getMessage());
    $helperError = "Error loading helper functions: " . $e->getMessage();
    $useHelperFunctions = false;
}

$message = '';
$message_type = '';
$errors = [];

// Manual book ID generation function (improved)
function manualGenerateBookId($title, $month, $year, $category, $titleId, $copyNumber) {
    // Extract first 2 letters from title
    $cleanTitle = preg_replace('/[^A-Za-z0-9]/', '', $title);
    $titlePrefix = strtoupper(substr($cleanTitle, 0, 2));
    if (strlen($titlePrefix) < 2) {
        $titlePrefix = str_pad($titlePrefix, 2, 'X', STR_PAD_RIGHT);
    }
    
    // Convert month to abbreviation
    $monthAbbrev = [
        'JANUARY' => 'JAN', 'FEBRUARY' => 'FEB', 'MARCH' => 'MAR',
        'APRIL' => 'APR', 'MAY' => 'MAY', 'JUNE' => 'JUN',
        'JULY' => 'JUL', 'AUGUST' => 'AUG', 'SEPTEMBER' => 'SEP',
        'OCTOBER' => 'OCT', 'NOVEMBER' => 'NOV', 'DECEMBER' => 'DEC'
    ];
    
    $monthStr = $monthAbbrev[strtoupper($month)] ?? 'JAN';
    $dayStr = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
    $yearStr = (string)$year;
    
    // Category code - use first 3 letters of category name
    $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $category);
    $categoryCode = strtoupper(substr($cleanCategory, 0, 3));
    if (strlen($categoryCode) < 3) {
        $categoryCode = str_pad($categoryCode, 3, 'X', STR_PAD_RIGHT);
    }
    
    // Format count with leading zeros
    $countStr = str_pad($titleId . str_pad($copyNumber, 2, '0', STR_PAD_LEFT), 5, '0', STR_PAD_LEFT);
    
    return $titlePrefix . $monthStr . $dayStr . $yearStr . '-' . $categoryCode . $countStr;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transactionStarted = false; // Initialize at the top level
    
    try {
        // Validate and sanitize input
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $published_year = trim($_POST['published_year'] ?? '');
        $published_month = trim($_POST['published_month'] ?? '');
        $total_copies = (int)($_POST['total_copies'] ?? 1);
        $status = 'Available';
        
        // Handle custom category
        if ($category === '__custom__' && !empty($_POST['category_custom'])) {
            $category = trim($_POST['category_custom']);
        }
        
        // Validation
        if (empty($title)) {
            $errors[] = "Book title is required.";
        }
        
        if (empty($author)) {
            $errors[] = "Author name is required.";
        }
        
        if (empty($category)) {
            $errors[] = "Category is required.";
        }
        
        if (!empty($published_year) && (!is_numeric($published_year) || $published_year < 1000 || $published_year > date('Y'))) {
            $errors[] = "Please enter a valid publication year.";
        }
        
        if ($total_copies < 1 || $total_copies > 50) {
            $errors[] = "Number of copies must be between 1 and 50.";
        }
        
        // Handle file upload
        $book_image = null;
        if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/book-images/';
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                if (!mkdir($upload_dir, 0777, true)) {
                    $errors[] = "Failed to create upload directory.";
                }
            }
            
            if (empty($errors)) {
                $file_tmp = $_FILES['book_image']['tmp_name'];
                $file_name = $_FILES['book_image']['name'];
                $file_size = $_FILES['book_image']['size'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                // Validate file
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                $max_file_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file_ext, $allowed_extensions)) {
                    $errors[] = "Only JPG, JPEG, PNG, GIF, and WEBP files are allowed.";
                } elseif ($file_size > $max_file_size) {
                    $errors[] = "File size must be less than 5MB.";
                } else {
                    // Generate unique filename
                    $book_image = uniqid() . '_' . time() . '.' . $file_ext;
                    $upload_path = $upload_dir . $book_image;
                    
                    if (!move_uploaded_file($file_tmp, $upload_path)) {
                        $errors[] = "Failed to upload image.";
                        $book_image = null;
                    }
                }
            }
        }
        
        // If no errors, insert into database
        if (empty($errors)) {
            // Start transaction
            $conn->beginTransaction();
            $transactionStarted = true;
            
            // Insert into book_titles table
            $sql = "INSERT INTO book_titles (title, author, category, published_year, published_month, book_image, status, total_copies, available_copies, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                $title,
                $author,
                $category,
                $published_year ?: null,
                $published_month ?: null,
                $book_image,
                $status,
                $total_copies,
                $total_copies // available_copies starts same as total_copies
            ]);
            
            if ($result) {
                $title_id = $conn->lastInsertId();
                
                // Verify the title_id exists before proceeding
                $verifyStmt = $conn->prepare("SELECT title_id FROM book_titles WHERE title_id = ?");
                $verifyStmt->execute([$title_id]);
                if (!$verifyStmt->fetch()) {
                    throw new Exception("Failed to verify book title insertion. Title ID: $title_id not found.");
                }
                
                $generated_book_ids = [];
                
                // Create individual book copies
                for ($copy_number = 1; $copy_number <= $total_copies; $copy_number++) {
                    $success = false;
                    
                    // Try helper function first
                    if ($useHelperFunctions) {
                        try {
                            $copyResult = addBookCopy(
                                $conn, 
                                $title_id, 
                                'Excellent', 
                                date('Y-m-d'), 
                                null,
                                'Available'
                            );
                            
                            $generated_book_ids[] = $copyResult['book_id'];
                            $success = true;
                            
                        } catch (Exception $e) {
                            error_log("Helper function failed for copy $copy_number: " . $e->getMessage());
                            $success = false;
                        }
                    }
                    
                    // Fallback to manual method if helper function failed or unavailable
                    if (!$success) {
                        try {
                            // Verify title_id still exists before manual insert
                            $checkStmt = $conn->prepare("SELECT title_id FROM book_titles WHERE title_id = ?");
                            $checkStmt->execute([$title_id]);
                            if (!$checkStmt->fetch()) {
                                throw new Exception("Title ID $title_id no longer exists for manual insert");
                            }
                            
                            // Generate book_id manually
                            $book_id = manualGenerateBookId(
                                $title,
                                $published_month ?: 'January',
                                $published_year ?: date('Y'),
                                $category,
                                $title_id,
                                $copy_number
                            );
                            
                            // Insert manually with explicit foreign key
                            $copy_sql = "INSERT INTO book_copies (title_id, book_id, copy_number, condition_status, acquisition_date, status, created_at) 
                                        VALUES (?, ?, ?, 'Excellent', CURDATE(), 'Available', NOW())";
                            
                            $copy_stmt = $conn->prepare($copy_sql);
                            $copy_result = $copy_stmt->execute([$title_id, $book_id, $copy_number]);
                            
                            if (!$copy_result) {
                                $errorInfo = $copy_stmt->errorInfo();
                                throw new Exception("Manual insert failed: " . implode(', ', $errorInfo));
                            }
                            
                            $generated_book_ids[] = $book_id;
                            $success = true;
                            
                        } catch (Exception $e) {
                            error_log("Manual method also failed for copy $copy_number: " . $e->getMessage());
                            throw new Exception("Failed to create copy $copy_number: " . $e->getMessage());
                        }
                    }
                }
                
                // Update book availability counts (try helper function, fallback to manual)
                try {
                    if ($useHelperFunctions && function_exists('updateBookAvailability')) {
                        updateBookAvailability($conn, $title_id);
                    } else {
                        // Manual update
                        $updateSql = "
                            UPDATE book_titles 
                            SET 
                                total_copies = (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status != 'Archived'),
                                available_copies = (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status = 'Available'),
                                status = CASE 
                                    WHEN (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status = 'Available') > 0 THEN 'Available'
                                    ELSE 'Borrowed'
                                END
                            WHERE title_id = ?
                        ";
                        
                        $updateStmt = $conn->prepare($updateSql);
                        $updateStmt->execute([$title_id, $title_id, $title_id, $title_id]);
                    }
                } catch (Exception $e) {
                    error_log("Failed to update book availability: " . $e->getMessage());
                    // This is not critical, so we continue
                }
                
                // Commit transaction
                $conn->commit();
                
                $message = "Book added successfully!<br>";
                $message .= "<strong>Title:</strong> {$title}<br>";
                $message .= "<strong>Total Copies:</strong> {$total_copies}<br>";
                $message .= "<strong>Generated Book IDs:</strong><br>";
                foreach ($generated_book_ids as $index => $book_id) {
                    $copy_num = $index + 1;
                    $message .= "&nbsp;&nbsp;Copy #{$copy_num}: <code>{$book_id}</code><br>";
                }
                $message_type = "success";
                
                // Clear form data on success
                $title = $author = $category = $published_year = $published_month = '';
                $total_copies = 1;
                
            } else {
                if ($transactionStarted) {
                    $conn->rollBack();
                }
                $errors[] = "Failed to add book to database.";
            }
        }
        
    } catch (PDOException $e) {
        if ($transactionStarted) {
            try {
                $conn->rollBack();
            } catch (PDOException $rollbackException) {
                error_log("Rollback failed: " . $rollbackException->getMessage());
            }
        }
        if ($e->getCode() == 23000) {
            $errors[] = "A book with this combination already exists. Please try again.";
        } else {
            $errors[] = "Database error: " . $e->getMessage();
            error_log("Database error in add_book.php: " . $e->getMessage());
        }
    } catch (Exception $e) {
        if ($transactionStarted) {
            try {
                $conn->rollBack();
            } catch (PDOException $rollbackException) {
                error_log("Rollback failed: " . $rollbackException->getMessage());
            }
        }
        $errors[] = "Error adding book: " . $e->getMessage();
        error_log("Book error in add_book.php: " . $e->getMessage());
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Get existing categories for dropdown
try {
    $categoryStmt = $conn->prepare("SELECT DISTINCT category FROM book_titles WHERE category IS NOT NULL AND category != '' ORDER BY category");
    $categoryStmt->execute();
    $existing_categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $existing_categories = [];
    error_log("Error fetching categories: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Book - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_add_book.css">
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
                    <li><a href="manage_books.php" class="active">Manage Books</a></li>
                    <li><a href="manage_borrowed.php">Borrowed Books</a></li>
                    <li><a href="manage_students.php">Students</a></li>
                    <li><a href="archive_books.php">Archive</a></li>
                    <li><a href="logout.php">Logout</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="admin-main">
            <!-- Header -->
            <header class="admin-header">
                <h1 class="page-title">📚 Add New Book</h1>
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

            <!-- Debug info -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="message info">
                    <strong>Debug Info:</strong><br>
                    Helper functions available: <?php echo $useHelperFunctions ? 'Yes' : 'No'; ?><br>
                    Helper error: <?php echo htmlspecialchars($helperError); ?><br>
                    book_functions.php exists: <?php echo file_exists('../includes/book_functions.php') ? 'Yes' : 'No'; ?><br>
                    addBookCopy function exists: <?php echo function_exists('addBookCopy') ? 'Yes' : 'No'; ?><br>
                    Database connection: <?php echo isset($conn) && $conn ? 'OK' : 'Failed'; ?>
                </div>
            <?php endif; ?>

            <!-- Messages -->
            <?php if (!empty($message)): ?>
                <div class="message <?php echo $message_type; ?>">
                    <?php if ($message_type === 'success'): ?>
                        ✅ <?php echo $message; ?>
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

            <!-- Book ID Format Explanation -->
            <div class="book-id-explanation">
                <h5>📖 Book ID Format (Auto-Generated for Each Copy)</h5>
                <ul>
                    <li><strong>TE</strong> - First 2 letters from Book Title</li>
                    <li><strong>MAR</strong> - Published Month (JAN, FEB, MAR, etc.)</li>
                    <li><strong><?php echo date('d'); ?></strong> - Day when added to system (today: <?php echo date('d'); ?>)</li>
                    <li><strong><?php echo date('Y'); ?></strong> - Published Year</li>
                    <li><strong>FAN</strong> - Category Code (First 3 letters of category name)</li>
                    <li><strong>00101, 00102, 00103...</strong> - Title ID + Copy Number</li>
                </ul>
                <p><em>Example: "Test Book" in "Fantasy" category with 3 copies → TEMAR<?php echo date('d') . date('Y'); ?>-FAN00101, TEMAR<?php echo date('d') . date('Y'); ?>-FAN00102, TEMAR<?php echo date('d') . date('Y'); ?>-FAN00103</em></p>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <form method="POST" enctype="multipart/form-data" id="addBookForm">

                    <div class="form-grid">
                        <!-- Book Title -->
                        <div class="form-group">
                            <label for="title">Book Title *</label>
                            <input type="text" id="title" name="title" required 
                                   value="<?php echo htmlspecialchars($title ?? ''); ?>"
                                   placeholder="Enter book title">
                        </div>

                        <!-- Author -->
                        <div class="form-group">
                            <label for="author">Author *</label>
                            <input type="text" id="author" name="author" required 
                                   value="<?php echo htmlspecialchars($author ?? ''); ?>"
                                   placeholder="Enter author name">
                        </div>

                        <!-- Category -->
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <div class="category-input-group">
                                <select id="categorySelect" name="category" class="category-select" required>
                                    <option value="">Select a category</option>
                                    <?php foreach ($existing_categories as $cat): ?>
                                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                                <?php echo (isset($category) && $category === $cat) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat); ?>
                                        </option>
                                    <?php endforeach; ?>
                                    <option value="__custom__">+ Add New Category</option>
                                </select>
                                <input type="text" id="categoryInput" name="category_custom" class="category-input" 
                                       placeholder="Enter new category">
                            </div>
                        </div>

                        <!-- Number of Copies -->
                        <div class="form-group">
                            <label for="total_copies">Number of Copies *</label>
                            <input type="number" id="total_copies" name="total_copies" 
                                   min="1" max="50" value="<?php echo htmlspecialchars($total_copies ?? '1'); ?>"
                                   placeholder="How many copies?" required>
                            <small class="form-hint">Each copy will get a unique Book ID</small>
                        </div>

                        <!-- Published Year -->
                        <div class="form-group">
                            <label for="published_year">Published Year</label>
                            <input type="number" id="published_year" name="published_year" 
                                   min="1000" max="<?php echo date('Y'); ?>"
                                   value="<?php echo htmlspecialchars($published_year ?? ''); ?>"
                                   placeholder="e.g., <?php echo date('Y'); ?>">
                        </div>

                        <!-- Published Month -->
                        <div class="form-group">
                            <label for="published_month">Published Month</label>
                            <select id="published_month" name="published_month">
                                <option value="">Select month</option>
                                <option value="January" <?php echo (isset($published_month) && $published_month === 'January') ? 'selected' : ''; ?>>January</option>
                                <option value="February" <?php echo (isset($published_month) && $published_month === 'February') ? 'selected' : ''; ?>>February</option>
                                <option value="March" <?php echo (isset($published_month) && $published_month === 'March') ? 'selected' : ''; ?>>March</option>
                                <option value="April" <?php echo (isset($published_month) && $published_month === 'April') ? 'selected' : ''; ?>>April</option>
                                <option value="May" <?php echo (isset($published_month) && $published_month === 'May') ? 'selected' : ''; ?>>May</option>
                                <option value="June" <?php echo (isset($published_month) && $published_month === 'June') ? 'selected' : ''; ?>>June</option>
                                <option value="July" <?php echo (isset($published_month) && $published_month === 'July') ? 'selected' : ''; ?>>July</option>
                                <option value="August" <?php echo (isset($published_month) && $published_month === 'August') ? 'selected' : ''; ?>>August</option>
                                <option value="September" <?php echo (isset($published_month) && $published_month === 'September') ? 'selected' : ''; ?>>September</option>
                                <option value="October" <?php echo (isset($published_month) && $published_month === 'October') ? 'selected' : ''; ?>>October</option>
                                <option value="November" <?php echo (isset($published_month) && $published_month === 'November') ? 'selected' : ''; ?>>November</option>
                                <option value="December" <?php echo (isset($published_month) && $published_month === 'December') ? 'selected' : ''; ?>>December</option>
                            </select>
                        </div>

                        <!-- Book Image Upload -->
                        <div class="form-group full-width">
                            <label>Book Cover Image</label>
                            <div class="file-upload-area" onclick="document.getElementById('book_image').click()">
                                <div class="upload-icon">📷</div>
                                <div class="upload-text">Click to upload book cover</div>
                                <div class="upload-hint">or drag and drop an image here</div>
                                <div class="upload-hint">Supported formats: JPG, PNG, GIF, WEBP (Max: 5MB)</div>
                            </div>
                            <input type="file" id="book_image" name="book_image" class="file-input" 
                                   accept="image/*">
                            <div class="preview-container" id="previewContainer" style="display: none;">
                                <img id="previewImage" class="preview-image" alt="Preview">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <a href="manage_books.php" class="btn-secondary">
                            ← Cancel
                        </a>
                        <button type="submit" class="btn-primary">
                            📚 Add Book & Generate Copies
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('categorySelect');
            const categoryInput = document.getElementById('categoryInput');
            const fileInput = document.getElementById('book_image');
            const uploadArea = document.querySelector('.file-upload-area');
            const previewContainer = document.getElementById('previewContainer');
            const previewImage = document.getElementById('previewImage');

            // Handle category selection
            categorySelect.addEventListener('change', function() {
                if (this.value === '__custom__') {
                    categoryInput.style.display = 'block';
                    categoryInput.required = true;
                    categoryInput.focus();
                    this.required = false;
                } else {
                    categoryInput.style.display = 'none';
                    categoryInput.required = false;
                    this.required = true;
                }
            });

            // Handle file upload preview
            fileInput.addEventListener('change', function(e) {
                handleFilePreview(e.target.files[0]);
            });

            // Drag and drop functionality
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFilePreview(files[0]);
                }
            });

            function handleFilePreview(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        previewContainer.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                } else {
                    previewContainer.style.display = 'none';
                }
            }

            // Form validation
            document.getElementById('addBookForm').addEventListener('submit', function(e) {
                const categorySelect = document.getElementById('categorySelect');
                const categoryInput = document.getElementById('categoryInput');
                if (categorySelect.value === '__custom__') {
                    categorySelect.name = '';
                    categoryInput.name = 'category';
                }
            });
        });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>