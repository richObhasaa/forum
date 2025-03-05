<?php
// instructor/quizzes/questions.php - Specifies the file location and purpose
require_once '../../includes/header.php'; // Includes the header file from two directories up
requireRole('instructor'); // Restricts access to users with instructor role only

$quiz_id = isset($_GET['quiz_id']) ? (int)$_GET['quiz_id'] : 0; // Gets quiz ID from URL or defaults to 0
$instructor_id = $_SESSION['user_id']; // Retrieves the instructor's ID from the session

// Verify the quiz belongs to a course taught by this instructor - Comment indicating quiz ownership check
$verify_query = "SELECT q.*, c.title as course_title 
                FROM quizzes q 
                JOIN courses c ON q.course_id = c.course_id 
                WHERE q.quiz_id = ? AND c.instructor_id = ?"; // SQL to verify quiz and get course title
$stmt = $conn->prepare($verify_query); // Prepares the verification query
$stmt->bind_param("ii", $quiz_id, $instructor_id); // Binds quiz_id and instructor_id as integers
$stmt->execute(); // Executes the verification query
$quiz = $stmt->get_result()->fetch_assoc(); // Fetches quiz data as associative array

if (!$quiz) { // Checks if quiz doesn't exist or isn't owned by instructor
    $_SESSION['error'] = "Quiz not found or unauthorized access"; // Sets error message in session
    header("Location: index.php"); // Redirects to index page
    exit; // Terminates script execution
} // Closes quiz verification check

