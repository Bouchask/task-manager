<?php
require_once 'db.php';

// Filtering Logic
$filter = $_GET['filter'] ?? 'all';
$category_filter = $_GET['category'] ?? null;

$query = "SELECT * FROM tasks WHERE 1=1";
$params = [];

if ($filter === 'pending') {
    $query .= " AND status = 'pending'";
} elseif ($filter === 'in_progress') {
    $query .= " AND status = 'in_progress'";
} elseif ($filter === 'completed') {
    $query .= " AND status = 'completed'";
}

if ($category_filter) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get unique categories for sidebar
$categories = $pdo->query("SELECT DISTINCT category FROM tasks")->fetchAll(PDO::FETCH_COLUMN);

// Stats for header
$stats = $pdo->query("SELECT status, COUNT(*) as count FROM tasks GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
$pending_count = $stats['pending'] ?? 0;
$in_progress_count = $stats['in_progress'] ?? 0;
$completed_count = $stats['completed'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskMaster Pro</title>
    <style>
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --bg: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --radius: 16px;

            --high: #ef4444;
            --medium: #f59e0b;
            --low: #10b981;
            --in-progress: #3b82f6;
        }

        * { box-sizing: border-box; }
        body { 
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text-main);
            margin: 0;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        aside {
            width: 280px;
            background: #ffffff;
            border-right: 1px solid var(--border);
            padding: 2.5rem 1.5rem;
            display: flex;
            flex-direction: column;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .logo { 
            font-size: 1.5rem; 
            font-weight: 800; 
            color: var(--primary); 
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-section { margin-bottom: 2rem; }
        .nav-title {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
            padding-left: 0.75rem;
        }

        nav ul { list-style: none; padding: 0; margin: 0; }
        nav li { margin-bottom: 4px; }
        nav a {
            text-decoration: none;
            color: #475569;
            padding: 0.75rem 1rem;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
            font-weight: 500;
        }
        nav a:hover { background: #f1f5f9; color: var(--primary); }
        nav a.active { background: #eef2ff; color: var(--primary); }

        .count-badge {
            background: #f1f5f9;
            color: #64748b;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
        }
        nav a.active .count-badge { background: var(--primary); color: #fff; }

        /* Main */
        main { flex: 1; padding: 3rem; max-width: 1200px; margin: 0 auto; }

        .header-content { margin-bottom: 3rem; }
        .header-content h1 { font-size: 2.25rem; font-weight: 800; margin: 0; letter-spacing: -0.025em; }
        .header-content p { color: var(--text-muted); margin-top: 0.5rem; font-size: 1.1rem; }

        /* Add Task Section */
        .add-card {
            background: #fff;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid var(--border);
            margin-bottom: 3rem;
        }

        .form-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 1.5rem; }
        .input-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .input-group label { font-size: 0.875rem; font-weight: 600; color: #475569; }
        
        input, select {
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(99, 102, 241, 0.1);
        }

        .btn-submit {
            grid-column: span 4;
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1rem;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 1rem;
        }
        .btn-submit:hover { background: var(--primary-dark); }

        /* Task List */
        .task-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 1.5rem; }
        .task-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--radius);
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
        }
        .task-card:hover { transform: translateY(-4px); box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1); }

        .task-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .task-title { font-size: 1.125rem; font-weight: 700; margin: 0; line-height: 1.4; }
        .task-title.completed { text-decoration: line-through; color: var(--text-muted); }

        .tag-group { display: flex; flex-wrap: wrap; gap: 0.5rem; }
        .tag {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .tag-priority-High { background: #fee2e2; color: #b91c1c; }
        .tag-priority-Medium { background: #fef3c7; color: #b45309; }
        .tag-priority-Low { background: #d1fae5; color: #047857; }
        .tag-status { background: #f1f5f9; color: #475569; }
        .tag-status-in_progress { background: #dbeafe; color: #1d4ed8; }
        .tag-status-completed { background: #dcfce7; color: #15803d; }

        .task-footer {
            margin-top: auto;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-action {
            background: #f8fafc;
            border: 1px solid var(--border);
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.875rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }
        .btn-action:hover { background: #f1f5f9; border-color: #cbd5e1; }
        
        .btn-advance { color: var(--primary); }
        .btn-advance:hover { background: #eef2ff; border-color: #c7d2fe; }
        
        .btn-delete { color: #ef4444; }
        .btn-delete:hover { background: #fef2f2; border-color: #fecaca; }

        @media (max-width: 1024px) {
            body { flex-direction: column; }
            aside { 
                width: 100%; 
                height: auto; 
                position: static; 
                padding: 1.5rem; 
                flex-direction: row; 
                justify-content: space-between; 
                align-items: center;
            }
            .logo { margin-bottom: 0; }
            .nav-section { display: none; } /* Simplified for mobile */
            main { padding: 1.5rem; }
            .form-row { grid-template-columns: 1fr; }
            .btn-submit { grid-column: span 1; }
        }
    </style>
</head>
<body>

    <aside>
        <div class="logo">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
            TaskMaster
        </div>

        <div class="nav-section">
            <div class="nav-title">Smart Views</div>
            <nav>
                <ul>
                    <li>
                        <a href="index.php?filter=all" class="<?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <span>All Tasks</span>
                            <span class="count-badge"><?php echo array_sum($stats); ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="index.php?filter=pending" class="<?php echo $filter === 'pending' ? 'active' : ''; ?>">
                            <span>Pending</span>
                            <span class="count-badge"><?php echo $pending_count; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="index.php?filter=in_progress" class="<?php echo $filter === 'in_progress' ? 'active' : ''; ?>">
                            <span>In Progress</span>
                            <span class="count-badge"><?php echo $in_progress_count; ?></span>
                        </a>
                    </li>
                    <li>
                        <a href="index.php?filter=completed" class="<?php echo $filter === 'completed' ? 'active' : ''; ?>">
                            <span>Completed</span>
                            <span class="count-badge"><?php echo $completed_count; ?></span>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <div class="nav-section">
            <div class="nav-title">Categories</div>
            <nav>
                <ul>
                    <?php foreach ($categories as $cat): ?>
                        <li>
                            <a href="index.php?category=<?php echo urlencode($cat); ?>" class="<?php echo $category_filter === $cat ? 'active' : ''; ?>">
                                <span><?php echo htmlspecialchars($cat); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        </div>
    </aside>

    <main>
        <header class="header-content">
            <h1>Good Day, Explorer</h1>
            <p>You have <?php echo ($pending_count + $in_progress_count); ?> tasks to focus on today.</p>
        </header>

        <section class="add-card">
            <form action="actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-row">
                    <div class="input-group">
                        <label>What are you planning?</label>
                        <input type="text" name="title" placeholder="Buy groceries, Finish report..." required>
                    </div>
                    <div class="input-group">
                        <label>Category</label>
                        <input type="text" name="category" placeholder="Personal" list="cats">
                        <datalist id="cats">
                            <?php foreach($categories as $cat) echo "<option value='".htmlspecialchars($cat)."'>"; ?>
                        </datalist>
                    </div>
                    <div class="input-group">
                        <label>Priority</label>
                        <select name="priority">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    <div class="input-group">
                        <label>Deadline</label>
                        <input type="date" name="due_date">
                    </div>
                    <button type="submit" class="btn-submit">Add to My Schedule</button>
                </div>
            </form>
        </section>

        <div class="task-grid">
            <?php if (empty($tasks)): ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 4rem; background: #fff; border-radius: var(--radius); border: 2px dashed var(--border);">
                    <p style="color: var(--text-muted); font-size: 1.1rem;">No tasks found. Time to relax or start something new!</p>
                </div>
            <?php endif; ?>

            <?php foreach ($tasks as $task): ?>
                <article class="task-card">
                    <div class="task-header">
                        <h3 class="task-title <?php echo $task['status'] === 'completed' ? 'completed' : ''; ?>">
                            <?php echo htmlspecialchars($task['title']); ?>
                        </h3>
                    </div>

                    <div class="tag-group">
                        <span class="tag tag-status tag-status-<?php echo $task['status']; ?>">
                            <?php echo str_replace('_', ' ', ucfirst($task['status'])); ?>
                        </span>
                        <span class="tag tag-priority-<?php echo $task['priority']; ?>">
                            <?php echo $task['priority']; ?>
                        </span>
                        <span class="tag" style="background: #f1f5f9; color: #475569;">
                            <?php echo htmlspecialchars($task['category']); ?>
                        </span>
                    </div>

                    <div style="color: var(--text-muted); font-size: 0.875rem; display: flex; align-items: center; gap: 6px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12.5"/><polyline points="16 2 22 8 12 18 8 18 8 14 18 4"/></svg>
                        <?php echo $task['due_date'] ? date('M d, Y', strtotime($task['due_date'])) : 'No deadline'; ?>
                    </div>

                    <div class="task-footer">
                        <form action="actions.php" method="POST">
                            <input type="hidden" name="action" value="advance_status">
                            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                            <button type="submit" class="btn-action btn-advance">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"></polyline></svg>
                                <?php 
                                    if($task['status'] === 'pending') echo 'Start';
                                    elseif($task['status'] === 'in_progress') echo 'Finish';
                                    else echo 'Restart';
                                ?>
                            </button>
                        </form>

                        <form action="actions.php" method="POST" onsubmit="return confirm('Delete this task?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo $task['id']; ?>">
                            <button type="submit" class="btn-action btn-delete">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>

</body>
</html>
