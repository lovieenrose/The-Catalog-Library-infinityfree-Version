<?php
/**
 * PHP functions to replace MySQL views that can't be created on shared hosting
 * These functions replicate the functionality of the original views
 * 
 * Include this file after including db.php:
 * require_once 'includes/db.php';
 * require_once 'includes/view_functions.php';
 */

/**
 * Get available books (replaces available_books_view)
 * Returns books with available copies including copy details
 */
function getAvailableBooks($pdo, $limit = null, $offset = 0) {
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
            bt.title_id,
            bt.title,
            bt.author,
            bt.category,
            bt.published_year,
            bt.published_month,
            bt.book_image,
            bt.status,
            bt.total_copies,
            bt.available_copies,
            bc.copy_id,
            bc.book_id,
            bc.copy_number,
            bc.condition_status,
            bc.location,
            bc.status AS copy_status
        FROM book_titles bt
        JOIN book_copies bc ON bt.title_id = bc.title_id
        WHERE bc.status = 'Available'
        ORDER BY bt.title ASC, bc.copy_number ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting available books: " . $e->getMessage());
        return [];
    }
}

/**
 * Get books overview (replaces books view)
 * Returns simplified book information with availability status
 * FIXED: Now correctly includes total_copies and available_copies
 */
function getBooksOverview($pdo, $category = null, $limit = null, $offset = 0) {
    $whereClause = $category ? "WHERE bt.category = :category" : "";
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
            bt.title_id AS book_id,
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
            END AS status,
            bt.created_at,
            bt.updated_at
        FROM book_titles bt
        $whereClause
        ORDER BY bt.title ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($category) {
            $stmt->bindParam(':category', $category);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting books overview: " . $e->getMessage());
        return [];
    }
}

/**
 * Get book inventory summary (replaces book_inventory_summary view)
 * Returns detailed inventory statistics for each book
 */
function getBookInventorySummary($pdo, $titleId = null) {
    $whereClause = $titleId ? "WHERE bt.title_id = :title_id" : "";
    
    $sql = "
        SELECT 
            bt.title_id,
            bt.title,
            bt.author,
            bt.category,
            bt.total_copies,
            COUNT(CASE WHEN bc.status = 'Available' THEN 1 END) AS available_count,
            COUNT(CASE WHEN bc.status = 'Borrowed' THEN 1 END) AS borrowed_count,
            COUNT(CASE WHEN bc.status = 'Maintenance' THEN 1 END) AS maintenance_count,
            COUNT(CASE WHEN bc.status = 'Archived' THEN 1 END) AS archived_count,
            COUNT(CASE WHEN bc.status = 'Reserved' THEN 1 END) AS reserved_count
        FROM book_titles bt
        LEFT JOIN book_copies bc ON bt.title_id = bc.title_id
        $whereClause
        GROUP BY bt.title_id, bt.title, bt.author, bt.category, bt.total_copies
        ORDER BY bt.title ASC
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        if ($titleId) {
            $stmt->bindParam(':title_id', $titleId);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting book inventory summary: " . $e->getMessage());
        return [];
    }
}

/**
 * Get borrowing history (replaces borrowing_history_view)
 * Returns detailed borrowing information with user and book details
 */
function getBorrowingHistory($pdo, $userId = null, $status = null, $limit = null, $offset = 0) {
    $conditions = [];
    $params = [];
    
    if ($userId) {
        $conditions[] = "bb.user_id = :user_id";
        $params[':user_id'] = $userId;
    }
    
    if ($status) {
        $conditions[] = "bb.status = :status";
        $params[':status'] = $status;
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
            bb.id,
            u.username,
            u.first_name,
            u.last_name,
            bt.title,
            bt.author,
            bc.book_id,
            bc.copy_number,
            bb.borrow_date,
            bb.due_date,
            bb.return_date,
            bb.status,
            bb.renewal_count,
            bb.fine_amount,
            CASE 
                WHEN bb.status = 'borrowed' AND bb.due_date < CURDATE() THEN 'Overdue'
                WHEN bb.status = 'borrowed' AND bb.due_date >= CURDATE() THEN 'Active'
                ELSE 'Completed'
            END AS loan_status
        FROM borrowed_books bb
        JOIN users u ON bb.user_id = u.user_id
        JOIN book_copies bc ON bb.copy_id = bc.copy_id
        JOIN book_titles bt ON bb.title_id = bt.title_id
        $whereClause
        ORDER BY bb.borrow_date DESC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting borrowing history: " . $e->getMessage());
        return [];
    }
}

/**
 * Search books by title, author, or category
 * Enhanced search functionality
 * FIXED: Now correctly includes total_copies and available_copies
 */
