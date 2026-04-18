// Solar-Powered IoT Irrigation & Fertilizing Management - CRUD Functionality

document.addEventListener("DOMContentLoaded", function() {
    initializeTasks();
    loadTasks();
});

let tasks = [
    { id: 1, type: 'irrigation', zone: 'Zone A', description: 'Auto-irrigation triggered by low moisture sensor', date: '2026-02-05', time: '06:00', status: 'pending', auto: true },
    { id: 2, type: 'fertilization', zone: 'Zone B', description: 'Scheduled fungicide fertilization - Pest detection alert', date: '2026-02-05', time: '08:00', status: 'completed', auto: true },
    { id: 3, type: 'irrigation', zone: 'Zone C', description: 'Evening irrigation - Solar powered pump', date: '2026-02-05', time: '18:00', status: 'pending', auto: true },
    { id: 4, type: 'fertilization', zone: 'Zone A', description: 'Pesticide fertilization - Automated schedule', date: '2026-02-06', time: '06:00', status: 'pending', auto: true },
    { id: 5, type: 'irrigation', zone: 'All Zones', description: 'Morning irrigation - Sensor triggered', date: '2026-02-06', time: '07:00', status: 'pending', auto: true }
];

let currentDeleteId = null;

function initializeTasks() {
    const storedTasks = localStorage.getItem('irrigationFertilizationTasks');
    if (storedTasks) {
        tasks = JSON.parse(storedTasks);
    } else {
        saveTasksToLocalStorage();
    }
}

function saveTasksToLocalStorage() {
    localStorage.setItem('irrigationFertilizationTasks', JSON.stringify(tasks));
}

// READ - Load Tasks
function loadTasks() {
    renderTasks(tasks);
}

