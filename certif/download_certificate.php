<?php
// student/download_certificate.php
// This file generates a certificate PDF for download

// Don't include the header as it might send HTML content
// We only want to output the PDF file
session_start(); // Start PHP session
require_once '../config/database.php'; // Include database configuration
require_once '../includes/functions.php'; // Include utility functions

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'student') { // Verify user authentication and role
    header("Location: ../auth/login.php"); // Redirect to login page if not authenticated as student
    exit; // Stop script execution
}

$student_id = $_SESSION['user_id']; // Get current student ID from session
$certificate_id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // Get and sanitize certificate ID from URL parameter

// Verify certificate ownership
$certificate_query = $conn->prepare("
    SELECT 
        cert.*,
        c.title AS course_title,
        u.full_name AS student_name,
        u2.full_name AS instructor_name
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    JOIN users u ON cert.user_id = u.user_id
    JOIN users u2 ON c.instructor_id = u2.user_id
    WHERE cert.certificate_id = ? AND cert.user_id = ?
"); // Prepare SQL to get certificate details with related data
$certificate_query->bind_param("ii", $certificate_id, $student_id); // Bind parameters to query
$certificate_query->execute(); // Execute the query
$certificate = $certificate_query->get_result()->fetch_assoc(); // Fetch certificate data

if (!$certificate) { // If certificate not found or does not belong to student
    $_SESSION['error'] = "Certificate not found or unauthorized access."; // Set error message
    header("Location: certificates.php"); // Redirect to certificates page
    exit; // Stop script execution
}

// Create a simple HTML-based certificate instead of PDF
// This is a fallback solution if TCPDF is not available
$filename = 'certificate_' . $certificate['certificate_number'] . '.html'; // Create filename based on certificate number

// Set headers for file download
header('Content-Type: text/html'); // Set content type as HTML
header('Content-Disposition: attachment; filename="' . $filename . '"'); // Set file download headers
header('Cache-Control: max-age=0'); // Prevent caching

// Generate the certificate HTML
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"> <!-- Set character encoding -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Set viewport for responsiveness -->
    <title>Certificate of Completion</title> <!-- Set document title -->
    <style>
        body {
            font-family: Arial, sans-serif; <!-- Set font family -->
            margin: 0; <!-- Remove default margin -->
            padding: 0; <!-- Remove default padding -->
            background-color: #f5f5f5; <!-- Set light gray background -->
        }
        .certificate-container {
            width: 100%; <!-- Full width container -->
            max-width: 1000px; <!-- Maximum width -->
            margin: 20px auto; <!-- Center container with top/bottom margin -->
            padding: 20px; <!-- Add padding around container -->
            box-sizing: border-box; <!-- Include padding in width calculation -->
        }
        .certificate {
            background-color: #fff; <!-- White background for certificate -->
            border: 5px solid #4a90e2; <!-- Blue border around certificate -->
            padding: 50px; <!-- Add padding inside certificate -->
            text-align: center; <!-- Center all content -->
            color: #333; <!-- Dark text color -->
            position: relative; <!-- Establish positioning context -->
        }
        .certificate h1 {
            font-size: 36px; <!-- Large font size for heading -->
            font-weight: bold; <!-- Bold heading text -->
            color: #4a90e2; <!-- Blue color for heading -->
            margin-bottom: 20px; <!-- Add space below heading -->
        }
        .certificate .recipient {
            font-size: 28px; <!-- Large font for recipient name -->
            font-weight: bold; <!-- Bold recipient name -->
            color: #333; <!-- Dark text color -->
            margin: 30px 0; <!-- Add vertical spacing -->
        }
        .certificate .course {
            font-size: 22px; <!-- Medium font for course title -->
            margin: 20px 0; <!-- Add vertical spacing -->
        }
        .certificate .date {
            font-size: 16px; <!-- Smaller font for date -->
            margin: 30px 0; <!-- Add vertical spacing -->
        }
        .certificate .signature {
            margin-top: 50px; <!-- Space above signatures -->
            display: inline-block; <!-- Allow width on inline element -->
            width: 200px; <!-- Set signature width -->
        }
        .signature-line {
            border-top: 1px solid #333; <!-- Add signature line -->
            padding-top: 10px; <!-- Space between line and text -->
            width: 200px; <!-- Width of signature line -->
            display: inline-block; <!-- Allow width on inline element -->
            margin: 0 30px; <!-- Add horizontal spacing between signatures -->
        }
        .certificate .number {
            font-size: 12px; <!-- Small font for certificate number -->
            color: #666; <!-- Medium gray color -->
            margin-top: 40px; <!-- Space above number -->
        }
        @media print {
            body {
                background-color: #fff; <!-- White background when printing -->
            }
            .certificate-container {
                margin: 0; <!-- No margin when printing -->
                padding: 0; <!-- No padding when printing -->
                width: 100%; <!-- Full width when printing -->
            }
            .certificate {
                border: 5px solid #4a90e2; <!-- Keep border when printing -->
                padding: 20px; <!-- Reduce padding when printing -->
            }
        }
    </style>
</head>
<body>
    <div class="certificate-container"> <!-- Main container -->
        <div class="certificate"> <!-- Certificate design wrapper -->
            <h1>CERTIFICATE OF COMPLETION</h1> <!-- Main heading -->
            <p>This is to certify that</p> <!-- Introductory text -->
            <div class="recipient">' . htmlspecialchars($certificate['student_name']) . '</div> <!-- Display student name with XSS protection -->
            <p>has successfully completed the course</p> <!-- Course completion text -->
            <div class="course">' . htmlspecialchars($certificate['course_title']) . '</div> <!-- Display course title with XSS protection -->
            <div class="date">Issued on: ' . date('F d, Y', strtotime($certificate['issued_date'])) . '</div> <!-- Display formatted issue date -->
            
            <div class="signatures"> <!-- Signature container -->
                <div class="signature-line"> <!-- First signature -->
                    ' . htmlspecialchars($certificate['instructor_name']) . '<br> <!-- Display instructor name with XSS protection -->
                    <small>Instructor</small> <!-- Instructor label -->
                </div>
                <div class="signature-line"> <!-- Second signature -->
                    Mini E-Learning<br> <!-- Platform name -->
                    <small>Platform Director</small> <!-- Director label -->
                </div>
            </div>
            
            <div class="number">Certificate ID: ' . htmlspecialchars($certificate['certificate_number']) . '</div> <!-- Display certificate number with XSS protection -->
        </div>
    </div>
    <script>
        // Auto print when opened
        window.onload = function() { <!-- Function to run when page loads -->
            // Uncomment the next line if you want automatic printing
            // window.print(); <!-- Would automatically open print dialog when uncommented -->
        }
    </script>
</body>
</html>';

echo $html; // Output the certificate HTML
exit; // Stop script execution
?>