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

// Include database connection and helper functions
require_once '../includes/db.php';
require_once '../includes/view_functions.php';
require_once '../includes/book_functions.php';

$message = '';
$message_type = '';
$errors = [];

// Handle return book action (updated for new structure)
if (isset($_POST['return_book']) && isset($_POST['borrow_id'])) {
    try {
        $borrow_id = intval($_POST['borrow_id']);
        
        // Get borrow details for updating copy status
        $borrowQuery = $conn->prepare("
            SELECT bb.copy_id, bb.title_id, bt.title, bc.book_id
            FROM borrowed_books bb 
            JOIN book_titles bt ON bb.title_id = bt.title_id 
            JOIN book_copies bc ON bb.copy_id = bc.copy_id
            WHERE bb.id = ? AND bb.status IN ('borrowed', 'overdue', 'renewed')
        ");
        $borrowQuery->execute([$borrow_id]);
        $borrowData = $borrowQuery->fetch();
        
        if ($borrowData) {
            $conn->beginTransaction();
            
            // Update borrowed_books table
            $returnStmt = $conn->prepare("
                UPDATE borrowed_books 
                SET return_date = CURDATE(), status = 'returned' 
                WHERE id = ? AND status IN ('borrowed', 'overdue', 'renewed')
            ");
            
            // Update specific copy status
            $copyStatusStmt = $conn->prepare("
                UPDATE book_copies 
                SET status = 'Available' 
                WHERE copy_id = ?
            ");
            
            // Execute updates
            $returnResult = $returnStmt->execute([$borrow_id]);
            $copyResult = $copyStatusStmt->execute([$borrowData['copy_id']]);
            
            if ($returnResult && $copyResult) {
                // Update book availability using our helper function
                updateBookAvailability($conn, $borrowData['title_id']);
                
                $conn->commit();
                $message = "Book '{$borrowData['title']}' (Copy: {$borrowData['book_id']}) returned successfully!";
                $message_type = "success";
            } else {
                $conn->rollback();
                $message = "Failed to process book return.";
                $message_type = "error";
            }
        } else {
            $message = "Book not found or already returned.";
            $message_type = "error";
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        $message = "Error processing return: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle new borrowing (updated for new structure)
if (isset($_POST['borrow_book'])) {
    $user_id = intval($_POST['user_id']);
    $title_id = intval($_POST['title_id']);
    $due_days = 7; // Fixed 7 days borrowing period
    
    try {
        // Check if book title has available copies
        $titleCheck = $conn->prepare("SELECT title, available_copies FROM book_titles WHERE title_id = ?");
        $titleCheck->execute([$title_id]);
        $title = $titleCheck->fetch();
        
        if (!$title) {
            $errors[] = "Book not found.";
        } elseif ($title['available_copies'] <= 0) {
            $errors[] = "No copies of this book are currently available for borrowing.";
        } else {
            // Check if user exists
            $userCheck = $conn->prepare("SELECT user_id, first_name, last_name FROM users WHERE user_id = ?");
            $userCheck->execute([$user_id]);
            $user = $userCheck->fetch();
            
            if (!$user) {
                $errors[] = "User not found.";
            } else {
                // Check if user already has this title borrowed
                $existingBorrowCheck = $conn->prepare("
                    SELECT id FROM borrowed_books 
                    WHERE user_id = ? AND title_id = ? AND status IN ('borrowed', 'overdue', 'renewed')
                ");
                $existingBorrowCheck->execute([$user_id, $title_id]);
                if ($existingBorrowCheck->fetch()) {
                    $errors[] = "User already has this book borrowed.";
                } else {
                    // Check user's borrowing limit (2 books max)
                    $borrowCountCheck = $conn->prepare("
                        SELECT COUNT(*) as count FROM borrowed_books 
                        WHERE user_id = ? AND status IN ('borrowed', 'overdue', 'renewed')
                    ");
                    $borrowCountCheck->execute([$user_id]);
                    $borrowCount = $borrowCountCheck->fetch()['count'];
                    
                    if ($borrowCount >= 2) {
                        $errors[] = "User has reached the maximum borrowing limit of 2 books.";
                    } else {
                        // Find an available copy to assign
                        $copyQuery = $conn->prepare("
                            SELECT copy_id, book_id, copy_number 
                            FROM book_copies 
                            WHERE title_id = ? AND status = 'Available' 
                            ORDER BY copy_number ASC 
                            LIMIT 1
                        ");
                        $copyQuery->execute([$title_id]);
                        $availableCopy = $copyQuery->fetch();
                        
                        if (!$availableCopy) {
                            $errors[] = "No available copies found for assignment.";
                        } else {
                            // Process borrowing
                            $borrow_date = date('Y-m-d');
                            $due_date = date('Y-m-d', strtotime("+$due_days days"));
                            
                            $conn->beginTransaction();
                            
                            // Insert borrow record
                            $borrowStmt = $conn->prepare("
                                INSERT INTO borrowed_books (user_id, copy_id, title_id, book_id, borrow_date, due_date, status) 
                                VALUES (?, ?, ?, ?, ?, ?, 'borrowed')
                            ");
                            
                            // Update copy status
                            $updateCopyStmt = $conn->prepare("UPDATE book_copies SET status = 'Borrowed' WHERE copy_id = ?");
                            
                            $borrowResult = $borrowStmt->execute([$user_id, $availableCopy['copy_id'], $title_id, $availableCopy['book_id'], $borrow_date, $due_date]);
                            $copyUpdateResult = $updateCopyStmt->execute([$availableCopy['copy_id']]);
                            
                            if ($borrowResult && $copyUpdateResult) {
                                // Update book availability using our helper function
                                updateBookAvailability($conn, $title_id);
                                
                                $conn->commit();
                                $message = "Book '{$title['title']}' (Copy #{$availableCopy['copy_number']}: {$availableCopy['book_id']}) borrowed by {$user['first_name']} {$user['last_name']} successfully! Due date: " . date('M j, Y', strtotime($due_date));
                                $message_type = "success";
                            } else {
                                $conn->rollback();
                                $errors[] = "Failed to process borrowing.";
                            }
                        }
                    }
                }
            }
        }
        
    } catch (PDOException $e) {
        $conn->rollback();
        $errors[] = "Database error: " . $e->getMessage();
    }
    
    if (!empty($errors)) {
        $message_type = "error";
    }
}

// Handle search and filtering
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$overdue_filter = isset($_GET['overdue']) ? $_GET['overdue'] : '';

try {
    // Use helper function to get borrowing history with filters
    $borrowed_books = [];
    
    if (!empty($search) || !empty($status_filter) || !empty($overdue_filter)) {
        // Get all borrowing history and filter manually for complex conditions
        $allHistory = getBorrowingHistory($conn);
        
        foreach ($allHistory as $borrow) {
            $include = true;
            
            // Search filter
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $searchableText = strtolower(
                    $borrow['title'] . ' ' . 
                    $borrow['first_name'] . ' ' . 
                    $borrow['last_name'] . ' ' . 
                    $borrow['username'] . ' ' . 
                    $borrow['book_id']
                );
                if (strpos($searchableText, $searchLower) === false) {
                    $include = false;
                }
            }
            
            // Status filter
            if (!empty($status_filter) && $include) {
                if ($status_filter === 'currently_borrowed') {
                    if (!in_array($borrow['status'], ['borrowed', 'overdue', 'renewed'])) {
                        $include = false;
                    }
                } elseif ($status_filter === 'overdue') {
                    $isOverdue = in_array($borrow['status'], ['borrowed', 'overdue', 'renewed']) && 
                                 $borrow['due_date'] < date('Y-m-d');
                    if (!$isOverdue) {
                        $include = false;
                    }
                } else {
                    if ($borrow['status'] !== $status_filter) {
                        $include = false;
                    }
                }
            }
            
            // Overdue filter
            if ($overdue_filter === 'yes' && $include) {
                $isOverdue = in_array($borrow['status'], ['borrowed', 'overdue', 'renewed']) && 
                             $borrow['due_date'] < date('Y-m-d');
                if (!$isOverdue) {
                    $include = false;
                }
            }
            
            if ($include) {
                // Add calculated fields
                $borrow['display_status'] = (in_array($borrow['status'], ['borrowed', 'overdue', 'renewed']) && 
                                            $borrow['due_date'] < date('Y-m-d')) ? 'overdue' : $borrow['status'];
                
                $borrow['days_overdue'] = 0;
                $borrow['calculated_fine'] = 0;
                
                if ($borrow['display_status'] === 'overdue') {
                    $borrow['days_overdue'] = daysDifference($borrow['due_date'], date('Y-m-d'));
                    $borrow['calculated_fine'] = $borrow['days_overdue'] * 10;
                } elseif ($borrow['status'] === 'returned' && $borrow['return_date'] > $borrow['due_date']) {
                    $borrow['days_overdue'] = daysDifference($borrow['due_date'], $borrow['return_date']);
                    $borrow['calculated_fine'] = $borrow['days_overdue'] * 10;
                }
                
                $borrowed_books[] = $borrow;
            }
        }
    } else {
        // Get all borrowing history
        $allHistory = getBorrowingHistory($conn);
        foreach ($allHistory as $borrow) {
            // Add calculated fields
            $borrow['display_status'] = (in_array($borrow['status'], ['borrowed', 'overdue', 'renewed']) && 
                                        $borrow['due_date'] < date('Y-m-d')) ? 'overdue' : $borrow['status'];
            
            $borrow['days_overdue'] = 0;
            $borrow['calculated_fine'] = 0;
            
            if ($borrow['display_status'] === 'overdue') {
                $borrow['days_overdue'] = daysDifference($borrow['due_date'], date('Y-m-d'));
                $borrow['calculated_fine'] = $borrow['days_overdue'] * 10;
            } elseif ($borrow['status'] === 'returned' && $borrow['return_date'] > $borrow['due_date']) {
                $borrow['days_overdue'] = daysDifference($borrow['due_date'], $borrow['return_date']);
                $borrow['calculated_fine'] = $borrow['days_overdue'] * 10;
            }
            
            $borrowed_books[] = $borrow;
        }
    }
    
    // Sort: overdue first, then by borrow date descending
    usort($borrowed_books, function($a, $b) {
        if ($a['display_status'] === 'overdue' && $b['display_status'] !== 'overdue') {
            return -1;
        } elseif ($a['display_status'] !== 'overdue' && $b['display_status'] === 'overdue') {
            return 1;
        } else {
            return strtotime($b['borrow_date']) - strtotime($a['borrow_date']);
        }
    });
    
    // Get available book titles for new borrowing using helper function
    $availableTitlesRaw = getBooksOverview($conn);
    $availableTitles = array_filter($availableTitlesRaw, function($book) {
        return $book['status'] === 'Available' && isset($book['available_copies']) && $book['available_copies'] > 0;
    });
    
    // Transform to match expected format
    $availableTitles = array_map(function($book) {
        return [
            'title_id' => $book['book_id'], // Note: getBooksOverview returns title_id as book_id
            'title' => $book['title'],
            'author' => $book['author'],
            'available_copies' => $book['available_copies'] ?? 0
        ];
    }, $availableTitles);
    
    // Get users for borrowing
    $usersQuery = "SELECT user_id, username, first_name, last_name FROM users ORDER BY first_name, last_name";
    $users = $conn->query($usersQuery)->fetchAll();
    
    // Get statistics using our helper function
    $dashStats = getDashboardStats($conn);
    $stats = [
        'currently_borrowed' => $dashStats['active_loans'] ?? 0,
        'total_returned' => count(array_filter($borrowed_books, function($b) { return $b['status'] === 'returned'; })),
        'overdue_count' => $dashStats['overdue_loans'] ?? 0
    ];
    
} catch (PDOException $e) {
    $borrowed_books = [];
    $availableTitles = [];
    $users = [];
    $stats = ['currently_borrowed' => 0, 'total_returned' => 0, 'overdue_count' => 0];
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function to get status badge class
function getStatusBadgeClass($status) {
    switch($status) {
        case 'borrowed': 
        case 'renewed': return 'status-borrowed';
        case 'returned': return 'status-available';
        case 'overdue': return 'status-overdue';
        default: return 'status-borrowed';
    }
}

// Helper function to calculate days difference
function daysDifference($date1, $date2 = null) {
    $date2 = $date2 ?: date('Y-m-d');
    $diff = strtotime($date2) - strtotime($date1);
    return floor($diff / (60 * 60 * 24));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Borrowed Books - The Cat-alog Library</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    <link rel="stylesheet" href="../assets/css/admin_manage_borrowed.css">
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
                    <li><a href="manage_borrowed.php" class="active">Borrowed Books</a></li>
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
                <h1 class="page-title">📚 Manage Borrowed Books</h1>
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
                    <div class="stat-number-small"><?php echo $stats['currently_borrowed']; ?></div>
                    <div class="stat-label">Currently Borrowed</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['overdue_count']; ?></div>
                    <div class="stat-label">Overdue Books</div>
                </div>
                <div class="stat-card-small">
                    <div class="stat-number-small"><?php echo $stats['total_returned']; ?></div>
                    <div class="stat-label">Total Returned</div>
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

            <!-- Overdue Alert -->
            <?php if ($stats['overdue_count'] > 0): ?>
                <div class="overdue-alert">
                    ⚠️ <strong>Alert:</strong> There are <?php echo $stats['overdue_count']; ?> overdue book(s) that need attention!
                    <a href="?overdue=yes" style="color: #e74c3c; text-decoration: underline; margin-left: 1rem;">View Overdue Books</a>
                </div>
            <?php endif; ?>

            <!-- New Borrowing Section -->
            <div class="new-borrow-section">
                <h3>📖 Process New Borrowing</h3>
                <form method="POST" class="borrow-form">
                    <div class="form-group">
                        <label for="user_id">Select Student</label>
                        <select id="user_id" name="user_id" required>
                            <option value="">Choose a student...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>">
                                    <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title_id">Select Book Title</label>
                        <select id="title_id" name="title_id" required>
                            <option value="">Choose a book title...</option>
                            <?php foreach ($availableTitles as $title): ?>
                                <option value="<?php echo $title['title_id']; ?>">
                                    <?php echo htmlspecialchars($title['title'] . ' - ' . $title['author'] . ' (' . $title['available_copies'] . ' available)'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Borrowing Period</label>
                        <div style="padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; background: #f8f9fa; color: #666;">
                            7 days (Fixed period) - System will auto-assign available copy
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" name="borrow_book" class="action-btn">
                            📚 Process Borrowing
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <form method="GET" action="">
                    <div class="filters-grid">
                        <div class="form-group">
                            <label for="search">Search</label>
                            <input type="text" 
                            id="search" 
                            name="search" 
                            class="search-input-wide"
                            placeholder="Search by book title, student name, or copy ID..." 
                            value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="currently_borrowed" <?php echo $status_filter === 'currently_borrowed' ? 'selected' : ''; ?>>Currently Borrowed</option>
                                <option value="returned" <?php echo $status_filter === 'returned' ? 'selected' : ''; ?>>Returned</option>
                                <option value="renewed" <?php echo $status_filter === 'renewed' ? 'selected' : ''; ?>>Renewed</option>
                                <option value="overdue" <?php echo $status_filter === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="overdue">Quick Filter</label>
                            <select id="overdue" name="overdue">
                                <option value="">All Books</option>
                                <option value="yes" <?php echo $overdue_filter === 'yes' ? 'selected' : ''; ?>>Overdue Only</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="action-btn">
                                🔍 Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Borrowed Books Table -->
            <div class="borrowed-books-table">
                <div class="table-header">
                    <h2 style="color: var(--black);">📋 Borrowed Books (<?php echo count($borrowed_books); ?> records)</h2>
                    <a href="?" class="action-btn" style="background: var(--caramel); color: var(--white);">
                        🔄 Clear Filters
                    </a>
                </div>

                <?php if (empty($borrowed_books)): ?>
                    <div class="empty-state">
                        <h3>📚 No borrowed books found</h3>
                        <p>No books match your current filters.</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Copy Info</th>
                                <th>Student</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Return Date</th>
                                <th>Status</th>
                                <th>Fine</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($borrowed_books as $borrow): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($borrow['title']); ?></strong><br>
                                        <small>by <?php echo htmlspecialchars($borrow['author']); ?></small>
                                    </td>
                                    <td>
                                        <strong>Copy #<?php echo $borrow['copy_number'] ?? 'N/A'; ?></strong><br>
                                        <small style="font-family: monospace; color: #666;"><?php echo htmlspecialchars($borrow['book_id']); ?></small><br>
                                        <small>User: <?php echo htmlspecialchars($borrow['username']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($borrow['first_name'] . ' ' . $borrow['last_name']); ?><br>
                                        <small><?php echo htmlspecialchars($borrow['username']); ?></small>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($borrow['borrow_date'])); ?></td>
                                    <td>
                                        <?php echo date('M j, Y', strtotime($borrow['due_date'])); ?>
                                        <?php if ($borrow['display_status'] === 'overdue'): ?>
                                            <br><small style="color: #e74c3c;">
                                                <?php echo $borrow['days_overdue']; ?> days overdue
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrow['return_date']): ?>
                                            <?php echo date('M j, Y', strtotime($borrow['return_date'])); ?>
                                            <?php if ($borrow['calculated_fine'] > 0 && $borrow['status'] === 'returned'): ?>
                                                <br><small style="color: #e74c3c;">
                                                    Returned <?php echo abs($borrow['days_overdue']); ?> day(s) late
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">Not returned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo getStatusBadgeClass($borrow['display_status']); ?>">
                                            <?php echo ucfirst($borrow['display_status']); ?>
                                        </span>
                                        <?php if ($borrow['renewal_count'] > 0): ?>
                                            <br><small>Renewed <?php echo $borrow['renewal_count']; ?>x</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrow['calculated_fine'] > 0): ?>
                                            <span style="color: #e74c3c; font-weight: 600;">
                                                ₱<?php echo number_format($borrow['calculated_fine'], 2); ?>
                                            </span>
                                            <br><small style="color: #666;">
                                                <?php echo $borrow['days_overdue']; ?> day(s) × ₱10.00
                                            </small>
                                        <?php else: ?>
                                            <span style="color: #27ae60;">₱0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($borrow['status'] === 'borrowed' || $borrow['status'] === 'overdue' || $borrow['status'] === 'renewed'): ?>
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('Mark this book copy as returned?');">
                                                <input type="hidden" name="borrow_id" value="<?php echo $borrow['id']; ?>">
                                                <button type="submit" name="return_book" class="btn-return">
                                                    ↩️ Return
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span style="color: #27ae60;">✅ Completed</span>
                                        <?php endif; ?>
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
        // Auto-submit form when filters change
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filters-section form');
            const selects = filterForm.querySelectorAll('select');
            
            selects.forEach(select => {
                select.addEventListener('change', function() {
                    filterForm.submit();
                });
            });
        });
    </script>
<?php include '../includes/footer.php'; ?>
</body>
</html>