const express = require('express');
const bodyParser = require('body-parser');
const db = require('./db');
const path = require('path');

const app = express();
const PORT = 8000;

// Middleware
app.use(bodyParser.urlencoded({ extended: true }));
app.use(bodyParser.json());
app.use(express.static(path.join(__dirname, 'public')));

// Current user (simulating logged-in admin)
const currentUserId = 1;

// ==========================================
// DASHBOARD PAGE
// ==========================================
app.get('/admin', (req, res) => {
  const page = req.query.page || 'dashboard';

  // Get stats for dashboard
  db.get('SELECT COUNT(*) as count FROM startups', [], (err, startups) => {
    db.get('SELECT COUNT(*) as count FROM events', [], (err, events) => {
      db.get('SELECT COUNT(*) as count FROM users', [], (err, users) => {
        
        let dashboardData = {
          totalStartups: startups?.count || 0,
          totalEvents: events?.count || 0,
          totalUsers: users?.count || 0,
          page: page
        };

        if (page === 'dashboard') {
          db.all('SELECT title, stage, created_at FROM startups ORDER BY created_at DESC LIMIT 4', [], (err, recentStartups) => {
            db.all('SELECT title, start_date, venue FROM events WHERE status="upcoming" ORDER BY start_date ASC LIMIT 4', [], (err, upcomingEvents) => {
              res.send(renderDashboard(dashboardData, recentStartups, upcomingEvents));
            });
          });
        } else if (page === 'startups') {
          const search = req.query.search || '';
          const stage = req.query.stage || '';
          let query = 'SELECT s.*, u.name AS founder_name FROM startups s LEFT JOIN users u ON s.creator_id = u.user_id WHERE 1=1';
          if (search) query += ` AND s.title LIKE '%${search}%'`;
          if (stage) query += ` AND s.stage = '${stage}'`;
          query += ' ORDER BY s.created_at DESC';

          db.all(query, [], (err, startups) => {
            db.get('SELECT SUM(funding_needed) as total FROM startups', [], (err, funding) => {
              res.send(renderStartupsPage(dashboardData, startups, funding?.total || 0, search, stage));
            });
          });
        } else if (page === 'events') {
          const search = req.query.search || '';
          const type = req.query.type || '';
          const status = req.query.status || '';
          let query = 'SELECT e.*, u.name AS organizer_name FROM events e LEFT JOIN users u ON e.organized_by = u.user_id WHERE 1=1';
          if (search) query += ` AND (e.title LIKE '%${search}%' OR e.venue LIKE '%${search}%')`;
          if (type) query += ` AND e.event_type = '${type}'`;
          if (status) query += ` AND e.status = '${status}'`;
          query += ' ORDER BY e.start_date ASC';

          db.all(query, [], (err, events) => {
            db.get('SELECT COUNT(*) as count FROM events WHERE status="upcoming"', [], (err, upcoming) => {
              db.get('SELECT SUM(prize_pool) as total FROM events', [], (err, prize) => {
                res.send(renderEventsPage(dashboardData, events, upcoming?.count || 0, prize?.total || 0, search, type, status));
              });
            });
          });
        }
      });
    });
  });
});

// ==========================================
// API ROUTES - STARTUPS
// ==========================================

// GET: Fetch all startups
app.get('/api/startups', (req, res) => {
  db.all('SELECT * FROM startups ORDER BY created_at DESC', [], (err, rows) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json(rows);
    }
  });
});

// POST: Create new startup
app.post('/api/startups', (req, res) => {
  const { title, description, stage, website_url, equity_offered, funding_needed } = req.body;
  const sql = `
    INSERT INTO startups (title, domain_id, description, creator_id, stage, website_url, equity_offered, funding_needed)
    VALUES (?, 1, ?, ?, ?, ?, ?)
  `;
  db.run(sql, [title, description, currentUserId, stage, website_url, funding_needed], function(err) {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Startup created!', startup_id: this.lastID });
    }
  });
});

// PUT: Update startup
app.put('/api/startups/:id', (req, res) => {
  const { id } = req.params;
  const { stage } = req.body;
  db.run('UPDATE startups SET stage = ? WHERE startup_id = ?', [stage, id], (err) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Startup updated!' });
    }
  });
});

