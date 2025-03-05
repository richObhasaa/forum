<?php
// Simplified topic.php with error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include header
require_once '../includes/header.php';

// Get topic ID from URL
$topic_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get initial data about the topic for breadcrumbs
$topic_title = 'Topic Not Found';
$category_name = 'Forums';
$category_id = 0;

// Try to get topic data
if (isset($conn) && $topic_id > 0) {
    try {
        $stmt = $conn->prepare("
            SELECT t.topic_id, t.title, t.content, t.created_at, t.user_id, 
                   c.category_id, c.category_name, 
                   u.username
            FROM forum_topics t
            LEFT JOIN forum_categories c ON t.category_id = c.category_id
            LEFT JOIN users u ON t.user_id = u.user_id
            WHERE t.topic_id = ?
        ");
        
        if ($stmt) {
            $stmt->bind_param("i", $topic_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                $topic = $result->fetch_assoc();
                $topic_title = $topic['title'];
                $category_name = $topic['category_name'];
                $category_id = $topic['category_id'];
            }
        }
    } catch (Exception $e) {
        echo '<div class="container my-4 alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    }
}
?>

<div class="container my-4">
    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="index.php">Forums</a></li>
            <li class="breadcrumb-item">
                <a href="index.php"><?php echo htmlspecialchars($category_name); ?></a>
            </li>
            <li class="breadcrumb-item active" aria-current="page">
                <?php echo htmlspecialchars($topic_title); ?>
            </li>
        </ol>
    </nav>
    
    <?php if (!$topic_id || empty($topic)): ?>
        <div class="alert alert-warning">
            Topic not found. <a href="index.php">Return to forums</a>
        </div>
    <?php else: ?>
        <!-- Topic header -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($topic['title']); ?></h1>
                <small>
                    Posted by <?php echo htmlspecialchars($topic['username']); ?> 
                    on <?php echo date('F j, Y, g:i a', strtotime($topic['created_at'])); ?>
                </small>
            </div>
            
            <div class="card-body">
                <div class="topic-content">
                    <?php echo nl2br(htmlspecialchars($topic['content'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Replies section -->
        <h3>Replies</h3>
        
        <?php
        // Fetch replies if topic exists
        $replies = [];
        try {
            $stmt = $conn->prepare("
                SELECT r.reply_id, r.content, r.created_at, r.updated_at, 
                       u.user_id, u.username
                FROM forum_replies r
                LEFT JOIN users u ON r.user_id = u.user_id
                WHERE r.topic_id = ?
                ORDER BY r.created_at ASC
            ");
            
            if ($stmt) {
                $stmt->bind_param("i", $topic_id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $replies[] = $row;
                    }
                }
            }
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Error fetching replies: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <?php if (empty($replies)): ?>
            <div class="alert alert-info">
                No replies yet. Be the first to reply!
            </div>
        <?php else: ?>
            <?php foreach ($replies as $reply): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between">
                            <span><?php echo htmlspecialchars($reply['username']); ?></span>
                            <small><?php echo date('F j, Y, g:i a', strtotime($reply['created_at'])); ?></small>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php echo nl2br(htmlspecialchars($reply['content'])); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Reply form for logged in users -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h4>Post a Reply</h4>
                </div>
                <div class="card-body">
                    <form action="process_reply.php" method="POST">
                        <input type="hidden" name="topic_id" value="<?php echo $topic_id; ?>">
                        
                        <div class="mb-3">
                            <label for="content" class="form-label">Your Reply</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Post Reply</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info mt-4">
                Please <a href="/auth/login.php">log in</a> to reply to this topic.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>