/**
 * Application Main JavaScript File for SmartFace Attendance System
 */

document.addEventListener('DOMContentLoaded', () => {
    // Sidebar Toggle Functionality
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.getElementById('sidebar');
    if (sidebarCollapse && sidebar) {
        sidebarCollapse.addEventListener('click', () => {
            sidebar.classList.toggle('active');
        });
    }

    // Initialize Digital Clock if element exists
    const clockElement = document.getElementById('liveClock');
    if (clockElement) {
        updateClock();
        setInterval(updateClock, 1000);
    }

    // Auto-dismiss Alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

function updateClock() {
    const clockElement = document.getElementById('liveClock');
    const dateElement = document.getElementById('liveDate');
    if (clockElement) {
        const now = new Date();
        clockElement.textContent = now.toLocaleTimeString('en-US', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        if (dateElement) {
            dateElement.textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        }
    }
}

// Toast notification helper
function showToast(message, type = 'info') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const bgClass = type === 'success' ? 'bg-success text-white' : 
                    type === 'danger' ? 'bg-danger text-white' : 
                    type === 'warning' ? 'bg-warning text-dark' : 'bg-info text-white';

    const toastHtml = `
        <div class="toast align-items-center ${bgClass} border-0 show" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = toastHtml.trim();
    const toastEl = tempDiv.firstChild;
    toastContainer.appendChild(toastEl);

    setTimeout(() => {
        toastEl.remove();
    }, 4000);
}