// DELETE: Remove startup
app.delete('/api/startups/:id', (req, res) => {
  const { id } = req.params;
  db.run('DELETE FROM startups WHERE startup_id = ?', [id], (err) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Startup deleted!' });
    }
  });
});

// ==========================================
// API ROUTES - EVENTS
// ==========================================

// GET: Fetch all events
app.get('/api/events', (req, res) => {
  db.all('SELECT * FROM events ORDER BY start_date DESC', [], (err, rows) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json(rows);
    }
  });
});

// GET: Fetch single event
app.get('/api/events/:id', (req, res) => {
  const { id } = req.params;
  db.get('SELECT * FROM events WHERE event_id = ?', [id], (err, row) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else if (!row) {
      res.status(404).json({ error: 'Event not found' });
    } else {
      res.json(row);
    }
  });
});

// POST: Create new event
app.post('/api/events', (req, res) => {
  const { title, event_type, description, venue, start_date, end_date, max_participants, prize_pool } = req.body;

  const sql = `
    INSERT INTO events (title, event_type, description, organized_by, venue, start_date, end_date, max_participants, prize_pool)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
  `;

  db.run(sql, [title, event_type, description, currentUserId, venue, start_date, end_date, max_participants, prize_pool], function(err) {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Event created successfully!', event_id: this.lastID });
    }
  });
});

// PUT: Update event
app.put('/api/events/:id', (req, res) => {
  const { id } = req.params;
  const { title, status, description, venue, start_date, end_date, max_participants, prize_pool } = req.body;

  const sql = `
    UPDATE events 
    SET title = ?, status = ?, description = ?, venue = ?, start_date = ?, end_date = ?, max_participants = ?, prize_pool = ?
    WHERE event_id = ?
  `;

  db.run(sql, [title, status, description, venue, start_date, end_date, max_participants, prize_pool, id], (err) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Event updated successfully!' });
    }
  });
});

// DELETE: Remove event
app.delete('/api/events/:id', (req, res) => {
  const { id } = req.params;
  db.run('DELETE FROM events WHERE event_id = ?', [id], (err) => {
    if (err) {
      res.status(500).json({ error: err.message });
    } else {
      res.json({ message: 'Event deleted successfully!' });
    }
  });
});

// ==========================================
// DASHBOARD RENDER FUNCTIONS
// ==========================================

function renderDashboard(data, recentStartups, upcomingEvents) {
  return `
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard | Startup Portal</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --primary: #2563EB; --dark: #0F172A; --bg: #F8FAFC; --card: #FFFFFF; --border: #E2E8F0; --text-main: #1E293B; --text-muted: #64748B; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; height: 100vh; }
    .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
    .sidebar h2 { padding: 0 20px; color: #38BDF8; margin-bottom: 30px; }
    .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; }
    .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
    .main-content { flex: 1; padding: 30px; overflow-y: auto; }
    .header h1 { margin-top: 0; margin-bottom: 5px; }
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
    .stat-card { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); }
    .stat-card h2 { color: var(--primary); font-size: 2rem; margin: 10px 0 0; }
    .stat-card p { color: var(--text-muted); font-size: 0.9rem; margin: 0; }
    .feed-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; margin-top: 20px; }
    .feed-panel { background: var(--card); border-radius: 10px; border: 1px solid var(--border); padding: 20px; }
    .feed-panel h3 { margin-top: 0; border-bottom: 1px solid var(--border); padding-bottom: 10px; }
    .feed-item { padding: 15px 0; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; }
    .feed-item:last-child { border-bottom: none; }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #DBEAFE; color: #1E40AF; }
    .nav-links { display: flex; gap: 10px; margin: 20px 0; }
    .btn { padding: 10px 16px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; text-decoration: none; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>🚀 Portal</h2>
    <a href="/admin?page=dashboard" class="active">📊 Dashboard</a>
    <a href="/admin?page=startups">💡 Startups</a>
    <a href="/admin?page=events">📅 Events</a>
  </div>
  <div class="main-content">
    <div class="header">
      <h1>Platform Overview</h1>
      <p>Welcome to the Startup Collaboration Portal Admin Dashboard.</p>
    </div>
    <div class="stats-row">
      <div class="stat-card"><p>Total Startups</p><h2>${data.totalStartups}</h2></div>
      <div class="stat-card"><p>Total Events</p><h2>${data.totalEvents}</h2></div>
      <div class="stat-card"><p>Registered Users</p><h2>${data.totalUsers}</h2></div>
    </div>
    <div class="feed-grid">
      <div class="feed-panel">
        <h3>Latest Startups</h3>
        ${recentStartups && recentStartups.length > 0 ? recentStartups.map(s => `
          <div class="feed-item">
            <div><strong>${s.title}</strong><br><small>${new Date(s.created_at).toDateString()}</small></div>
            <span class="badge">${s.stage.toUpperCase()}</span>
          </div>
        `).join('') : '<p>No startups yet</p>'}
      </div>
      <div class="feed-panel">
        <h3>Upcoming Events</h3>
        ${upcomingEvents && upcomingEvents.length > 0 ? upcomingEvents.map(e => `
          <div class="feed-item">
            <div><strong>${e.title}</strong><br><small>${e.venue}</small></div>
            <span>${new Date(e.start_date).toLocaleDateString()}</span>
          </div>
        `).join('') : '<p>No upcoming events</p>'}
      </div>
    </div>
  </div>
</body>
</html>
  `;
}

