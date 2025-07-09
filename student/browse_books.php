<?php 
require_once '../includes/session.php';
require_once '../includes/db.php';

// Get user information from session
$user_id = $_SESSION['user_id'] ?? $_SESSION['student_id'] ?? null;
$user_name = $_SESSION['user_name'] ?? $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Student';

// Get current borrowed books count for the user
$borrowed_count = 0;
if ($user_id) {
    try {
        $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM borrowed_books WHERE user_id = ? AND status IN ('borrowed', 'overdue')");
        $count_stmt->execute([$user_id]);
        $count_result = $count_stmt->fetch();
        $borrowed_count = $count_result['count'];
    } catch (PDOException $e) {
        error_log("Error getting borrowed count: " . $e->getMessage());
    }
}

// Handle search and filter parameters
$search_title = $_GET['title'] ?? '';
$search_author = $_GET['author'] ?? '';
$search_category = $_GET['category'] ?? '';
$search_published_year = $_GET['published_year'] ?? '';
$search_book_id = $_GET['book_id'] ?? '';
$search_status = $_GET['status'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'title_asc'; // Default sort

// Build the query conditions
try {
    $where_conditions = ["1=1"]; // Always true condition to start
    $params = [];
    
    // Add search conditions
    if (!empty($search_title)) {
        $where_conditions[] = "bt.title LIKE ?";
        $params[] = "%$search_title%";
    }
    
    if (!empty($search_author)) {
        $where_conditions[] = "bt.author LIKE ?";
        $params[] = "%$search_author%";
    }
    
    if (!empty($search_category)) {
        $where_conditions[] = "bt.category = ?";
        $params[] = $search_category;
    }
    
    if (!empty($search_published_year)) {
        $where_conditions[] = "bt.published_year = ?";
        $params[] = $search_published_year;
    }
    
    if (!empty($search_status)) {
        if ($search_status === 'Available') {
            $where_conditions[] = "bt.available_copies > 0";
        } else {
            $where_conditions[] = "bt.available_copies = 0";
        }
    }
    
    // Handle book ID search
    if (!empty($search_book_id)) {
        $where_conditions[] = "bt.title_id IN (SELECT DISTINCT bc.title_id FROM book_copies bc WHERE bc.book_id LIKE ?)";
        $params[] = "%$search_book_id%";
    }
    
    $where_clause = "WHERE " . implode(" AND ", $where_conditions);
    
    // Main query - using DISTINCT to prevent SQL-level duplicates
    $query = "
        SELECT DISTINCT
            bt.title_id,
            bt.title,
            bt.author,
            bt.category,
            bt.published_year,
            bt.published_month,
            bt.book_image,
            bt.total_copies,
            bt.available_copies,
            CASE 
                WHEN bt.available_copies > 0 THEN 'Available'
                WHEN bt.available_copies = 0 AND bt.total_copies > 0 THEN 'Borrowed'
                ELSE 'Archived'
            END as status
        FROM book_titles bt
        $where_clause
        ORDER BY bt.title_id ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rawBooks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ROBUST DEDUPLICATION - using associative array with title_id as key
    $uniqueBooks = [];
    
    foreach ($rawBooks as $book) {
        $titleId = $book['title_id'];
        // Use title_id as array key - this automatically prevents duplicates
        $uniqueBooks[$titleId] = $book;
    }
    
    // Convert back to indexed array
    $books = array_values($uniqueBooks);

    // Apply sorting
    switch ($sort_by) {
        case 'title_desc':
            usort($books, function($a, $b) { return strcmp($b['title'], $a['title']); });
            break;
        case 'author_asc':
            usort($books, function($a, $b) { 
                $result = strcmp($a['author'] ?? '', $b['author'] ?? '');
                return $result !== 0 ? $result : strcmp($a['title'], $b['title']);
            });
            break;
        case 'author_desc':
            usort($books, function($a, $b) { 
                $result = strcmp($b['author'] ?? '', $a['author'] ?? '');
                return $result !== 0 ? $result : strcmp($a['title'], $b['title']);
            });
            break;
        case 'year_asc':
            usort($books, function($a, $b) { 
                $result = ($a['published_year'] ?? 0) - ($b['published_year'] ?? 0);
                return $result !== 0 ? $result : strcmp($a['title'], $b['title']);
            });
            break;
        case 'year_desc':
            usort($books, function($a, $b) { 
                $result = ($b['published_year'] ?? 0) - ($a['published_year'] ?? 0);
                return $result !== 0 ? $result : strcmp($a['title'], $b['title']);
            });
            break;
        default: // title_asc
            usort($books, function($a, $b) { return strcmp($a['title'], $b['title']); });
            break;
    }

    // FIXED: Get sample book IDs - using index instead of reference to prevent duplication
    $finalBooks = [];
    for ($i = 0; $i < count($books); $i++) {
        $book = $books[$i];
        
        try {
            $stmt = $conn->prepare("SELECT book_id FROM book_copies WHERE title_id = ? ORDER BY copy_number LIMIT 3");
            $stmt->execute([$book['title_id']]);
            $bookIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $book['sample_book_ids'] = implode(', ', $bookIds);
        } catch (PDOException $e) {
            $book['sample_book_ids'] = '';
            error_log("Error getting sample book IDs: " . $e->getMessage());
        }
        
        $finalBooks[] = $book;
    }
    
    $books = $finalBooks;

} catch (PDOException $e) {
    $books = [];
    error_log("Error fetching books: " . $e->getMessage());
}

$total_books = count($books);

// Add debugging info
if (isset($_GET['debug'])) {
    echo "<pre>";
    echo "Total books found: " . $total_books . "\n";
    echo "Books array:\n";
    foreach ($books as $book) {
        echo "ID: " . $book['title_id'] . " - " . $book['title'] . "\n";
    }
    echo "</pre>";
    
    // Count duplicates in final array
    $titleCounts = [];
    foreach ($books as $book) {
        $id = $book['title_id'];
        $titleCounts[$id] = ($titleCounts[$id] ?? 0) + 1;
    }
    
    echo "<h3>Final Duplicate Check:</h3>";
    $foundDuplicates = false;
    foreach ($titleCounts as $id => $count) {
        if ($count > 1) {
            echo "<p style='color: red;'>Title ID $id appears $count times!</p>";
            $foundDuplicates = true;
        }
    }
    if (!$foundDuplicates) {
        echo "<p style='color: green;'>✅ No duplicates found!</p>";
    }
}

// Get filter options display text
function getFilterDisplayText($sort_by) {
    switch ($sort_by) {
        case 'title_asc': return 'A to Z';
        case 'title_desc': return 'Z to A';
        case 'author_asc': return 'Author (A-Z)';
        case 'author_desc': return 'Author (Z-A)';
        case 'year_asc': return 'Year (Oldest First)';
        case 'year_desc': return 'Year (Newest First)';
        default: return 'A to Z';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Browse Books - The Cat-alog Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/browse_books.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sniglet:wght@400;800&display=swap" rel="stylesheet">
    <?php include '../includes/favicon.php'; ?>
    <style>
        /* Filter dropdown styles */
        .filter-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .filter-btn {
            background-color: var(--blush);
            border: 1px solid var(--black);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'Sniglet', sans-serif;
            font-size: 0.9rem;
            transition: background-color 0.3s ease;
        }
        
        .filter-btn:hover {
            background-color: var(--pinkish);
        }
        
        .filter-options {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background-color: white;
            border: 1px solid var(--black);
            border-radius: 0.5rem;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            z-index: 1000;
            min-width: 180px;
            margin-top: 0.25rem;
        }
        
        .filter-options.show {
            display: block;
        }
        
        .filter-option {
            padding: 0.7rem 1rem;
            cursor: pointer;
            font-family: 'Sniglet', sans-serif;
            font-size: 0.9rem;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .filter-option:last-child {
            border-bottom: none;
        }
        
        .filter-option:hover {
            background-color: var(--blush);
        }
        
        .filter-option.active {
            background-color: var(--pinkish);
            color: white;
            font-weight: bold;
        }
        
        .current-filter {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        /* Updated book meta styles for new fields */
        .copies-info {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            font-size: 0.85rem;
        }
        
        .copies-available {
            color: #28a745;
            font-weight: 600;
        }
        
        .copies-total {
            color: #666;
        }
        
        .sample-ids {
            font-family: 'Courier New', monospace;
            font-size: 0.75rem;
            color: #888;
            margin-top: 0.5rem;
            word-break: break-all;
            line-height: 1.2;
        }
    </style>
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
            <li><a href="browse_books.php" class="active">Browse Books</a></li>
            <li><a href="my_borrowed.php">My Borrowed Books</a></li>
        </ul>
    </nav>
    <a href="logout.php" class="logout-btn">Log Out</a>
</header>

<!-- Banner Section - ONLY WELCOME CONTENT -->
<section class="browse-header">
    <img src="../assets/images/banner-browse.png" alt="Library Banner" class="banner-bg">
    
    <!-- Welcome Content -->
    <div class="welcome-content">
        <h2 class="sniglet-extrabold">Browse Books</h2>
        <p>Here, you can explore a diverse selection of titles carefully curated to inform, inspire, and entertain. Take your time and enjoy discovering your next great read.</p>
    </div>
</section>

<!-- Search Section -->
<section class="search-section">
    <?php if (isset($_SESSION['borrow_error'])): ?>
        <div class="alert alert-error">
            <?php echo htmlspecialchars($_SESSION['borrow_error']); ?>
            <?php unset($_SESSION['borrow_error']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['borrow_success'])): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($_SESSION['borrow_success']); ?>
            <?php unset($_SESSION['borrow_success']); ?>
        </div>
    <?php endif; ?>
    
    <form method="GET" action="browse_books.php" id="searchForm">
        <input type="hidden" name="sort_by" value="<?php echo htmlspecialchars($sort_by); ?>">
        <div class="search-bar">
            <input type="text" name="title" placeholder="Search By Title" value="<?php echo htmlspecialchars($search_title); ?>">
            <button type="submit">🔍</button>
        </div>
    </form>
</section>

<!-- Books Section -->
<section class="books-section">
    <div class="books-header">
        <h3>All Books (<?php echo $total_books; ?>)</h3>
        <div class="filter-dropdown">
            <div class="filter-btn" onclick="toggleFilterDropdown()">
                <span>⚙️</span>
                <span>Filter</span>
            </div>
            <div class="current-filter">
                Sorted by: <?php echo getFilterDisplayText($sort_by); ?>
            </div>
            <div class="filter-options" id="filterDropdown">
                <div class="filter-option <?php echo $sort_by === 'title_asc' ? 'active' : ''; ?>" onclick="applyFilter('title_asc')">
                    A to Z
                </div>
                <div class="filter-option <?php echo $sort_by === 'title_desc' ? 'active' : ''; ?>" onclick="applyFilter('title_desc')">
                    Z to A
                </div>
                <div class="filter-option <?php echo $sort_by === 'author_asc' ? 'active' : ''; ?>" onclick="applyFilter('author_asc')">
                    Author (A-Z)
                </div>
                <div class="filter-option <?php echo $sort_by === 'author_desc' ? 'active' : ''; ?>" onclick="applyFilter('author_desc')">
                    Author (Z-A)
                </div>
                <div class="filter-option <?php echo $sort_by === 'year_asc' ? 'active' : ''; ?>" onclick="applyFilter('year_asc')">
                    Year (Oldest First)
                </div>
                <div class="filter-option <?php echo $sort_by === 'year_desc' ? 'active' : ''; ?>" onclick="applyFilter('year_desc')">
                    Year (Newest First)
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($books)): ?>
        <div class="no-books">
            <img src="../assets/images/no-books.png" alt="No books found">
            <h3>No books found</h3>
            <p>Try adjusting your search criteria or browse all available books.</p>
            <a href="browse_books.php" class="btn" style="display: inline-block; margin-top: 15px;">View All Books</a>
        </div>
    <?php else: ?>
        <div class="books-grid">
            <?php foreach ($books as $book): ?>
                <?php
                // Handle book image
                $book_image_path = '../uploads/book-images/' . ($book['book_image'] ?? 'default_book.jpg');
                $default_image_path = '../uploads/book-images/default_book.jpg';
                
                // Check if book image exists, fallback to default
                if (!$book['book_image'] || !file_exists($book_image_path)) {
                    $book_image_path = $default_image_path;
                }
                
                // Get first book ID for display purposes
                $sample_book_ids = explode(', ', $book['sample_book_ids'] ?? '');
                $display_book_id = $sample_book_ids[0] ?? 'N/A';
                
                // Use the status from the database query (this should now be correct)
                $status = $book['status'];
                ?>
                <div class="book-card">
                    <div class="book-content">
                        <div class="book-image">
                            <img src="<?php echo htmlspecialchars($book_image_path); ?>" 
                                 alt="<?php echo htmlspecialchars($book['title']); ?>" 
                                 onerror="this.src='../uploads/book-images/default_book.jpg'">
                        </div>
                        <div class="book-details">
                            <div class="book-info">
                                <div class="book-title"><?php echo htmlspecialchars($book['title']); ?></div>
                                <div class="book-meta"><strong>Book ID:</strong> <?php echo htmlspecialchars($display_book_id); ?></div>
                                <div class="book-meta"><strong>Author:</strong> <?php echo htmlspecialchars($book['author'] ?? 'Unknown'); ?></div>
                                <div class="book-meta"><strong>Category:</strong> <?php echo htmlspecialchars($book['category'] ?? 'General'); ?></div>
                                <div class="book-meta"><strong>Published:</strong> 
                                    <?php 
                                    $published = $book['published_month'] && $book['published_year'] 
                                        ? $book['published_month'] . ' ' . $book['published_year']
                                        : ($book['published_year'] ?? 'N/A');
                                    echo htmlspecialchars($published);
                                    ?>
                                </div>
                                <div class="copies-info">
                                    <span class="copies-available">Available: <?php echo $book['available_copies'] ?? 0; ?></span>
                                    <span class="copies-total">Total: <?php echo $book['total_copies'] ?? 0; ?></span>
                                </div>
                                <div class="book-meta"><strong>Status:</strong> 
                                    <span class="book-status <?php echo $status === 'Available' ? 'status-available' : 'status-borrowed'; ?>">
                                        <?php echo htmlspecialchars($status); ?>
                                    </span>
                                </div>
                                <?php if (count($sample_book_ids) > 1): ?>
                                    <div class="sample-ids">
                                        <strong>Copy IDs:</strong> <?php echo htmlspecialchars(implode(', ', array_slice($sample_book_ids, 0, 3))); ?>
                                        <?php if (count($sample_book_ids) > 3): ?>
                                            ... (<?php echo count($sample_book_ids); ?> total)
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="button-container">
                                <?php if ($status === 'Available'): ?>
                                    <button class="borrow-btn" onclick="showBorrowModal(
                                        <?php echo $book['title_id']; ?>, 
                                        '<?php echo addslashes($book['title']); ?>', 
                                        '<?php echo addslashes($book['author'] ?? 'Unknown'); ?>', 
                                        '<?php echo addslashes($book['category'] ?? 'General'); ?>',
                                        '<?php echo addslashes($published); ?>',
                                        '<?php echo addslashes($book_image_path); ?>',
                                        '<?php echo addslashes($status); ?>',
                                        '<?php echo addslashes($display_book_id); ?>',
                                        <?php echo $book['available_copies'] ?? 0; ?>,
                                        <?php echo $book['total_copies'] ?? 0; ?>
                                    )">Borrow Now</button>
                                <?php else: ?>
                                    <button class="borrow-btn" disabled>Not Available</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Borrow Modal -->
<div id="borrowModal" class="modal">
    <div class="modal-content">
        <h2>You are about to Borrow:</h2>
        <div class="modal-book-info">
            <div class="modal-book-image">
                <img id="modalBookImage" src="" alt="" 
                     onerror="this.src='../uploads/book-images/default_book.jpg'">
            </div>
            <div class="modal-book-details">
                <h3 id="modalBookTitle"></h3>
                <p><strong>Book ID:</strong> <span id="modalBookCode"></span></p>
                <p><strong>Author:</strong> <span id="modalBookAuthor"></span></p>
                <p><strong>Category:</strong> <span id="modalBookCategory"></span></p>
                <p><strong>Published:</strong> <span id="modalBookPublished"></span></p>
                <p><strong>Available Copies:</strong> <span id="modalAvailableCopies"></span> of <span id="modalTotalCopies"></span></p>
                <p><strong>Status:</strong> <span class="status-available"></span></p>
            </div>
        </div>
        <div class="borrow-note">
            <p><strong>Note:</strong> You may borrow up to 2 books for a period of 7 days (including weekends). A ₱10.00 fine will be charged per day for each overdue book.</p>
            <p><strong>Current Status:</strong> You have borrowed <span id="currentBorrowedCount"><?php echo $borrowed_count; ?></span> out of 2 books.</p>
            <p><strong>Copy Assignment:</strong> A specific copy will be automatically assigned when you confirm the borrowing.</p>
        </div>
        <div class="modal-buttons">
            <button id="confirmBorrow" class="btn-confirm">Confirm</button>
            <button onclick="closeBorrowModal()" class="btn-cancel">Cancel</button>
        </div>
    </div>
</div>

<script>
let currentTitleId = null;
const borrowedCount = <?php echo $borrowed_count; ?>;
const maxBorrowLimit = 2;

// Filter functionality
function toggleFilterDropdown() {
    const dropdown = document.getElementById('filterDropdown');
    dropdown.classList.toggle('show');
}

function applyFilter(sortBy) {
    const url = new URL(window.location);
    url.searchParams.set('sort_by', sortBy);
    window.location.href = url.toString();
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('filterDropdown');
    const filterBtn = event.target.closest('.filter-btn');
    
    if (!filterBtn && !dropdown.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

// Updated showBorrowModal function for new structure
function showBorrowModal(titleId, title, author, category, publishedYear, imagePath, status, sampleBookId, availableCopies, totalCopies) {
    console.log('showBorrowModal called with:', {
        titleId: titleId,
        title: title, 
        author: author, 
        category: category, 
        publishedYear: publishedYear, 
        imagePath: imagePath,
        status: status,
        sampleBookId: sampleBookId,
        availableCopies: availableCopies,
        totalCopies: totalCopies
    });
    
    currentTitleId = titleId;
    
    // Set ALL book details
    document.getElementById('modalBookTitle').textContent = title;
    document.getElementById('modalBookCode').textContent = sampleBookId || 'N/A';
    document.getElementById('modalBookAuthor').textContent = author;
    document.getElementById('modalBookCategory').textContent = category;
    document.getElementById('modalBookPublished').textContent = publishedYear || 'N/A';
    document.getElementById('modalAvailableCopies').textContent = availableCopies;
    document.getElementById('modalTotalCopies').textContent = totalCopies;
    
    // Update status
    const statusElement = document.querySelector('#borrowModal .status-available');
    if (statusElement) {
        statusElement.textContent = status || 'Available';
    }
    
    // Handle the image with forced constraints
    const modalBookImage = document.getElementById('modalBookImage');
    const modalImageContainer = document.querySelector('.modal-book-image');
    
    if (modalBookImage && modalImageContainer) {
        console.log('Setting up modal image with forced constraints...');
        
        // Reset any inline styles that might interfere
        modalBookImage.removeAttribute('style');
        
        // FORCE the container sizes immediately
        modalImageContainer.style.width = '120px';
        modalImageContainer.style.height = '180px';
        modalImageContainer.style.overflow = 'hidden';
        modalImageContainer.style.position = 'relative';
        modalImageContainer.style.flexShrink = '0';
        modalImageContainer.style.display = 'block';
        modalImageContainer.style.boxSizing = 'border-box';
        
        // FORCE the image constraints before setting the source
        modalBookImage.style.width = '120px';
        modalBookImage.style.height = '180px';
        modalBookImage.style.maxWidth = '120px';
        modalBookImage.style.maxHeight = '180px';
        modalBookImage.style.minWidth = '120px';
        modalBookImage.style.minHeight = '180px';
        modalBookImage.style.objectFit = 'cover';
        modalBookImage.style.position = 'absolute';
        modalBookImage.style.top = '0';
        modalBookImage.style.left = '0';
        modalBookImage.style.right = '0';
        modalBookImage.style.bottom = '0';
        modalBookImage.style.margin = '0';
        modalBookImage.style.padding = '0';
        modalBookImage.style.display = 'block';
        modalBookImage.style.borderRadius = '6px';
        modalBookImage.style.zIndex = '1';
        modalBookImage.style.transform = 'none';
        modalBookImage.style.boxSizing = 'border-box';
        
        // Set the image source with fallback
        const imageToUse = imagePath && imagePath !== '../uploads/book-images/default_book.jpg' ? imagePath : '../uploads/book-images/default_book.jpg';
        modalBookImage.src = imageToUse;
        modalBookImage.alt = title;
        
        console.log('Modal image configured with path:', imageToUse);
        
        // Handle image load/error events with constraint re-enforcement
        modalBookImage.onload = function() {
            console.log('✅ Modal image loaded successfully');
            
            // RE-ENFORCE constraints after image loads (critical!)
            this.style.width = '120px';
            this.style.height = '180px';
            this.style.maxWidth = '120px';
            this.style.maxHeight = '180px';
            this.style.objectFit = 'cover';
            this.style.position = 'absolute';
            this.style.top = '0';
            this.style.left = '0';
            this.style.transform = 'none';
            
            // Show the image container when image loads
            modalImageContainer.style.display = 'block';
            
            console.log('✅ Image constraints re-enforced after load');
        };
        
        modalBookImage.onerror = function() {
            console.log('❌ Modal image failed to load, using default');
            this.src = '../uploads/book-images/default_book.jpg';
            
            // Apply constraints to fallback image too
            this.style.width = '120px';
            this.style.height = '180px';
            this.style.maxWidth = '120px';
            this.style.maxHeight = '180px';
            this.style.objectFit = 'cover';
            this.style.position = 'absolute';
            this.style.top = '0';
            this.style.left = '0';
        };
    } else {
        console.error('❌ Modal image elements not found!');
    }
    
    // Show modal and prevent body scroll
    const modal = document.getElementById('borrowModal');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    console.log('Modal displayed with forced constraints');
}

function closeBorrowModal() {
    document.getElementById('borrowModal').style.display = 'none';
    currentTitleId = null;
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    console.log('Modal closed');
}

document.getElementById('confirmBorrow').onclick = function() {
    if (currentTitleId) {
        // Check borrowing limit before proceeding
        if (borrowedCount >= maxBorrowLimit) {
            alert('⚠️ Borrowing Limit Reached!\n\nYou have already borrowed ' + borrowedCount + ' books. You can only borrow a maximum of ' + maxBorrowLimit + ' books at a time.\n\nPlease return a book before borrowing a new one.');
            closeBorrowModal();
            return;
        }
        borrowBook(currentTitleId);
    }
};

function borrowBook(titleId) {
    // Double-check the limit client-side
    if (borrowedCount >= maxBorrowLimit) {
        alert('⚠️ You have reached the maximum borrowing limit of ' + maxBorrowLimit + ' books.');
        return;
    }
    
    // Show loading state
    const confirmBtn = document.getElementById('confirmBorrow');
    confirmBtn.textContent = 'Processing...';
    confirmBtn.disabled = true;
    
    // Create a form and submit it - now using title_id instead of book_id
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'borrow_book.php';
    
    const titleInput = document.createElement('input');
    titleInput.type = 'hidden';
    titleInput.name = 'title_id';
    titleInput.value = titleId;
    
    form.appendChild(titleInput);
    document.body.appendChild(form);
    form.submit();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('borrowModal');
    if (event.target === modal) {
        closeBorrowModal();
    }
}
</script>

<?php include '../includes/footer.php'; ?>

</body>
</html>