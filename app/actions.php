<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (!empty($_POST['title'])) {
                    $stmt = $pdo->prepare("INSERT INTO tasks (title, description, priority, category, due_date) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'] ?? '',
                        $_POST['priority'] ?? 'Medium',
                        $_POST['category'] ?? 'General',
                        $_POST['due_date'] ?? null
                    ]);
                }
                break;
            case 'advance_status':
                if (isset($_POST['id'])) {
                    // Get current status
                    $stmt = $pdo->prepare("SELECT status FROM tasks WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $task = $stmt->fetch();
                    
                    if ($task) {
                        $current = $task['status'];
                        $next = 'pending';
                        
                        if ($current === 'pending') $next = 'in_progress';
                        elseif ($current === 'in_progress') $next = 'completed';
                        elseif ($current === 'completed') $next = 'pending';
                        
                        $update = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                        $update->execute([$next, $_POST['id']]);
                    }
                }
                break;
            case 'update_status':
                if (isset($_POST['id']) && isset($_POST['status'])) {
                    $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                    $stmt->execute([$_POST['status'], $_POST['id']]);
                }
                break;
            case 'delete':
                if (isset($_POST['id'])) {
                    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                }
                break;
        }
    }
}

header("Location: index.php");
exit;
?>
