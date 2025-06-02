<?php
session_start();
include 'db.php';
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'employee') {
    header('Location: login.html');
    exit();
}
$user_id = $_SESSION['user']['id'];
$sql = 'SELECT l.id, lt.name as leave_type, l.purpose, l.duration, l.status, l.applied_at, l.start_date, l.end_date FROM leaves l JOIN leave_types lt ON l.leave_type = lt.id WHERE l.user_id = ? ORDER BY l.applied_at DESC';
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Employee Dashboard</title>
  <link rel="stylesheet" href="styles.css">
</head>
<body>
  <div style="text-align:right; margin: 20px 40px 0 0;"><a href="logout.php" class="logout-link">Logout</a></div>
  <div class="dashboard-box" style="max-width:900px; margin:40px auto 0 auto; background:none; padding:0; border:none; border-radius:0; box-shadow:none;">
    <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 18px;">
      <h2 style="margin-bottom: 0; color:#111; letter-spacing:1px; font-size:2rem; font-weight:700;">Request List</h2>
      <button id="openLeaveModalBtn" class="add-employee-btn" style="padding: 8px 16px; font-size: 1em; margin: 0;">Request</button>
    </div>
    <!-- Leave Request Modal -->
    <div id="leaveModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:1000; align-items:center; justify-content:center;">
      <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:95vw; box-shadow:0 8px 32px #0002; position:relative;">
        <button id="closeLeaveModalBtn" style="position:absolute; top:10px; right:18px; font-size:22px; color:#888; cursor:pointer; background:none; border:none;">&times;</button>
        <h2 style="margin-top:0; color:#111;">Request Form</h2>
        <form action="apply_leave.php" method="POST" id="leaveForm" style="display:flex; flex-direction:column; gap:10px;">
          <label for="leave_type">Leave Type:</label>
          <select name="leave_type" id="leave_type" required>
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
          <label for="duration">Duration:</label>
          <select name="duration" id="duration" required style="width:100%;"></select>
          <label for="purpose">Purpose:</label>
          <textarea name="purpose" id="purpose" required style="width:100%;resize:vertical;min-height:80px;max-height:200px;" rows="4"></textarea>
          <div style="display:flex; gap:10px; align-items:end; margin-top:10px;">
            <div style="flex:1;">
              <label for="start_date">Start Date:</label>
              <input type="date" name="start_date" id="start_date" required style="width:100%; min-width:0;">
            </div>
            <div style="flex:1;">
              <label for="end_date">End Date:</label>
              <input type="date" name="end_date" id="end_date" required readonly style="width:100%; min-width:0;">
            </div>
          </div>
          <div style="display:flex; gap:16px; margin-top:18px; justify-content:center;">
            <button type="submit" style="background:#333; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600; font-size:1em; cursor:pointer;">Save</button>
            <button type="button" id="cancelLeaveModalBtn" style="background:#333; color:#fff; border:none; border-radius:4px; padding:8px 16px; font-weight:600; font-size:1em; cursor:pointer;">Cancel</button>
          </div>
        </form>
      </div>
    </div>
    <table style="width: 100%; background: #fff; border-radius: 8px; overflow: hidden; table-layout: fixed;">
      <thead style="background: #1976d2; color: #fff;">
        <tr>
          <th style="width: 120px;">Request Date</th>
          <th style="width: 120px;">Duration</th>
          <th style="width: 120px;">Leave Type</th>
          <th style="width: 100px;">From</th>
          <th style="width: 100px;">To</th>
          <th style="width: 220px;">Reason</th>
          <th style="width: 100px;">Status</th>
        </tr>
      </thead>
      <tbody>
        <?php while($row = $result->fetch_assoc()): ?>
        <tr style="height: 44px;">
          <td><?= htmlspecialchars($row['applied_at']) ?></td>
          <td><?= htmlspecialchars($row['duration']) ?></td>
          <td><?= htmlspecialchars($row['leave_type']) ?></td>
          <td><?= htmlspecialchars($row['start_date']) ?></td>
          <td><?= htmlspecialchars($row['end_date']) ?></td>
          <td style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor:pointer;" onclick="showReasonPopup(this)">
            <?= htmlspecialchars($row['purpose']) ?>
          </td>
          <td>
            <?php
              $status = strtolower($row['status']);
              $color = $status === 'approved' ? '#e6ffed' : ($status === 'rejected' ? '#ffeaea' : '#e3e3e3');
              $text = $status === 'approved' ? '#1a7f37' : ($status === 'rejected' ? '#c00' : '#b59f00');
            ?>
            <span style="background:<?= $color ?>;color:<?= $text ?>;padding:2px 10px;border-radius:12px;">
              <?= ucfirst($status) ?>
            </span>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
    <!-- Reason Popup Modal -->
    <div id="reasonPopup" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.18); z-index:2000; align-items:center; justify-content:center;">
      <div style="background:#fff; border-radius:10px; padding:32px 28px; min-width:320px; max-width:95vw; box-shadow:0 8px 32px #0002; position:relative;">
        <button id="closeReasonPopupBtn" style="position:absolute; top:10px; right:18px; font-size:22px; color:#888; cursor:pointer; background:none; border:none; padding:5px; z-index:1001; width:fit-content; height:fit-content;">&times;</button>
        <h3 style="margin-top:0; color:#111;">Reason</h3>
        <div id="reasonPopupText" style="font-size:1.1em; color:#222; word-break:break-word;"></div>
      </div>
    </div>
  </div>
  <script>
    // Modal logic
    const leaveModal = document.getElementById('leaveModal');
    const openLeaveModalBtn = document.getElementById('openLeaveModalBtn');
    const closeLeaveModalBtn = document.getElementById('closeLeaveModalBtn');
    const cancelLeaveModalBtn = document.getElementById('cancelLeaveModalBtn');
    openLeaveModalBtn.onclick = () => { leaveModal.style.display = 'flex'; };
    closeLeaveModalBtn.onclick = () => { leaveModal.style.display = 'none'; };
    cancelLeaveModalBtn.onclick = () => { leaveModal.style.display = 'none'; };
    window.onclick = function(event) {
      if (event.target == leaveModal) {
        leaveModal.style.display = 'none';
      }
    }

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
      leaveDurations[leaveType].forEach(function(day) {
        const opt = document.createElement('option');
        opt.value = day;
        opt.text = day + (day === 1 ? ' day' : ' days');
        durationSelect.appendChild(opt);
      });
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
    document.getElementById('leave_type').addEventListener('change', updateDurationOptions);
    document.getElementById('start_date').addEventListener('change', calculateEndDate);
    document.getElementById('duration').addEventListener('change', calculateEndDate);
    window.onload = function() {
      // Don't set duration options until a leave type is selected
      document.getElementById('duration').innerHTML = '';
      calculateEndDate();
    };

    // Reason popup logic for employee dashboard
    function showReasonPopup(cell) {
      var text = cell.textContent;
      document.getElementById('reasonPopupText').textContent = text;
      document.getElementById('reasonPopup').style.display = 'flex';
    }
    document.getElementById('closeReasonPopupBtn').onclick = function() {
      document.getElementById('reasonPopup').style.display = 'none';
    };
    window.onclick = function(event) {
      if (event.target == document.getElementById('reasonPopup')) {
        document.getElementById('reasonPopup').style.display = 'none';
      }
      // Existing modal close logic for leaveModal
      if (event.target == document.getElementById('leaveModal')) {
        leaveModal.style.display = 'none';
      }
    }
  </script>
</body>
</html> 