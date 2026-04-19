<?php
  session_start();
  require 'config/db.php';

  if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
  }

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
      echo "User Added"; 
      exit;

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
      echo "User Updated"; 
      exit;

    } elseif($action == 'delete'){
      $id = $_POST['id'];
      $stmt = $conn->prepare("DELETE FROM users WHERE id=?");
      $stmt->bind_param("i", $id);
      $stmt->execute();
      echo "User Deleted"; 
      exit;

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

      echo json_encode([
        'users'=>$users,
        'totalPages'=>$totalPages,
        'currentPage'=>$page,
        'totalRows'=>$totalRows
      ]);
      exit;
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Users | HydroNourish</title>
    <link rel="shortcut icon" href="public/tomato.png" type="image/png">

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;0,800;1,400;1,600&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Fira+Code:wght@300;400;500&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />

    <link rel="stylesheet" href="css/app.css">
    <link rel="stylesheet" href="css/mis.css">
    
    <script src="js/jquery.min.js"></script>
    <script src="js/app.js"></script>
    <script src="js/mis.js"></script>
  </head>
  <body>
    <div class="layout">
      <?php 
        $active_page = 'users'; 
        include 'components/sidebar.php'; 
      ?>

      <div class="main">
        <?php
          $page_title = "User Management";
          $page_icon = "fa-users";
          include 'components/header.php';
        ?>

        <div class="page-content">
          <!-- PAGE HEADER -->
          <div class="page-header">
            <div class="hero-emoji">👤</div>
            <h1>Manage <span>Users</span></h1>
            <p>Add, edit, and manage system accounts for HydroNourish users.</p>
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
              <div class="stat-value red f-s-16" id="stat-date">—</div>
              <div class="stat-label">Last Updated</div>
            </div>
          </div>

          <!-- USER TABLE CARD -->
          <div class="fade-up-anim">
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
              <div id="print-title" class="d-none">
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

        <?php include "components/footer.php" ?>
      </div>
    </div>

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
  </body>
</html>