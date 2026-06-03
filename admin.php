
<?php
// ==========================================
// UNIFIED STARTUP PORTAL ADMIN DASHBOARD
// Combines: index.php, startups.php, events.php
// ==========================================

// 1. DATABASE CONNECTION
$conn = new mysqli("localhost", "root", "", "startup_portal");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$current_user_id = 1; // Simulating Admin
$message = "";

// Ensure default domain exists
$conn->query("INSERT IGNORE INTO domains (domain_id, domain_name) VALUES (1, 'General Tech')");

// Get current page from URL
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// ==========================================
// DASHBOARD OPERATIONS
// ==========================================
if ($page == 'dashboard') {
    $total_startups = $conn->query("SELECT COUNT(*) as c FROM startups")->fetch_assoc()['c'] ?: 0;
    $total_events = $conn->query("SELECT COUNT(*) as c FROM events")->fetch_assoc()['c'] ?: 0;
    $total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'] ?: 0;
    $recent_startups = $conn->query("SELECT title, stage, created_at FROM startups ORDER BY created_at DESC LIMIT 4");
    $upcoming_events = $conn->query("SELECT title, start_date, venue FROM events WHERE status='upcoming' ORDER BY start_date ASC LIMIT 4");
}

// ==========================================
// STARTUPS OPERATIONS
// ==========================================
if ($page == 'startups') {
    // ADD NEW STARTUP
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_startup') {
        $title = $conn->real_escape_string($_POST['title']);
        $desc = $conn->real_escape_string($_POST['description']);
        $stage = $conn->real_escape_string($_POST['stage']);
        $website = $conn->real_escape_string($_POST['website_url']);
        $equity = (float)$_POST['equity_offered'];
        $funding = (float)$_POST['funding_needed'];

        $sql = "INSERT INTO startups (title, domain_id, description, creator_id, stage, website_url, equity_offered, funding_needed) 
                VALUES ('$title', 1, '$desc', $current_user_id, '$stage', '$website', $equity, $funding)";
        
        if ($conn->query($sql)) {
            $message = "✅ Startup successfully registered!";
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    }
    
    // EDIT STARTUP
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_startup') {
        $startup_id = (int)$_POST['startup_id'];
        $stage = $conn->real_escape_string($_POST['stage']);
        
        $sql = "UPDATE startups SET stage='$stage' WHERE startup_id=$startup_id";
        if ($conn->query($sql)) {
            $message = "✅ Startup stage updated successfully!";
        }
    }
    
    // DELETE STARTUP
    if (isset($_GET['delete_startup'])) {
        $del_id = (int)$_GET['delete_startup'];
        $conn->query("DELETE FROM startups WHERE startup_id = $del_id");
        $message = "✅ Startup deleted successfully!";
    }
    
    // FETCH STARTUPS WITH FILTERS
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $filter_stage = isset($_GET['stage']) ? $conn->real_escape_string($_GET['stage']) : '';

    $query = "SELECT s.*, u.name AS founder_name FROM startups s LEFT JOIN users u ON s.creator_id = u.user_id WHERE 1=1";
    if (!empty($search)) $query .= " AND s.title LIKE '%$search%'";
    if (!empty($filter_stage)) $query .= " AND s.stage = '$filter_stage'";
    $query .= " ORDER BY s.created_at DESC";
    $startups_result = $conn->query($query);

    $total_startups = $conn->query("SELECT COUNT(*) as c FROM startups")->fetch_assoc()['c'] ?: 0;
    $total_funding = $conn->query("SELECT SUM(funding_needed) as s FROM startups")->fetch_assoc()['s'] ?: 0;
}

