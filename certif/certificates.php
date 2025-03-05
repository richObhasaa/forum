<?php
// student/certificates.php
require_once '../includes/header.php'; // Include the header file
requireRole('student'); // Check if user has student role

$student_id = $_SESSION['user_id']; // Get current student ID from session

// Handle certificate generation request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate_certificate'])) { // Check if form is submitted
    $course_id = (int)$_POST['course_id']; // Get and cast course ID to integer
    
    // Check if certificate already exists
    $check_cert = $conn->prepare("SELECT certificate_id FROM certificates WHERE user_id = ? AND course_id = ?"); // Prepare SQL to check existing certificate
    $check_cert->bind_param("ii", $student_id, $course_id); // Bind parameters to query
    $check_cert->execute(); // Execute the query
    
    if ($check_cert->get_result()->num_rows > 0) { // If certificate exists
        $_SESSION['error'] = "Certificate for this course already exists!"; // Set error message
    } else {
        try {
            // Check if course is completed
            $check_progress = $conn->prepare("SELECT progress FROM enrollments WHERE user_id = ? AND course_id = ?"); // Prepare SQL to check course progress
            $check_progress->bind_param("ii", $student_id, $course_id); // Bind parameters to query
            $check_progress->execute(); // Execute the query
            $progress_result = $check_progress->get_result(); // Get the result
            
            if ($progress_result->num_rows > 0) { // If enrollment exists
                $progress_data = $progress_result->fetch_assoc(); // Fetch progress data
                if ($progress_data['progress'] < 100) { // If course not completed
                    throw new Exception("You must complete the course (100%) before generating a certificate."); // Throw exception
                }
                
                // Generate a unique certificate number
                $certificate_number = 'CERT-' . date('Ymd') . '-' . str_pad($student_id, 5, '0', STR_PAD_LEFT) . '-' . 
                                     str_pad($course_id, 5, '0', STR_PAD_LEFT) . '-' . substr(md5(uniqid()), 0, 6); // Create unique certificate number
                
                // Get course details
                $course_query = $conn->prepare("SELECT title FROM courses WHERE course_id = ?"); // Prepare SQL to get course title
                $course_query->bind_param("i", $course_id); // Bind parameter to query
                $course_query->execute(); // Execute the query
                $course = $course_query->get_result()->fetch_assoc(); // Fetch course data
                
                $title = "Certificate of Completion: " . $course['title']; // Create certificate title
                
                // Insert new certificate - include created_by field set to the current user
                $insert_cert = $conn->prepare(
                    "INSERT INTO certificates (user_id, course_id, certificate_number, issued_date, status, title, created_by) 
                     VALUES (?, ?, ?, CURRENT_TIMESTAMP, 'valid', ?, ?)"
                ); // Prepare SQL to insert certificate
                $insert_cert->bind_param("iissi", $student_id, $course_id, $certificate_number, $title, $student_id); // Bind parameters to query
                
                if (!$insert_cert->execute()) { // If insert fails
                    throw new Exception("Database error: " . $conn->error); // Throw exception with error
                }
                
                $_SESSION['success'] = "Certificate generated successfully!"; // Set success message
            } else {
                throw new Exception("You are not enrolled in this course."); // Throw exception if not enrolled
            }
        } catch (Exception $e) {
            $_SESSION['error'] = $e->getMessage(); // Set error message from exception
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: certificates.php"); // Redirect to same page
    exit; // Stop script execution
}

// Get all certificates for the student
$certificates_query = $conn->prepare("
    SELECT 
        cert.certificate_id,
        cert.certificate_number,
        cert.issued_date,
        cert.title,
        c.course_id,
        c.title AS course_title,
        u.full_name AS student_name,
        u2.full_name AS instructor_name
    FROM certificates cert
    JOIN courses c ON cert.course_id = c.course_id
    JOIN users u ON cert.user_id = u.user_id
    JOIN users u2 ON c.instructor_id = u2.user_id
    WHERE cert.user_id = ?
    ORDER BY cert.issued_date DESC
"); // Prepare SQL to get all certificates
$certificates_query->bind_param("i", $student_id); // Bind parameter to query
$certificates_query->execute(); // Execute the query
$certificates = $certificates_query->get_result(); // Get the result set

// Get completed courses without certificates
$completed_courses_query = $conn->prepare("
    SELECT 
        c.course_id,
        c.title,
        e.progress,
        u.full_name AS instructor_name
    FROM enrollments e
    JOIN courses c ON e.course_id = c.course_id
    JOIN users u ON c.instructor_id = u.user_id
    WHERE e.user_id = ? 
    AND e.progress = 100
    AND c.course_id NOT IN (
        SELECT course_id FROM certificates WHERE user_id = ?
    )
"); // Prepare SQL to get completed courses without certificates
$completed_courses_query->bind_param("ii", $student_id, $student_id); // Bind parameters to query
$completed_courses_query->execute(); // Execute the query
$completed_courses = $completed_courses_query->get_result(); // Get the result set
?>

<div class="container-fluid py-4"> <!-- Main container with padding -->
    <?php if (isset($_SESSION['success'])): ?> <!-- Check for success message -->
        <div class="alert alert-success alert-dismissible fade show" role="alert"> <!-- Success alert box -->
            <?php 
                echo $_SESSION['success']; // Display success message
                unset($_SESSION['success']); // Clear success message
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> <!-- Close button -->
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?> <!-- Check for error message -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert"> <!-- Error alert box -->
            <?php 
                echo $_SESSION['error']; // Display error message
                unset($_SESSION['error']); // Clear error message
            ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> <!-- Close button -->
        </div>
    <?php endif; ?>

    <div class="row mb-4"> <!-- Row with bottom margin -->
        <div class="col-12"> <!-- Full width column -->
            <div class="card shadow"> <!-- Card with shadow -->
                <div class="card-header bg-primary text-white"> <!-- Card header with primary color -->
                    <h4 class="card-title mb-0">My Certificates</h4> <!-- Card title -->
                </div>
                <div class="card-body"> <!-- Card body -->
                    <?php if ($certificates->num_rows > 0): ?> <!-- If certificates exist -->
                        <div class="table-responsive"> <!-- Responsive table container -->
                            <table class="table table-hover"> <!-- Hoverable table -->
                                <thead> <!-- Table header -->
                                    <tr> <!-- Header row -->
                                        <th>Certificate</th> <!-- Header column -->
                                        <th>Course</th> <!-- Header column -->
                                        <th>Instructor</th> <!-- Header column -->
                                        <th>Issue Date</th> <!-- Header column -->
                                        <th>Certificate Number</th> <!-- Header column -->
                                        <th>Actions</th> <!-- Header column -->
                                    </tr>
                                </thead>
                                <tbody> <!-- Table body -->
                                    <?php while ($certificate = $certificates->fetch_assoc()): ?> <!-- Loop through certificates -->
                                        <tr> <!-- Certificate row -->
                                            <td><?php echo htmlspecialchars($certificate['title'] ?? 'Certificate of Completion'); ?></td> <!-- Certificate title -->
                                            <td><?php echo htmlspecialchars($certificate['course_title']); ?></td> <!-- Course title -->
                                            <td><?php echo htmlspecialchars($certificate['instructor_name']); ?></td> <!-- Instructor name -->
                                            <td><?php echo date('M d, Y', strtotime($certificate['issued_date'])); ?></td> <!-- Formatted issue date -->
                                            <td><small class="text-muted"><?php echo htmlspecialchars($certificate['certificate_number']); ?></small></td> <!-- Certificate number -->
                                            <td> <!-- Actions column -->
                                                <a href="view_certificate.php?id=<?php echo $certificate['certificate_id']; ?>" 
                                                   class="btn btn-sm btn-primary"> <!-- View button with link -->
                                                    <i class="fas fa-eye"></i> View <!-- Button text with icon -->
                                                </a>
                                                <a href="#" class="btn btn-sm btn-success print-cert" 
                                                   data-cert-id="<?php echo $certificate['certificate_id']; ?>"> <!-- Print button with data attribute -->
                                                    <i class="fas fa-print"></i> Print <!-- Button text with icon -->
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?> <!-- If no certificates -->
                        <div class="text-center py-4"> <!-- Centered content with padding -->
                            <div class="mb-3"> <!-- Element with bottom margin -->
                                <i class="fas fa-award fa-4x text-muted"></i> <!-- Award icon -->
                            </div>
                            <h4>No Certificates Yet</h4> <!-- Heading -->
                            <p class="text-muted">Complete courses to earn your certificates!</p> <!-- Helper text -->
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($completed_courses->num_rows > 0): ?> <!-- If completed courses exist -->
        <div class="row"> <!-- New row -->
            <div class="col-12"> <!-- Full width column -->
                <div class="card shadow"> <!-- Card with shadow -->
                    <div class="card-header bg-success text-white"> <!-- Card header with success color -->
                        <h4 class="card-title mb-0">Completed Courses</h4> <!-- Card title -->
                    </div>
                    <div class="card-body"> <!-- Card body -->
                        <p>Generate certificates for courses you've completed:</p> <!-- Helper text -->
                        <div class="table-responsive"> <!-- Responsive table container -->
                            <table class="table table-hover"> <!-- Hoverable table -->
                                <thead> <!-- Table header -->
                                    <tr> <!-- Header row -->
                                        <th>Course</th> <!-- Header column -->
                                        <th>Instructor</th> <!-- Header column -->
                                        <th>Progress</th> <!-- Header column -->
                                        <th>Actions</th> <!-- Header column -->
                                    </tr>
                                </thead>
                                <tbody> <!-- Table body -->
                                    <?php while ($course = $completed_courses->fetch_assoc()): ?> <!-- Loop through completed courses -->
                                        <tr> <!-- Course row -->
                                            <td><?php echo htmlspecialchars($course['title']); ?></td> <!-- Course title -->
                                            <td><?php echo htmlspecialchars($course['instructor_name']); ?></td> <!-- Instructor name -->
                                            <td> <!-- Progress column -->
                                                <div class="progress"> <!-- Progress bar container -->
                                                    <div class="progress-bar bg-success" role="progressbar" 
                                                         style="width: <?php echo $course['progress']; ?>%" 
                                                         aria-valuenow="<?php echo $course['progress']; ?>" 
                                                         aria-valuemin="0" aria-valuemax="100"> <!-- Progress bar -->
                                                        <?php echo $course['progress']; ?>% <!-- Progress percentage -->
                                                    </div>
                                                </div>
                                            </td>
                                            <td> <!-- Actions column -->
                                                <form method="POST"> <!-- Form for certificate generation -->
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>"> <!-- Hidden course ID input -->
                                                    <button type="submit" name="generate_certificate" class="btn btn-primary"> <!-- Submit button -->
                                                        <i class="fas fa-certificate"></i> Generate Certificate <!-- Button text with icon -->
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() { // When DOM is fully loaded
        // Add print functionality for certificates
        const printButtons = document.querySelectorAll('.print-cert'); // Get all print buttons
        printButtons.forEach(button => { // Loop through each button
            button.addEventListener('click', function(e) { // Add click event listener
                e.preventDefault(); // Prevent default link behavior
                const certId = this.getAttribute('data-cert-id'); // Get certificate ID
                // Open certificate in new window for printing
                window.open('view_certificate.php?id=' + certId + '&print=true', '_blank'); // Open certificate in new window
            });
        });
    });
</script>

<?php require_once '../includes/footer.php'; ?> // Include the footer file