<?php
// instructor/quizzes/index.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

$instructor_id = $_SESSION['user_id']; // Retrieves the instructor's ID from the session

// Fetch all quizzes for this instructor - Comment indicating purpose of quiz retrieval
$query = "SELECT q.*, c.title as course_title,
          (SELECT COUNT(*) FROM quiz_questions WHERE quiz_id = q.quiz_id) as total_questions,
          (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.quiz_id) as total_attempts
          FROM quizzes q
          JOIN courses c ON q.course_id = c.course_id
          WHERE c.instructor_id = ?
          ORDER BY q.created_at DESC"; // SQL query with subqueries to fetch quizzes and stats

$stmt = $conn->prepare($query); // Prepares the SQL query
$stmt->bind_param("i", $instructor_id); // Binds instructor_id as an integer parameter
$stmt->execute(); // Executes the prepared statement
$quizzes = $stmt->get_result(); // Stores the query results in $quizzes
?>

<!DOCTYPE html> <!-- Declares HTML5 document type -->
<html lang="en"> <!-- Opens HTML document with English language -->
<head> <!-- Opens head section -->
    <meta charset="UTF-8"> <!-- Sets character encoding to UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Sets responsive viewport -->
    <title>Quiz Management - Instructor Dashboard</title> <!-- Sets page title -->
    <!-- CSS dan Bootstrap sudah termasuk di header.php --> <!-- Original comment about CSS inclusion -->
</head> <!-- Closes head section -->
<body> <!-- Opens body section -->

<div class="container-fluid py-4"> <!-- Opens fluid container with vertical padding -->
    <div class="d-flex justify-content-between align-items-center mb-4"> <!-- Creates flex container with spacing -->
        <h2>Quiz Management</h2> <!-- Displays page title -->
        <a href="create.php" class="btn btn-primary"> <!-- Creates link button to create new quiz -->
            <i class="fas fa-plus"></i> Create New Quiz <!-- Adds plus icon and text -->
        </a> <!-- Closes create quiz link -->
    </div> <!-- Closes flex container -->

    <?php if (isset($_SESSION['success'])): ?> <!-- Checks if success message exists in session -->
        <div class="alert alert-success alert-dismissible fade show" role="alert"> <!-- Creates dismissible success alert -->
            <?php 
                echo $_SESSION['success']; // Displays success message
                unset($_SESSION['success']); // Removes success message from session
            ?> <!-- Closes PHP block for success message -->
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> <!-- Adds close button to alert -->
        </div> <!-- Closes success alert -->
    <?php endif; ?> <!-- Closes success message check -->

    <?php if (isset($_SESSION['error'])): ?> <!-- Checks if error message exists in session -->
        <div class="alert alert-danger alert-dismissible fade show" role="alert"> <!-- Creates dismissible error alert -->
            <?php 
                echo $_SESSION['error']; // Displays error message
                unset($_SESSION['error']); // Removes error message from session
            ?> <!-- Closes PHP block for error message -->
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button> <!-- Adds close button to alert -->
        </div> <!-- Closes error alert -->
    <?php endif; ?> <!-- Closes error message check -->

    <div class="row"> <!-- Opens row for grid layout -->
        <?php if ($quizzes->num_rows > 0): ?> <!-- Checks if there are any quizzes -->
            <?php while ($quiz = $quizzes->fetch_assoc()): ?> <!-- Loops through each quiz -->
                <div class="col-md-6 col-lg-4 mb-4"> <!-- Creates responsive column for quiz card -->
                    <div class="card h-100"> <!-- Creates card with full height -->
                        <div class="card-header"> <!-- Opens card header section -->
                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($quiz['title']); ?></h5> <!-- Displays quiz title -->
                        </div> <!-- Closes card header -->
                        <div class="card-body"> <!-- Opens card body section -->
                            <p class="mb-2"><strong>Course:</strong> <?php echo htmlspecialchars($quiz['course_title']); ?></p> <!-- Displays course title -->
                            <p class="mb-2"><strong>Questions:</strong> <?php echo $quiz['total_questions']; ?></p> <!-- Displays total questions -->
                            <p class="mb-2"><strong>Attempts:</strong> <?php echo $quiz['total_attempts']; ?></p> <!-- Displays total attempts -->
                            <p class="mb-2"><strong>Time Limit:</strong> <?php echo $quiz['time_limit']; ?> minutes</p> <!-- Displays time limit -->
                            <p class="mb-2"><strong>Passing Score:</strong> <?php echo $quiz['passing_score']; ?>%</p> <!-- Displays passing score -->
                            <p class="mb-0"> <!-- Opens paragraph for creation date -->
                                <small class="text-muted">Created: <?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></small> <!-- Displays formatted creation date -->
                            </p> <!-- Closes creation date paragraph -->
                        </div> <!-- Closes card body -->
                        <div class="card-footer bg-transparent p-2"> <!-- Opens transparent footer with padding -->
                            <div class="row g-1"> <!-- Opens row with minimal gutters -->
                                <div class="col-4"> <!-- Creates first column (1/3 width) -->
                                    <a href="edit.php?id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-sm btn-primary w-100" title="Edit Quiz"> <!-- Creates edit button link -->
                                        <i class="fas fa-edit"></i> Edit <!-- Adds edit icon and text -->
                                    </a> <!-- Closes edit link -->
                                </div> <!-- Closes first column -->
                                <div class="col-4"> <!-- Creates second column (1/3 width) -->
                                    <a href="questions.php?quiz_id=<?php echo $quiz['quiz_id']; ?>" class="btn btn-sm btn-info w-100" title="Manage Questions"> <!-- Creates questions button link -->
                                        <i class="fas fa-list"></i> Questions <!-- Adds list icon and text -->
                                    </a> <!-- Closes questions link -->
                                </div> <!-- Closes second column -->
                                <div class="col-4"> <!-- Creates third column (1/3 width) -->
                                    <button type="button" class="btn btn-sm btn-danger w-100" title="Delete Quiz" <!-- Creates delete button -->
                                            onclick="deleteQuiz(<?php echo $quiz['quiz_id']; ?>)"> <!-- Triggers deleteQuiz function with quiz ID -->
                                        <i class="fas fa-trash"></i> Delete <!-- Adds trash icon and text -->
                                    </button> <!-- Closes delete button -->
                                </div> <!-- Closes third column -->
                            </div> <!-- Closes button row -->
                        </div> <!-- Closes card footer -->
                    </div> <!-- Closes quiz card -->
                </div> <!-- Closes quiz column -->
            <?php endwhile; ?> <!-- Ends quiz loop -->
        <?php else: ?> <!-- Executes if no quizzes are found -->
            <div class="col-12"> <!-- Creates full-width column -->
                <div class="alert alert-info" role="alert"> <!-- Creates info alert -->
                    <h4 class="alert-heading">No Quizzes Found!</h4> <!-- Displays heading for no quizzes -->
                    <p>You haven't created any quizzes yet. Click the "Create New Quiz" button to get started.</p> <!-- Displays encouragement message -->
                </div> <!-- Closes info alert -->
            </div> <!-- Closes full-width column -->
        <?php endif; ?> <!-- Closes quiz existence check -->
    </div> <!-- Closes main row -->
