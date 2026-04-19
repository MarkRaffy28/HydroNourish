const NOW = new Date();
const todayKey = fmtKey(NOW.getFullYear(), NOW.getMonth() + 1, NOW.getDate());
let calDate = new Date(NOW.getFullYear(), NOW.getMonth(), 1);
let events = window.SCHED_DATA || {};
let selectedDate = null;
let selectedType = 'fertigation';
let editType = 'fertigation';
let editingKey = null;
let editingId = null;
let isSaving = false;

const TC = {
  irrigation:  { color:'var(--water)',     pale:'var(--water-pale)',  icon:'fa-droplet',        pill:'irrigation',  badge:'badge-irr', label:'Irrigation'},
  fertigation: { color:'var(--solar)',     pale:'var(--solar-pale)',  icon:'fa-flask',          pill:'fertigation', badge:'badge-spr', label:'Fertigation'},
  harvest:     { color:'var(--red)',       pale:'var(--red-pale)',    icon:'fa-basket-shopping',pill:'harvest',     badge:'badge-har', label:'Harvest'},
  maintenance: { color:'var(--green-mid)', pale:'var(--green-pale)',  icon:'fa-wrench',         pill:'maintenance', badge:'badge-mnt', label:'Maintenance'},
};
const TYPES = Object.keys(TC);

function fmtKey(y, m, d) {
  return y + '-' + String(m).padStart(2,'0') + '-' + String(d).padStart(2,'0');
}

function refreshCalendar() {
  renderCalendar();
  if (selectedDate) renderDayPanel();
  renderUpcoming();
}

const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function renderCalendar() {
  const y = calDate.getFullYear(), mo = calDate.getMonth();
  $('#cal-month-label').text(MONTHS[mo] + ' ' + y);
  const grid = $('#cal-days-grid');
  grid.empty();
  const firstDay = new Date(y, mo, 1).getDay();
  const daysInM  = new Date(y, mo + 1, 0).getDate();
  const prevDays = new Date(y, mo,     0).getDate();

  for (let i = firstDay - 1; i >= 0; i--)
    renderCell(grid, prevDays - i, fmtKey(y, mo, prevDays - i), true);

  for (let d = 1; d <= daysInM; d++)
    renderCell(grid, d, fmtKey(y, mo + 1, d), false);

  const rem = (firstDay + daysInM) % 7;
  if (rem > 0) for (let d = 1; d <= 7 - rem; d++)
    renderCell(grid, d, fmtKey(y, mo + 2, d), true);
}

