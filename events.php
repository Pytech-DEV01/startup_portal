<?php
// ==========================================
// 1. DATABASE CONNECTION & SETUP
// ==========================================
$conn = new mysqli("localhost", "root", "", "startup_portal");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Session simulation (Assume Admin with ID 1 is logged in)
$current_user_id = 1;
$message = "";

// ==========================================
// 2. HANDLE CRUD OPERATIONS (POST REQUESTS)
// ==========================================

// CREATE: Add New Event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add') {
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
        $message = "Event successfully created!";
    } else {
        $message = "Error creating event: " . $conn->error;
    }
}

// UPDATE: Edit Existing Event
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit') {
    $event_id = (int)$_POST['event_id'];
    $title = $conn->real_escape_string($_POST['title']);
    $status = $conn->real_escape_string($_POST['status']);
    
    $sql = "UPDATE events SET title='$title', status='$status' WHERE event_id=$event_id";
    if ($conn->query($sql)) {
        $message = "Event updated successfully!";
    }
}

// DELETE: Remove Event
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    $conn->query("DELETE FROM events WHERE event_id = $del_id");
    header("Location: events.php?msg=deleted");
    exit();
}

// ==========================================
// 3. ADVANCED DATA RETRIEVAL (GET REQUESTS)
// ==========================================
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filter_type = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';

// Build dynamic query with JOIN to get the Organizer's Name
$query = "
    SELECT e.*, u.name AS organizer_name 
    FROM events e 
    LEFT JOIN users u ON e.organized_by = u.user_id 
    WHERE 1=1
";

if (!empty($search)) {
    $query .= " AND (e.title LIKE '%$search%' OR e.venue LIKE '%$search%')";
}
if (!empty($filter_type)) {
    $query .= " AND e.event_type = '$filter_type'";
}
if (!empty($filter_status)) {
    $query .= " AND e.status = '$filter_status'";
}

$query .= " ORDER BY e.start_date ASC";
$result = $conn->query($query);