</div> <!-- Closes fluid container -->

<!-- Delete Quiz Modal --> <!-- Comment indicating delete modal section -->
<div class="modal fade" id="deleteQuizModal" tabindex="-1" aria-hidden="true"> <!-- Creates hidden modal -->
    <div class="modal-dialog"> <!-- Opens modal dialog -->
        <div class="modal-content"> <!-- Opens modal content -->
            <div class="modal-header"> <!-- Opens modal header -->
                <h5 class="modal-title">Delete Quiz</h5> <!-- Sets modal title -->
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button> <!-- Adds close button -->
            </div> <!-- Closes modal header -->
            <div class="modal-body"> <!-- Opens modal body -->
                <p>Are you sure you want to delete this quiz?</p> <!-- Displays confirmation question -->
                <p class="text-danger"><strong>Warning:</strong> This will also delete:</p> <!-- Displays warning header -->
                <ul class="text-danger"> <!-- Opens warning list in red -->
                    <li>All quiz questions and options</li> <!-- Lists questions deletion consequence -->
                    <li>All student attempts and scores</li> <!-- Lists attempts deletion consequence -->
                </ul> <!-- Closes warning list -->
                <p class="text-danger">This action cannot be undone!</p> <!-- Emphasizes irreversibility -->
            </div> <!-- Closes modal body -->
            <div class="modal-footer"> <!-- Opens modal footer -->
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button> <!-- Creates cancel button -->
                <form action="delete.php" method="POST"> <!-- Opens delete form -->
                    <input type="hidden" name="quiz_id" id="deleteQuizId"> <!-- Creates hidden input for quiz ID -->
                    <button type="submit" class="btn btn-danger">Delete Quiz</button> <!-- Creates delete submit button -->
                </form> <!-- Closes delete form -->
            </div> <!-- Closes modal footer -->
        </div> <!-- Closes modal content -->
    </div> <!-- Closes modal dialog -->
</div> <!-- Closes modal -->

<!-- Script untuk delete quiz --> <!-- Comment indicating delete script -->
<script> <!-- Opens script tag -->
function deleteQuiz(quizId) { // Defines deleteQuiz function with quizId parameter
    if (document.getElementById('deleteQuizId')) { // Checks if deleteQuizId element exists
        document.getElementById('deleteQuizId').value = quizId; // Sets quiz ID in hidden input
        var modal = new bootstrap.Modal(document.getElementById('deleteQuizModal')); // Creates Bootstrap modal instance
        modal.show(); // Shows the delete confirmation modal
    } // Closes element existence check
} // Closes deleteQuiz function
</script> <!-- Closes script tag -->

<?php require_once '../../includes/footer.php'; ?> <!-- Includes footer file -->