// Handle form submissions - Comment indicating form processing section
if ($_SERVER['REQUEST_METHOD'] == 'POST') { // Checks if form was submitted via POST
    // Validate and process question submission - Comment indicating question processing
    $question_text = $conn->real_escape_string($_POST['question_text']); // Sanitizes and gets question text
    $question_type = $conn->real_escape_string($_POST['question_type']); // Sanitizes and gets question type
    $points = (int)$_POST['points']; // Converts points to integer
    
    // Get reference answer if it's an essay question
    $reference_answer = NULL;
    if ($question_type == 'essay' && isset($_POST['reference_answer'])) {
        $reference_answer = $conn->real_escape_string($_POST['reference_answer']);
    }

    // Start transaction for safe insertion - Comment indicating transaction start
    $conn->begin_transaction(); // Begins a database transaction

    try { // Starts try block for error handling
        // Insert question - Comment indicating question insertion
        $question_insert = $conn->prepare("
            INSERT INTO quiz_questions (quiz_id, question_text, question_type, points, reference_answer) 
            VALUES (?, ?, ?, ?, ?)
        "); // Prepares query to insert new question
        $question_insert->bind_param("issis", $quiz_id, $question_text, $question_type, $points, $reference_answer); // Binds parameters
        $question_insert->execute(); // Executes question insertion
        $question_id = $conn->insert_id; // Gets the inserted question ID

        // Handle options based on question type - Comment indicating options processing
        switch($question_type) { // Switches based on question type
            case 'multiple_choice': // Starts multiple choice case
                // Validate and insert multiple choice options - Comment for multiple choice options
                if (isset($_POST['options']) && is_array($_POST['options'])) { // Checks if options array exists
                    $option_insert = $conn->prepare("
                        INSERT INTO quiz_options (question_id, option_text, is_correct) 
                        VALUES (?, ?, ?)
                    "); // Prepares query to insert options

                    foreach ($_POST['options'] as $index => $option_text) { // Loops through options
                        if (trim($option_text) != '') { // Checks if option text is not empty
                            $is_correct = isset($_POST['correct_option']) && $_POST['correct_option'] == $index; // Determines if option is correct
                            $option_insert->bind_param("isi", $question_id, $option_text, $is_correct); // Binds parameters
                            $option_insert->execute(); // Executes option insertion
                        } // Closes empty check
                    } // Closes options loop
                } // Closes options array check
                break; // Ends multiple choice case

            case 'true_false': // Starts true/false case
                // Insert True/False options - Comment for true/false options
                $correct_answer = $_POST['correct_option'] === 'true'; // Determines if true is correct
                $option_insert = $conn->prepare("
                    INSERT INTO quiz_options (question_id, option_text, is_correct) 
                    VALUES (?, 'True', ?), (?, 'False', ?)
                "); // Prepares query to insert true/false options
                $option_insert->bind_param("iiii", 
                    $question_id, $correct_answer, 
                    $question_id, !$correct_answer
                ); // Binds parameters for both options
                $option_insert->execute(); // Executes true/false options insertion
                break; // Ends true/false case

            case 'essay': // Starts essay case
                // No options for essay questions - just the reference answer stored in the question table
                break; // Ends essay case
        } // Closes switch statement

        // Commit transaction - Comment indicating transaction completion
        $conn->commit(); // Commits all changes to database
        $_SESSION['success'] = "Question added successfully"; // Sets success message in session
        
        // Redirect back to the questions page - Comment indicating redirect
        header("Location: questions.php?quiz_id=" . $quiz_id); // Redirects to questions page with quiz ID
        exit; // Terminates script execution

    } catch (Exception $e) { // Catches any exceptions during processing
        // Rollback transaction on error - Comment indicating error handling
        $conn->rollback(); // Rolls back changes on error
        $_SESSION['error'] = "Error adding question: " . $e->getMessage(); // Sets error message with exception details
    } // Closes catch block
} // Closes POST method check

// Fetch existing questions for this quiz - Comment indicating existing questions retrieval
$questions_query = $conn->prepare("
    SELECT q.*, 
    (SELECT COUNT(*) FROM quiz_options WHERE question_id = q.question_id) as options_count
    FROM quiz_questions q 
    WHERE q.quiz_id = ?
    ORDER BY q.question_id
"); // Prepares query to fetch questions with option count
$questions_query->bind_param("i", $quiz_id); // Binds quiz_id as integer
$questions_query->execute(); // Executes the questions query
$questions = $questions_query->get_result(); // Stores question results
?>

<!DOCTYPE html> <!-- Declares HTML5 document type -->
<html lang="en"> <!-- Opens HTML document with English language -->
<head> <!-- Opens head section -->
    <meta charset="UTF-8"> <!-- Sets character encoding to UTF-8 -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Sets responsive viewport -->
    <title>Manage Quiz Questions</title> <!-- Sets page title -->
    <style> <!-- Opens internal CSS -->
        .question-card { /* Styles question cards */
            border: 1px solid #ddd; /* Sets border */
            border-radius: 8px; /* Rounds corners */
            margin-bottom: 20px; /* Adds bottom margin */
            padding: 15px; /* Adds internal padding */
        } /* Closes question-card style */
        .correct-option { /* Styles correct options */
            background-color: #d4edda; /* Sets light green background */
            border-color: #c3e6cb; /* Sets green border */
        } /* Closes correct-option style */
        .reference-answer {
            background-color: #e3f2fd;
            border-left: 3px solid #4a90e2;
            padding: 10px 15px;
            margin-top: 10px;
        }
    </style> <!-- Closes internal CSS -->
</head> <!-- Closes head section -->
<body> <!-- Opens body section -->
<div class="container-fluid py-4"> <!-- Opens fluid container with padding -->
    <div class="row"> <!-- Opens row for layout -->
        <div class="col-md-8 mx-auto"> <!-- Creates centered column with medium width 8 -->
            <div class="card"> <!-- Creates card container -->
                <div class="card-header bg-primary text-white"> <!-- Opens header with blue background */
                    <h4 class="mb-0"> <!-- Opens header with no margin -->
                        Manage Questions for: <?php echo htmlspecialchars($quiz['title']); ?> <!-- Displays quiz title -->
                        <small class="text-white-50 d-block"> <!-- Creates small text block -->
                            Course: <?php echo htmlspecialchars($quiz['course_title']); ?> <!-- Displays course title -->
                        </small> <!-- Closes small text -->
                    </h4> <!-- Closes header -->
                </div> <!-- Closes card header -->
                
                <div class="card-body"> <!-- Opens card body -->
                    <?php if (isset($_SESSION['success'])): ?> <!-- Checks for success message -->
                        <div class="alert alert-success"> <!-- Creates success alert -->
                            <?php 
                            echo $_SESSION['success']; // Displays success message
                            unset($_SESSION['success']); // Removes success message
                            ?> <!-- Closes PHP block -->
                        </div> <!-- Closes success alert -->
                    <?php endif; ?> <!-- Closes success check -->

                    <?php if (isset($_SESSION['error'])): ?> <!-- Checks for error message -->
                        <div class="alert alert-danger"> <!-- Creates error alert -->
                            <?php 
                            echo $_SESSION['error']; // Displays error message
                            unset($_SESSION['error']); // Removes error message
                            ?> <!-- Closes PHP block -->
                        </div> <!-- Closes error alert -->
                    <?php endif; ?> <!-- Closes error check -->

                    <!-- Add Question Form --> <!-- Comment indicating form section -->
                    <div class="card mb-4"> <!-- Creates card with bottom margin -->
                        <div class="card-header"> <!-- Opens form header -->
                            <h5 class="mb-0">Add New Question</h5> <!-- Displays form title -->
                        </div> <!-- Closes form header -->
                        <div class="card-body"> <!-- Opens form body -->
                            <form method="POST" id="addQuestionForm"> <!-- Opens POST form with ID -->
                                <div class="mb-3"> <!-- Creates form group with margin -->
                                    <label for="question_text" class="form-label">Question Text</label> <!-- Creates question text label -->
                                    <textarea class="form-control" id="question_text" name="question_text" rows="3" required></textarea> <!-- Creates required textarea -->
                                </div> <!-- Closes question text group -->

                                <div class="row"> <!-- Opens row for type and points -->
                                    <div class="col-md-6"> <!-- Creates column for question type -->
                                        <div class="mb-3"> <!-- Creates form group -->
                                            <label for="question_type" class="form-label">Question Type</label> <!-- Creates type label -->
                                            <select class="form-select" id="question_type" name="question_type" required> <!-- Creates required dropdown -->
                                                <option value="multiple_choice">Multiple Choice</option> <!-- Multiple choice option -->
                                                <option value="true_false">True/False</option> <!-- True/false option -->
                                                <option value="essay">Essay</option> <!-- Essay option -->
                                            </select> <!-- Closes dropdown -->
                                        </div> <!-- Closes type group -->
                                    </div> <!-- Closes type column -->
                                    <div class="col-md-6"> <!-- Creates column for points -->
                                        <div class="mb-3"> <!-- Creates form group -->
                                            <label for="points" class="form-label">Points</label> <!-- Creates points label -->
                                            <input type="number" class="form-control" id="points" name="points" 
                                                   min="1" max="100" value="1" required> <!-- Creates required number input -->
                                        </div> <!-- Closes points group -->
                                    </div> <!-- Closes points column -->
                                </div> <!-- Closes type/points row -->

                                <!-- Options Container - Dynamic Content --> <!-- Comment for dynamic options -->
                                <div id="optionsContainer" class="mb-3"> <!-- Creates container for options -->
                                    <!-- Options will be dynamically added here based on question type --> <!-- Original comment -->
                                </div> <!-- Closes options container -->

                                <button type="submit" class="btn btn-primary">Add Question</button> <!-- Creates submit button -->
                            </form> <!-- Closes form -->
                        </div> <!-- Closes form body -->
                    </div> <!-- Closes form card -->

                    <!-- Existing Questions List --> <!-- Comment indicating questions list -->
                    <h4>Existing Questions</h4> <!-- Displays section title -->
                    <?php if ($questions->num_rows > 0): ?> <!-- Checks if questions exist -->
                        <?php while ($question = $questions->fetch_assoc()): ?> <!-- Loops through questions -->
                            <div class="card question-card"> <!-- Creates question card -->
                                <div class="card-body"> <!-- Opens question body -->
                                    <h5><?php echo htmlspecialchars($question['question_text']); ?></h5> <!-- Displays question text -->
                                    <div class="row"> <!-- Opens row for details -->
                                        <div class="col-md-6"> <!-- Creates column for type -->
                                            <strong>Type:</strong> 
                                            <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?> <!-- Displays formatted type -->
                                        </div> <!-- Closes type column -->
                                        <div class="col-md-6"> <!-- Creates column for points -->
                                            <strong>Points:</strong> <?php echo $question['points']; ?> <!-- Displays points -->
                                        </div> <!-- Closes points column -->
                                    </div> <!-- Closes details row -->
                                    
                                    <!-- Display Options --> <!-- Comment indicating options display -->
                                    <?php if ($question['question_type'] != 'essay'): ?> <!-- Checks if not essay type -->
                                        <div class="mt-3"> <!-- Creates div with top margin -->
                                            <strong>Options:</strong> <!-- Displays options label -->
                                            <?php 
                                            $options_query = $conn->prepare("SELECT * FROM quiz_options WHERE question_id = ?"); // Prepares options query
                                            $options_query->bind_param("i", $question['question_id']); // Binds question ID
                                            $options_query->execute(); // Executes options query
                                            $options = $options_query->get_result(); // Gets options results
                                            ?> <!-- Closes PHP block -->
                                            <ul class="list-group"> <!-- Creates options list -->
                                                <?php while ($option = $options->fetch_assoc()): ?> <!-- Loops through options -->
                                                    <li class="list-group-item <?php echo $option['is_correct'] ? 'correct-option' : ''; ?>"> <!-- Creates list item with conditional class -->
                                                        <?php echo htmlspecialchars($option['option_text']); ?> <!-- Displays option text -->
                                                        <?php if ($option['is_correct']): ?> <!-- Checks if option is correct -->
                                                            <span class="badge bg-success float-end">Correct</span> <!-- Displays correct badge -->
                                                        <?php endif; ?> <!-- Closes correct check -->
                                                    </li> <!-- Closes list item -->
                                                <?php endwhile; ?> <!-- Ends options loop -->
                                            </ul> <!-- Closes options list -->
                                        </div> <!-- Closes options div -->
                                    <?php else: ?> <!-- For essay questions -->
                                        <?php if (!empty($question['reference_answer'])): ?>
                                        <div class="mt-3"> <!-- Creates div with top margin -->
                                            <strong>Reference Answer:</strong> <!-- Displays reference answer label -->
                                            <div class="reference-answer">
                                                <?php echo nl2br(htmlspecialchars($question['reference_answer'])); ?> <!-- Displays reference answer with line breaks -->
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    <?php endif; ?> <!-- Closes question type check -->
                                </div> <!-- Closes question body -->
                                <div class="card-footer"> <!-- Opens question footer -->
                                    <button class="btn btn-sm btn-warning">Edit</button> <!-- Creates edit button -->
                                    <button class="btn btn-sm btn-danger">Delete</button> <!-- Creates delete button -->
                                </div> <!-- Closes question footer -->
                            </div> <!-- Closes question card -->
                        <?php endwhile; ?> <!-- Ends questions loop -->
                    <?php else: ?> <!-- Executes if no questions exist -->
                        <div class="alert alert-info">No questions added yet.</div> <!-- Displays no questions message -->
                    <?php endif; ?> <!-- Closes questions check -->
                </div> <!-- Closes card body -->
            </div> <!-- Closes main card -->
        </div> <!-- Closes centered column -->
    </div> <!-- Closes main row -->
</div> <!-- Closes fluid container -->

<script> <!-- Opens script tag -->
document.getElementById('question_type').addEventListener('change', function() { // Adds change event listener to question type
    const optionsContainer = document.getElementById('optionsContainer'); // Gets options container element
    const questionType = this.value; // Gets selected question type

    // Clear previous options - Comment indicating options clearing
    optionsContainer.innerHTML = ''; // Clears existing content

    // Dynamically add options based on question type - Comment for dynamic options
    switch(questionType) { // Switches based on question type
        case 'multiple_choice': // Starts multiple choice case
            optionsContainer.innerHTML = `
                <label class="form-label">Options</label>
                <div class="options">
                    <!-- Initial 4 options -->
                    ${generateMultipleChoiceOptions(4)}
                </div>
                <button type="button" class="btn btn-outline-secondary mt-2" onclick="addMultipleChoiceOption()">
                    Add Option
                </button>
                <small class="text-muted d-block mt-2">Select the radio button next to the correct answer.</small>
            `; // Adds multiple choice options HTML
            optionsContainer.style.display = 'block'; // Shows container
            break; // Ends multiple choice case

        case 'true_false': // Starts true/false case
            optionsContainer.innerHTML = `
                <label class="form-label">Correct Answer</label>
                <div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="correct_option" value="true" required>
                        <label class="form-check-label">True</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="correct_option" value="false" required>
                        <label class="form-check-label">False</label>
                    </div>
                </div>
            `; // Adds true/false options HTML
            optionsContainer.style.display = 'block'; // Shows container
            break; // Ends true/false case
            
        case 'essay': // Starts essay case
            // New code to add reference answer field for essay questions
            optionsContainer.innerHTML = `
                <label class="form-label">Reference Answer</label>
                <div class="mb-3">
                    <textarea class="form-control" name="reference_answer" rows="5" 
                        placeholder="Enter a reference answer or grading guidelines for this essay question"></textarea>
                    <small class="text-muted">This answer will be used as a reference when grading student submissions.</small>
                </div>
            `; // Adds essay reference answer field
            optionsContainer.style.display = 'block'; // Shows container
            break; // Ends essay case
    } // Closes switch statement
}); // Closes event listener

function generateMultipleChoiceOptions(count) { // Defines function to generate multiple choice options
    let optionsHtml = ''; // Initializes empty HTML string
    for (let i = 0; i < count; i++) { // Loops to create specified number of options
        optionsHtml += `
            <div class="input-group mb-2">
                <div class="input-group-text">
                    <input type="radio" name="correct_option" value="${i}" required>
                </div>
                <input type="text" class="form-control" name="options[]" placeholder="Option ${i+1}" required>
                <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `; // Adds option HTML to string
    } // Closes loop
    return optionsHtml; // Returns generated HTML
} // Closes generate function

function addMultipleChoiceOption() { // Defines function to add new multiple choice option
    const optionsContainer = document.querySelector('.options'); // Gets options container
    const currentOptionsCount = optionsContainer.children.length; // Gets current number of options
    
    const newOption = document.createElement('div'); // Creates new div element
    newOption.className = 'input-group mb-2'; // Sets class for new option
    newOption.innerHTML = `
        <div class="input-group-text">
            <input type="radio" name="correct_option" value="${currentOptionsCount}" required>
        </div>
        <input type="text" class="form-control" name="options[]" placeholder="Option ${currentOptionsCount+1}" required>
        <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
            <i class="fas fa-times"></i>
        </button>
    `; // Sets HTML for new option
    
    optionsContainer.appendChild(newOption); // Adds new option to container
} // Closes add function

function removeOption(button) { // Defines function to remove option
    const optionsContainer = document.querySelector('.options'); // Gets options container
    if (optionsContainer.children.length > 2) { // Checks if more than 2 options remain
        button.closest('.input-group').remove(); // Removes the option
        
        // Re-index radio button values - Comment indicating re-indexing
        optionsContainer.querySelectorAll('input[type="radio"]').forEach((radio, index) => { // Loops through radio buttons
            radio.value = index; // Updates radio button value
        }); // Closes re-indexing loop
    } else { // Executes if only 2 options remain
        alert('A multiple choice question must have at least 2 options.'); // Shows alert
    } // Closes minimum options check
} // Closes remove function

// Trigger initial question type change to set up options - Comment indicating initial trigger
document.getElementById('question_type').dispatchEvent(new Event('change')); // Triggers change event on page load
</script> <!-- Closes script tag -->

<?php require_once '../../includes/footer.php'; ?> <!-- Includes footer file -->