// Analytics for Dashboard Header
$total_events = $conn->query("SELECT COUNT(*) as c FROM events")->fetch_assoc()['c'];
$upcoming_events = $conn->query("SELECT COUNT(*) as c FROM events WHERE status='upcoming'")->fetch_assoc()['c'];
$total_prize = $conn->query("SELECT SUM(prize_pool) as s FROM events")->fetch_assoc()['s'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Advanced Event Manager | Startup Portal</title>
    <style>
        /* ==========================================
           4. ADVANCED UI/UX CSS
           ========================================== */
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

        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; background: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        /* Layout */
        .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
        .sidebar h2 { padding: 0 20px; color: #38BDF8; font-size: 1.5rem; margin-bottom: 30px; }
        .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
        .main-content { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
        
        /* Top Stats Row */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; }
        .stat-card { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .stat-card h4 { margin: 0; color: var(--text-muted); font-size: 0.9rem; text-transform: uppercase; }
        .stat-card h2 { margin: 10px 0 0 0; font-size: 2rem; color: var(--primary); }

        /* Control Panel (Search & Filters) */
        .control-panel { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .filter-form { display: flex; gap: 10px; flex-wrap: wrap; }
        .filter-form input, .filter-form select { padding: 10px; border: 1px solid var(--border); border-radius: 6px; outline: none; }
        .filter-form input:focus { border-color: var(--primary); }
        .btn { padding: 10px 16px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; transition: 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-danger { background: var(--danger); color: white; padding: 6px 10px; font-size: 0.8rem; }
        .btn-warning { background: var(--warning); color: white; padding: 6px 10px; font-size: 0.8rem; }

        /* Advanced Data Table */
        .table-container { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: #F1F5F9; padding: 15px; font-size: 0.85rem; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.5px; }
        td { padding: 15px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:hover { background: #F8FAFC; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; }
        .badge-upcoming { background: #DBEAFE; color: #1E40AF; }
        .badge-ongoing { background: #FEF3C7; color: #92400E; }
        .badge-completed { background: #D1FAE5; color: #065F46; }

        /* Modal Styles */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; justify-content: center; align-items: center; z-index: 1000; opacity: 0; transition: opacity 0.3s; }
        .modal-overlay.active { display: flex; opacity: 1; }
        .modal { background: white; padding: 30px; border-radius: 12px; width: 100%; max-width: 500px; transform: translateY(-20px); transition: transform 0.3s; }
        .modal-overlay.active .modal { transform: translateY(0); }
        .modal h3 { margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
        .modal .form-group { margin-bottom: 15px; }
        .modal label { display: block; margin-bottom: 5px; font-size: 0.9rem; font-weight: 500; }
        .modal input, .modal select, .modal textarea { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 6px; box-sizing: border-box; }
        
        /* Toast Notification */
        .toast { position: fixed; bottom: 20px; right: 20px; background: var(--success); color: white; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); transform: translateY(100px); opacity: 0; transition: 0.4s; }
        .toast.show { transform: translateY(0); opacity: 1; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>🚀 Portal</h2>
        <a href="index.php">🏠 Dashboard</a>
        <a href="startups.php">💡 Startups</a>
        <a href="events.php" class="active">📅 Events Master</a>
        <a href="#">👥 Teams</a>
        <a href="#">🎓 Mentors</a>
        <a href="#">💰 Investors</a>
    </div>

    <div class="main-content">
        
        <div>
            <h1 style="margin-top:0;">Event Management</h1>
            <p style="color:var(--text-muted); margin-top:-10px;">Create, retrieve, update, and manage all portal events.</p>
        </div>

        <div class="stats-row">
            <div class="stat-card"><h4>Total Events</h4><h2><?php echo $total_events ?: 0; ?></h2></div>
            <div class="stat-card"><h4>Upcoming</h4><h2><?php echo $upcoming_events ?: 0; ?></h2></div>
            <div class="stat-card"><h4>Total Prize Pool</h4><h2>₹<?php echo number_format($total_prize ?: 0, 2); ?></h2></div>
        </div>

        <div class="control-panel">
            <form class="filter-form" method="GET" action="">
                <input type="text" name="search" placeholder="Search title or venue..." value="<?php echo htmlspecialchars($search); ?>">
                
                <select name="type">
                    <option value="">All Types</option>
                    <option value="hackathon" <?php if($filter_type=='hackathon') echo 'selected'; ?>>Hackathon</option>
                    <option value="pitch_day" <?php if($filter_type=='pitch_day') echo 'selected'; ?>>Pitch Day</option>
                    <option value="workshop" <?php if($filter_type=='workshop') echo 'selected'; ?>>Workshop</option>
                </select>

                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="upcoming" <?php if($filter_status=='upcoming') echo 'selected'; ?>>Upcoming</option>
                    <option value="ongoing" <?php if($filter_status=='ongoing') echo 'selected'; ?>>Ongoing</option>
                    <option value="completed" <?php if($filter_status=='completed') echo 'selected'; ?>>Completed</option>
                </select>

                <button type="submit" class="btn btn-primary" style="background:#475569;">🔍 Filter</button>
                <a href="events.php" class="btn" style="background:#E2E8F0; color:#475569;">Reset</a>
            </form>

            <button class="btn btn-primary" onclick="openModal('addModal')">+ Create New Event</button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Event Details</th>
                        <th>Date & Time</th>
                        <th>Organizer</th>
                        <th>Stats</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $row['event_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($row['title']); ?></strong><br>
                                    <small style="color:var(--text-muted);"><?php echo htmlspecialchars($row['venue']); ?> • <?php echo ucfirst($row['event_type']); ?></small>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($row['start_date'])); ?><br>
                                    <small style="color:var(--text-muted);"><?php echo date('h:i A', strtotime($row['start_date'])); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($row['organizer_name'] ?: 'Admin'); ?></td>
                                <td>
                                    <small>Pool: ₹<?php echo $row['prize_pool']; ?></small><br>
                                    <small>Max: <?php echo $row['max_participants']; ?> pax</small>
                                </td>
                                <td><span class="badge badge-<?php echo $row['status']; ?>"><?php echo ucfirst($row['status']); ?></span></td>
                                <td>
                                    <button class="btn btn-warning" onclick="openEditModal(<?php echo $row['event_id']; ?>, '<?php echo addslashes($row['title']); ?>', '<?php echo $row['status']; ?>')">Edit</button>
                                    <a href="events.php?delete=<?php echo $row['event_id']; ?>" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this event? This cannot be undone.');">Del</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="text-align:center; padding:40px;">No events found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="addModal">
        <div class="modal">
            <h3>Create New Event</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" required>
                </div>
                
                <div class="form-group" style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Event Type</label>
                        <select name="event_type" required>
                            <option value="hackathon">Hackathon</option>
                            <option value="pitch_day">Pitch Day</option>
                            <option value="workshop">Workshop</option>
                            <option value="networking">Networking</option>
                        </select>
                    </div>
                    <div style="flex:1;">
                        <label>Max Participants</label>
                        <input type="number" name="max_participants" value="100">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" rows="3"></textarea>
                </div>

                <div class="form-group" style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Start Date</label>
                        <input type="datetime-local" name="start_date" required>
                    </div>
                    <div style="flex:1;">
                        <label>End Date</label>
                        <input type="datetime-local" name="end_date" required>
                    </div>
                </div>

                <div class="form-group" style="display:flex; gap:10px;">
                    <div style="flex:1;">
                        <label>Venue / Link</label>
                        <input type="text" name="venue" required>
                    </div>
                    <div style="flex:1;">
                        <label>Prize Pool (₹)</label>
                        <input type="number" name="prize_pool" value="0.00" step="0.01">
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeModal('addModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Event</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <h3>Quick Edit Event</h3>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="event_id" id="edit_event_id">
                
                <div class="form-group">
                    <label>Title</label>
                    <input type="text" name="title" id="edit_title" required>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status" required>
                        <option value="upcoming">Upcoming</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn" style="background:#E2E8F0;" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-warning">Update Event</button>
                </div>
            </form>
        </div>
    </div>

    <?php if(!empty($message) || isset($_GET['msg'])): ?>
    <div class="toast show" id="toast">
        <?php echo !empty($message) ? $message : "Record deleted successfully!"; ?>
    </div>
    <?php endif; ?>

    <script>
        // Modal Control Functions
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        // Populate Edit Modal Data
        function openEditModal(id, title, status) {
            document.getElementById('edit_event_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_status').value = status;
            openModal('editModal');
        }

        // Close modal if clicking outside the box
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }

        // Auto-hide Toast Notification after 3 seconds
        setTimeout(() => {
            const toast = document.getElementById('toast');
            if(toast) {
                toast.classList.remove('show');
            }
        }, 3000);
    </script>
</body>
</html>