// ==========================================
// EVENTS OPERATIONS
// ==========================================
if ($page == 'events') {
    // ADD NEW EVENT
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_event') {
        $title = $conn->real_escape_string($_POST['title']);
        $type = $conn->real_escape_string($_POST['event_type']);
        $desc = $conn->real_escape_string($_POST['description']);
        $venue = $conn->real_escape_string($_POST['venue']);
        $start = $conn->real_escape_string($_POST['start_date']);
        $end = $conn->real_escape_string($_POST['end_date']);
        $max_p = (int)$_POST['max_participants'];
        $prize = (float)$_POST['prize_pool'];

        $sql = "INSERT INTO events (title, event_type, description, organized_by, venue, start_date, end_date, max_participants, prize_pool) 
                VALUES ('$title', '$type', '$desc', $current_user_id, '$venue', '$start', '$end', $max_p, $prize)";
        
        if ($conn->query($sql)) {
            $message = "✅ Event successfully created!";
        } else {
            $message = "❌ Error: " . $conn->error;
        }
    }
    
    // EDIT EVENT
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_event') {
        $event_id = (int)$_POST['event_id'];
        $title = $conn->real_escape_string($_POST['title']);
        $status = $conn->real_escape_string($_POST['status']);
        
        $sql = "UPDATE events SET title='$title', status='$status' WHERE event_id=$event_id";
        if ($conn->query($sql)) {
            $message = "✅ Event updated successfully!";
        }
    }
    
    // DELETE EVENT
    if (isset($_GET['delete_event'])) {
        $del_id = (int)$_GET['delete_event'];
        $conn->query("DELETE FROM events WHERE event_id = $del_id");
        $message = "✅ Event deleted successfully!";
    }
    
    // FETCH EVENTS WITH FILTERS
    $search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
    $filter_type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
    $filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

    $query = "SELECT e.*, u.name AS organizer_name FROM events e LEFT JOIN users u ON e.organized_by = u.user_id WHERE 1=1";
    if (!empty($search)) $query .= " AND (e.title LIKE '%$search%' OR e.venue LIKE '%$search%')";
    if (!empty($filter_type)) $query .= " AND e.event_type = '$filter_type'";
    if (!empty($filter_status)) $query .= " AND e.status = '$filter_status'";
    $query .= " ORDER BY e.start_date ASC";
    $events_result = $conn->query($query);

    $total_events = $conn->query("SELECT COUNT(*) as c FROM events")->fetch_assoc()['c'] ?: 0;
    $upcoming_events = $conn->query("SELECT COUNT(*) as c FROM events WHERE status='upcoming'")->fetch_assoc()['c'] ?: 0;
    $total_prize = $conn->query("SELECT SUM(prize_pool) as s FROM events")->fetch_assoc()['s'] ?: 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unified Admin Dashboard | Startup Portal</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        :root {
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --dark: #0F172A;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text-main: #1E293B;
            --text-muted: #64748B;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text-main);
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        .sidebar {
            width: 280px;
            background: var(--dark);
            color: white;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.1);
            overflow-y: auto;
        }

        .sidebar h2 {
            padding: 0 20px;
            color: #38BDF8;
            font-size: 1.3rem;
            margin-bottom: 30px;
            margin-top: 10px;
        }

        .sidebar a {
            color: #CBD5E1;
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: 0.2s;
            border-left: 3px solid transparent;
            margin: 5px 0;
        }

        .sidebar a:hover, .sidebar a.active {
            background: #1E293B;
            color: white;
            border-left: 3px solid var(--primary);
        }

        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .header {
            margin-bottom: 10px;
        }

        .header h1 {
            margin: 0 0 5px 0;
            font-size: 2rem;
        }

        .header p {
            color: var(--text-muted);
            margin: 0;
        }

        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .stat-card h4 {
            margin: 0;
            color: var(--text-muted);
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .stat-card h2 {
            margin: 10px 0 0 0;
            font-size: 2rem;
            color: var(--primary);
        }

        .control-panel {
            background: var(--card);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-form input, .filter-form select {
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            outline: none;
            font-size: 0.9rem;
        }

        .filter-form input:focus, .filter-form select:focus {
            border-color: var(--primary);
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            text-decoration: none;
            display: inline-block;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            padding: 6px 10px;
            font-size: 0.8rem;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
            padding: 6px 10px;
            font-size: 0.8rem;
        }

        .table-container {
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
            overflow-x: auto;
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }

        th {
            background: #F1F5F9;
            padding: 15px;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }

        tr:hover {
            background: #F8FAFC;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: bold;
            display: inline-block;
        }

        .badge-idea { background: #DBEAFE; color: #1E40AF; }
        .badge-prototype { background: #FEF3C7; color: #92400E; }
        .badge-mvp { background: #D1FAE5; color: #065F46; }
        .badge-launched { background: #DCFCE7; color: #166534; }
        .badge-hackathon { background: #DBEAFE; color: #1E40AF; }
        .badge-pitch_day { background: #FEF3C7; color: #92400E; }
        .badge-workshop { background: #F3E8FF; color: #6B21A8; }
        .badge-upcoming { background: #DBEAFE; color: #1E40AF; }
        .badge-ongoing { background: #FEF3C7; color: #92400E; }
        .badge-completed { background: #D1FAE5; color: #065F46; }

        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .modal-overlay.active {
            display: flex;
            opacity: 1;
        }

        .modal {
            background: white;
            padding: 30px;
            border-radius: 12px;
            width: 100%;
            max-width: 500px;
            transform: translateY(-20px);
            transition: transform 0.3s;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-overlay.active .modal {
            transform: translateY(0);
        }

        .modal h3 {
            margin-top: 0;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: 6px;
            box-sizing: border-box;
            font-family: inherit;
            font-size: 0.9rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-buttons {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn-close {
            background: #E2E8F0;
            color: var(--text-main);
        }

        .btn-close:hover {
            background: #CBD5E1;
        }

        .feed-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .feed-panel {
            background: var(--card);
            border-radius: 10px;
            border: 1px solid var(--border);
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .feed-panel h3 {
            margin-top: 0;
            border-bottom: 1px solid var(--border);
            padding-bottom: 15px;
            color: var(--text-main);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feed-panel a {
            font-size: 0.85rem;
            color: var(--primary);
            text-decoration: none;
            font-weight: normal;
        }

        .feed-panel a:hover {
            text-decoration: underline;
        }

        .feed-item {
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .feed-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .feed-title {
            font-weight: 600;
            margin-bottom: 3px;
            display: block;
        }

        .feed-sub {
            font-size: 0.85rem;
            color: var(--text-muted);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-muted);
        }

        .empty-state p {
            font-size: 1.1rem;
            margin: 10px 0;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>🚀 Portal Admin</h2>
        <a href="admin.php?page=dashboard" <?php echo $page == 'dashboard' ? 'class="active"' : ''; ?>>📊 Dashboard</a>
        <a href="admin.php?page=startups" <?php echo $page == 'startups' ? 'class="active"' : ''; ?>>💡 Startups</a>
        <a href="admin.php?page=events" <?php echo $page == 'events' ? 'class="active"' : ''; ?>>📅 Events</a>
        <hr style="margin: 20px 0; border: none; border-top: 1px solid rgba(255,255,255,0.1);">
        <a href="http://localhost:8000" target="_blank">🔗 API Server (Node.js)</a>
        <a href="#">👥 Teams</a>
        <a href="#">🎓 Mentors</a>
        <a href="#">💰 Investors</a>
    </div>

    <div class="main-content">
        
        <?php if (!empty($message)): ?>
            <div class="message" style="background: <?php echo strpos($message, '✅') !== false ? 'var(--success)' : 'var(--danger)'; ?>20; border-left: 4px solid <?php echo strpos($message, '✅') !== false ? 'var(--success)' : 'var(--danger)'; ?>; color: <?php echo strpos($message, '✅') !== false ? 'var(--success)' : 'var(--danger)'; ?>;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- DASHBOARD PAGE -->
        <?php if ($page == 'dashboard'): ?>
            <div class="header">
                <h1>Platform Overview</h1>
                <p>Welcome back to the Startup Collaboration Portal Admin Dashboard.</p>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <h4>Total Startups</h4>
                    <h2><?php echo $total_startups; ?></h2>
                </div>
                <div class="stat-card">
                    <h4>Total Events</h4>
                    <h2><?php echo $total_events; ?></h2>
                </div>
                <div class="stat-card">
                    <h4>Registered Users</h4>
                    <h2><?php echo $total_users; ?></h2>
                </div>
            </div>

            <div class="feed-grid">
                <div class="feed-panel">
                    <h3>Latest Startups <a href="admin.php?page=startups">View All →</a></h3>
                    <?php if ($recent_startups->num_rows > 0): ?>
                        <?php while($row = $recent_startups->fetch_assoc()): ?>
                            <div class="feed-item">
                                <div>
                                    <span class="feed-title"><?php echo htmlspecialchars($row['title']); ?></span>
                                    <span class="feed-sub">Added: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                                </div>
                                <span class="badge badge-<?php echo $row['stage']; ?>"><?php echo strtoupper($row['stage']); ?></span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="feed-sub" style="text-align:center; padding: 20px; color: var(--text-muted);">No startups registered yet.</p>
                    <?php endif; ?>
                </div>

                <div class="feed-panel">
                    <h3>Upcoming Events <a href="admin.php?page=events">View All →</a></h3>
                    <?php if ($upcoming_events->num_rows > 0): ?>
                        <?php while($row = $upcoming_events->fetch_assoc()): ?>
                            <div class="feed-item">
                                <div>
                                    <span class="feed-title"><?php echo htmlspecialchars($row['title']); ?></span>
                                    <span class="feed-sub">📍 <?php echo htmlspecialchars($row['venue']); ?></span>
                                </div>
                                <span class="feed-sub" style="font-weight:bold; color:var(--primary);">
                                    <?php echo date('M d', strtotime($row['start_date'])); ?>
                                </span>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="feed-sub" style="text-align:center; padding: 20px; color: var(--text-muted);">No upcoming events scheduled.</p>
                    <?php endif; ?>
                </div>
            </div>

        <!-- STARTUPS PAGE -->
        <?php elseif ($page == 'startups'): ?>
            <div class="header">
                <h1>Startup Directory</h1>
                <p>Manage all platform startup concepts and funding stages.</p>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <h4>Total Startups</h4>
                    <h2><?php echo $total_startups; ?></h2>
                </div>
                <div class="stat-card">
                    <h4>Total Funding Requested</h4>
                    <h2>₹<?php echo number_format($total_funding, 2); ?></h2>
                </div>
            </div>

            <div class="control-panel">
                <form class="filter-form" method="GET" action="">
                    <input type="hidden" name="page" value="startups">
                    <input type="text" name="search" placeholder="Search startup name..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="stage">
                        <option value="">All Stages</option>
                        <option value="idea" <?php if($filter_stage=='idea') echo 'selected'; ?>>Idea</option>
                        <option value="prototype" <?php if($filter_stage=='prototype') echo 'selected'; ?>>Prototype</option>
                        <option value="mvp" <?php if($filter_stage=='mvp') echo 'selected'; ?>>MVP</option>
                        <option value="launched" <?php if($filter_stage=='launched') echo 'selected'; ?>>Launched</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                </form>
                <button class="btn btn-primary" onclick="openStartupModal('add')">+ Register Startup</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Startup Details</th>
                            <th>Founder</th>
                            <th>Stage</th>
                            <th>Funding Needed</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($startups_result->num_rows > 0): ?>
                            <?php while($row = $startups_result->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                        <small style="color:var(--text-muted);"><?php echo htmlspecialchars($row['website_url'] ?: 'No website'); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($row['founder_name'] ?: 'Admin'); ?></td>
                                    <td><span class="badge badge-<?php echo $row['stage']; ?>"><?php echo strtoupper($row['stage']); ?></span></td>
                                    <td>₹<?php echo number_format($row['funding_needed'], 2); ?><br><small><?php echo $row['equity_offered']; ?>% Equity</small></td>
                                    <td>
                                        <button class="btn btn-warning" onclick="openStartupModal('edit', <?php echo $row['startup_id']; ?>, '<?php echo $row['stage']; ?>')">Edit</button>
                                        <a href="admin.php?page=startups&delete_startup=<?php echo $row['startup_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this startup?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5"><div class="empty-state"><p>No startups found.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <!-- EVENTS PAGE -->
        <?php elseif ($page == 'events'): ?>
            <div class="header">
                <h1>Event Management</h1>
                <p>Create, retrieve, update, and manage all portal events.</p>
            </div>

            <div class="stats-row">
                <div class="stat-card">
                    <h4>Total Events</h4>
                    <h2><?php echo $total_events; ?></h2>
                </div>
                <div class="stat-card">
                    <h4>Upcoming Events</h4>
                    <h2><?php echo $upcoming_events; ?></h2>
                </div>
                <div class="stat-card">
                    <h4>Total Prize Pool</h4>
                    <h2>₹<?php echo number_format($total_prize, 2); ?></h2>
                </div>
            </div>

            <div class="control-panel">
                <form class="filter-form" method="GET" action="">
                    <input type="hidden" name="page" value="events">
                    <input type="text" name="search" placeholder="Search title or venue..." value="<?php echo htmlspecialchars($search); ?>">
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="hackathon" <?php if($filter_type=='hackathon') echo 'selected'; ?>>Hackathon</option>
                        <option value="pitch_day" <?php if($filter_type=='pitch_day') echo 'selected'; ?>>Pitch Day</option>
                        <option value="workshop" <?php if($filter_type=='workshop') echo 'selected'; ?>>Workshop</option>
                    </select>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="upcoming" <?php if($filter_status=='upcoming') echo 'selected'; ?>>Upcoming</option>
                        <option value="ongoing" <?php if($filter_status=='ongoing') echo 'selected'; ?>>Ongoing</option>
                        <option value="completed" <?php if($filter_status=='completed') echo 'selected'; ?>>Completed</option>
                    </select>
                    <button type="submit" class="btn btn-primary">🔍 Filter</button>
                </form>
                <button class="btn btn-primary" onclick="openEventModal('add')">+ Create Event</button>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Event Name</th>
                            <th>Type</th>
                            <th>Organizer</th>
                            <th>Venue</th>
                            <th>Dates</th>
                            <th>Status</th>
                            <th>Prize Pool</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($events_result->num_rows > 0): ?>
                            <?php while($row = $events_result->fetch_assoc()): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($row['title']); ?></strong></td>
                                    <td><span class="badge badge-<?php echo $row['event_type']; ?>"><?php echo strtoupper($row['event_type']); ?></span></td>
                                    <td><?php echo htmlspecialchars($row['organizer_name'] ?: 'Admin'); ?></td>
                                    <td><?php echo htmlspecialchars($row['venue']); ?></td>
                                    <td><small><?php echo date('M d', strtotime($row['start_date'])); ?> - <?php echo date('M d', strtotime($row['end_date'])); ?></small></td>
                                    <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo strtoupper($row['status']); ?></span></td>
                                    <td>₹<?php echo number_format($row['prize_pool'], 2); ?></td>
                                    <td>
                                        <button class="btn btn-warning" onclick="openEventModal('edit', <?php echo $row['event_id']; ?>, '<?php echo $row['status']; ?>')">Edit</button>
                                        <a href="admin.php?page=events&delete_event=<?php echo $row['event_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this event?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8"><div class="empty-state"><p>No events found.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php endif; ?>
    </div>

    <!-- STARTUPS MODALS -->
    <div class="modal-overlay" id="startupModal">
        <div class="modal">
            <h3 id="startupModalTitle">Add New Startup</h3>
            <form method="POST" action="">
                <input type="hidden" id="startupAction" name="action" value="add_startup">
                <input type="hidden" id="startupId" name="startup_id" value="">
                
                <div class="form-group" id="titleGroup">
                    <label>Startup Name *</label>
                    <input type="text" id="startupTitle" name="title" required>
                </div>
                
                <div class="form-group" id="descGroup">
                    <label>Description *</label>
                    <textarea id="startupDesc" name="description" required></textarea>
                </div>
                
                <div class="form-group" id="stageGroup">
                    <label>Stage *</label>
                    <select id="startupStage" name="stage" required>
                        <option value="">Select Stage</option>
                        <option value="idea">Idea</option>
                        <option value="prototype">Prototype</option>
                        <option value="mvp">MVP</option>
                        <option value="launched">Launched</option>
                    </select>
                </div>
                
                <div class="form-group" id="websiteGroup">
                    <label>Website URL</label>
                    <input type="url" id="startupWebsite" name="website_url">
                </div>
                
                <div class="form-group" id="equityGroup">
                    <label>Equity Offered (%) *</label>
                    <input type="number" id="startupEquity" name="equity_offered" step="0.01" required>
                </div>
                
                <div class="form-group" id="fundingGroup">
                    <label>Funding Needed (₹) *</label>
                    <input type="number" id="startupFunding" name="funding_needed" step="0.01" required>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-close" onclick="closeModal('startupModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Startup</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EVENTS MODALS -->
    <div class="modal-overlay" id="eventModal">
        <div class="modal">
            <h3 id="eventModalTitle">Create New Event</h3>
            <form method="POST" action="">
                <input type="hidden" id="eventAction" name="action" value="add_event">
                <input type="hidden" id="eventId" name="event_id" value="">
                
                <div class="form-group">
                    <label>Event Title *</label>
                    <input type="text" id="eventTitle" name="title" required>
                </div>
                
                <div class="form-group">
                    <label>Event Type *</label>
                    <select id="eventType" name="event_type" required>
                        <option value="">Select Type</option>
                        <option value="hackathon">Hackathon</option>
                        <option value="pitch_day">Pitch Day</option>
                        <option value="workshop">Workshop</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea id="eventDesc" name="description" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Venue *</label>
                    <input type="text" id="eventVenue" name="venue" required>
                </div>
                
                <div class="form-group">
                    <label>Start Date *</label>
                    <input type="datetime-local" id="eventStart" name="start_date" required>
                </div>
                
                <div class="form-group">
                    <label>End Date *</label>
                    <input type="datetime-local" id="eventEnd" name="end_date" required>
                </div>
                
                <div class="form-group">
                    <label>Max Participants *</label>
                    <input type="number" id="eventParticipants" name="max_participants" required>
                </div>
                
                <div class="form-group">
                    <label>Prize Pool (₹) *</label>
                    <input type="number" id="eventPrize" name="prize_pool" step="0.01" required>
                </div>
                
                <div class="form-group" id="eventStatusGroup" style="display:none;">
                    <label>Status</label>
                    <select id="eventStatus" name="status">
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn btn-close" onclick="closeModal('eventModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="eventSubmitBtn">Create Event</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openStartupModal(mode, id, stage) {
            const modal = document.getElementById('startupModal');
            const titleEl = document.getElementById('startupModalTitle');
            const actionEl = document.getElementById('startupAction');
            const idEl = document.getElementById('startupId');
            const titleGroupEl = document.getElementById('titleGroup');
            const descGroupEl = document.getElementById('descGroup');
            const websiteGroupEl = document.getElementById('websiteGroup');
            const equityGroupEl = document.getElementById('equityGroup');
            const fundingGroupEl = document.getElementById('fundingGroup');

            if (mode === 'add') {
                titleEl.textContent = 'Register New Startup';
                actionEl.value = 'add_startup';
                titleGroupEl.style.display = 'block';
                descGroupEl.style.display = 'block';
                websiteGroupEl.style.display = 'block';
                equityGroupEl.style.display = 'block';
                fundingGroupEl.style.display = 'block';
                document.getElementById('startupTitle').value = '';
                document.getElementById('startupDesc').value = '';
                document.getElementById('startupStage').value = '';
                document.getElementById('startupWebsite').value = '';
                document.getElementById('startupEquity').value = '';
                document.getElementById('startupFunding').value = '';
                idEl.value = '';
            } else {
                titleEl.textContent = 'Update Startup Stage';
                actionEl.value = 'edit_startup';
                titleGroupEl.style.display = 'none';
                descGroupEl.style.display = 'none';
                websiteGroupEl.style.display = 'none';
                equityGroupEl.style.display = 'none';
                fundingGroupEl.style.display = 'none';
                document.getElementById('startupStage').value = stage;
                idEl.value = id;
            }

            modal.classList.add('active');
        }

        function openEventModal(mode, id, status) {
            const modal = document.getElementById('eventModal');
            const titleEl = document.getElementById('eventModalTitle');
            const actionEl = document.getElementById('eventAction');
            const idEl = document.getElementById('eventId');
            const statusGroupEl = document.getElementById('eventStatusGroup');
            const submitBtn = document.getElementById('eventSubmitBtn');

            if (mode === 'add') {
                titleEl.textContent = 'Create New Event';
                actionEl.value = 'add_event';
                statusGroupEl.style.display = 'none';
                submitBtn.textContent = 'Create Event';
                document.getElementById('eventTitle').value = '';
                document.getElementById('eventType').value = '';
                document.getElementById('eventDesc').value = '';
                document.getElementById('eventVenue').value = '';
                document.getElementById('eventStart').value = '';
                document.getElementById('eventEnd').value = '';
                document.getElementById('eventParticipants').value = '';
                document.getElementById('eventPrize').value = '';
                idEl.value = '';
            } else {
                titleEl.textContent = 'Update Event';
                actionEl.value = 'edit_event';
                statusGroupEl.style.display = 'block';
                submitBtn.textContent = 'Update Event';
                document.getElementById('eventStatus').value = status;
                idEl.value = id;
            }

            modal.classList.add('active');
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('active');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });
    </script>

</body>
</html>
