<?php
// instructor/quizzes/edit.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

$instructor_id = $_SESSION['user_id']; // Retrieves the instructor's ID from the session
$quiz_id = isset($_GET['id']) ? intval($_GET['id']) : 0; // Gets quiz ID from URL parameter or defaults to 0

if (!$quiz_id) { // Checks if quiz_id is invalid (0)
    $_SESSION['error'] = "Invalid quiz ID"; // Sets error message in session
    header("Location: index.php"); // Redirects to index page
    exit; // Terminates script execution
} // Closes invalid quiz_id check

// Fetch quiz data along with the course - Comment indicating purpose of quiz retrieval
$query = "SELECT q.*, c.course_id, c.title AS course_title 
          FROM quizzes q
          JOIN courses c ON q.course_id = c.course_id
          WHERE q.quiz_id = ? AND c.instructor_id = ?"; // SQL to fetch quiz and course details
$stmt = $conn->prepare($query); // Prepares the SQL query
$stmt->bind_param("ii", $quiz_id, $instructor_id); // Binds quiz_id and instructor_id as integers
$stmt->execute(); // Executes the prepared statement
$quiz = $stmt->get_result()->fetch_assoc(); // Fetches quiz data as associative array

if (!$quiz) { // Checks if quiz was not found or not owned by instructor
    $_SESSION['error'] = "Quiz not found or you don't have permission to edit it"; // Sets error message in session
    header("Location: index.php"); // Redirects to index page
    exit; // Terminates script execution
} // Closes quiz existence check

// Fetch all courses for this instructor for the dropdown - Comment indicating course list retrieval
$course_query = "SELECT course_id, title FROM courses WHERE instructor_id = ? ORDER BY title"; // SQL to fetch instructor's courses
$course_stmt = $conn->prepare($course_query); // Prepares the course query
$course_stmt->bind_param("i", $instructor_id); // Binds instructor_id as integer
$course_stmt->execute(); // Executes the course query
$courses = $course_stmt->get_result(); // Stores course results

// Handle form submission - Comment indicating form processing section
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Checks if form was submitted via POST
    $title = trim($_POST['title']); // Trims and gets quiz title from POST
    $description = trim($_POST['description']); // Trims and gets quiz description from POST
    $course_id = intval($_POST['course_id']); // Converts course_id from POST to integer
    $time_limit = intval($_POST['time_limit']); // Converts time_limit from POST to integer
    $passing_score = intval($_POST['passing_score']); // Converts passing_score from POST to integer
    $is_randomized = isset($_POST['is_randomized']) ? 1 : 0; // Sets is_randomized based on checkbox (1 or 0)
    $is_active = isset($_POST['is_active']) ? 1 : 0; // Sets is_active based on checkbox (1 or 0)
    
    // Validate input - Comment indicating validation section
    $errors = []; // Initializes empty array for validation errors
    
    if (empty($title)) { // Checks if title is empty
        $errors[] = "Quiz title is required"; // Adds error message to array
    } // Closes title check
    
    if ($time_limit < 1) { // Checks if time limit is less than 1
        $errors[] = "Time limit must be at least 1 minute"; // Adds error message to array
    } // Closes time limit check
    
    if ($passing_score < 0 || $passing_score > 100) { // Checks if passing score is out of range
        $errors[] = "Passing score must be between 0 and 100"; // Adds error message to array
    } // Closes passing score check
    
    // Verify the course belongs to this instructor - Comment indicating course ownership check
    $course_check = "SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?"; // SQL to verify course ownership
    $check_stmt = $conn->prepare($course_check); // Prepares the course verification query
    $check_stmt->bind_param("ii", $course_id, $instructor_id); // Binds course_id and instructor_id as integers
    $check_stmt->execute(); // Executes the verification query
    $course_result = $check_stmt->get_result(); // Gets verification result
    
    if ($course_result->num_rows === 0) { // Checks if course doesn't belong to instructor
        $errors[] = "Invalid course selection"; // Adds error message to array
    } // Closes course verification
    
    if (empty($errors)) { // Checks if there are no validation errors
        // Update the quiz in the database - Comment indicating database update
        $update_query = "UPDATE quizzes SET 
                        title = ?, 
                        description = ?, 
                        course_id = ?, 
                        time_limit = ?, 
                        passing_score = ?, 
                        is_randomized = ?,
                        is_active = ?,
                        updated_at = NOW()
                        WHERE quiz_id = ?"; // SQL to update quiz details
        
        $update_stmt = $conn->prepare($update_query); // Prepares the update query
        $update_stmt->bind_param("ssiiiiii", $title, $description, $course_id, $time_limit, $passing_score, $is_randomized, $is_active, $quiz_id); // Binds parameters
        
        if ($update_stmt->execute()) { // Executes update and checks if successful
            $_SESSION['success'] = "Quiz updated successfully"; // Sets success message in session
            header("Location: index.php"); // Redirects to index page
            exit; // Terminates script execution
        } else { // Executes if update fails
            $_SESSION['error'] = "Error updating quiz: " . $conn->error; // Sets error message with database error
        } // Closes update execution check
    } else { // Executes if there are validation errors
        $_SESSION['error'] = implode("<br>", $errors); // Joins errors with line breaks and sets in session
    } // Closes error check
} // Closes POST method check
?>

