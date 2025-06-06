<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'employee') {
    header('Location: login.html');
    exit();
}
$user_id = $_SESSION['user']['id'];

// Fetch leave statistics
$total_leaves = 0;
$stmt_total = $conn->prepare('SELECT COUNT(*) FROM leaves WHERE user_id = ?');
$stmt_total->bind_param('i', $user_id);
$stmt_total->execute();
$stmt_total->bind_result($total_leaves);
$stmt_total->fetch();
$stmt_total->close();

$approved_leaves = 0;
$stmt_approved = $conn->prepare('SELECT COUNT(*) FROM leaves WHERE user_id = ? AND status = "approved"');
$stmt_approved->bind_param('i', $user_id);
$stmt_approved->execute();
$stmt_approved->bind_result($approved_leaves);
$stmt_approved->fetch();
$stmt_approved->close();

$pending_leaves = 0;
$stmt_pending = $conn->prepare('SELECT COUNT(*) FROM leaves WHERE user_id = ? AND status = "pending"');
$stmt_pending->bind_param('i', $user_id);
$stmt_pending->execute();
$stmt_pending->bind_result($pending_leaves);
$stmt_pending->fetch();
$stmt_pending->close();

$rejected_leaves = 0;
$stmt_rejected = $conn->prepare('SELECT COUNT(*) FROM leaves WHERE user_id = ? AND status = "rejected"');
$stmt_rejected->bind_param('i', $user_id);
$stmt_rejected->execute();
$stmt_rejected->bind_result($rejected_leaves);
$stmt_rejected->fetch();
$stmt_rejected->close();

