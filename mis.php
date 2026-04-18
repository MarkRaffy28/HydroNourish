<?php
  session_start();
  require 'config/db.php';

  if(!isset($_SESSION['user_id'])) {
      header("Location: login.php");
      exit;
  }

  $username = htmlspecialchars($_SESSION['username'] ?? 'User', ENT_QUOTES, 'UTF-8');

  if(isset($_POST['action'])){
      $action = $_POST['action'];

      if($action == 'add'){
          $name     = $_POST['name'];
          $email    = $_POST['email'];
          $uname    = trim($_POST['username']);
          $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
          $stmt = $conn->prepare("INSERT INTO users(name,email,username,password) VALUES(?,?,?,?)");
          $stmt->bind_param("ssss", $name, $email, $uname, $password);
          $stmt->execute();
          echo "User Added"; exit;

      } elseif($action == 'edit'){
          $id    = $_POST['id'];
          $name  = $_POST['name'];
          $email = $_POST['email'];
          $uname = trim($_POST['username']);
          if(!empty($_POST['password'])){
              $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
              $stmt = $conn->prepare("UPDATE users SET name=?, email=?, username=?, password=? WHERE id=?");
              $stmt->bind_param("ssssi", $name, $email, $uname, $password, $id);
          } else {
              $stmt = $conn->prepare("UPDATE users SET name=?, email=?, username=? WHERE id=?");
              $stmt->bind_param("sssi", $name, $email, $uname, $id);
          }
          $stmt->execute();
          echo "User Updated"; exit;

      } elseif($action == 'delete'){
          $id = $_POST['id'];
          $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
          $stmt->bind_param("i", $id);
          $stmt->execute();
          echo "User Deleted"; exit;

      } elseif($action == 'fetch'){
          $search      = $_POST['search'] ?? '';
          $page        = max(1, intval($_POST['page'] ?? 1));
          $limit       = intval($_POST['limit'] ?? 8);
          $sort_column = in_array($_POST['sort_column'] ?? '', ['id','name','email','username']) ? $_POST['sort_column'] : 'id';
          $sort_order  = ($_POST['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
          $offset      = ($page - 1) * $limit;
          $where = "";
          if($search != ""){
              $s = $conn->real_escape_string($search);
              $where = "WHERE name LIKE '%$s%' OR email LIKE '%$s%' OR username LIKE '%$s%'";
          }
          $totalResult = $conn->query("SELECT COUNT(*) as total FROM users $where");
          $totalRows   = $totalResult->fetch_assoc()['total'];
          $totalPages  = ceil($totalRows / $limit);
          $sql    = "SELECT * FROM users $where ORDER BY $sort_column $sort_order LIMIT $offset,$limit";
          $result = $conn->query($sql);
          $users  = [];
          if($result->num_rows > 0){
              while($row = $result->fetch_assoc()) $users[] = $row;
          }
          echo json_encode(['users'=>$users,'totalPages'=>$totalPages,'currentPage'=>$page,'totalRows'=>$totalRows]);
          exit;
      }
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Manage Users — Tomato Cultivation System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

  <link rel="stylesheet" href="css/app.css">
  <link rel="stylesheet" href="css/mis.css">
  
  <script src="js/jquery.min.js"></script>
  <script src="js/app.js"></script>

  <style>
    @media print {
      /* ── Page setup: A4 portrait ── */
      @page {
        size: A4 portrait;
        margin: 15mm 12mm;
      }

      /* ── Blank white sheet ── */
      html, body {
        margin: 0 !important;
        padding: 0 !important;
        background: #fff !important;
      }

      /* ── Hide everything ── */
      body * { visibility: hidden !important; }

      /* ── Reveal the print title and table ── */
      #print-title,
      #print-title *,
      #usersTable,
      #usersTable * { visibility: visible !important; }

      /* ── Print title block: sits at the very top ── */
      #print-title {
        position: absolute !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        font-family: Arial, sans-serif !important;
        margin-bottom: 10px !important;
        padding-bottom: 8px !important;
        border-bottom: 2px solid #2d6a4f !important;
      }
      #print-title h2 {
        margin: 0 0 2px 0 !important;
        font-size: 15pt !important;
        font-weight: 700 !important;
        color: #2d6a4f !important;
        font-family: Arial, sans-serif !important;
      }
      #print-title p {
        margin: 0 !important;
        font-size: 8pt !important;
        color: #666 !important;
        font-family: Arial, sans-serif !important;
      }

      /* ── Table sits just below the title ── */
      #usersTable {
        position: absolute !important;
        top: 52px !important;
        left: 0 !important;
        width: 100% !important;
        border-collapse: collapse !important;
        font-family: Arial, sans-serif !important;
        font-size: 10pt !important;
        background: #fff !important;
        page-break-inside: auto !important;
      }

      /* ── Header row ── */
      #usersTable thead { display: table-header-group !important; }
      #usersTable thead tr {
        background: #2d6a4f !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }
      #usersTable thead th {
        color: #fff !important;
        font-weight: 700 !important;
        padding: 8px 10px !important;
        border: 1px solid #1b4332 !important;
        text-align: left !important;
      }

      /* Hide sort icons */
      #usersTable .sort-icon { display: none !important; }

      /* ── Body rows ── */
      #usersTable tbody tr { page-break-inside: avoid !important; }
      #usersTable tbody td {
        padding: 6px 10px !important;
        border: 1px solid #ccc !important;
        color: #111 !important;
        text-align: left !important;
        vertical-align: middle !important;
      }

      /* ── Zebra striping ── */
      #usersTable tbody tr:nth-child(even) td {
        background: #f0faf5 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
      }

      /* ── Avatar cell: hide circle, show name text only ── */
      #usersTable .avatar-cell { display: block !important; }
      #usersTable .row-avatar  { display: none !important; }

      /* ── Hide the Actions column ── */
      #usersTable thead th:last-child,
      #usersTable tbody td:last-child { display: none !important; }
    }
  </style>
