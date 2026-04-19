let currentPage = 1, currentSort = 'id', currentOrder = 'asc', currentSearch = '';
let pendingDeleteId = null, totalUsersCount = 0;
const limit = 8;

function fetchUsers() {
  const body = new URLSearchParams({
    action: 'fetch',
    search: currentSearch,
    page: currentPage,
    limit: limit,
    sort_column: currentSort,
    sort_order: currentOrder
  });

  fetch('', { method: 'POST', body })
    .then(r => r.json())
    .then(res => {
      totalUsersCount = parseInt(res.totalRows) || 0;
      renderTable(res.users);
      renderPagination(res.totalPages, res.currentPage, res.totalRows);
      updateStats(res.totalRows);
    })
    .catch(() => showToast('Failed to load users.', true));
}

function updateStats(total) {
  $('#stat-total, #stat-active').text(total);
  $('#stat-date').text(new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
  $('#user-count-meta').text(`${total} users total`);
}

function renderTable(users) {
  const $tbody = $('#usersBody');
  
  if (!users || users.length === 0) {
    $tbody.html(`
      <tr>
        <td colspan="6">
          <div class="empty-state">
            <i class="fas fa-users-slash"></i>
            <p>No users found. Try a different search term or add a new user.</p>
          </div>
        </td>
      </tr>`);
    return;
  }

  const rows = users.map(u => {
    const initial = (u.name || u.username || '?')[0].toUpperCase();
    const colors = ['#52C78A', '#72C9EA', '#F5C842', '#ff9090', '#A07050'];
    const color = colors[u.id % colors.length];
    
    return `
      <tr>
        <td><span class="user-id-badge">#${u.id}</span></td>
        <td>
          <div class="avatar-cell">
            <div class="row-avatar avatar-gradient" style="--avatar-color:${color};">${initial}</div>
            <div><div class="row-name">${escHtml(u.name)}</div></div>
          </div>
        </td>
        <td class="email-cell">${escHtml(u.email)}</td>
        <td><span class="username-badge">@${escHtml(u.username)}</span></td>
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
  
  $tbody.html(rows);
}

function renderPagination(totalPages, current, totalRows) {
  const offset = (current - 1) * limit;
  const showing = Math.min(offset + limit, totalRows);
  $('#pagination-info').text(`Showing ${offset + 1}–${showing} of ${totalRows} users`);

  const $ul = $('#pagination');
  let html = '';

  if (current > 1) {
    html += `<li><a onclick="goPage(${current - 1})"><i class="fas fa-chevron-left f-s-065"></i></a></li>`;
  }

  const start = Math.max(1, current - 2);
  const end = Math.min(totalPages, current + 2);

  if (start > 1) {
    html += `<li><a onclick="goPage(1)">1</a></li>`;
    if (start > 2) html += `<li><span>…</span></li>`;
  }

  for (let i = start; i <= end; i++) {
    html += `<li class="${i === current ? 'active' : ''}"><a onclick="goPage(${i})">${i}</a></li>`;
  }

  if (end < totalPages) {
    if (end < totalPages - 1) html += `<li><span>…</span></li>`;
    html += `<li><a onclick="goPage(${totalPages})">${totalPages}</a></li>`;
  }

  if (current < totalPages) {
    html += `<li><a onclick="goPage(${current + 1})"><i class="fas fa-chevron-right f-s-065"></i></a></li>`;
  }

  $ul.html(html);
}

function goPage(p) { 
  currentPage = p; 
  fetchUsers(); 
}

$('thead th[data-col]').on('click', function() {
  const col = $(this).data('col');
  
  if (currentSort === col) {
    currentOrder = currentOrder === 'asc' ? 'desc' : 'asc';
  } else {
    currentSort = col;
    currentOrder = 'asc';
  }

  $('thead th').removeClass('sorted');
  $(this).addClass('sorted');

  $('.sort-icon').attr('class', 'fas fa-sort sort-icon');
  $(this).find('.sort-icon').attr('class', `fas fa-sort-${currentOrder === 'asc' ? 'up' : 'down'} sort-icon`);

  currentPage = 1;
  fetchUsers();
});

let searchTimer;
function wireSearch(id) {
  $(`#${id}`).on('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      currentSearch = $(this).val().trim();
      currentPage = 1;

      // Sync both search inputs
      $('#searchInput, #topbarSearch').val(currentSearch);
      fetchUsers();
    }, 300);
  });
}
wireSearch('searchInput');
wireSearch('topbarSearch');

