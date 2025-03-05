<?php
// instructor/quizzes/create.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

$instructor_id = $_SESSION['user_id']; // Retrieves the instructor's ID from the session

// Fetch courses taught by this instructor - Comment indicating purpose of course retrieval
$query = "SELECT course_id, title FROM courses WHERE instructor_id = ? AND status = 'published'"; // SQL to fetch published courses
$stmt = $conn->prepare($query); // Prepares the SQL query
$stmt->bind_param("i", $instructor_id); // Binds instructor_id as an integer parameter
$stmt->execute(); // Executes the prepared statement
$courses = $stmt->get_result(); // Stores the query results in $courses

if ($_SERVER['REQUEST_METHOD'] == 'POST') { // Checks if the form was submitted via POST method
    $title = $conn->real_escape_string($_POST['title']); // Sanitizes and gets the quiz title
    $description = $conn->real_escape_string($_POST['description']); // Sanitizes and gets the quiz description
    $course_id = (int)$_POST['course_id']; // Converts course_id from POST to integer
    $time_limit = (int)$_POST['time_limit']; // Converts time_limit from POST to integer
    $passing_score = (int)$_POST['passing_score']; // Converts passing_score from POST to integer
    
    // Verify the course belongs to this instructor - Comment indicating course ownership verification
    $verify_query = "SELECT course_id FROM courses WHERE course_id = ? AND instructor_id = ?"; // SQL to verify course ownership
    $stmt = $conn->prepare($verify_query); // Prepares the verification query
    $stmt->bind_param("ii", $course_id, $instructor_id); // Binds course_id and instructor_id as integers
    $stmt->execute(); // Executes the verification query
    $result = $stmt->get_result(); // Gets the verification result
    
    if ($result->num_rows === 1) { // Checks if exactly one course was found (valid course)
        $sql = "INSERT INTO quizzes (course_id, title, description, time_limit, passing_score, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())"; // SQL to insert new quiz record
        
        if ($stmt = $conn->prepare($sql)) { // Prepares the insert statement and checks if successful
            $stmt->bind_param("issii", $course_id, $title, $description, $time_limit, $passing_score); // Binds parameters to statement
            
            if ($stmt->execute()) { // Executes the insert and checks if successful
                $quiz_id = $conn->insert_id; // Gets the auto-generated quiz ID
                $_SESSION['success'] = "Quiz created successfully. Add questions now."; // Sets success message in session
                header("Location: questions.php?quiz_id=" . $quiz_id); // Redirects to questions page with quiz ID
                exit; // Terminates script execution
            } else { // Executes if insertion fails
                $error = "Error creating quiz: " . $conn->error; // Sets error message with database error
            } // Closes execution check
        } // Closes statement preparation check
    } else { // Executes if course verification fails
        $error = "Invalid course selected."; // Sets error message for invalid course
    } // Closes course verification check
} // Closes POST method check
?>

<div class="container-fluid"> <!-- Opens a fluid-width container -->
    <div class="row"> <!-- Opens a row for grid layout -->
        <div class="col-md-8 mx-auto"> <!-- Creates a centered column with medium width 8 -->
            <div class="card shadow"> <!-- Creates a card with shadow effect -->
                <div class="card-header"> <!-- Opens the card header section -->
                    <h4 class="card-title">Create New Quiz</h4> <!-- Displays the card title -->
                </div> <!-- Closes the card header -->
                <div class="card-body"> <!-- Opens the card body section -->
                    <?php if (isset($error)): ?> <!-- Checks if an error message exists -->
                        <div class="alert alert-danger"><?php echo $error; ?></div> <!-- Displays error in red alert box -->
                    <?php endif; ?> <!-- Closes error check -->

                    <form method="POST"> <!-- Opens a form with POST method -->
                        <div class="mb-3"> <!-- Creates a form group with bottom margin -->
                            <label for="course_id" class="form-label">Select Course</label> <!-- Creates label for course selection -->
                            <select class="form-select" id="course_id" name="course_id" required> <!-- Creates required course dropdown -->
                                <option value="">Choose a course...</option> <!-- Adds default empty option -->
                                <?php while ($course = $courses->fetch_assoc()): ?> <!-- Loops through available courses -->
                                    <option value="<?php echo $course['course_id']; ?>"> <!-- Creates option with course ID -->
                                        <?php echo htmlspecialchars($course['title']); ?> <!-- Displays course title safely -->
                                    </option> <!-- Closes course option -->
                                <?php endwhile; ?> <!-- Ends course loop -->
                            </select> <!-- Closes course select -->
                        </div> <!-- Closes course selection group -->

                        <div class="mb-3"> <!-- Creates a form group for quiz title -->
                            <label for="title" class="form-label">Quiz Title</label> <!-- Creates label for title input -->
                            <input type="text" class="form-control" id="title" name="title" required
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>"> <!-- Creates required title input with persisted value -->
                        </div> <!-- Closes title group -->

                        <div class="mb-3"> <!-- Creates a form group for description -->
                            <label for="description" class="form-label">Description</label> <!-- Creates label for description -->
                            <textarea class="form-control" id="description" name="description" rows="3" required><?php 
                                echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; 
                            ?></textarea> <!-- Creates required description textarea with persisted value -->
                        </div> <!-- Closes description group -->

                        <div class="row"> <!-- Opens a row for time limit and passing score -->
                            <div class="col-md-6"> <!-- Creates a column for time limit (half width) -->
                                <div class="mb-3"> <!-- Creates a form group for time limit -->
                                    <label for="time_limit" class="form-label">Time Limit (minutes)</label> <!-- Creates label for time limit -->
                                    <input type="number" class="form-control" id="time_limit" name="time_limit" 
                                           min="1" max="180" required
                                           value="<?php echo isset($_POST['time_limit']) ? $_POST['time_limit'] : '30'; ?>"> <!-- Creates required number input for time limit -->
                                </div> <!-- Closes time limit group -->
                            </div> <!-- Closes time limit column -->
                            <div class="col-md-6"> <!-- Creates a column for passing score (half width) -->
                                <div class="mb-3"> <!-- Creates a form group for passing score -->
                                    <label for="passing_score" class="form-label">Passing Score (%)</label> <!-- Creates label for passing score -->
                                    <input type="number" class="form-control" id="passing_score" name="passing_score" 
                                           min="0" max="100" required
                                           value="<?php echo isset($_POST['passing_score']) ? $_POST['passing_score'] : '70'; ?>"> <!-- Creates required number input for passing score -->
                                </div> <!-- Closes passing score group -->
                            </div> <!-- Closes passing score column -->
                        </div> <!-- Closes time limit/passing score row -->

                        <div class="d-grid gap-2"> <!-- Creates a grid container for buttons with spacing -->
                            <button type="submit" class="btn btn-primary">Create Quiz</button> <!-- Creates submit button -->
                            <a href="index.php" class="btn btn-light">Cancel</a> <!-- Creates cancel link button -->
                        </div> <!-- Closes button grid -->
                    </form> <!-- Closes the form -->
                </div> <!-- Closes the card body -->
            </div> <!-- Closes the card -->
        </div> <!-- Closes the centered column -->
    </div> <!-- Closes the row -->
</div> <!-- Closes the fluid container -->

<?php require_once '../../includes/footer.php'; ?> <!-- Includes the footer file -->