function searchBooks($pdo, $searchTerm, $category = null, $availableOnly = false, $limit = null, $offset = 0) {
    $conditions = ["(bt.title LIKE :search OR bt.author LIKE :search)"];
    $params = [':search' => "%$searchTerm%"];
    
    if ($category) {
        $conditions[] = "bt.category = :category";
        $params[':category'] = $category;
    }
    
    if ($availableOnly) {
        $conditions[] = "bt.available_copies > 0";
    }
    
    $whereClause = "WHERE " . implode(" AND ", $conditions);
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
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
                ELSE 'Borrowed'
            END AS status
        FROM book_titles bt
        $whereClause
        ORDER BY bt.title ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error searching books: " . $e->getMessage());
        return [];
    }
}

/**
 * Get overdue books
 * Returns books that are past their due date
 */
function getOverdueBooks($pdo, $limit = null, $offset = 0) {
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
            bb.id,
            u.username,
            u.first_name,
            u.last_name,
            u.email,
            bt.title,
            bt.author,
            bc.book_id,
            bc.copy_number,
            bb.borrow_date,
            bb.due_date,
            DATEDIFF(CURDATE(), bb.due_date) AS days_overdue,
            bb.fine_amount
        FROM borrowed_books bb
        JOIN users u ON bb.user_id = u.user_id
        JOIN book_copies bc ON bb.copy_id = bc.copy_id
        JOIN book_titles bt ON bb.title_id = bt.title_id
        WHERE bb.status = 'borrowed' AND bb.due_date < CURDATE()
        ORDER BY bb.due_date ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting overdue books: " . $e->getMessage());
        return [];
    }
}

/**
 * Get user's current borrowed books
 * Returns books currently borrowed by a specific user
 */
function getUserBorrowedBooks($pdo, $userId, $limit = null, $offset = 0) {
    $limitClause = $limit ? "LIMIT $limit OFFSET $offset" : "";
    
    $sql = "
        SELECT 
            bb.id,
            bt.title,
            bt.author,
            bt.book_image,
            bc.book_id,
            bc.copy_number,
            bb.borrow_date,
            bb.due_date,
            bb.renewal_count,
            CASE 
                WHEN bb.due_date < CURDATE() THEN 'Overdue'
                ELSE 'Active'
            END AS loan_status,
            DATEDIFF(bb.due_date, CURDATE()) AS days_until_due
        FROM borrowed_books bb
        JOIN book_copies bc ON bb.copy_id = bc.copy_id
        JOIN book_titles bt ON bb.title_id = bt.title_id
        WHERE bb.user_id = :user_id AND bb.status = 'borrowed'
        ORDER BY bb.due_date ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting user borrowed books: " . $e->getMessage());
        return [];
    }
}

/**
 * Get popular books
 * Returns books sorted by borrowing frequency
 */
function getPopularBooks($pdo, $limit = 10, $offset = 0) {
    $limitClause = "LIMIT $limit OFFSET $offset";
    
    $sql = "
        SELECT 
            bt.title_id,
            bt.title,
            bt.author,
            bt.category,
            bt.book_image,
            bt.available_copies,
            COUNT(bb.id) AS borrow_count
        FROM book_titles bt
        LEFT JOIN borrowed_books bb ON bt.title_id = bb.title_id
        GROUP BY bt.title_id, bt.title, bt.author, bt.category, bt.book_image, bt.available_copies
        ORDER BY borrow_count DESC, bt.title ASC
        $limitClause
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting popular books: " . $e->getMessage());
        return [];
    }
}

/**
 * Get dashboard statistics
 * Returns summary statistics for admin dashboard
 */
function getDashboardStats($pdo) {
    $sql = "
        SELECT 
            (SELECT COUNT(*) FROM book_titles) AS total_titles,
            (SELECT COUNT(*) FROM book_copies WHERE status != 'Archived') AS total_copies,
            (SELECT COUNT(*) FROM book_copies WHERE status = 'Available') AS available_copies,
            (SELECT COUNT(*) FROM book_copies WHERE status = 'Borrowed') AS borrowed_copies,
            (SELECT COUNT(*) FROM users) AS total_users,
            (SELECT COUNT(*) FROM borrowed_books WHERE status = 'borrowed') AS active_loans,
            (SELECT COUNT(*) FROM borrowed_books WHERE status = 'borrowed' AND due_date < CURDATE()) AS overdue_loans
    ";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting dashboard stats: " . $e->getMessage());
        return [];
    }
}

/**
 * Example usage:
 * 
 * // Get all available books
 * $availableBooks = getAvailableBooks($pdo);
 * 
 * // Get books overview for Fiction category
 * $fictionBooks = getBooksOverview($pdo, 'Fiction', 20, 0);
 * 
 * // Search for books
 * $searchResults = searchBooks($pdo, 'Harry Potter', null, true, 10);
 * 
 * // Get inventory summary for a specific book
 * $inventory = getBookInventorySummary($pdo, 1);
 * 
 * // Get borrowing history for a user
 * $history = getBorrowingHistory($pdo, 1, null, 50);
 * 
 * // Get overdue books
 * $overdueBooks = getOverdueBooks($pdo);
 * 
 * // Get dashboard statistics
 * $stats = getDashboardStats($pdo);
 */
?>