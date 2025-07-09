<?php 
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/view_functions.php';

// Get user information from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// Get all borrowed books for the current user using helper function
$borrowed_books = [];
if ($user_id) {
    try {
        // Use the helper function to get user's borrowed books
        $borrowed_books = getUserBorrowedBooks($conn, $user_id);
        
        // Transform data to match the original format
        $transformedBooks = [];
        foreach ($borrowed_books as $book) {
            // Get additional book details that helper function might not include
            $detailsQuery = $conn->prepare("
                SELECT 
                    bt.title_id,
                    bt.published_year,
                    bt.published_month,
                    bt.book_image,
                    bt.category,
                    bc.book_id,
                    bc.copy_number,
                    bc.condition_status,
                    bb.borrow_date,
                    bb.due_date,
                    bb.status,
                    bb.id as borrow_id,
                    bb.renewal_count,
                    bb.fine_amount
                FROM borrowed_books bb
                JOIN book_copies bc ON bb.copy_id = bc.copy_id
                JOIN book_titles bt ON bb.title_id = bt.title_id
                WHERE bb.id = ?
            ");
            $detailsQuery->execute([$book['id']]);
            $details = $detailsQuery->fetch(PDO::FETCH_ASSOC);
            
            if ($details) {
                $transformedBooks[] = array_merge($book, $details);
            }
        }
        $borrowed_books = $transformedBooks;
        
    } catch (PDOException $e) {
        error_log("Error fetching borrowed books: " . $e->getMessage());
        
        // Fallback to original query if helper function fails
        try {
            $borrowed_query = "
                SELECT 
                    bt.title_id,
                    bt.title,
                    bt.author,
                    bt.category,
                    bt.published_year,
                    bt.published_month,
                    bt.book_image,
                    bc.book_id,
                    bc.copy_number,
                    bc.condition_status,
                    bb.borrow_date,
                    bb.due_date,
                    bb.status,
                    bb.id as borrow_id,
                    bb.renewal_count,
                    bb.fine_amount
                FROM borrowed_books bb
                JOIN book_copies bc ON bb.copy_id = bc.copy_id
                JOIN book_titles bt ON bb.title_id = bt.title_id
                WHERE bb.user_id = ? AND bb.status IN ('borrowed', 'overdue', 'renewed')
                ORDER BY bb.due_date ASC
            ";
            
            $stmt = $conn->prepare($borrowed_query);
            $stmt->execute([$user_id]);
            $borrowed_books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e2) {
            error_log("Fallback query also failed: " . $e2->getMessage());
            $borrowed_books = [];
        }
    }
}

// Function to determine status display and class
function getStatusDisplay($due_date, $status) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    
    if ($status === 'overdue' || $due < $today) {
        return ['text' => 'Overdue', 'class' => 'status-overdue'];
    } else {
        return ['text' => 'On Time', 'class' => 'status-ontime'];
    }
}

// Calculate days remaining
function getDaysRemaining($due_date) {
    $today = new DateTime();
    $due = new DateTime($due_date);
    $diff = $today->diff($due);
    
    if ($due < $today) {
        return -$diff->days; // Negative for overdue
    } else {
        return $diff->days;
    }
}

