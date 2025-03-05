<?php
// student/view_certificate.php
require_once '../includes/header.php'; // Include the header file
requireRole('student'); // Check if the user has student role permissions

$student_id = $_SESSION['user_id']; // Get the current student ID from session
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
"); // Prepare SQL query to get certificate details with related course and user information
$certificate_query->bind_param("ii", $certificate_id, $student_id); // Bind parameters to the prepared statement
$certificate_query->execute(); // Execute the prepared statement
$certificate = $certificate_query->get_result()->fetch_assoc(); // Fetch certificate data as associative array

if (!$certificate) {
    $_SESSION['error'] = "Certificate not found or unauthorized access."; // Set error message if certificate not found or unauthorized
    header("Location: certificates.php"); // Redirect to certificates list page
    exit; // Stop script execution
}
?>

<div class="container-fluid py-4"> <!-- Main container with padding -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header bg-warning text-white d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">Certificate Preview</h4> <!-- Card title -->
                    <a href="certificates.php" class="btn btn-light btn-sm">Back to Certificates</a> <!-- Back button -->
                </div>
                <div class="card-body p-0">
                    <div class="certificate-container"> <!-- Container for responsive certificate display -->
                        <div class="certificate-inner"> <!-- Inner container to maintain minimum width -->
                            <div class="certificate p-5 text-center border border-4 border-warning m-3"> <!-- Certificate design with golden border -->
                                <h1 class="display-4 fw-bold text-primary mb-4">CERTIFICATE OF COMPLETION</h1> <!-- Certificate heading -->
                                <p class="fs-4">This is to certify that</p>
                                <div class="fs-1 fw-bold my-4"><?php echo htmlspecialchars($certificate['student_name']); ?></div> <!-- Student name -->
                                <p class="fs-4">has successfully completed the course</p>
                                <div class="fs-2 fw-bold my-4"><?php echo htmlspecialchars($certificate['course_title']); ?></div> <!-- Course title -->
                                <div class="fs-5 my-4">Issued on: <?php echo date('F d, Y', strtotime($certificate['issued_date'])); ?></div> <!-- Formatted issue date -->
                                
                                <div class="row mt-5">
                                    <div class="col-md-6">
                                        <div class="signature">
                                            <div class="border-top border-dark d-inline-block pt-2" style="width: 200px;">
                                                <?php echo htmlspecialchars($certificate['instructor_name']); ?><br> <!-- Instructor name -->
                                                <small>Instructor</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="signature">
                                            <div class="border-top border-dark d-inline-block pt-2" style="width: 200px;">
                                                Mini E-Learning<br> <!-- Platform name -->
                                                <small>Platform Director</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-5 text-secondary">
                                    Certificate ID: <?php echo htmlspecialchars($certificate['certificate_number']); ?> <!-- Certificate unique ID -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-center">
                        <a href="download_certificate.php?id=<?php echo $certificate_id; ?>" class="btn btn-success btn-lg">
                            <i class="fas fa-download"></i> Download Certificate <!-- Download certificate button -->
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.certificate-container {
    overflow-x: auto; /* Enable horizontal scrolling for small screens */
}
.certificate-inner {
    min-width: 900px; /* Set minimum width to ensure certificate looks good */
}
.certificate {
    background-color: #fff; /* White background for certificate */
    box-shadow: 0 0 20px rgba(0,0,0,0.1); /* Subtle shadow for depth */
}
@media print {
    .card-header, .card-footer, .navbar, .footer-modern {
        display: none !important; /* Hide non-certificate elements when printing */
    }
    .card {
        border: none !important; /* Remove card border when printing */
        box-shadow: none !important; /* Remove shadow when printing */
    }
    .certificate {
        border: 5px solid #ffc107 !important; /* Ensure golden border remains when printing */
    }
}
</style>

<script>
// Add print button
document.addEventListener('DOMContentLoaded', function() {
    const footer = document.querySelector('.card-footer .d-flex'); // Find footer container
    const printBtn = document.createElement('button'); // Create new button element
    printBtn.className = 'btn btn-primary btn-lg ms-2'; // Add classes to the button
    printBtn.innerHTML = '<i class="fas fa-print"></i> Print Certificate'; // Set button text and icon
    printBtn.addEventListener('click', function() {
        window.print(); // Add click event to trigger browser print dialog
    });
    footer.appendChild(printBtn); // Append button to the footer
});
</script>

<?php require_once '../includes/footer.php'; ?> <!-- Include the footer file -->