function renderTasks(tasksToRender) {
    const tbody = document.getElementById('tasksTableBody');
    const emptyState = document.getElementById('emptyState');
    
    if (tasksToRender.length === 0) {
        tbody.innerHTML = '';
        emptyState.classList.remove('d-none');
        return;
    }
    
    emptyState.classList.add('d-none');
    tbody.innerHTML = tasksToRender.map(task => `
        <tr>
            <td><strong>${task.id}</strong></td>
            <td>
                <span class="badge ${task.type === 'irrigation' ? 'bg-primary' : 'bg-warning'}">
                    ${task.type === 'irrigation' ? '<i class="fa-solid fa-water me-1"></i>' : '<i class="fa-solid fa-spray-can me-1"></i>'}
                    ${task.type.charAt(0).toUpperCase() + task.type.slice(1)}
                </span>
            </td>
            <td>${task.zone}</td>
            <td>${task.description}</td>
            <td>${formatDate(task.date)}</td>
            <td>${formatTime(task.time)}</td>
            <td>
                <span class="badge ${task.status === 'completed' ? 'bg-success' : 'bg-secondary'}">
                    ${task.status.charAt(0).toUpperCase() + task.status.slice(1)}
                </span>
            </td>
            <td>
                ${task.auto ? '<span class="badge bg-info me-1"><i class="fa-solid fa-robot me-1"></i>Auto</span>' : '<span class="badge bg-secondary me-1"><i class="fa-solid fa-hand me-1"></i>Manual</span>'}
                <div class="btn-group btn-group-sm mt-1">
                    <button class="btn btn-outline-primary" onclick="viewTask(${task.id})" title="View">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                    <button class="btn btn-outline-success" onclick="editTask(${task.id})" title="Edit">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteTask(${task.id})" title="Delete">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

// CREATE - Add New Task
function openAddModal() {
    document.getElementById('taskModalLabel').textContent = 'Add New Task';
    document.getElementById('taskForm').reset();
    document.getElementById('taskId').value = '';
    
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('taskDate').value = today;
    
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

function saveTask() {
    const id = document.getElementById('taskId').value;
    const type = document.getElementById('taskType').value;
    const zone = document.getElementById('taskZone').value;
    const description = document.getElementById('taskDescription').value;
    const date = document.getElementById('taskDate').value;
    const time = document.getElementById('taskTime').value;
    const status = document.getElementById('taskStatus').value;
    
    if (!type || !zone || !description || !date || !time) {
        showToast('Please fill in all required fields', 'danger');
        return;
    }
    
    if (id) {
        const taskIndex = tasks.findIndex(t => t.id === parseInt(id));
        if (taskIndex !== -1) {
            tasks[taskIndex] = { id: parseInt(id), type, zone, description, date, time, status };
            showToast('Task updated successfully!', 'success');
        }
    } else {
        const newId = tasks.length > 0 ? Math.max(...tasks.map(t => t.id)) + 1 : 1;
        tasks.push({ id: newId, type, zone, description, date, time, status });
        showToast('Task added successfully!', 'success');
    }
    
    saveTasksToLocalStorage();
    filterTasks();
    
    bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
}

// UPDATE - Edit Task
function editTask(id) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;
    
    document.getElementById('taskModalLabel').textContent = 'Edit Task';
    document.getElementById('taskId').value = task.id;
    document.getElementById('taskType').value = task.type;
    document.getElementById('taskZone').value = task.zone;
    document.getElementById('taskDescription').value = task.description;
    document.getElementById('taskDate').value = task.date;
    document.getElementById('taskTime').value = task.time;
    document.getElementById('taskStatus').value = task.status;
    
    new bootstrap.Modal(document.getElementById('taskModal')).show();
}

// DELETE - Remove Task
function deleteTask(id) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;
    
    currentDeleteId = id;
    document.getElementById('deleteTaskInfo').innerHTML = `<strong>Task #${task.id}</strong> - ${task.type} in ${task.zone}`;
    
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function confirmDelete() {
    if (currentDeleteId !== null) {
        tasks = tasks.filter(t => t.id !== currentDeleteId);
        saveTasksToLocalStorage();
        filterTasks();
        showToast('Task deleted successfully!', 'success');
        currentDeleteId = null;
    }
    bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
}

// VIEW - View Task Details
function viewTask(id) {
    const task = tasks.find(t => t.id === id);
    if (!task) return;
    
    document.getElementById('viewTaskContent').innerHTML = `
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Task #${task.id}</h5>
                <hr>
                <p><strong>Type:</strong> ${task.type.charAt(0).toUpperCase() + task.type.slice(1)}</p>
                <p><strong>Zone:</strong> ${task.zone}</p>
                <p><strong>Description:</strong> ${task.description}</p>
                <p><strong>Date:</strong> ${formatDate(task.date)}</p>
                <p><strong>Time:</strong> ${formatTime(task.time)}</p>
                <p><strong>Status:</strong> <span class="badge ${task.status === 'completed' ? 'bg-success' : 'bg-secondary'}">${task.status.charAt(0).toUpperCase() + task.status.slice(1)}</span></p>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('viewTaskModal')).show();
}

// Filter & Search
function filterTasks() {
    const typeFilter = document.getElementById('filterType').value;
    const zoneFilter = document.getElementById('filterZone').value;
    const statusFilter = document.getElementById('filterStatus').value;
    const searchTerm = document.getElementById('searchTask').value.toLowerCase();
    
    const filteredTasks = tasks.filter(task => {
        const matchesType = typeFilter === 'all' || task.type === typeFilter;
        const matchesZone = zoneFilter === 'all' || task.zone === zoneFilter;
        const matchesStatus = statusFilter === 'all' || task.status === statusFilter;
        const matchesSearch = task.description.toLowerCase().includes(searchTerm) ||
                            task.zone.toLowerCase().includes(searchTerm) ||
                            task.type.toLowerCase().includes(searchTerm);
        return matchesType && matchesZone && matchesStatus && matchesSearch;
    });
    
    renderTasks(filteredTasks);
}

// Utilities
function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatTime(timeStr) {
    const [hours, minutes] = timeStr.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

function showToast(message, type = 'success') {
    const toastEl = document.getElementById('successToast');
    const toastMessage = document.getElementById('toastMessage');
    toastEl.classList.remove('text-bg-success', 'text-bg-danger');
    toastEl.classList.add(type === 'success' ? 'text-bg-success' : 'text-bg-danger');
    toastMessage.textContent = message;
    new bootstrap.Toast(toastEl).show();
}

function logout() {
    localStorage.removeItem('isLoggedIn');
    window.location.href = 'index.html';
}

