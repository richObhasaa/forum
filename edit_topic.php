<?php
// forum/edit_topic.php - Edit an existing forum topic
require_once '../includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = "You must be logged in to edit a topic.";
    header("Location: /auth/login.php");
    exit;
}

// Get the topic ID from URL
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$topic_id) {
    $_SESSION['error'] = "Invalid topic ID.";
    header("Location: index.php");
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Get topic information
$topic = null;

if (isset($conn)) {
    $topic_query = $conn->prepare("
        SELECT ft.*, fc.category_name
        FROM forum_topics ft
        JOIN forum_categories fc ON ft.category_id = fc.category_id
        WHERE ft.topic_id = ?
    ");
    
    $topic_query->bind_param("i", $topic_id);
    $topic_query->execute();
    $result = $topic_query->get_result();
    
    if ($result->num_rows > 0) {
        $topic = $result->fetch_assoc();
        
        // Check if user is authorized to edit (topic owner or admin)
        if ($topic['user_id'] != $user_id && (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin')) {
            $_SESSION['error'] = "You don't have permission to edit this topic.";
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
    } else {
        $_SESSION['error'] = "Topic not found.";
        header("Location: index.php");
        exit;
    }
}

// Get list of categories
$categories = [];
if (isset($conn)) {
    $category_query = "SELECT category_id, category_name FROM forum_categories ORDER BY category_name";
    $result = $conn->query($category_query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// If no categories found, use sample data
if (empty($categories)) {
    $categories = [
        ['category_id' => 1, 'category_name' => 'General Discussion'],
        ['category_id' => 2, 'category_name' => 'Technical Support'],
        ['category_id' => 3, 'category_name' => 'Course Feedback'],
        ['category_id' => 4, 'category_name' => 'Study Groups']
    ];
}

// Get list of courses
$courses = [];
if (isset($conn)) {
    $course_query = "SELECT course_id, title FROM courses WHERE status = 'published' ORDER BY title";
    $result = $conn->query($course_query);
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $courses[] = $row;
        }
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
    $course_id = isset($_POST['course_id']) ? intval($_POST['course_id']) : null;
    $title = isset($_POST['title']) ? trim($_POST['title']) : '';
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    // Basic validation
    $errors = [];
    if (empty($category_id)) {
        $errors[] = "Please select a category";
    }
    if (empty($title)) {
        $errors[] = "Title is required";
    } elseif (strlen($title) < 5) {
        $errors[] = "Title must be at least 5 characters";
    } elseif (strlen($title) > 100) {
        $errors[] = "Title must be less than 100 characters";
    }
    if (empty($content)) {
        $errors[] = "Content is required";
    } elseif (strlen($content) < 10) {
        $errors[] = "Content must be at least 10 characters";
    }
    
    // If course_id is 0 or empty string, set to NULL
    if (empty($course_id)) {
        $course_id = null;
    }
    
    // If there are no errors, update the topic
    if (empty($errors)) {
        if (isset($conn)) {
            $update_query = $conn->prepare("
                UPDATE forum_topics
                SET category_id = ?, course_id = ?, title = ?, content = ?, updated_at = NOW()
                WHERE topic_id = ?
            ");
            
            $update_query->bind_param("iissi", $category_id, $course_id, $title, $content, $topic_id);
            
            if ($update_query->execute()) {
                $_SESSION['message'] = "Topic updated successfully.";
                $_SESSION['message_type'] = "success";
                header("Location: topic.php?id=" . $topic_id);
                exit;
            } else {
                $errors[] = "Failed to update topic: " . $conn->error;
            }
        } else {
            // No database connection, simulate success
            $_SESSION['message'] = "Topic updated successfully! (Test mode)";
            $_SESSION['message_type'] = "success";
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
    }
    
    // Display errors if any
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}
?>

<div class="container my-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Forums</a></li>
            <li class="breadcrumb-item"><a href="topic.php?id=<?php echo $topic_id; ?>"><?php echo htmlspecialchars($topic['title']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Topic</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h1 class="h3 mb-0">Edit Topic</h1>
        </div>
        
        <div class="card-body">
            <?php display_message(); ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="category_id" class="form-label">Select Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Choose a category...</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo ($topic['category_id'] == $category['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if (!empty($courses)): ?>
                <div class="mb-3">
                    <label for="course_id" class="form-label">Related Course (Optional)</label>
                    <select class="form-select" id="course_id" name="course_id">
                        <option value="">Not related to a specific course</option>
                        <?php foreach ($courses as $course): ?>
                            <option value="<?php echo $course['course_id']; ?>" <?php echo ($topic['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($course['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">If your topic is related to a specific course, select it here.</div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label for="title" class="form-label">Topic Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required
                           minlength="5" maxlength="100" value="<?php echo htmlspecialchars($topic['title']); ?>">
                    <div class="form-text">Be specific and descriptive (5-100 characters)</div>
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="content" name="content" rows="8" required
                              minlength="10"><?php echo htmlspecialchars($topic['content']); ?></textarea>
                    <div class="form-text">Describe your topic in detail (minimum 10 characters)</div>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Topic
                    </button>
                    <a href="topic.php?id=<?php echo $topic_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>