$sql = 'SELECT l.id, lt.name as leave_type, l.purpose, l.duration, l.status, l.applied_at, l.start_date, l.end_date FROM leaves l JOIN leave_types lt ON l.leave_type = lt.id WHERE l.user_id = ? ORDER BY l.applied_at DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Dashboard | Leave Management</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary: #1976d2;
      --primary-light: #e3f2fd;
      --primary-gradient: linear-gradient(135deg, #1976d2 0%, #0d47a1 100%);
      --secondary: #f5f5f5;
      --text: #333;
      --text-light: #777;
      --border: #e0e0e0;
      --success: #4caf50;
      --warning: #ff9800;
      --danger: #f44336;
      --card-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
      --hover-shadow: 0 10px 30px rgba(25, 118, 210, 0.15);
    }
    
    body {
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      color: var(--text);
      padding: 20px;
    }
    
    .dashboard-container {
      max-width: 1200px;
      margin: 0 auto;
    }
    
    .header-card {
      background: var(--primary-gradient);
      color: white;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      margin-bottom: 25px;
      overflow: hidden;
    }
    
    .main-card {
      background: white;
      border-radius: 12px;
      box-shadow: var(--card-shadow);
      padding: 30px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    
    .main-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--hover-shadow);
    }
    
    .btn-primary-custom {
      background: var(--primary-gradient);
      border: none;
      padding: 10px 25px;
      font-weight: 600;
      border-radius: 8px;
      transition: all 0.3s ease;
    }
    
    .btn-primary-custom:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 15px rgba(25, 118, 210, 0.4);
    }
    
    .status-badge {
      padding: 6px 12px;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 500;
    }
    
    .status-approved {
      background-color: rgba(76, 175, 80, 0.15);
      color: var(--success);
    }
    
    .status-pending {
      background-color: rgba(255, 152, 0, 0.15);
      color: var(--warning);
    }
    
    .status-rejected {
      background-color: rgba(244, 67, 54, 0.15);
      color: var(--danger);
    }
    
    .table-header {
      background: var(--primary-light);
    }
    
    .table th {
      font-weight: 600;
      color: var(--text);
    }
    
    .table-hover tbody tr:hover {
      background-color: rgba(25, 118, 210, 0.05);
    }
    
    .reason-cell {
      cursor: pointer;
      max-width: 250px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .reason-cell:hover {
      color: var(--primary);
    }
    
    .modal-header {
      background: var(--primary-gradient);
      color: white;
      border-radius: 8px 8px 0 0 !important;
    }
    
    .modal-title {
      font-weight: 600;
    }
    
    .logout-btn {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 6px;
      padding: 5px 15px;
      transition: all 0.3s ease;
    }
    
    .logout-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }
    
    .welcome-text {
      font-size: 1.1rem;
      opacity: 0.9;
    }
    
    .dashboard-title {
      font-weight: 700;
      letter-spacing: 0.5px;
      position: relative;
      padding-bottom: 15px;
    }
    
    .dashboard-title:after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      width: 60px;
      height: 4px;
      background: white;
      border-radius: 2px;
    }
    
    .stats-card {
      background: white;
      border-radius: 10px;
      padding: 20px;
      text-align: center;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
      transition: all 0.3s ease;
    }
    
    .stats-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    }
    
    .stats-icon {
      font-size: 2.5rem;
      margin-bottom: 15px;
      color: var(--primary);
    }
    
    .stats-number {
      font-size: 1.8rem;
      font-weight: 700;
      color: var(--primary);
    }
    
    .stats-label {
      font-size: 0.9rem;
      color: var(--text-light);
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .footer {
      text-align: center;
      padding: 20px;
      color: var(--text-light);
      font-size: 0.9rem;
      margin-top: 30px;
    }
  </style>
</head>
<body>
  <div class="dashboard-container">
    <!-- Header -->
    <div class="header-card p-4 mb-4">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h1 class="dashboard-title">Leave Management System</h1>
          <p class="welcome-text mb-0">Employee Dashboard</p>
        </div>
        <div class="d-flex align-items-center">
          <div class="me-3 text-end">
            <p class="mb-0">Welcome, <?= htmlspecialchars($_SESSION['user']['name'] ?? 'Employee') ?></p>
            <small class="opacity-75"><?= date('l, F j, Y') ?></small>
          </div>
          <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt me-1"></i> Logout
          </a>
        </div>
      </div>
    </div>
    
    <!-- Stats Overview -->
    <div class="row mb-4">
      <div class="col-md-3 mb-3">
        <div class="stats-card">
          <div class="stats-icon">
            <i class="fas fa-calendar-check"></i>
          </div>
          <div class="stats-number"><?= $total_leaves ?></div>
          <div class="stats-label">Total Leaves</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stats-card">
          <div class="stats-icon">
            <i class="fas fa-check-circle"></i>
          </div>
          <div class="stats-number"><?= $approved_leaves ?></div>
          <div class="stats-label">Approved</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stats-card">
          <div class="stats-icon">
            <i class="fas fa-clock"></i>
          </div>
          <div class="stats-number"><?= $pending_leaves ?></div>
          <div class="stats-label">Pending</div>
        </div>
      </div>
      <div class="col-md-3 mb-3">
        <div class="stats-card">
          <div class="stats-icon">
            <i class="fas fa-times-circle"></i>
          </div>
          <div class="stats-number"><?= $rejected_leaves ?></div>
          <div class="stats-label">Rejected</div>
        </div>
      </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-card">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0" style="color: var(--primary);">
          <i class="fas fa-list-alt me-2"></i>Leave Requests
        </h2>
        <button id="openLeaveModalBtn" class="btn btn-primary-custom">
          <i class="fas fa-plus me-2"></i>New Request
        </button>
      </div>
      
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-header">
            <tr>
              <th>Request Date</th>
              <th>Duration</th>
              <th>Leave Type</th>
              <th>From</th>
              <th>To</th>
              <th>Reason</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($row = $result->fetch_assoc()): 
              $status = strtolower($row['status']);
              $statusClass = $status === 'approved' ? 'status-approved' : 
                            ($status === 'rejected' ? 'status-rejected' : 'status-pending');
            ?>
            <tr>
              <td><?= htmlspecialchars($row['applied_at']) ?></td>
              <td><?= htmlspecialchars($row['duration']) ?> days</td>
              <td><?= htmlspecialchars($row['leave_type']) ?></td>
              <td><?= htmlspecialchars($row['start_date']) ?></td>
              <td><?= htmlspecialchars($row['end_date']) ?></td>
              <td class="reason-cell" data-bs-toggle="modal" data-bs-target="#reasonModal" data-reason="<?= htmlspecialchars($row['purpose']) ?>">
                <?= htmlspecialchars(mb_strimwidth($row['purpose'], 0, 30, '...')) ?>
              </td>
              <td>
                <span class="status-badge <?= $statusClass ?>">
                  <?= ucfirst($status) ?>
                </span>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
    
    <!-- Footer -->
    <div class="footer">
      <p class="mb-0">© 2023 Leave Management System. All rights reserved.</p>
      <small>Employee Dashboard v2.0</small>
    </div>
  </div>
  
  <!-- Leave Request Modal -->
  <div class="modal fade" id="leaveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">New Leave Request</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form action="apply_leave.php" method="POST" id="leaveForm">
            <div class="row mb-3">
              <div class="col-md-6">
                <label for="leave_type" class="form-label">Leave Type</label>
                <select name="leave_type" id="leave_type" class="form-select" required>
                  <option value="">Select Leave Type</option>
                  <?php
                  $leave_types_query = $conn->query('SELECT id, name, description FROM leave_types ORDER BY name');
                  while($lt = $leave_types_query->fetch_assoc()):
                  ?>
                  <option value="<?= $lt['id'] ?>" title="<?= htmlspecialchars($lt['description']) ?>">
                      <?= htmlspecialchars($lt['name']) ?>
                  </option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label for="duration" class="form-label">Duration (days)</label>
                <select name="duration" id="duration" class="form-select" required></select>
              </div>
            </div>
            
            <div class="mb-3">
              <label for="purpose" class="form-label">Purpose</label>
              <textarea name="purpose" id="purpose" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="row mb-4">
              <div class="col-md-6">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" name="start_date" id="start_date" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" name="end_date" id="end_date" class="form-control" required readonly>
              </div>
            </div>
            
            <div class="d-flex justify-content-end gap-2">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary-custom">Submit Request</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Reason Popup Modal -->
  <div class="modal fade" id="reasonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Leave Reason</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p id="reasonPopupText" class="lead"></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Initialize Bootstrap components
    const leaveModal = new bootstrap.Modal(document.getElementById('leaveModal'));
    const reasonModal = new bootstrap.Modal(document.getElementById('reasonModal'));
    
    // Open leave modal
    document.getElementById('openLeaveModalBtn').addEventListener('click', () => {
      leaveModal.show();
    });
    
    // Handle reason popup
    const reasonCells = document.querySelectorAll('.reason-cell');
    reasonCells.forEach(cell => {
      cell.addEventListener('click', () => {
        document.getElementById('reasonPopupText').textContent = cell.getAttribute('data-reason');
        reasonModal.show();
      });
    });
    
    // Map leave type IDs to duration options
    const leaveDurations = {
      1: [5], // Service Incentive Leave (SIL): 5 days
      2: [5,6,7,8,9,10,11,12,13,14,15], // Sick Leave: 5–15 days
      3: [10,11,12,13,14,15], // Vacation Leave: 10–15 days
      4: [105,106,107,108,109,110,111,112,113,114,115,116,117,118,119,120], // Maternity: 105–120 days
      5: [7], // Paternity Leave: 7 days
      6: [3,4,5], // Bereavement: 3–5 days
      7: [7], // Solo Parent Leave: 7 days
      8: [30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50,51,52,53,54,55,56,57,58,59,60] // Special Leave for Women: up to 2 months (30–60 days)
    };
    
    function updateDurationOptions() {
      const leaveType = document.getElementById('leave_type').value;
      const durationSelect = document.getElementById('duration');
      durationSelect.innerHTML = '';
      
      if (leaveType && leaveDurations[leaveType]) {
        leaveDurations[leaveType].forEach(function(day) {
          const opt = document.createElement('option');
          opt.value = day;
          opt.text = day + (day === 1 ? ' day' : ' days');
          durationSelect.appendChild(opt);
        });
      }
    }
    
    function calculateEndDate() {
      const startDate = document.getElementById('start_date').value;
      const duration = parseInt(document.getElementById('duration').value);
      
      if (startDate && duration) {
        const start = new Date(startDate);
        const end = new Date(start);
        end.setDate(start.getDate() + duration - 1);
        document.getElementById('end_date').value = end.toISOString().split('T')[0];
      } else {
        document.getElementById('end_date').value = '';
      }
    }
    
    // Event listeners
    document.getElementById('leave_type').addEventListener('change', updateDurationOptions);
    document.getElementById('start_date').addEventListener('change', calculateEndDate);
    document.getElementById('duration').addEventListener('change', calculateEndDate);
    
    // Initialize duration options
    window.addEventListener('DOMContentLoaded', () => {
      document.getElementById('duration').innerHTML = '';
      calculateEndDate();
    });
  </script>
</body>
</html>