function renderCell(grid, d, key, otherMonth) {
  const cell = $('<div></div>');
  const cellDate = new Date(key + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  const isPast = cellDate < today;
  
  cell.attr('class', 'cal-cell' + (otherMonth ? ' other-month' : '') + (isPast ? ' past-date' : ''));
  if (key === todayKey && !otherMonth) cell.addClass('today');
  if (key === selectedDate)            cell.addClass('selected');

  const evs = events[key] || [];
  let pillsHTML = '';
  evs.slice(0, 3).forEach(ev => {
    const safe = ev.name.length > 14 ? ev.name.slice(0, 13) + '…' : ev.name;
    pillsHTML += `<div class="cal-event-pill ${TC[ev.type].pill}"><i class="fas ${TC[ev.type].icon}"></i> ${safe}</div>`;
  });
  if (evs.length > 3) 
    pillsHTML += `<div class="cal-event-pill maintenance">+${evs.length - 3} more</div>`;

  cell.html(`
    <div class="cal-cell-day">
      <span>${d}</span>
      ${key === todayKey && !otherMonth ? '<span class="cal-today-dot"></span>' : ''}
    </div>
    <div class="cal-cell-events">${pillsHTML}</div>
    <div class="cal-add-hint">+ Add task</div>`);

  cell.on('click', () => selectDate(key));
  grid.append(cell);
}

function selectDate(key) {
  const cellDate = new Date(key + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  if (cellDate < today) { showToast('Cannot select past dates.', true); return; }
  
  selectedDate = key;
  renderCalendar();
  renderDayPanel();
  const d = new Date(key + 'T00:00:00');
  $('#add-form-date-label').text(d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'}));
  $('#selected-day-label').text(d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric'}));
}

function renderDayPanel() {
  const list = $('#day-events-list');
  const evs  = events[selectedDate] || [];
  $('#selected-day-count').text(evs.length === 0 ? 'No tasks scheduled' : evs.length + ' task' + (evs.length !== 1 ? 's' : '') + ' scheduled');

  if (evs.length === 0) {
    list.html(`<div class="empty-state">
      <div class="empty-icon">🌱</div>
      <div class="empty-title">No tasks this day</div>
      <div class="empty-desc">Use the form below to schedule irrigation, fertigation, or other farm work.</div>
    </div>`);
    return;
  }

  list.empty();
  evs.forEach(ev => {
    const cfg  = TC[ev.type];
    const item = $('<div></div>').addClass('event-item');
    item.html(`
      <div class="event-dot ${cfg.pill}-bg"></div>
      <div class="event-body">
        <div class="event-title">${escHtml(ev.name)}</div>
        <div class="event-meta">
          <span class="upcoming-badge ${cfg.badge}">${cfg.label}</span>
          <span class="event-time"><i class="fas fa-clock m-r-4 f-s-065"></i>${ev.time}</span>
          ${ev.notes ? `<span class="p-color-text-muted m-l-5">· ${escHtml(ev.notes)}</span>` : ''}
        </div>
      </div>
      <div class="event-actions">
        <button type="button" class="btn-edit-sm"   title="Edit"   onclick="openEditModal('${selectedDate}', ${ev.id})"><i class="fas fa-pen"></i></button>
        <button type="button" class="btn-danger-sm" title="Delete" onclick="confirmDelete('${selectedDate}', ${ev.id}, '${escHtml(ev.name)}')"><i class="fas fa-trash-can"></i></button>
      </div>`);
    list.append(item);
  });
}

function renderUpcoming() {
  const list = $('#upcoming-list');
  const upcoming = [];
  for (let i = 0; i <= 14; i++) {
    const d = new Date(NOW.getFullYear(), NOW.getMonth(), NOW.getDate() + i);
    const key = fmtKey(d.getFullYear(), d.getMonth() + 1, d.getDate());
    if (events[key]) events[key].forEach(ev => upcoming.push({key, date: d, ev}));
  }
  upcoming.sort((a, b) => a.key.localeCompare(b.key) || a.ev.time.localeCompare(b.ev.time));
  $('#upcoming-count').text('Next 14 days · ' + upcoming.length + ' task' + (upcoming.length !== 1 ? 's' : ''));

  if (upcoming.length === 0) {
    list.html(`<div class="empty-state"><div class="empty-icon">📆</div><div class="empty-title">No upcoming tasks</div><div class="empty-desc">Schedule your first task above.</div></div>`);
    return;
  }

  list.empty();
  upcoming.forEach(({key, date, ev}) => {
    const cfg      = TC[ev.type];
    const dayLabel = key === todayKey ? 'Today' : date.toLocaleDateString('en-US',{month:'short',day:'numeric'});
    const item     = $('<div></div>').addClass('upcoming-item');
    item.html(`
      <div class="event-dot ${cfg.pill}-bg shrink-0"></div>
      <div class="flex-1 min-w-0">
        <div class="upcoming-title">${escHtml(ev.name)}</div>
        <div class="event-meta">
          <span class="upcoming-badge ${cfg.badge}">${cfg.label}</span>
          <span class="event-time"><i class="fas fa-clock m-r-4 f-s-065"></i>${ev.time}</span>
          <span class="p-color-text-muted m-l-5">· ${dayLabel}</span>
        </div>
      </div>
      <div class="flex-center-gap-5 shrink-0">
        <span class="upcoming-badge ${cfg.badge}">${cfg.label}</span>
        <button type="button" class="btn-edit-sm p-4-8 f-s-07" title="Edit" onclick="openEditModal('${key}', ${ev.id})">
          <i class="fas fa-pen"></i>
        </button>
      </div>`);
    list.append(item);
  });
}

function selectType(type) {
  selectedType = type;
  TYPES.forEach(t => {
    const btn = $('#type-' + t);
    if (!btn.length) return;
    TYPES.forEach(cls => btn.removeClass('selected-' + cls));
    if (t === type) btn.addClass('selected-' + type);
  });
}

function selectEditType(type) {
  editType = type;
  const cfg = TC[type];
  TYPES.forEach(t => {
    const btn = $('#edit-type-' + t);
    if (!btn.length) return;
    TYPES.forEach(cls => btn.removeClass('selected-' + cls));
    if (t === type) btn.addClass('selected-' + type);
  });
  const icon = $('#modal-type-icon');
  if (icon.length) { icon.css({ background: cfg.pale, color: cfg.color }); }
}

function addEvent() {
  if (!selectedDate) { showToast('Please select a date on the calendar first.', true); return; }
  
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const selected = new Date(selectedDate + 'T00:00:00');
  if (selected < today) { showToast('Cannot schedule tasks on past dates.', true); return; }
  
  const name = $('#task-name').val().trim();
  if (!name) { $('#task-name').trigger('focus'); showToast('Task name is required.', true); return; }
  if (isSaving) return;
  isSaving = true;

  const addBtn = $('#add-btn');
  addBtn.prop('disabled', true);
  addBtn.html('<i class="fas fa-spinner fa-spin"></i> Saving…');

  $.ajax({
    url: 'schedule.php',
    method: 'POST',
    data: {
      action : 'add',
      date   : selectedDate,
      type   : selectedType,
      name   : name,
      time   : $('#task-time').val() || '06:00',
      notes  : $('#task-notes').val().trim()
    },
    dataType: 'json'
  }).done(data => {
    if (data.success) {
      if (!events[selectedDate]) events[selectedDate] = [];
      events[selectedDate].push({
        id   : data.id,
        type : selectedType,
        name : name,
        time : $('#task-time').val() || '06:00',
        notes: $('#task-notes').val().trim()
      });
      events[selectedDate].sort((a, b) => a.time.localeCompare(b.time));
      clearForm();
      renderCalendar();
      renderDayPanel();
      renderUpcoming();
      showToast('✓ Task added successfully!');
    } else {
      showToast('Error: ' + (data.error || 'Could not save task.'), true);
    }
  }).fail(() => {
    showToast('Network error. Please try again.', true);
  }).always(() => {
    isSaving = false;
    addBtn.prop('disabled', false);
    addBtn.html('<i class="fas fa-plus"></i> Add Task');
  });
}

let deleteTargetKey = null;
let deleteTargetId = null;

function confirmDelete(key, id, name) {
  deleteTargetKey = key;
  deleteTargetId = id;
  $('#delete-task-name').text(name);
  $('#delete-modal').addClass('open');
  $('body').css('overflow', 'hidden');
}

function closeDeleteModal() {
  $('#delete-modal').removeClass('open');
  $('body').css('overflow', '');
  deleteTargetKey = null;
  deleteTargetId = null;
}

function handleDeleteBackdropClick(e) {
  if (e.target === $('#delete-modal')[0]) closeDeleteModal();
}

function confirmDeleteEvent() {
  if (deleteTargetKey === null || deleteTargetId === null) return;
  const key = deleteTargetKey;
  const id = deleteTargetId;
  closeDeleteModal();
  deleteEvent(key, id);
}

function deleteEvent(key, id) {
  const reqData = { action: 'delete', id: String(id) };
  console.log('Sending delete request:', $.param(reqData));
  
  $.ajax({
    url: 'schedule.php',
    method: 'POST',
    data: reqData,
    dataType: 'json'
  }).done((data, textStatus, jqXHR) => {
    console.log('Delete response status:', jqXHR.status);
    console.log('Delete response data:', data);
    if (data.success || data.deleted) {
      if (events[key] && Array.isArray(events[key])) {
        events[key] = events[key].filter(e => Number(e.id) !== Number(id));
        if (events[key].length === 0) {
          delete events[key];
        }
      }
      renderCalendar();
      renderDayPanel();
      renderUpcoming();
      showToast('Task deleted.');
    } else {
      showToast('Error: ' + (data.error || 'Could not delete task.'), true);
    }
  }).fail((jqXHR, textStatus, errorThrown) => {
    console.error('Delete error:', errorThrown);
    showToast('Network error.', true);
  });
}

function clearForm() {
  $('#task-name').val('');
  $('#task-time').val('06:00');
  $('#task-notes').val('');
}

function changeMonth(delta) {
  calDate.setMonth(calDate.getMonth() + delta);
  refreshCalendar();
}

function goToday() {
  calDate = new Date(NOW.getFullYear(), NOW.getMonth(), 1);
  selectDate(todayKey);
  refreshCalendar();
}

function openEditModal(key, id) {
  const ev = (events[key] || []).find(e => e.id === id);
  if (!ev) return;
  editingKey = key;
  editingId  = id;

  $('#edit-name').val(ev.name);
  $('#edit-time').val(ev.time);
  $('#edit-date').val(key);
  $('#edit-notes').val(ev.notes || '');
  selectEditType(ev.type);

  const d = new Date(key + 'T00:00:00');
  $('#modal-date-label').text(d.toLocaleDateString('en-US',{weekday:'long',month:'long',day:'numeric',year:'numeric'}));

  $('#edit-modal').addClass('open');
  $('body').css('overflow', 'hidden');
  setTimeout(() => $('#edit-name').trigger('focus'), 250);
}

function closeModal() {
  $('#edit-modal').removeClass('open');
  $('body').css('overflow', '');
  editingKey = null; editingId = null;
}

function handleBackdropClick(e) {
  if (e.target === $('#edit-modal')[0]) closeModal();
}

function saveEdit() {
  if (editingKey === null || editingId === null) return;
  
  const newDate = $('#edit-date').val() || editingKey;
  const editDate = new Date(newDate + 'T00:00:00');
  const today = new Date(); today.setHours(0,0,0,0);
  if (editDate < today) { showToast('Cannot reschedule to a past date.', true); return; }
  
  const name = $('#edit-name').val().trim();
  if (!name) { $('#edit-name').trigger('focus'); showToast('Task name is required.', true); return; }

  const time  = $('#edit-time').val() || '06:00';
  const notes = $('#edit-notes').val().trim();

  $.ajax({
    url: 'schedule.php',
    method: 'POST',
    data: {
      action: 'update',
      id    : editingId,
      date  : newDate,
      type  : editType,
      name  : name,
      time  : time,
      notes : notes
    },
    dataType: 'json'
  }).done(data => {
    if (data.success) {
      if (events[editingKey]) {
        events[editingKey] = events[editingKey].filter(e => e.id !== editingId);
        if (events[editingKey].length === 0) delete events[editingKey];
      }
      if (!events[newDate]) events[newDate] = [];
      events[newDate].push({ id: editingId, type: editType, name, time, notes });
      events[newDate].sort((a, b) => a.time.localeCompare(b.time));

      if (newDate !== editingKey) {
        const [py, pm] = newDate.split('-').map(Number);
        calDate = new Date(py, pm - 1, 1);
        selectedDate = newDate;
      }

      closeModal();
      renderCalendar();
      renderDayPanel();
      renderUpcoming();
      showToast('✓ Task updated successfully!');
    } else {
      showToast('Error: ' + (data.error || 'Could not update task.'), true);
    }
  }).fail(() => {
    showToast('Network error.', true);
  });
}

function deleteFromModal() {
  if (editingKey === null || editingId === null) return;
  const ev   = (events[editingKey] || []).find(e => e.id === editingId);
  const name = ev ? ev.name : 'this task';
  closeModal();
  setTimeout(() => confirmDelete(editingKey, editingId, name), 100);
}

$(document).on('keydown', e => {
  const editModal = $('#edit-modal');
  const deleteModal = $('#delete-modal');
  
  if (editModal.hasClass('open')) {
    if (e.key === 'Escape') closeModal();
    if (e.key === 'Enter' && e.ctrlKey) saveEdit();
  }
  
  if (deleteModal.hasClass('open')) {
    if (e.key === 'Escape') closeDeleteModal();
    if (e.key === 'Enter') confirmDeleteEvent();
  }
});

$(document).on('click', e => {
  const sidebar = $('#sidebar');
  if (sidebar.length && !sidebar[0].contains(e.target) && !$(e.target).closest('.mobile-toggle').length)
    sidebar.removeClass('open');
});

function escHtml(str) {
  return String(str)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

$(function() {
  selectType('fertigation');

  const preselectDate = window.SCHED_PRESELECT || '';
  if (preselectDate) {
    const [py, pm] = preselectDate.split('-').map(Number);
    calDate = new Date(py, pm - 1, 1);
    selectDate(preselectDate);
  } else {
    selectDate(todayKey);
  }
  refreshCalendar();
});