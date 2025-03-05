<?php
// instructor/quizzes/manage.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

$instructor_id = $_SESSION['user_id']; // Retrieves the instructor's ID from the session
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0; // Gets course ID from URL or defaults to 0

// Verify course belongs to instructor - Comment indicating course ownership verification
$verify_query = $conn->prepare("
    SELECT c.*, 
           (SELECT COUNT(*) FROM quizzes WHERE course_id = c.course_id) as quiz_count
    FROM courses c 
    WHERE c.course_id = ? AND c.instructor_id = ?
"); // Prepares query to verify course and get quiz count
$verify_query->bind_param("ii", $course_id, $instructor_id); // Binds course_id and instructor_id as integers
$verify_query->execute(); // Executes the verification query
$course = $verify_query->get_result()->fetch_assoc(); // Fetches course data as associative array

if (!$course) { // Checks if course doesn't exist or isn't owned by instructor
    $_SESSION['error'] = "Course not found or unauthorized access"; // Sets error message in session
    header("Location: index.php"); // Redirects to index page
    exit; // Terminates script execution
} // Closes course verification check

// Get all quizzes for this course - Comment indicating quiz retrieval
$quizzes_query = $conn->prepare("
    SELECT q.*,
           (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as attempt_count,
           (SELECT AVG(score) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as average_score
    FROM quizzes q
    WHERE q.course_id = ?
    ORDER BY q.created_at DESC
"); // Prepares query to fetch quizzes with statistics
$quizzes_query->bind_param("i", $course_id); // Binds course_id as integer
$quizzes_query->execute(); // Executes the quizzes query
$quizzes = $quizzes_query->get_result(); // Stores quiz results
?>

<div class="container-fluid py-4"> <!-- Opens fluid container with vertical padding -->
    <div class="row mb-4"> <!-- Opens row with bottom margin -->
        <div class="col-12"> <!-- Creates full-width column -->
            <div class="card"> <!-- Creates card container -->
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center"> <!-- Opens header with flex layout -->
                    <h4 class="mb-0">Quiz Management - <?php echo htmlspecialchars($course['title']); ?></h4> <!-- Displays course title -->
                    <a href="create.php?course_id=<?php echo $course_id; ?>" class="btn btn-light"> <!-- Creates add quiz link -->
                        <i class="fas fa-plus"></i> Add New Quiz <!-- Adds plus icon and text -->
                    </a> <!-- Closes add quiz link -->
                </div> <!-- Closes card header -->
                <div class="card-body"> <!-- Opens card body section -->
                    <div class="row mb-4"> <!-- Opens row for statistics with margin -->
                        <div class="col-md-4"> <!-- Creates column for quiz count (1/3 width on medium screens) -->
                            <div class="card bg-info text-white"> <!-- Creates info card -->
                                <div class="card-body"> <!-- Opens card body for stats -->
                                    <h5 class="card-title">Total Quizzes</h5> <!-- Displays stats title -->
                                    <h2><?php echo $course['quiz_count']; ?></h2> <!-- Displays total quiz count -->
                                </div> <!-- Closes stats card body -->
                            </div> <!-- Closes info card -->
                        </div> <!-- Closes stats column -->
                    </div> <!-- Closes stats row -->

                    <?php if (isset($_SESSION['success'])): ?> <!-- Checks if success message exists -->
                        <div class="alert alert-success alert-dismissible fade show"> <!-- Creates dismissible success alert -->
                            <?php 
                                echo $_SESSION['success']; // Displays success message
                                unset($_SESSION['success']); // Removes success message from session
                            ?> <!-- Closes PHP block for success -->
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button> <!-- Adds close button -->
                        </div> <!-- Closes success alert -->
                    <?php endif; ?> <!-- Closes success check -->

                    <?php if (isset($_SESSION['error'])): ?> <!-- Checks if error message exists -->
                        <div class="alert alert-danger alert-dismissible fade show"> <!-- Creates dismissible error alert -->
                            <?php 
                                echo $_SESSION['error']; // Displays error message
                                unset($_SESSION['error']); // Removes error message from session
                            ?> <!-- Closes PHP block for error -->
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button> <!-- Adds close button -->
                        </div> <!-- Closes error alert -->
                    <?php endif; ?> <!-- Closes error check -->

                    <?php if ($quizzes->num_rows > 0): ?> <!-- Checks if there are any quizzes -->
                        <div class="table-responsive"> <!-- Creates responsive table container -->
                            <table class="table table-hover"> <!-- Creates hoverable table -->
                                <thead> <!-- Opens table header -->
                                    <tr> <!-- Opens header row -->
                                        <th>Quiz Title</th> <!-- Header for quiz title -->
                                        <th>Questions</th> <!-- Header for question count -->
                                        <th>Time Limit</th> <!-- Header for time limit -->
                                        <th>Passing Score</th> <!-- Header for passing score -->
                                        <th>Attempts</th> <!-- Header for attempt count -->
                                        <th>Average Score</th> <!-- Header for average score -->
                                        <th>Actions</th> <!-- Header for actions -->
                                    </tr> <!-- Closes header row -->
                                </thead> <!-- Closes table header -->
                                <tbody> <!-- Opens table body -->
                                    <?php while ($quiz = $quizzes->fetch_assoc()): ?> <!-- Loops through each quiz -->
                                        <tr> <!-- Opens table row for quiz -->
                                            <td><?php echo htmlspecialchars($quiz['title']); ?></td> <!-- Displays quiz title -->
                                            <td> <!-- Opens cell for question count -->
                                                <span class="badge bg-info"> <!-- Creates info badge -->
                                                    <?php echo $quiz['question_count']; ?> Questions <!-- Displays question count -->
                                                </span> <!-- Closes badge -->
                                            </td> <!-- Closes question count cell -->
                                            <td><?php echo $quiz['time_limit']; ?> minutes</td> <!-- Displays time limit -->
                                            <td><?php echo $quiz['passing_score']; ?>%</td> <!-- Displays passing score -->
                                            <td><?php echo $quiz['attempt_count']; ?></td> <!-- Displays attempt count -->
                                            <td> <!-- Opens cell for average score -->
                                                <?php if ($quiz['average_score']): ?> <!-- Checks if average score exists -->
                                                    <?php echo number_format($quiz['average_score'], 1); ?>% <!-- Displays formatted average score -->
                                                <?php else: ?> <!-- Executes if no average score -->
                                                    No attempts <!-- Displays no attempts message -->
                                                <?php endif; ?> <!-- Closes average score check -->
                                            </td> <!-- Closes average score cell -->
                                            <td> <!-- Opens cell for actions -->
                                                <div class="btn-group"> <!-- Creates button group -->
                                                    <a href="questions.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" 
                                                       class="btn btn-sm btn-primary" title="Manage Questions"> <!-- Creates questions link -->
                                                        <i class="fas fa-list"></i> <!-- Adds list icon -->
                                                    </a> <!-- Closes questions link -->
                                                    <a href="edit.php?id=<?php echo $quiz['quiz_id']; ?>" 
                                                       class="btn btn-sm btn-info" title="Edit Quiz"> <!-- Creates edit link -->
                                                        <i class="fas fa-edit"></i> <!-- Adds edit icon -->
                                                    </a> <!-- Closes edit link -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            onclick="deleteQuiz(<?php echo $quiz['quiz_id']; ?>)"
                                                            title="Delete Quiz"> <!-- Creates delete button -->
                                                        <i class="fas fa-trash"></i> <!-- Adds trash icon -->
                                                    </button> <!-- Closes delete button -->
                                                </div> <!-- Closes button group -->
                                            </td> <!-- Closes actions cell -->
                                        </tr> <!-- Closes quiz row -->
                                    <?php endwhile; ?> <!-- Ends quiz loop -->
                                </tbody> <!-- Closes table body -->
                            </table> <!-- Closes table -->
                        </div> <!-- Closes responsive table container -->
                    <?php else: ?> <!-- Executes if no quizzes exist -->
                        <div class="alert alert-info"> <!-- Creates info alert -->
                            <h5>No Quizzes Yet</h5> <!-- Displays no quizzes message -->
                            <p>This course doesn't have any quizzes. Click "Add New Quiz" to create your first quiz.</p> <!-- Displays encouragement -->
                        </div> <!-- Closes info alert -->
                    <?php endif; ?> <!-- Closes quiz existence check -->
                </div> <!-- Closes card body -->
            </div> <!-- Closes card -->
        </div> <!-- Closes full-width column -->
    </div> <!-- Closes main row -->

<!-- Quiz Statistics --> <!-- Comment indicating statistics section -->
<?php if ($quizzes->num_rows > 0): ?> <!-- Checks if quizzes exist for statistics -->
    <div class="row"> <!-- Opens row for statistics -->
        <div class="col-12"> <!-- Creates full-width column -->
            <div class="card"> <!-- Creates statistics card -->
                <div class="card-header"> <!-- Opens card header -->
                    <h5 class="card-title mb-0">Recent Quiz Attempts</h5> <!-- Displays statistics title -->
                </div> <!-- Closes card header -->
                <div class="card-body"> <!-- Opens card body -->
                    <?php
                    // Get recent quiz attempts - Comment indicating attempt retrieval
                    $attempts_query = $conn->prepare("
                        SELECT qa.*, 
                               q.title as quiz_title, 
                               q.passing_score,
                               u.username, 
                               u.full_name
                        FROM quiz_attempts qa
                        JOIN quizzes q ON qa.quiz_id = q.quiz_id
                        JOIN users u ON qa.user_id = u.user_id
                        WHERE q.course_id = ?
                        ORDER BY qa.completed_at DESC
                        LIMIT 10
                    "); // Prepares query for recent attempts
                    $attempts_query->bind_param("i", $course_id); // Binds course_id as integer
                    $attempts_query->execute(); // Executes the attempts query
                    $attempts = $attempts_query->get_result(); // Stores attempt results
                    ?>

                    <?php if ($attempts->num_rows > 0): ?> <!-- Checks if there are any attempts -->
                        <div class="table-responsive"> <!-- Creates responsive table container -->
                            <table class="table"> <!-- Creates table for attempts -->
                                <thead> <!-- Opens table header -->
                                    <tr> <!-- Opens header row -->
                                        <th>Student</th> <!-- Header for student name -->
                                        <th>Quiz</th> <!-- Header for quiz title -->
                                        <th>Score</th> <!-- Header for score -->
                                        <th>Date</th> <!-- Header for completion date -->
                                        <th>Status</th> <!-- Header for pass/fail status -->
                                    </tr> <!-- Closes header row -->
                                </thead> <!-- Closes table header -->
                                <tbody> <!-- Opens table body -->
                                    <?php while ($attempt = $attempts->fetch_assoc()): ?> <!-- Loops through each attempt -->
                                        <tr> <!-- Opens row for attempt -->
                                            <td><?php echo htmlspecialchars($attempt['full_name']); ?></td> <!-- Displays student name -->
                                            <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td> <!-- Displays quiz title -->
                                            <td><?php echo $attempt['score']; ?>%</td> <!-- Displays score percentage -->
                                            <td><?php echo date('M d, Y H:i', strtotime($attempt['completed_at'])); ?></td> <!-- Displays formatted completion date -->
                                            <td> <!-- Opens cell for status -->
                                                <?php if ($attempt['score'] >= $attempt['passing_score']): ?> <!-- Checks if score meets passing threshold -->
                                                    <span class="badge bg-success">Passed</span> <!-- Displays passed badge -->
                                                <?php else: ?> <!-- Executes if score is below passing -->
                                                    <span class="badge bg-danger">Failed</span> <!-- Displays failed badge -->
                                                <?php endif; ?> <!-- Closes status check -->
                                            </td> <!-- Closes status cell -->
                                        </tr> <!-- Closes attempt row -->
                                    <?php endwhile; ?> <!-- Ends attempt loop -->
                                </tbody> <!-- Closes table body -->
                            </table> <!-- Closes attempts table -->
                        </div> <!-- Closes responsive table container -->
                    <?php else: ?> <!-- Executes if no attempts exist -->
                        <p class="text-center">No quiz attempts yet.</p> <!-- Displays no attempts message -->
                    <?php endif; ?> <!-- Closes attempt existence check -->
                </div> <!-- Closes card body -->
            </div> <!-- Closes statistics card -->
        </div> <!-- Closes full-width column -->
    </div> <!-- Closes statistics row -->
<?php endif; ?> <!-- Closes quiz existence check for statistics -->

<!-- Delete Quiz Modal --> <!-- Comment indicating delete modal -->
<div class="modal fade" id="deleteQuizModal" tabindex="-1"> <!-- Creates hidden modal -->
    <div class="modal-dialog"> <!-- Opens modal dialog -->
        <div class="modal-content"> <!-- Opens modal content -->
            <div class="modal-header"> <!-- Opens modal header -->
                <h5 class="modal-title">Delete Quiz</h5> <!-- Sets modal title -->
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button> <!-- Adds close button -->
            </div> <!-- Closes modal header -->
            <div class="modal-body"> <!-- Opens modal body -->
                <p>Are you sure you want to delete this quiz?</p> <!-- Displays confirmation question -->
                <p class="text-danger"><strong>Warning:</strong> This will also delete:</p> <!-- Displays warning header -->
                <ul class="text-danger"> <!-- Opens warning list -->
                    <li>All questions and answers</li> <!-- Lists questions deletion consequence -->
                    <li>All student attempts and scores</li> <!-- Lists attempts deletion consequence -->
                </ul> <!-- Closes warning list -->
                <p class="text-danger">This action cannot be undone!</p> <!-- Emphasizes irreversibility -->
            </div> <!-- Closes modal body -->
            <div class="modal-footer"> <!-- Opens modal footer -->
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button> <!-- Creates cancel button -->
                <form action="delete.php" method="POST"> <!-- Opens delete form -->
                    <input type="hidden" name="quiz_id" id="deleteQuizId"> <!-- Creates hidden input for quiz ID -->
                    <input type="hidden" name="course_id" value="<?php echo $course_id; ?>"> <!-- Creates hidden input for course ID -->
                    <button type="submit" class="btn btn-danger">Delete Quiz</button> <!-- Creates delete submit button -->
                </form> <!-- Closes delete form -->
            </div> <!-- Closes modal footer -->
        </div> <!-- Closes modal content -->
    </div> <!-- Closes modal dialog -->
</div> <!-- Closes modal -->

<script> <!-- Opens script tag -->
function deleteQuiz(quizId) { // Defines deleteQuiz function with quizId parameter
    document.getElementById('deleteQuizId').value = quizId; // Sets quiz ID in hidden input
    new bootstrap.Modal(document.getElementById('deleteQuizModal')).show(); // Shows delete confirmation modal
} // Closes deleteQuiz function
</script> <!-- Closes script tag -->

<?php require_once '../../includes/footer.php'; ?> <!-- Includes footer file -->