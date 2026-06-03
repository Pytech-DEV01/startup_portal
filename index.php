<?php
// 1. Database Connection
$conn = new mysqli("localhost", "root", "", "startup_portal");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// 2. Fetch Live Statistics
$total_startups = $conn->query("SELECT COUNT(*) as c FROM startups")->fetch_assoc()['c'];
$total_events = $conn->query("SELECT COUNT(*) as c FROM events")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users")->fetch_assoc()['c'];

// 3. Fetch Recent Activity for Dashboard Feeds
$recent_startups = $conn->query("SELECT title, stage, created_at FROM startups ORDER BY created_at DESC LIMIT 4");
$upcoming_events = $conn->query("SELECT title, start_date, venue FROM events WHERE status='upcoming' ORDER BY start_date ASC LIMIT 4");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Startup Portal</title>
    <style>
        /* Shared Master CSS for seamless navigation */
        :root {
            --primary: #2563EB;
            --primary-hover: #1D4ED8;
            --dark: #0F172A;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --border: #E2E8F0;
            --text-main: #1E293B;
            --text-muted: #64748B;
        }

        body { font-family: 'Inter', system-ui, sans-serif; margin: 0; background: var(--bg); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar Navigation */
        .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
        .sidebar h2 { padding: 0 20px; color: #38BDF8; font-size: 1.5rem; margin-bottom: 30px; }
        .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; transition: 0.2s; border-left: 3px solid transparent; }
        .sidebar a:hover, .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
        
        /* Main Dashboard Content */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 20px; }
        .header h1 { margin-top: 0; margin-bottom: 5px; }
        .header p { color: var(--text-muted); margin-top: 0; }
        
        /* Live Stat Cards */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; }
        .stat-card { background: var(--card); padding: 25px; border-radius: 10px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; align-items: center; justify-content: space-between; }
        .stat-card h3 { margin: 0; font-size: 2.5rem; color: var(--primary); }
        .stat-card p { margin: 5px 0 0; color: var(--text-muted); font-weight: 600; text-transform: uppercase; font-size: 0.85rem; }
        .stat-icon { font-size: 2.5rem; opacity: 0.2; }

        /* Dashboard Feed Panels */
        .feed-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 10px; }
        .feed-panel { background: var(--card); border-radius: 10px; border: 1px solid var(--border); padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .feed-panel h3 { margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 15px; color: var(--text-main); display: flex; justify-content: space-between; align-items: center; }
        .feed-panel a { font-size: 0.85rem; color: var(--primary); text-decoration: none; font-weight: normal; }
        .feed-panel a:hover { text-decoration: underline; }
        
        .feed-item { padding: 15px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
        .feed-item:last-child { border-bottom: none; padding-bottom: 0; }
        .feed-title { font-weight: 600; margin-bottom: 3px; display: block; }
        .feed-sub { font-size: 0.85rem; color: var(--text-muted); }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #DBEAFE; color: #1E40AF; }

        /* Animations */
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(15px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate { animation: slideUp 0.6s ease-out forwards; }
    </style>
</head>
<body>

    <div class="sidebar">
        <h2>🚀 Portal</h2>
        <a href="index.php" class="active">🏠 Dashboard</a>
        <a href="startups.php">💡 Startups</a>
        <a href="events.php">📅 Events Master</a>
        <a href="#">👥 Teams</a>
        <a href="#">🎓 Mentors</a>
        <a href="#">💰 Investors</a>
    </div>

    <div class="main-content">
        <div class="header animate">
            <h1>Platform Overview</h1>
            <p>Welcome back to the Startup Collaboration Portal Admin Dashboard.</p>
        </div>

        <div class="stats-grid animate" style="animation-delay: 0.1s;">
            <div class="stat-card">
                <div>
                    <h3><?php echo $total_startups ?: 0; ?></h3>
                    <p>Total Startups</p>
                </div>
                <div class="stat-icon">💡</div>
            </div>
            
            <div class="stat-card">
                <div>
                    <h3><?php echo $total_events ?: 0; ?></h3>
                    <p>Total Events</p>
                </div>
                <div class="stat-icon">📅</div>
            </div>

            <div class="stat-card">
                <div>
                    <h3><?php echo $total_users ?: 0; ?></h3>
                    <p>Registered Users</p>
                </div>
                <div class="stat-icon">👥</div>
            </div>
        </div>

        <div class="feed-grid animate" style="animation-delay: 0.2s;">
            
            <div class="feed-panel">
                <h3>Latest Startups <a href="startups.php">View All →</a></h3>
                <?php if ($recent_startups->num_rows > 0): ?>
                    <?php while($row = $recent_startups->fetch_assoc()): ?>
                        <div class="feed-item">
                            <div>
                                <span class="feed-title"><?php echo htmlspecialchars($row['title']); ?></span>
                                <span class="feed-sub">Added: <?php echo date('M d, Y', strtotime($row['created_at'])); ?></span>
                            </div>
                            <span class="badge"><?php echo strtoupper($row['stage']); ?></span>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="feed-sub">No startups registered yet.</p>
                <?php endif; ?>
            </div>

            <div class="feed-panel">
                <h3>Upcoming Events <a href="events.php">View All →</a></h3>
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
                    <p class="feed-sub">No upcoming events scheduled.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>

</body>
</html>