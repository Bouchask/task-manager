<?php
$db_file = __DIR__ . '/database.sqlite';

try {
    $pdo = new PDO("sqlite:" . $db_file);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Migration logic for the 'in_progress' status
    $sql_check = "PRAGMA table_info(tasks)";
    $columns_info = $pdo->query($sql_check)->fetchAll();
    
    // Check if we need to migrate the 'status' CHECK constraint
    // In SQLite, we can't easily read the CHECK constraint, but we can recreate the table to be safe
    // if we want to ensure it supports 'in_progress'. 
    // For simplicity, we'll redefine the table if 'in_progress' fails an insert, but better to just do it once.
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        priority TEXT DEFAULT 'Medium',
        category TEXT DEFAULT 'General',
        due_date DATE,
        status TEXT CHECK(status IN ('pending', 'in_progress', 'completed')) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Ensure all columns exist (from previous versions)
    $columns = array_column($columns_info, 'name');
    if (!in_array('priority', $columns) && !empty($columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN priority TEXT DEFAULT 'Medium'");
    }
    if (!in_array('category', $columns) && !empty($columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN category TEXT DEFAULT 'General'");
    }
    if (!in_array('due_date', $columns) && !empty($columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN due_date DATE");
    }
    if (!in_array('description', $columns) && !empty($columns)) {
        $pdo->exec("ALTER TABLE tasks ADD COLUMN description TEXT");
    }

    // Special check for 'status' constraint: 
    // Since SQLite doesn't support ALTER TABLE for constraints, we check if we can insert 'in_progress'.
    // If it fails, we know we need to recreate the table.
    try {
        $pdo->exec("SAVEPOINT status_check;");
        $pdo->exec("INSERT INTO tasks (title, status) VALUES ('check', 'in_progress');");
        $pdo->exec("ROLLBACK TO status_check;");
        $pdo->exec("RELEASE status_check;");
    } catch (\PDOException $e) {
        $pdo->exec("ROLLBACK TO status_check;");
        $pdo->exec("RELEASE status_check;");
        
        // Recreate table migration
        $pdo->exec("CREATE TABLE tasks_new (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            description TEXT,
            priority TEXT DEFAULT 'Medium',
            category TEXT DEFAULT 'General',
            due_date DATE,
            status TEXT CHECK(status IN ('pending', 'in_progress', 'completed')) DEFAULT 'pending',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $pdo->exec("INSERT INTO tasks_new (id, title, description, priority, category, due_date, status, created_at) 
                    SELECT id, title, description, priority, category, due_date, status, created_at FROM tasks");
        $pdo->exec("DROP TABLE tasks");
        $pdo->exec("ALTER TABLE tasks_new RENAME TO tasks");
    }

} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