</head>
<body>
<div class="layout">

  <!-- SIDEBAR -->
  <?php $active_page = 'users'; include 'components/sidebar.php'; ?>

  <div class="main">
    <?php
      $page_title = "User Management";
      $page_icon = "fa-users";
      include 'components/header.php';
    ?>

    <div class="page-content">

      <!-- PAGE HEADER -->
      <div class="page-header">
        <div class="page-header-bg">👤</div>
        <div class="page-header-tag">System Administration</div>
        <h1>Manage <span>Users</span></h1>
        <p>Add, edit, and manage system accounts for the Solar IoT Tomato Cultivation platform.</p>
      </div>

      <!-- STAT CARDS -->
      <div class="stats-row" id="stats-row">
        <div class="stat-card green">
          <div class="stat-top"><div class="stat-icon green"><i class="fas fa-users"></i></div><span class="stat-badge up">Total</span></div>
          <div class="stat-value green" id="stat-total">—</div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card blue">
          <div class="stat-top"><div class="stat-icon blue"><i class="fas fa-user-check"></i></div><span class="stat-badge info">Active</span></div>
          <div class="stat-value blue" id="stat-active">—</div>
          <div class="stat-label">Active Accounts</div>
        </div>
        <div class="stat-card solar">
          <div class="stat-top"><div class="stat-icon solar"><i class="fas fa-user-shield"></i></div></div>
          <div class="stat-value solar">OK</div>
          <div class="stat-label">System Access</div>
        </div>
        <div class="stat-card red">
          <div class="stat-top"><div class="stat-icon red"><i class="fas fa-calendar-plus"></i></div></div>
          <div class="stat-value red" style="font-size:1.6rem;" id="stat-date">—</div>
          <div class="stat-label">Last Updated</div>
        </div>
      </div>

      <!-- USER TABLE CARD -->
      <div style="animation:fadeUp 0.5s ease 0.15s both;">
        <div class="section-head">
          <div class="section-icon green"><i class="fas fa-users"></i></div>
          <div class="section-label">User Accounts</div>
          <div class="section-meta" id="user-count-meta">Loading…</div>
        </div>

        <div class="card">
          <!-- TOOLBAR -->
          <div class="toolbar">
            <div class="toolbar-search">
              <i class="fas fa-magnifying-glass"></i>
              <input type="text" id="searchInput" placeholder="Search by name, email, or username…" autocomplete="off" />
            </div>
            <div class="toolbar-actions">
              <button class="btn btn-solar btn-sm" onclick="exportCSV()"><i class="fas fa-download"></i> CSV</button>
              <button class="btn btn-ghost btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
              <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-user-plus"></i> Add User</button>
            </div>
          </div>

          <!-- PRINT-ONLY TITLE (hidden on screen, visible on print) -->
          <div id="print-title" style="display:none;">
            <h2>List of Users</h2>
            <p>Solar IoT Tomato Cultivation System &mdash; User Accounts</p>
          </div>

          <!-- TABLE -->
          <div class="table-wrap">
            <table id="usersTable">
              <colgroup>
                <col> <!-- ID -->
                <col> <!-- Name -->
                <col> <!-- Email -->
                <col> <!-- Username -->
                <col> <!-- Actions -->
              </colgroup>
              <thead>
                <tr>
                  <th data-col="id">ID <i class="fas fa-sort sort-icon"></i></th>
                  <th data-col="name">Name <i class="fas fa-sort sort-icon"></i></th>
                  <th data-col="email">Email <i class="fas fa-sort sort-icon"></i></th>
                  <th data-col="username">Username <i class="fas fa-sort sort-icon"></i></th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="usersBody">
                <tr><td colspan="5"><div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading users…</p></div></td></tr>
              </tbody>
            </table>
          </div>

          <!-- PAGINATION -->
          <div class="pagination-wrap">
            <div class="pagination-info" id="pagination-info">—</div>
            <ul class="pagination" id="pagination"></ul>
          </div>
        </div>
      </div>

    </div>

    <!-- FOOTER -->
    <?php include "components/footer.php" ?>

  </div>
