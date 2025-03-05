<?php
// forum/edit_reply.php - Edit an existing forum reply
require_once '../includes/header.php';

// Check if user is logged in
if (!is_logged_in()) {
    $_SESSION['error'] = "You must be logged in to edit a reply.";
    header("Location: /auth/login.php");
    exit;
}

// Get the reply ID from URL
$reply_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$reply_id) {
    $_SESSION['error'] = "Invalid reply ID.";
    header("Location: index.php");
    exit;
}

// Get the user ID from session
$user_id = $_SESSION['user_id'];

// Get reply information
$reply = null;
$topic_id = 0;

if (isset($conn)) {
    $reply_query = $conn->prepare("
        SELECT fr.*, ft.topic_id, ft.title as topic_title, ft.status as topic_status
        FROM forum_replies fr
        JOIN forum_topics ft ON fr.topic_id = ft.topic_id
        WHERE fr.reply_id = ?
    ");
    
    $reply_query->bind_param("i", $reply_id);
    $reply_query->execute();
    $result = $reply_query->get_result();
    
    if ($result->num_rows > 0) {
        $reply = $result->fetch_assoc();
        $topic_id = $reply['topic_id'];
        
        // Check if user is authorized to edit (reply owner or admin)
        if ($reply['user_id'] != $user_id && (!isset($_SESSION['role']) || $_SESSION['role'] != 'admin')) {
            $_SESSION['error'] = "You don't have permission to edit this reply.";
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
        
        // Check if topic is closed
        if ($reply['topic_status'] === 'closed') {
            $_SESSION['error'] = "This topic is closed and replies cannot be edited.";
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
    } else {
        $_SESSION['error'] = "Reply not found.";
        header("Location: index.php");
        exit;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = isset($_POST['content']) ? trim($_POST['content']) : '';
    
    // Basic validation
    if (empty($content)) {
        $_SESSION['error'] = "Reply content cannot be empty.";
    } else {
        // Update the reply
        if (isset($conn)) {
            $update_query = $conn->prepare("
                UPDATE forum_replies
                SET content = ?, updated_at = NOW()
                WHERE reply_id = ?
            ");
            
            $update_query->bind_param("si", $content, $reply_id);
            
            if ($update_query->execute()) {
                $_SESSION['message'] = "Reply updated successfully.";
                $_SESSION['message_type'] = "success";
                header("Location: topic.php?id=" . $topic_id . "#reply-" . $reply_id);
                exit;
            } else {
                $_SESSION['error'] = "Failed to update reply.";
            }
        } else {
            // No database connection, simulate success
            $_SESSION['message'] = "Reply updated successfully! (Test mode)";
            $_SESSION['message_type'] = "success";
            header("Location: topic.php?id=" . $topic_id);
            exit;
        }
    }
}
?>

<div class="container my-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Forums</a></li>
            <li class="breadcrumb-item"><a href="topic.php?id=<?php echo $topic_id; ?>"><?php echo htmlspecialchars($reply['topic_title']); ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit Reply</li>
        </ol>
    </nav>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h1 class="h3 mb-0">Edit Reply</h1>
        </div>
        
        <div class="card-body">
            <?php display_message(); ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="content" class="form-label">Reply Content <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="content" name="content" rows="6" required><?php echo htmlspecialchars($reply['content']); ?></textarea>
                </div>
                
                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Reply
                    </button>
                    <a href="topic.php?id=<?php echo $topic_id; ?>#reply-<?php echo $reply_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>