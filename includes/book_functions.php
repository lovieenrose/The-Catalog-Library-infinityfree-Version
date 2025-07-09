<?php
/**
 * Helper functions to replace MySQL functions that are not allowed on shared hosting
 * These functions handle book ID generation and copy number management
 */

// Prevent direct access
if (!defined('DB_CONNECTION_INCLUDED')) {
    // Check if we have a database connection
    if (!isset($conn) || !($conn instanceof PDO)) {
        die('Database connection required');
    }
}

/**
 * Generate a unique book ID based on book information
 * Replaces the MySQL generate_book_id function
 */
function generateBookId($bookTitle, $pubMonth, $pubYear, $bookCategory, $titleId, $copyNumber) {
    // Extract first 2 letters from title (remove spaces and special chars)
    $cleanTitle = preg_replace('/[^A-Za-z0-9]/', '', $bookTitle);
    $titlePrefix = strtoupper(substr($cleanTitle, 0, 2));
    
    // Ensure we have at least 2 characters
    if (strlen($titlePrefix) < 2) {
        $titlePrefix = str_pad($titlePrefix, 2, 'X', STR_PAD_RIGHT);
    }
    
    // Convert month name to abbreviation
    $monthAbbrev = [
        'JANUARY' => 'JAN', 'FEBRUARY' => 'FEB', 'MARCH' => 'MAR',
        'APRIL' => 'APR', 'MAY' => 'MAY', 'JUNE' => 'JUN',
        'JULY' => 'JUL', 'AUGUST' => 'AUG', 'SEPTEMBER' => 'SEP',
        'OCTOBER' => 'OCT', 'NOVEMBER' => 'NOV', 'DECEMBER' => 'DEC'
    ];
    
    $monthStr = isset($monthAbbrev[strtoupper($pubMonth)]) ? $monthAbbrev[strtoupper($pubMonth)] : 'JAN';
    
    // Set day (current day when book is added)
    $dayStr = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
    
    // Set year
    $yearStr = (string)$pubYear;
    
    // Set category code - use first 3 letters of category name
    $cleanCategory = preg_replace('/[^A-Za-z0-9]/', '', $bookCategory);
    $categoryCode = strtoupper(substr($cleanCategory, 0, 3));
    if (strlen($categoryCode) < 3) {
        $categoryCode = str_pad($categoryCode, 3, 'X', STR_PAD_RIGHT);
    }
    
    // Format count with leading zeros - combines title_id and copy_number
    $countStr = str_pad($titleId . str_pad($copyNumber, 2, '0', STR_PAD_LEFT), 5, '0', STR_PAD_LEFT);
    
    // Construct book ID
    $bookId = $titlePrefix . $monthStr . $dayStr . $yearStr . '-' . $categoryCode . $countStr;
    
    return $bookId;
}

/**
 * Get the next copy number for a given title ID
 * Replaces the MySQL get_next_copy_number function
 */
function getNextCopyNumber($pdo, $titleId) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(copy_number), 0) + 1 as next_copy FROM book_copies WHERE title_id = ?");
        $stmt->execute([$titleId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['next_copy'] ?? 1;
    } catch (PDOException $e) {
        error_log("Error getting next copy number: " . $e->getMessage());
        return 1; // Default fallback
    }
}

/**
 * Add a new book copy with automatically generated book_id and copy_number
 * This replaces the functionality that was handled by the MySQL trigger
 * NOTE: This function should NOT start its own transaction when called within an existing transaction
 */
function addBookCopy($pdo, $titleId, $conditionStatus = 'Excellent', $acquisitionDate = null, $location = null, $status = 'Available', $notes = null) {
    try {
        // Don't start a new transaction if we're already in one
        $inTransaction = $pdo->inTransaction();
        if (!$inTransaction) {
            $pdo->beginTransaction();
        }
        
        // Get book title information
        $stmt = $pdo->prepare("SELECT title, published_month, published_year, category FROM book_titles WHERE title_id = ?");
        $stmt->execute([$titleId]);
        $bookInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$bookInfo) {
            throw new Exception("Book title not found for title_id: $titleId");
        }
        
        // Get next copy number
        $copyNumber = getNextCopyNumber($pdo, $titleId);
        
        // Generate book ID
        $bookId = generateBookId(
            $bookInfo['title'],
            $bookInfo['published_month'] ?? 'January',
            $bookInfo['published_year'] ?? date('Y'),
            $bookInfo['category'] ?? 'General',
            $titleId,
            $copyNumber
        );
        
        // Insert the book copy
        $stmt = $pdo->prepare("
            INSERT INTO book_copies (title_id, book_id, copy_number, condition_status, acquisition_date, location, status, notes, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $titleId,
            $bookId,
            $copyNumber,
            $conditionStatus,
            $acquisitionDate ?? date('Y-m-d'),
            $location,
            $status,
            $notes
        ]);
        
        $copyId = $pdo->lastInsertId();
        
        // Update total_copies and available_copies in book_titles
        $stmt = $pdo->prepare("
            UPDATE book_titles 
            SET total_copies = total_copies + 1,
                available_copies = CASE 
                    WHEN ? = 'Available' THEN available_copies + 1 
                    ELSE available_copies 
                END
            WHERE title_id = ?
        ");
        $stmt->execute([$status, $titleId]);
        
        // Only commit if we started the transaction
        if (!$inTransaction) {
            $pdo->commit();
        }
        
        return [
            'copy_id' => $copyId,
            'book_id' => $bookId,
            'copy_number' => $copyNumber
        ];
        
    } catch (Exception $e) {
        // Only rollback if we started the transaction
        if (!$inTransaction && isset($pdo)) {
            $pdo->rollBack();
        }
        error_log("Error adding book copy: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Update book availability counts
 * Call this function whenever book copy status changes
 */
function updateBookAvailability($pdo, $titleId) {
    try {
        $stmt = $pdo->prepare("
            UPDATE book_titles bt
            SET 
                total_copies = (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status != 'Archived'),
                available_copies = (SELECT COUNT(*) FROM book_copies WHERE title_id = ? AND status = 'Available')
            WHERE title_id = ?
        ");
        
        $stmt->execute([$titleId, $titleId, $titleId]);
        
        // Update overall status based on availability
        $stmt = $pdo->prepare("
            UPDATE book_titles 
            SET status = CASE 
                WHEN available_copies > 0 THEN 'Available'
                WHEN available_copies = 0 AND total_copies > 0 THEN 'Borrowed'
                ELSE 'Archived'
            END
            WHERE title_id = ?
        ");
        
        $stmt->execute([$titleId]);
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error updating book availability: " . $e->getMessage());
        throw $e;
    }
}

// Mark that this file has been included
define('BOOK_FUNCTIONS_INCLUDED', true);
?>