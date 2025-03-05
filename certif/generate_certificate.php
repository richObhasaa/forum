<?php // Opening PHP tag
// generate_certificate.php - Generate a PDF certificate // File description
session_start(); // Initialize session data
require_once 'includes/config.php'; // Include database configuration
require_once 'includes/auth.php'; // Include authentication functions
require_once 'vendor/fpdf.php'; // Include PDF generation library
requireLogin(); // Check if user is logged in
requireRole('student'); // Check if user has student role
try { // Start error handling block
    $certificateId = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT); // Get and validate certificate ID from URL
    if ($certificateId === false || $certificateId <= 0) { // Check if certificate ID is valid
        throw new Exception('Invalid certificate ID'); // Throw error if ID is invalid
    }
    $pdo = $GLOBALS['pdo'] ?? new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS); // Create database connection
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // Set PDO to throw exceptions on errors
    $stmt = $pdo->prepare(" // Prepare SQL query
        SELECT c.*, u.full_name AS recipient_name, co.title AS course_name // Select certificate, user and course data
        FROM certificates c // From certificates table
        LEFT JOIN users u ON c.user_id = u.user_id // Join with users table
        LEFT JOIN courses co ON c.course_id = co.course_id // Join with courses table
        WHERE c.certificate_id = ? AND c.user_id = ? // Filter by certificate ID and current user
    ");
    $stmt->execute([$certificateId, $_SESSION['user_id']]); // Execute query with parameters
    $certificate = $stmt->fetch(PDO::FETCH_ASSOC); // Get certificate data as associative array
    if (!$certificate) { // Check if certificate exists and belongs to current user
        throw new Exception('Certificate not found or access denied'); // Throw error if certificate not found
    }
    // Buat PDF
    $pdf = new FPDF('L', 'mm', 'A4'); // Create new PDF in landscape A4 format
    $pdf->AddPage(); // Add a new page to PDF
    
    // Border dekoratif
    $pdf->SetLineWidth(1); // Set border line width
    $pdf->Rect(10, 10, 277, 190, 'D'); // Draw decorative rectangle border
    
    // Header
    $pdf->SetFont('Arial', 'B', 24); // Set font to bold Arial 24pt
    $pdf->SetTextColor(0, 102, 204); // Set text color to blue
    $pdf->Cell(0, 20, 'Certificate of Completion', 0, 1, 'C'); // Add centered title text
    
    // Subheader
    $pdf->SetFont('Arial', '', 16); // Change font to regular Arial 16pt
    $pdf->SetTextColor(0, 0, 0); // Change text color to black
    $pdf->Ln(10); // Add 10mm vertical space
    $pdf->Cell(0, 10, 'This certifies that', 0, 1, 'C'); // Add centered text
    
    // Nama penerima
    $pdf->SetFont('Arial', 'B', 20); // Change font to bold Arial 20pt
    $pdf->Cell(0, 15, $certificate['recipient_name'], 0, 1, 'C'); // Add recipient name
    
    // Detail kursus
    $pdf->SetFont('Arial', '', 16); // Change font to regular Arial 16pt
    $pdf->Cell(0, 10, 'has successfully completed the course', 0, 1, 'C'); // Add centered text
    $pdf->SetFont('Arial', 'I', 18); // Change font to italic Arial 18pt
    $pdf->Cell(0, 15, $certificate['course_name'], 0, 1, 'C'); // Add course name
    
    // Info tambahan
    $pdf->SetFont('Arial', '', 12); // Change font to regular Arial 12pt
    $pdf->Ln(20); // Add 20mm vertical space
    $pdf->Cell(0, 8, 'Certificate Number: ' . $certificate['certificate_number'], 0, 1, 'C'); // Add certificate number
    $pdf->Cell(0, 8, 'Issued Date: ' . date('d M Y', strtotime($certificate['issued_date'])), 0, 1, 'C'); // Add formatted issue date
    $pdf->Cell(0, 8, 'Status: ' . ucfirst($certificate['status']), 0, 1, 'C'); // Add capitalized status
    // Footer (opsional)
    $pdf->SetY(180); // Set Y position to 180mm from top
    $pdf->SetFont('Arial', 'I', 10); // Change font to italic Arial 10pt
    $pdf->Cell(0, 10, 'Generated on ' . date('d M Y'), 0, 0, 'R'); // Add right-aligned generation date
    // Output sebagai download
    $pdf->Output('D', 'Certificate_' . $certificate['certificate_number'] . '.pdf'); // Output PDF as download with custom filename
} catch (Exception $e) { // Catch any exceptions
    error_log("Error generating certificate: " . $e->getMessage()); // Log error to server
    die("Oops, something went wrong: " . htmlspecialchars($e->getMessage())); // Display sanitized error message to user
}
?> // Closing PHP tag