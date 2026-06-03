<?php
// 1. Database Connection
$conn = new mysqli("localhost", "root", "", "startup_portal");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$current_user_id = 1; // Assuming Admin is logged in
$message = "";

// Ensure a default domain exists to prevent Foreign Key errors
$conn->query("INSERT IGNORE INTO domains (domain_id, domain_name) VALUES (1, 'General Tech')");

// 2. Handle CRUD Operations
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action'])) {
    
    // ADD NEW STARTUP
    if ($_POST['action'] == 'add') {
        $title = $conn->real_escape_string($_POST['title']);
        $desc = $conn->real_escape_string($_POST['description']);
        $stage = $conn->real_escape_string($_POST['stage']);
        $website = $conn->real_escape_string($_POST['website_url']);
        $equity = (float)$_POST['equity_offered'];
        $funding = (float)$_POST['funding_needed'];

        $sql = "INSERT INTO startups (title, domain_id, description, creator_id, stage, website_url, equity_offered, funding_needed) 
                VALUES ('$title', 1, '$desc', $current_user_id, '$stage', '$website', $equity, $funding)";
        
        if ($conn->query($sql)) {
            $message = "Startup successfully registered!";
        } else {
            $message = "Error: " . $conn->error;
        }
    }
    
    // EDIT STARTUP STAGE
    if ($_POST['action'] == 'edit') {
        $startup_id = (int)$_POST['startup_id'];
        $stage = $conn->real_escape_string($_POST['stage']);
        
        $sql = "UPDATE startups SET stage='$stage' WHERE startup_id=$startup_id";
        if ($conn->query($sql)) {
            $message = "Startup stage updated successfully!";
        }
    }
}

// DELETE STARTUP
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM startups WHERE startup_id = $del_id");
    header("Location: startups.php?msg=deleted");
    exit();
}

// 3. Advanced Retrieval & Filters
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_stage = isset($_GET['stage']) ? $conn->real_escape_string($_GET['stage']) : '';

$query = "SELECT s.*, u.name AS founder_name FROM startups s LEFT JOIN users u ON s.creator_id = u.user_id WHERE 1=1";

if (!empty($search)) {
    $query .= " AND s.title LIKE '%$search%'";
}
if (!empty($filter_stage)) {
    $query .= " AND s.stage = '$filter_stage'";
}
$query .= " ORDER BY s.created_at DESC";
$result = $conn->query($query);