</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay" id="addModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-title"><i class="fas fa-user-plus"></i> Add New User</div>
      <button class="modal-close" onclick="closeModal('addModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><i class="fas fa-id-card"></i>Full Name</label>
          <input type="text" class="form-input" id="add_name" placeholder="e.g. Juan dela Cruz" required />
        </div>
        <div class="form-group">
          <label class="form-label"><i class="fas fa-user"></i>Username</label>
          <input type="text" class="form-input" id="add_username" placeholder="e.g. juan_dc" required />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
        <input type="email" class="form-input" id="add_email" placeholder="e.g. juan@farm.com" required />
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fas fa-lock"></i>Password</label>
        <input type="password" class="form-input" id="add_password" placeholder="Enter a strong password" required />
        <div class="form-hint">Minimum 8 characters recommended.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('addModal')"><i class="fas fa-times"></i> Cancel</button>
      <button class="btn btn-primary" onclick="submitAdd()"><i class="fas fa-save"></i> Create User</button>
    </div>
  </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editModal">
  <div class="modal-box">
    <div class="modal-head">
      <div class="modal-head-title"><i class="fas fa-user-edit"></i> Edit User</div>
      <button class="modal-close" onclick="closeModal('editModal')"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <input type="hidden" id="edit_id" />
      <div class="form-row">
        <div class="form-group">
          <label class="form-label"><i class="fas fa-id-card"></i>Full Name</label>
          <input type="text" class="form-input" id="edit_name" placeholder="Full name" required />
        </div>
        <div class="form-group">
          <label class="form-label"><i class="fas fa-user"></i>Username</label>
          <input type="text" class="form-input" id="edit_username" placeholder="Username" required />
        </div>
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fas fa-envelope"></i>Email Address</label>
        <input type="email" class="form-input" id="edit_email" placeholder="Email address" required />
      </div>
      <div class="form-group">
        <label class="form-label"><i class="fas fa-lock"></i>New Password</label>
        <input type="password" class="form-input" id="edit_password" placeholder="Leave blank to keep current" />
        <div class="form-hint">Only fill this if you want to change the password.</div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-ghost" onclick="closeModal('editModal')"><i class="fas fa-times"></i> Cancel</button>
      <button class="btn btn-primary" onclick="submitEdit()"><i class="fas fa-save"></i> Save Changes</button>
    </div>
  </div>
</div>

<!-- DELETE CONFIRM -->
<div class="delete-overlay" id="deleteOverlay">
  <div class="delete-box">
    <div class="delete-icon"><i class="fas fa-trash-can"></i></div>
    <div class="delete-title">Delete User?</div>
    <p class="delete-desc">This action is permanent and cannot be undone. Are you sure you want to remove <strong id="delete-username-display">this user</strong> from the system?</p>
    <div class="delete-actions">
      <button class="btn btn-ghost" onclick="closeDeleteModal()"><i class="fas fa-times"></i> Cancel</button>
      <button class="btn btn-danger" onclick="confirmDelete()"><i class="fas fa-trash-can"></i> Delete</button>
    </div>
  </div>
</div>