function openAddModal() {
  $('#add_name, #add_email, #add_username, #add_password').val('');
  openModal('addModal');
}

function submitAdd() {
  const data = {
    action: 'add',
    name: $('#add_name').val().trim(),
    email: $('#add_email').val().trim(),
    username: $('#add_username').val().trim(),
    password: $('#add_password').val()
  };

  if (!data.name || !data.email || !data.username || !data.password) {
    showToast('Please fill in all fields.', true);
    return;
  }

  fetch('', { method: 'POST', body: new URLSearchParams(data) })
    .then(() => {
      closeModal('addModal');
      fetchUsers();
      showToast('User created successfully!', false);
    })
    .catch(() => showToast('Error creating user.', true));
}

function openEditModal(id, name, email, username) {
  $('#edit_id').val(id);
  $('#edit_name').val(name);
  $('#edit_email').val(email);
  $('#edit_username').val(username);
  $('#edit_password').val('');
  openModal('editModal');
}

function submitEdit() {
  const data = {
    action: 'edit',
    id: $('#edit_id').val(),
    name: $('#edit_name').val().trim(),
    email: $('#edit_email').val().trim(),
    username: $('#edit_username').val().trim(),
    password: $('#edit_password').val()
  };

  if (!data.name || !data.email || !data.username) {
    showToast('Please fill in required fields.', true);
    return;
  }

  fetch('', { method: 'POST', body: new URLSearchParams(data) })
    .then(() => {
      closeModal('editModal');
      fetchUsers();
      showToast('User updated successfully!', false);
    })
    .catch(() => showToast('Error updating user.', true));
}

function openDeleteModal(id, username) {
  pendingDeleteId = id;
  $('#delete-username-display').text('@' + username);
  $('#deleteOverlay').addClass('open');
}

function closeDeleteModal() {
  pendingDeleteId = null;
  $('#deleteOverlay').removeClass('open');
}

function confirmDelete() {
  if (!pendingDeleteId) return;
  fetch('', { 
    method: 'POST', 
    body: new URLSearchParams({ action: 'delete', id: pendingDeleteId }) 
  })
    .then(() => {
      closeDeleteModal();
      fetchUsers();
      showToast('User deleted.', false);
    })
    .catch(() => showToast('Error deleting user.', true));
}

function openModal(id) { $(`#${id}`).addClass('open'); }
function closeModal(id) { $(`#${id}`).removeClass('open'); }

function exportCSV() {
  const rows = [['ID', 'Name', 'Email', 'Username']];
  $('#usersBody tr').each(function() {
    const $tds = $(this).find('td');
    if ($tds.length >= 4) {
      rows.push([
        $tds.eq(0).text().replace('#', '').trim(),
        $tds.eq(1).find('.row-name').text().trim() || $tds.eq(1).text().trim(),
        $tds.eq(2).text().trim(),
        $tds.eq(3).text().replace('@', '').trim()
      ]);
    }
  });

  const csvContent = rows.map(r => r.map(c => `"${c}"`).join(',')).join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `users_${new Date().toISOString().slice(0, 10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  
  showToast('CSV exported!', false);
}

function escHtml(s) { return $('<div>').text(s || '').html(); }
function escAttr(s) { return (s || '').replace(/'/g, "&#39;").replace(/"/g, "&quot;"); }

$(document).ready(() => {
  fetchUsers();

  $(document).on('click', '.modal-overlay, .delete-overlay', function(e) {
    if (e.target === this) {
      if ($(this).hasClass('delete-overlay')) {
        closeDeleteModal();
      } else {
        $(this).removeClass('open');
      }
    }
  });
});