function renderStartupsPage(data, startups, totalFunding, search, stage) {
  return `
<!DOCTYPE html>
<html>
<head>
  <title>Startups | Admin Dashboard</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --primary: #2563EB; --dark: #0F172A; --bg: #F8FAFC; --card: #FFFFFF; --border: #E2E8F0; --text-main: #1E293B; --text-muted: #64748B; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; height: 100vh; }
    .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
    .sidebar h2 { padding: 0 20px; color: #38BDF8; margin-bottom: 30px; }
    .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; }
    .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
    .main-content { flex: 1; padding: 30px; overflow-y: auto; }
    .header h1 { margin-top: 0; }
    .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin: 20px 0; }
    .stat-card { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); }
    .stat-card h2 { color: var(--primary); font-size: 2rem; }
    .controls { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); display: flex; gap: 10px; margin: 20px 0; }
    .controls input, .controls select { padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
    .table-container { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F1F5F9; padding: 15px; text-align: left; }
    td { padding: 15px; border-bottom: 1px solid var(--border); }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #DBEAFE; color: #1E40AF; }
    .btn { padding: 10px 16px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; }
    .btn-danger { background: #EF4444; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>🚀 Portal</h2>
    <a href="/admin?page=dashboard">📊 Dashboard</a>
    <a href="/admin?page=startups" class="active">💡 Startups</a>
    <a href="/admin?page=events">📅 Events</a>
  </div>
  <div class="main-content">
    <h1>Startup Directory</h1>
    <div class="stats-row">
      <div class="stat-card"><p>Total Startups</p><h2>${data.totalStartups}</h2></div>
      <div class="stat-card"><p>Total Funding Requested</p><h2>₹${totalFunding.toLocaleString()}</h2></div>
    </div>
    <div class="controls">
      <form style="display: flex; gap: 10px; width: 100%;">
        <input type="text" name="search" placeholder="Search..." value="${search}">
        <select name="stage">
          <option value="">All Stages</option>
          <option value="idea" ${stage === 'idea' ? 'selected' : ''}>Idea</option>
          <option value="prototype" ${stage === 'prototype' ? 'selected' : ''}>Prototype</option>
          <option value="mvp" ${stage === 'mvp' ? 'selected' : ''}>MVP</option>
          <option value="launched" ${stage === 'launched' ? 'selected' : ''}>Launched</option>
        </select>
        <button type="submit" class="btn">🔍 Filter</button>
      </form>
      <button class="btn" onclick="alert('Add startup functionality - integrate with admin.php')">+ Add Startup</button>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Name</th><th>Stage</th><th>Funding</th><th>Equity</th><th>Action</th></tr></thead>
        <tbody>
          ${startups && startups.length > 0 ? startups.map(s => `
            <tr>
              <td><strong>${s.title}</strong></td>
              <td><span class="badge">${s.stage.toUpperCase()}</span></td>
              <td>₹${s.funding_needed.toLocaleString()}</td>
              <td>${s.equity_offered}%</td>
              <td><button class="btn btn-danger" onclick="alert('Delete: ${s.startup_id}')">Delete</button></td>
            </tr>
          `).join('') : '<tr><td colspan="5">No startups found</td></tr>'}
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
  `;
}