<!DOCTYPE html> <!-- Declares HTML5 document type -->
<html lang="en"> <!-- Opens HTML document with English language -->
<head> <!-- Opens head section -->
    <meta charset="UTF-8"> <!-- Sets character encoding to UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Sets responsive viewport -->
    <title>Edit Quiz - Instructor Dashboard</title> <!-- Sets page title -->
    <!-- CSS dan Bootstrap sudah termasuk di header.php --> <!-- Original comment about CSS inclusion -->
</head> <!-- Closes head section -->
<body> <!-- Opens body section -->

<div class="container py-4"> <!-- Opens container with vertical padding -->
    <div class="row"> <!-- Opens row for grid layout -->
        <div class="col-md-8 offset-md-2"> <!-- Creates centered column with medium width 8 -->
            <div class="card shadow"> <!-- Creates card with shadow effect -->
                <div class="card-header bg-primary text-white"> <!-- Opens header with blue background and white text -->
                    <div class="d-flex justify-content-between align-items-center"> <!-- Creates flex container for header content -->
                        <h4 class="mb-0">Edit Quiz</h4> <!-- Displays card title with no bottom margin -->
                        <a href="index.php" class="btn btn-light btn-sm"> <!-- Creates back button link -->
                            <i class="fas fa-arrow-left me-1"></i> Back to Quizzes <!-- Adds arrow icon and text -->
                        </a> <!-- Closes back button link -->
                    </div> <!-- Closes flex container -->
                </div> <!-- Closes card header -->
                <div class="card-body"> <!-- Opens card body section -->
                    <?php if (isset($_SESSION['error'])): ?> <!-- Checks if error message exists in session -->
                        <div class="alert alert-danger alert-dismissible fade show" role="alert"> <!-- Creates dismissible error alert -->
                            <?php 
                                echo $_SESSION['error']; // Displays error message
                                unset($_SESSION['error']); // Removes error from session
                            ?> <!-- Closes PHP block for error display -->
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> <!-- Adds close button -->
                        </div> <!-- Closes error alert -->
                    <?php endif; ?> <!-- Closes error check -->

                    <form action="edit.php?id=<?php echo $quiz_id; ?>" method="POST"> <!-- Opens form with POST method and quiz ID -->
                        <div class="mb-3"> <!-- Creates form group with bottom margin -->
                            <label for="title" class="form-label">Quiz Title <span class="text-danger">*</span></label> <!-- Creates title label with required indicator -->
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required> <!-- Creates required title input -->
                        </div> <!-- Closes title group -->

                        <div class="mb-3"> <!-- Creates form group for description -->
                            <label for="description" class="form-label">Description</label> <!-- Creates description label -->
                            <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea> <!-- Creates description textarea -->
                            <small class="text-muted">Provide a brief description of what this quiz covers.</small> <!-- Adds description instruction -->
                        </div> <!-- Closes description group -->

                        <div class="mb-3"> <!-- Creates form group for course selection -->
                            <label for="course_id" class="form-label">Course <span class="text-danger">*</span></label> <!-- Creates course label with required indicator -->
                            <select class="form-select" id="course_id" name="course_id" required> <!-- Creates required course dropdown -->
                                <?php while ($course = $courses->fetch_assoc()): ?> <!-- Loops through instructor's courses -->
                                    <option value="<?php echo $course['course_id']; ?>" <?php echo ($course['course_id'] == $quiz['course_id']) ? 'selected' : ''; ?>> <!-- Creates course option -->
                                        <?php echo htmlspecialchars($course['title']); ?> <!-- Displays course title -->
                                    </option> <!-- Closes course option -->
                                <?php endwhile; ?> <!-- Ends course loop -->
                            </select> <!-- Closes course select -->
                        </div> <!-- Closes course group -->

                        <div class="row mb-3"> <!-- Opens row for time limit and passing score -->
                            <div class="col-md-6"> <!-- Creates column for time limit (half width) -->
                                <label for="time_limit" class="form-label">Time Limit (minutes) <span class="text-danger">*</span></label> <!-- Creates time limit label -->
                                <input type="number" class="form-control" id="time_limit" name="time_limit" min="1" value="<?php echo $quiz['time_limit']; ?>" required> <!-- Creates required time limit input -->
                                <small class="text-muted">How long students have to complete the quiz.</small> <!-- Adds time limit instruction -->
                            </div> <!-- Closes time limit column -->

                            <div class="col-md-6"> <!-- Creates column for passing score (half width) -->
                                <label for="passing_score" class="form-label">Passing Score (%) <span class="text-danger">*</span></label> <!-- Creates passing score label -->
                                <input type="number" class="form-control" id="passing_score" name="passing_score" min="0" max="100" value="<?php echo $quiz['passing_score']; ?>" required> <!-- Creates required passing score input -->
                                <small class="text-muted">Minimum percentage required to pass the quiz.</small> <!-- Adds passing score instruction -->
                            </div> <!-- Closes passing score column -->
                        </div> <!-- Closes time/passing score row -->

                        <div class="row mb-4"> <!-- Opens row for checkboxes with margin -->
                            <div class="col-md-6"> <!-- Creates column for randomize checkbox -->
                                <div class="form-check"> <!-- Creates checkbox container -->
                                    <input class="form-check-input" type="checkbox" id="is_randomized" name="is_randomized" <?php echo ($quiz['is_randomized'] == 1) ? 'checked' : ''; ?>> <!-- Creates randomize checkbox -->
                                    <label class="form-check-label" for="is_randomized"> <!-- Creates randomize label -->
                                        Randomize Questions <!-- Displays checkbox text -->
                                    </label> <!-- Closes randomize label -->
                                    <div class="form-text">Shuffle questions for each attempt</div> <!-- Adds randomize instruction -->
                                </div> <!-- Closes randomize checkbox container -->
                            </div> <!-- Closes randomize column -->

                            <div class="col-md-6"> <!-- Creates column for active checkbox -->
                                <div class="form-check"> <!-- Creates checkbox container -->
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" <?php echo ($quiz['is_active'] == 1) ? 'checked' : ''; ?>> <!-- Creates active checkbox -->
                                    <label class="form-check-label" for="is_active"> <!-- Creates active label -->
                                        Active <!-- Displays checkbox text -->
                                    </label> <!-- Closes active label -->
                                    <div class="form-text">Make quiz available to students</div> <!-- Adds active instruction -->
                                </div> <!-- Closes active checkbox container -->
                            </div> <!-- Closes active column -->
                        </div> <!-- Closes checkbox row -->

                        <div class="d-flex justify-content-end"> <!-- Creates flex container for buttons aligned right -->
                            <a href="index.php" class="btn btn-outline-secondary me-2">Cancel</a> <!-- Creates cancel button link -->
                            <button type="submit" class="btn btn-primary"> <!-- Creates submit button -->
                                <i class="fas fa-save me-1"></i> Update Quiz <!-- Adds save icon and text -->
                            </button> <!-- Closes submit button -->
                        </div> <!-- Closes button container -->
                    </form> <!-- Closes form -->
                </div> <!-- Closes card body -->
                <div class="card-footer"> <!-- Opens card footer section -->
                    <div class="d-flex justify-content-between align-items-center"> <!-- Creates flex container for footer content -->
                        <a href="questions.php?quiz_id=<?php echo $quiz_id; ?>" class="btn btn-success"> <!-- Creates manage questions link -->
                            <i class="fas fa-list me-1"></i> Manage Questions <!-- Adds list icon and text -->
                        </a> <!-- Closes manage questions link -->
                        <small class="text-muted">Last updated: <?php echo date('M d, Y H:i', strtotime($quiz['updated_at'])); ?></small> <!-- Displays last updated timestamp -->
                    </div> <!-- Closes footer flex container -->
                </div> <!-- Closes card footer -->
            </div> <!-- Closes card -->
        </div> <!-- Closes centered column -->
    </div> <!-- Closes row -->
</div> <!-- Closes container -->

<?php require_once '../../includes/footer.php'; ?> <!-- Includes footer file -->