<script>
  /* ── STATE ── */
  let currentPage = 1, currentSort = 'id', currentOrder = 'asc', currentSearch = '';
  let pendingDeleteId = null, totalUsersCount = 0;
  const limit = 8;

  /* ── FETCH USERS ── */
  function fetchUsers(){
    const body = new URLSearchParams({
      action:'fetch', search:currentSearch, page:currentPage,
      limit:limit, sort_column:currentSort, sort_order:currentOrder
    });
    fetch('', { method:'POST', body })
      .then(r => r.json())
      .then(res => {
        totalUsersCount = parseInt(res.totalRows) || 0;
        renderTable(res.users);
        renderPagination(res.totalPages, res.currentPage, res.totalRows);
        updateStats(res.totalRows);
      })
      .catch(() => showToast('Failed to load users.', true));
  }

  function updateStats(total){
    document.getElementById('stat-total').textContent  = total;
    document.getElementById('stat-active').textContent = total;
    const now = new Date();
    document.getElementById('stat-date').textContent =
      now.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    document.getElementById('user-count-meta').textContent = total + ' users total';
  }

  function renderTable(users){
    const tbody = document.getElementById('usersBody');
    if(!users || users.length === 0){
      tbody.innerHTML = `<tr><td colspan="6"><div class="empty-state">
        <i class="fas fa-users-slash"></i><p>No users found. Try a different search term or add a new user.</p>
      </div></td></tr>`;
      return;
    }
    tbody.innerHTML = users.map(u => {
      const initial = (u.name || u.username || '?')[0].toUpperCase();
      const colors = ['#52C78A','#72C9EA','#F5C842','#ff9090','#A07050'];
      const color  = colors[u.id % colors.length];
      return `<tr>
        <td><span style="font-family:var(--font-mono);font-size:0.78rem;color:var(--text-muted);">#${u.id}</span></td>
        <td>
          <div class="avatar-cell">
            <div class="row-avatar" style="background:linear-gradient(135deg,${color},${color}aa)">${initial}</div>
            <div>
              <div class="row-name">${escHtml(u.name)}</div>
            </div>
          </div>
        </td>
        <td style="color:var(--text-muted);font-size:0.84rem;">${escHtml(u.email)}</td>
        <td><span style="font-family:var(--font-mono);font-size:0.82rem;color:var(--green-mid);">@${escHtml(u.username)}</span></td>
        <td>
          <div class="action-cell">
            <button class="btn btn-water btn-xs" onclick="openEditModal(${u.id},'${escAttr(u.name)}','${escAttr(u.email)}','${escAttr(u.username)}')">
              <i class="fas fa-pen"></i> Edit
            </button>
            <button class="btn btn-danger btn-xs" onclick="openDeleteModal(${u.id},'${escAttr(u.username)}')">
              <i class="fas fa-trash"></i>
            </button>
          </div>
        </td>
      </tr>`;
    }).join('');
  }

  function renderPagination(totalPages, current, totalRows){
    const offset = (current - 1) * limit;
    const showing = Math.min(offset + limit, totalRows);
    document.getElementById('pagination-info').textContent =
      `Showing ${offset + 1}–${showing} of ${totalRows} users`;

    const ul = document.getElementById('pagination');
    let html = '';
    if(current > 1) html += `<li><a onclick="goPage(${current-1})"><i class="fas fa-chevron-left" style="font-size:0.65rem;"></i></a></li>`;
    const start = Math.max(1, current - 2), end = Math.min(totalPages, current + 2);
    if(start > 1){ html += `<li><a onclick="goPage(1)">1</a></li>`; if(start > 2) html += `<li><span>…</span></li>`; }
    for(let i=start;i<=end;i++) html += `<li class="${i===current?'active':''}"><a onclick="goPage(${i})">${i}</a></li>`;
    if(end < totalPages){ if(end < totalPages-1) html += `<li><span>…</span></li>`; html += `<li><a onclick="goPage(${totalPages})">${totalPages}</a></li>`; }
    if(current < totalPages) html += `<li><a onclick="goPage(${current+1})"><i class="fas fa-chevron-right" style="font-size:0.65rem;"></i></a></li>`;
    ul.innerHTML = html;
  }

  function goPage(p){ currentPage = p; fetchUsers(); }

  /* ── SORT ── */
  document.querySelectorAll('thead th[data-col]').forEach(th => {
    th.addEventListener('click', () => {
      const col = th.dataset.col;
      if(currentSort === col) currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
      else { currentSort = col; currentOrder = 'asc'; }
      document.querySelectorAll('thead th').forEach(t => t.classList.remove('sorted'));
      th.classList.add('sorted');
      const icon = th.querySelector('.sort-icon');
      document.querySelectorAll('.sort-icon').forEach(i => { i.className = 'fas fa-sort sort-icon'; });
      icon.className = `fas fa-sort-${currentOrder === 'asc' ? 'up' : 'down'} sort-icon`;
      currentPage = 1; fetchUsers();
    });
  });

  /* ── SEARCH ── */
  let searchTimer;
  function wireSearch(id){
    const el = document.getElementById(id);
    if(!el) return;
    el.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        currentSearch = el.value.trim();
        currentPage = 1;
        
        document.getElementById('searchInput').value = currentSearch;
        document.getElementById('topbarSearch').value = currentSearch;
        fetchUsers();
      }, 300);
    });
  }
  wireSearch('searchInput');
  wireSearch('topbarSearch');

  /* ── ADD MODAL ── */
  function openAddModal(){
    document.getElementById('add_name').value = '';
    document.getElementById('add_email').value = '';
    document.getElementById('add_username').value = '';
    document.getElementById('add_password').value = '';
    openModal('addModal');
  }
  function submitAdd(){
    const name = document.getElementById('add_name').value.trim();
    const email = document.getElementById('add_email').value.trim();
    const username = document.getElementById('add_username').value.trim();
    const password = document.getElementById('add_password').value;
    if(!name || !email || !username || !password){ showToast('Please fill in all fields.', true); return; }
    const body = new URLSearchParams({action:'add', name, email, username, password});
    fetch('', { method:'POST', body })
      .then(r => r.text())
      .then(() => { closeModal('addModal'); fetchUsers(); showToast('User created successfully!', false); })
      .catch(() => showToast('Error creating user.', true));
  }

  /* ── EDIT MODAL ── */
  function openEditModal(id, name, email, username){
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_password').value = '';
    openModal('editModal');
  }
  function submitEdit(){
    const id = document.getElementById('edit_id').value;
    const name = document.getElementById('edit_name').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    const username = document.getElementById('edit_username').value.trim();
    const password = document.getElementById('edit_password').value;
    if(!name || !email || !username){ showToast('Please fill in required fields.', true); return; }
    const body = new URLSearchParams({action:'edit', id, name, email, username, password});
    fetch('', { method:'POST', body })
      .then(r => r.text())
      .then(() => { closeModal('editModal'); fetchUsers(); showToast('User updated successfully!', false); })
      .catch(() => showToast('Error updating user.', true));
  }

  /* ── DELETE ── */
  function openDeleteModal(id, username){
    pendingDeleteId = id;
    document.getElementById('delete-username-display').textContent = '@' + username;
    document.getElementById('deleteOverlay').classList.add('open');
  }
  function closeDeleteModal(){
    pendingDeleteId = null;
    document.getElementById('deleteOverlay').classList.remove('open');
  }
  function confirmDelete(){
    if(!pendingDeleteId) return;
    const body = new URLSearchParams({action:'delete', id:pendingDeleteId});
    fetch('', { method:'POST', body })
      .then(r => r.text())
      .then(() => { closeDeleteModal(); fetchUsers(); showToast('User deleted.', false); })
      .catch(() => showToast('Error deleting user.', true));
  }

  function openModal(id){ document.getElementById(id).classList.add('open'); }
  function closeModal(id){ document.getElementById(id).classList.remove('open'); }
  document.querySelectorAll('.modal-overlay').forEach(o => {
    o.addEventListener('click', e => { if(e.target === o) o.classList.remove('open'); });
  });
  document.getElementById('deleteOverlay').addEventListener('click', e => {
    if(e.target === document.getElementById('deleteOverlay')) closeDeleteModal();
  });

  function exportCSV(){
    const rows = [['ID','Name','Email','Username']];
    document.querySelectorAll('#usersBody tr').forEach(tr => {
      const tds = tr.querySelectorAll('td');
      if(tds.length >= 4){
        rows.push([
          tds[0].textContent.replace('#','').trim(),
          tds[1].querySelector('.row-name')?.textContent.trim() || tds[1].textContent.trim(),
          tds[2].textContent.trim(),
          tds[3].textContent.replace('@','').trim()
        ]);
      }
    });
    const csv = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
    const a = document.createElement('a');
    a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
    a.download = 'users_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    showToast('CSV exported!', false);
  }

  function escHtml(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
  function escAttr(s){ return (s||'').replace(/'/g,"&#39;").replace(/"/g,"&quot;"); }

  fetchUsers();
</script>
</body>
</html>