function renderEventsPage(data, events, upcomingCount, totalPrize, search, type, status) {
  return `
<!DOCTYPE html>
<html>
<head>
  <title>Events | Admin Dashboard</title>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    :root { --primary: #2563EB; --dark: #0F172A; --bg: #F8FAFC; --card: #FFFFFF; --border: #E2E8F0; --text-main: #1E293B; --text-muted: #64748B; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; height: 100vh; }
    .sidebar { width: 260px; background: var(--dark); color: white; padding: 20px 0; display: flex; flex-direction: column; }
    .sidebar h2 { padding: 0 20px; color: #38BDF8; margin-bottom: 30px; }
    .sidebar a { color: #CBD5E1; text-decoration: none; padding: 12px 20px; display: block; }
    .sidebar a.active { background: #1E293B; color: white; border-left: 3px solid var(--primary); }
    .main-content { flex: 1; padding: 30px; overflow-y: auto; }
    .header h1 { margin-top: 0; }
    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0; }
    .stat-card { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); }
    .stat-card h2 { color: var(--primary); font-size: 2rem; }
    .controls { background: var(--card); padding: 20px; border-radius: 10px; border: 1px solid var(--border); display: flex; gap: 10px; margin: 20px 0; }
    .controls input, .controls select { padding: 10px; border: 1px solid var(--border); border-radius: 6px; }
    .table-container { background: var(--card); border-radius: 10px; border: 1px solid var(--border); overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #F1F5F9; padding: 15px; text-align: left; }
    td { padding: 15px; border-bottom: 1px solid var(--border); }
    .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; background: #DBEAFE; color: #1E40AF; }
    .btn { padding: 10px 16px; background: var(--primary); color: white; border: none; border-radius: 6px; cursor: pointer; }
    .btn-danger { background: #EF4444; }
  </style>
</head>
<body>
  <div class="sidebar">
    <h2>🚀 Portal</h2>
    <a href="/admin?page=dashboard">📊 Dashboard</a>
    <a href="/admin?page=startups">💡 Startups</a>
    <a href="/admin?page=events" class="active">📅 Events</a>
  </div>
  <div class="main-content">
    <h1>Event Management</h1>
    <div class="stats-row">
      <div class="stat-card"><p>Total Events</p><h2>${data.totalEvents}</h2></div>
      <div class="stat-card"><p>Upcoming</p><h2>${upcomingCount}</h2></div>
      <div class="stat-card"><p>Prize Pool</p><h2>₹${totalPrize.toLocaleString()}</h2></div>
    </div>
    <div class="controls">
      <form style="display: flex; gap: 10px; width: 100%;">
        <input type="text" name="search" placeholder="Search..." value="${search}">
        <select name="type"><option value="">All Types</option><option value="hackathon">Hackathon</option><option value="pitch_day">Pitch Day</option></select>
        <select name="status"><option value="">All Status</option><option value="upcoming">Upcoming</option></select>
        <button type="submit" class="btn">🔍 Filter</button>
      </form>
      <button class="btn" onclick="alert('Add event functionality')">+ Create Event</button>
    </div>
    <div class="table-container">
      <table>
        <thead><tr><th>Name</th><th>Type</th><th>Venue</th><th>Date</th><th>Prize</th><th>Action</th></tr></thead>
        <tbody>
          ${events && events.length > 0 ? events.map(e => `
            <tr>
              <td><strong>${e.title}</strong></td>
              <td>${e.event_type}</td>
              <td>${e.venue}</td>
              <td>${new Date(e.start_date).toLocaleDateString()}</td>
              <td>₹${e.prize_pool.toLocaleString()}</td>
              <td><button class="btn btn-danger" onclick="alert('Delete: ${e.event_id}')">Delete</button></td>
            </tr>
          `).join('') : '<tr><td colspan="6">No events found</td></tr>'}
        </tbody>
      </table>
    </div>
  </div>
</body>
</html>
  `;
}

// Serve main HTML page
app.get('/', (req, res) => {
  res.sendFile(path.join(__dirname, 'public', 'index.html'));
});

// Start server
app.listen(PORT, () => {
  console.log(`\n✅ Server running at http://localhost:${PORT}`);
  console.log(`📋 Open your browser and navigate to:\n   • http://localhost:${PORT} (Main site)\n   • http://localhost:${PORT}/admin (Admin Dashboard)\n`);
});