// Dashboard Analytics
$total_startups = $conn->query("SELECT COUNT(*) as c FROM startups")->fetch_assoc()['c'];
$total_funding = $conn->query("SELECT SUM(funding_needed) as s FROM startups")->fetch_assoc()['s'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startup Manager | Portal</title>
    <style>
        /* Shared Master CSS */
        :root { --primary: #2563EB; --dark: #0F172A; --bg: #F8FAFC; --card: #FFFFFF; --border: #E2E8F0; --text-main: #1E293B; --text-muted: #64748B; --success: #10B981; --danger: #EF4444; --warning: #F59E0B; }
        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; background: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
        .sidebar h2 { padding: 0 20px; color: #38BDF8; font-size: 1.5rem; margin-bottom: 30px; }
        .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
        
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); }
        .stat-card h4 { margin: 0; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; }
        .stat-card h2 { margin: 10px 0 0 0; font-size: 2rem; color: var(--primary); }

        .control-panel { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; gap: 10px; }
        .filter-form input, .filter-form select { padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
        
        .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-warning { background: var(--warning); color: white; padding: 6px 10px; font-size: 0.8rem; }
        .btn-danger { background: var(--danger); color: white; padding: 6px 10px; font-size: 0.8rem; }

        .table-container { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F1F5F9; padding: 15px; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); }
        td { padding: 15px; border-bottom: 1px solid var(--border); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #DBEAFE; color: #1E40AF; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; font-size: 0.9rem; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--success); color: white; padding: 15px 25px; border-radius: 8px; opacity: 0; transform: translateY(20px); transition: 0.4s; }
        .toast.show { opacity: 1; transform: translateY(0); }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>🚀 Portal</h2>
        <a href="index.php">🏠 Dashboard</a>
        <a href="startups.php" class="active">💡 Startups</a>
        <a href="events.php">📅 Events Master</a>
        <a href="#">👥 Teams</a>
        <a href="#">🎓 Mentors</a>
        <a href="#">💰 Investors</a>
    </div>

    <div class="main-content">
        <div>
            <h1 style="margin:0;">Startup Directory</h1>
            <p style="color:var(--text-muted); margin-top:5px;">Manage all platform startup concepts and funding stages.</p>
        </div>

        <div class="stats-row">
            <div class="stat-card"><h4>Total Startups</h4><h2><?php echo $total_startups ?: 0; ?></h2></div>
            <div class="stat-card"><h4>Total Funding Requested</h4><h2>₹<?php echo number_format($total_funding ?: 0, 2); ?></h2></div>
        </div>

        <div class="control-panel">
            <form class="filter-form" method="GET" action="">
                <input type="text" name="search" placeholder="Search startup name..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="stage">
                    <option value="">All Stages</option>
                    <option value="idea" <?php if($filter_stage=='idea') echo 'selected'; ?>>Idea</option>
                    <option value="prototype" <?php if($filter_stage=='prototype') echo 'selected'; ?>>Prototype</option>
                    <option value="mvp" <?php if($filter_stage=='mvp') echo 'selected'; ?>>MVP</option>
                    <option value="launched" <?php if($filter_stage=='launched') echo 'selected'; ?>>Launched</option>
                </select>
                <button type="submit" class="btn btn-primary" style="background:#475569;">🔍 Filter</button>
            </form>
            <button class="btn btn-primary" onclick="openModal('addModal')">+ Register Startup</button>
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
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($row['website_url'] ?: 'No website yet'); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['founder_name'] ?: 'Admin'); ?></td>
                                <td><span class="badge"><?php echo strtoupper($row['stage']); ?></span></td>
                                <td>₹<?php echo number_format($row['funding_needed'], 2); ?><br><small><?php echo $row['equity_offered']; ?>% Equity</small></td>
                                <td>
                                    <button class="btn btn-warning" onclick="openEditModal(<?php echo $row['startup_id']; ?>, '<?php echo $row['stage']; ?>')">Edit</button>
                                    <a href="startups.php?delete=<?php echo $row['startup_id']; ?>" class="btn btn-danger" onclick="return confirm('Delete this startup?');">Del</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px;">No startups found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <h3>Register New Startup</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Startup Title</label>
                    <input type="text" name="title" placeholder="e.g., Redverse Studio" required>
                </div>
                
                <div class="form-group">
                    <label>Current Stage</label>
                    <select name="stage" required>
                        <option value="idea">Idea / Concept</option>
                        <option value="prototype">Prototype Built</option>
                        <option value="mvp">MVP Ready</option>
                        <option value="launched">Fully Launched</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3" placeholder="Describe the startup's mission..." required></textarea>
                </div>

                <div class="form-group" style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Funding Needed (₹)</label>
                        <input type="number" name="funding_needed" value="0.00" step="1000">
                    </div>
                    <div style="flex:1;">
                        <label>Equity Offered (%)</label>
                        <input type="number" name="equity_offered" value="0.00" step="0.1" max="100">
                    </div>
                </div>

                <div class="form-group">
                    <label>Website URL (Optional)</label>
                    <input type="text" name="website_url" placeholder="https://...">
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn" style="background:#E2E8F0; color:black;" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Startup</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3>Update Startup Stage</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="startup_id" id="edit_startup_id">
                
                <div class="form-group">
                    <label>New Stage</label>
                    <select name="stage" id="edit_stage" required>
                        <option value="idea">Idea / Concept</option>
                        <option value="prototype">Prototype Built</option>
                        <option value="mvp">MVP Ready</option>
                        <option value="launched">Fully Launched</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn" style="background:#E2E8F0; color:black;" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Stage</button>
                </div>
            </form>
        </div>
    </div>

    <?php if(!empty($message) || isset($_GET['msg'])): ?>
    <div class="toast show" id="toast">
        <?php echo !empty($message) ? $message : "Startup removed successfully!"; ?>
    </div>
    <?php endif; ?>

    <script>
        function openModal(id) { document.getElementById(id).classList.add('active'); }
        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
        
        function openEditModal(id, stage) {
            document.getElementById('edit_startup_id').value = id;
            document.getElementById('edit_stage').value = stage;
            openModal('editModal');
        }

        setTimeout(() => {
            let toast = document.getElementById('toast');
            if(toast) toast.classList.remove('show');
        }, 3000);
    </script>
</body>
</html>