// Function to format published date
function formatPublishedDate($month, $year) {
    if ($month && $year) {
        return $month . ' ' . $year;
    } elseif ($year) {
        return $year;
    }
    return 'N/A';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Borrowed Books - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/my_borrowed.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
</head>
<body>

<!-- Navigation Header - SEPARATE FROM BANNER -->
<header class="main-header">
    <div class="logo-title">
        <img src="../assets/images/logo.png" alt="Logo" class="banner-logo-dashboard">
        <h1 class="sniglet-extrabold">The Cat-alog Library</h1>
    </div>
    <nav class="main-nav">
        <ul class="nav-menu">
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="browse_books.php">Browse Books</a></li>
            <li><a href="my_borrowed.php" class="active">My Borrowed Books</a></li>
        </ul>
    </nav>
    <a href="logout.php" class="logout-btn">Log Out</a>
</header>

<!-- Banner Section - ONLY WELCOME CONTENT -->
<section class="banner-section">
    <img src="../assets/images/banner-borrowed.png" alt="Library Banner" class="banner-bg">
    
    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">My Borrowed Books</h2>
        <p>Here's a summary of the books you've borrowed from The Cat-alog Library. Keep track of your due dates, manage your returns, and ensure a smooth borrowing experience. Thank you for being a responsible reader!</p>
    </div>
</section>

<!-- Borrowed Books Section -->
<section class="borrowed-books-section">
    <?php if (isset($_SESSION['borrow_success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['borrow_success']); ?>
            <?php unset($_SESSION['borrow_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['borrow_error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['borrow_error']); ?>
            <?php unset($_SESSION['borrow_error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($borrowed_books)): ?>
        <div class="no-books-container">
            <div class="no-books-message">
                <h3>No Borrowed Books</h3>
                <p>You currently have no borrowed books.</p>
                <a href="browse_books.php" class="btn">Browse Books to Borrow</a>
            </div>
        </div>
    <?php else: ?>
        <div class="borrowed-summary">
            <h3>Currently Borrowed: <?php echo count($borrowed_books); ?> of 2 books</h3>
            <?php 
            $overdue_count = 0;
            foreach ($borrowed_books as $book) {
                if (getDaysRemaining($book['due_date']) < 0) {
                    $overdue_count++;
                }
            }
            if ($overdue_count > 0): ?>
                <p class="overdue-warning">⚠️ You have <?php echo $overdue_count; ?> overdue book<?php echo $overdue_count > 1 ? 's' : ''; ?>!</p>
            <?php endif; ?>
        </div>

        <div class="borrowed-books-grid">
            <?php foreach ($borrowed_books as $book): ?>
                <?php 
                $status_info = getStatusDisplay($book['due_date'], $book['status']);
                $days_remaining = getDaysRemaining($book['due_date']);
                $published_date = formatPublishedDate($book['published_month'] ?? null, $book['published_year'] ?? null);
                
                // Handle book image - carefully check if image exists
                $book_image_src = '';
                if (!empty($book['book_image'])) {
                    $book_image_path = '../uploads/book-images/' . $book['book_image'];
                    if (file_exists($book_image_path)) {
                        $book_image_src = $book_image_path;
                    }
                }
                // If no valid image, keep it empty to show brown placeholder
                ?>
                <div class="borrowed-book-card">
                    <div class="book-image-container">
                        <div class="book-image" <?php if ($book_image_src): ?>style="background-image: url('<?php echo htmlspecialchars($book_image_src); ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
                        </div>
                    </div>
                    <div class="book-details">
                        <h3 class="book-title"><?php echo htmlspecialchars($book['title']); ?></h3>
                        <div class="book-meta">
                            <p><strong>Book ID:</strong> <span class="book-id"><?php echo htmlspecialchars($book['book_id'] ?? 'N/A'); ?></span></p>
                            <p><strong>Copy #:</strong> <?php echo htmlspecialchars($book['copy_number'] ?? 'N/A'); ?></p>
                            <p><strong>Author:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></p>
                            <p><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?? 'General'); ?></p>
                            <p><strong>Published:</strong> <?php echo htmlspecialchars($published_date); ?></p>
                            <?php if (!empty($book['condition_status'])): ?>
                                <p><strong>Condition:</strong> <?php echo htmlspecialchars($book['condition_status']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="borrow-info">
                            <div class="borrow-details">
                                <p><strong>Date Borrowed:</strong> <?php echo date('M j, Y', strtotime($book['borrow_date'])); ?></p>
                                <p><strong>Return Book By:</strong> <?php echo date('M j, Y', strtotime($book['due_date'])); ?></p>
                                <?php if (($book['renewal_count'] ?? 0) > 0): ?>
                                    <p><strong>Renewals:</strong> <?php echo $book['renewal_count']; ?> time<?php echo $book['renewal_count'] > 1 ? 's' : ''; ?></p>
                                <?php endif; ?>
                                <p class="status <?php echo $status_info['class']; ?>">
                                    <strong>Status:</strong> <?php echo $status_info['text']; ?>
                                    <?php if ($days_remaining >= 0): ?>
                                        (<?php echo $days_remaining; ?> day<?php echo $days_remaining != 1 ? 's' : ''; ?> left)
                                    <?php else: ?>
                                        (<?php echo abs($days_remaining); ?> day<?php echo abs($days_remaining) != 1 ? 's' : ''; ?> overdue)
                                    <?php endif; ?>
                                </p>
                                <?php if (($book['fine_amount'] ?? 0) > 0): ?>
                                    <p class="fine-amount"><strong>Fine:</strong> ₱<?php echo number_format($book['fine_amount'], 2); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="borrowing-info">
            <h4>Borrowing Policy Reminder:</h4>
            <ul>
                <li>Maximum 2 books can be borrowed at a time</li>
                <li>Books are due 7 days after borrowing</li>
                <li>₱10.00 fine per day for each overdue book</li>
                <li>Books can be renewed once if no one else is waiting</li>
                <li>Please return books in good condition</li>
            </ul>
        </div>
    <?php endif; ?>
</section>

<?php include '../includes/footer.php'; ?>

</body>
</html>