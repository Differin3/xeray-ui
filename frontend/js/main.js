// Xeray UI - Основной JavaScript файл

function initializeApp() {
    initNavigation();
    initNetworkMap();
    initCharts();
    initEventHandlers();
    initAjaxHandlers();
    loadInitialData();
}

// Initialize app after components are loaded (PHP renders them server-side)
document.addEventListener('DOMContentLoaded', function() {
    // Components are already in DOM from PHP, initialize immediately
    initializeApp();
});

// Инициализация навигации (PHP handles page switching via ?section=)
function initNavigation() {
    // Navigation is handled by PHP server-side routing
    // This function can be used for any client-side navigation enhancements
    console.log('Navigation initialized - using PHP routing');
}

// Инициализация AJAX обработчиков
function initAjaxHandlers() {
    // Обработчики для кнопок действий
    document.addEventListener('click', function(e) {
        if (e.target.matches('[data-action]')) {
            e.preventDefault();
            const action = e.target.getAttribute('data-action');
            const id = e.target.getAttribute('data-id');
            handleAction(action, id);
        }
    });
    
    // Обработчики для форм
    document.addEventListener('submit', function(e) {
        if (e.target.matches('[data-ajax-form]')) {
            e.preventDefault();
            handleFormSubmit(e.target);
        }
    });
}

// Инициализация сетевой карты
function initNetworkMap() {
    const container = document.getElementById('networkCanvas');
    if (!container) return;

    // Создаем узлы сети
    const nodes = new vis.DataSet([
        { id: 1, label: "Pixel Router 1", color: "#22d3ee", shape: "box" },
        { id: 2, label: "PC Pixel 1", color: "#3b82f6", shape: "box" },
        { id: 3, label: "PC Pixel 2", color: "#3b82f6", shape: "box" },
        { id: 4, label: "Laptop Pixel 1", color: "#10b981", shape: "box" },
        { id: 5, label: "ISP", color: "#a78bfa", shape: "circle" }
    ]);

    // Создаем соединения
    const edges = new vis.DataSet([
        { from: 1, to: 2, color: { color: "#00d2ff" }, arrows: "to" },
        { from: 1, to: 3, color: { color: "#00d2ff" }, arrows: "to" },
        { from: 1, to: 4, color: { color: "#e74c3c" }, arrows: "to", dashes: true },
        { from: 2, to: 5, color: { color: "#2ecc71" }, arrows: "to" }
    ]);

    // Настройки сети
    const data = {
        nodes: nodes,
        edges: edges
    };

    const options = {
        nodes: {
            font: { size: 14, face: 'Segoe UI', color: '#e7eef8' },
            shape: 'box',
            borderWidth: 1,
            color: { border: 'rgba(255,255,255,0.2)', background: '#1b232c', highlight: { background: '#1b232c', border: '#22d3ee' } }
        },
        edges: { width: 2, smooth: true, color: { color: '#00d2ff' } },
        physics: { stabilization: true, enabled: true },
        layout: { improvedLayout: true },
        interaction: { hover: true }
    };

    // Инициализация сети
    new vis.Network(container, data, options);
}

// Инициализация графиков
function initCharts() {
    // График трафика
    const trafficCtx = document.getElementById('trafficChart');
    if (trafficCtx) {
        new Chart(trafficCtx, {
            type: 'pie',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#3a7bd5',
                        '#00d2ff',
                        '#2ecc71',
                        '#f39c12'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}

// Инициализация обработчиков событий
function initEventHandlers() {
    // Обработчик кнопки обновления
    const refreshBtn = document.getElementById('refreshBtn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.classList.add('rotate');
            setTimeout(() => {
                this.classList.remove('rotate');
                // Обновляем данные
                const activeSection = document.querySelector('.sidebar .nav-link.active');
                if (activeSection) {
                    const targetId = activeSection.getAttribute('href').substring(1);
                    updateSectionData(targetId);
                }
            }, 1000);
        });
    }

    // Обработчики модальных окон
    initModalHandlers();
}

// Загрузка начальных данных
function loadInitialData() {
    // Загружаем данные серверов
    loadServers();

    // Загружаем данные пользователей
    loadUsers();

    // Загружаем системные логи
    loadSystemLogs();
}

// Загрузка данных серверов
function loadServers() {
    // Здесь будет запрос к API для получения списка серверов
    // Пока используем тестовые данные
    const servers = [
        { id: 1, name: 'Server-1', location: 'Нидерланды', status: 'online', ip: '192.168.1.101', ports: '443, 80, 8080' },
        { id: 2, name: 'Server-2', location: 'Германия', status: 'online', ip: '192.168.1.102', ports: '443, 80' },
        { id: 3, name: 'Server-3', location: 'США', status: 'offline', ip: '192.168.1.103', ports: '443, 8443' }
    ];

    updateServersList(servers);
}

// Загрузка данных пользователей
function loadUsers() {
    // Здесь будет запрос к API для получения списка пользователей
    // Пока используем тестовые данные
    const users = [
        { id: 1, name: 'Иван Петров', email: 'ivan@example.com', traffic: '12.5/50 GB', status: 'active' },
        { id: 2, name: 'Мария Сидорова', email: 'maria@example.com', traffic: '35.2/100 GB', status: 'active' },
        { id: 3, name: 'Алексей Иванов', email: 'alex@example.com', traffic: '78.9/50 GB', status: 'limited' }
    ];

    updateUsersList(users);
}

// Загрузка системных логов
function loadSystemLogs() {
    // Здесь будет запрос к API для получения логов
    // Пока используем тестовые данные
    const logs = [
        { type: 'info', message: 'Система готова к работе', time: getCurrentTime() },
        { type: 'success', message: 'Сервер "Server-1 (NL)" успешно обновлен', time: getCurrentTime() },
        { type: 'warning', message: 'Высокая нагрузка на сервер "Server-2 (DE)"', time: getCurrentTime() },
        { type: 'error', message: 'Потеряно соединение с сервером "Server-3 (US)"', time: getCurrentTime() }
    ];

    updateSystemLogs(logs);
}

// Обновление списка серверов
function updateServersList(servers) {
    const tbody = document.getElementById('serversTableBody');
    const statusList = document.getElementById('serverStatusList');

    if (tbody) {
        if (servers.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center">Нет добавленных серверов</td></tr>';
        } else {
            tbody.innerHTML = servers.map(server => `
                <tr>
                    <td>${server.id}</td>
                    <td>${server.name}</td>
                    <td>${server.location}</td>
                    <td><span class="badge bg-${server.status === 'online' ? 'success' : 'danger'}">${server.status === 'online' ? 'Online' : 'Offline'}</span></td>
                    <td>${server.ip}</td>
                    <td>${server.ports}</td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editServer(${server.id})"><i class="fas fa-cog"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteServer(${server.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    }

    if (statusList) {
        if (servers.length === 0) {
            statusList.innerHTML = '<p class="text-muted">Нет добавленных серверов</p>';
        } else {
            statusList.innerHTML = servers.map(server => `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="server-status status-${server.status}"></span>
                        ${server.name} (${server.location})
                    </div>
                    <span class="badge bg-${server.status === 'online' ? 'success' : 'danger'}">${server.status === 'online' ? 'Online' : 'Offline'}</span>
                </div>
            `).join('');
        }
    }

    // Обновляем статистику
    updateStats(servers.length, 0, 0, 0);
}

// Обновление списка пользователей
function updateUsersList(users) {
    const tbody = document.getElementById('usersTableBody');

    if (tbody) {
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center">Нет добавленных пользователей</td></tr>';
        } else {
            tbody.innerHTML = users.map(user => `
                <tr>
                    <td>${user.id}</td>
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>${user.traffic}</td>
                    <td><span class="badge bg-${user.status === 'active' ? 'success' : user.status === 'limited' ? 'warning' : 'secondary'}">${user.status === 'active' ? 'Активен' : user.status === 'limited' ? 'Лимит' : 'Неактивен'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline-primary" onclick="editUser(${user.id})"><i class="fas fa-edit"></i></button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(${user.id})"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
            `).join('');
        }
    }

    // Обновляем статистику
    updateStats(0, 0, users.length, 0);
}

// Обновление системных логов
function updateSystemLogs(logs) {
    const logsContainer = document.getElementById('systemLogs');

    if (logsContainer) {
        logsContainer.innerHTML = logs.map(log => `
            <div class="log-entry ${log.type}">
                <span>[${log.time}]</span> ${log.message}
            </div>
        `).join('');
    }
}

// Обновление статистики
function updateStats(servers, inbounds, users, traffic) {
    // Обновляем количество серверов
    const serversCard = document.querySelector('.stats-card i.fa-server').parentElement;
    if (serversCard) {
        serversCard.querySelector('h2').textContent = servers;
    }

    // Обновляем количество inbounds
    const inboundsCard = document.querySelector('.stats-card i.fa-plug').parentElement;
    if (inboundsCard) {
        inboundsCard.querySelector('h2').textContent = inbounds;
    }

    // Обновляем количество пользователей
    const usersCard = document.querySelector('.stats-card i.fa-users').parentElement;
    if (usersCard) {
        usersCard.querySelector('h2').textContent = users;
    }

    // Обновляем трафик
    const trafficCard = document.querySelector('.stats-card i.fa-network-wired').parentElement;
    if (trafficCard) {
        trafficCard.querySelector('h2').textContent = `${traffic} GB`;
    }
}

// Обновление данных для текущего раздела
function updateSectionData(sectionId) {
    switch(sectionId) {
        case 'overview':
            // Обновляем данные для обзора
            loadServers();
            loadUsers();
            loadSystemLogs();
            break;
        case 'servers':
            // Обновляем данные для серверов
            loadServers();
            break;
        case 'users':
            // Обновляем данные для пользователей
            loadUsers();
            break;
        case 'stats':
            // Обновляем данные для статистики
            updateStatsData();
            break;
    }
}

// Обновление данных статистики
function updateStatsData() {
    // Здесь будет запрос к API для получения статистики
    // Пока используем тестовые данные
    // ...
}

// Получение текущего времени
function getCurrentTime() {
    const now = new Date();
    return `${now.getHours().toString().padStart(2, '0')}:${now.getMinutes().toString().padStart(2, '0')}:${now.getSeconds().toString().padStart(2, '0')}`;
}

// Инициализация обработчиков модальных окон
function initModalHandlers() {
    // Модальное окно добавления сервера
    const addServerModal = document.getElementById('addServerModal');
    if (addServerModal) {
        addServerModal.addEventListener('shown.bs.modal', function() {
            // Фокус на первое поле
            const firstInput = addServerModal.querySelector('input');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Обработчик отправки формы
        const form = addServerModal.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                // Здесь будет отправка данных на сервер
                console.log('Добавление сервера...');

                // Закрываем модальное окно
                bootstrap.Modal.getInstance(addServerModal).hide();

                // Обновляем данные
                loadServers();

                // Добавляем лог
                addSystemLog('success', 'Новый сервер успешно добавлен');
            });
        }
    }

    // Модальное окно добавления пользователя
    const addUserModal = document.getElementById('addUserModal');
    if (addUserModal) {
        addUserModal.addEventListener('shown.bs.modal', function() {
            // Фокус на первое поле
            const firstInput = addUserModal.querySelector('input');
            if (firstInput) {
                firstInput.focus();
            }
        });

        // Обработчик отправки формы
        const form = addUserModal.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                // Здесь будет отправка данных на сервер
                console.log('Добавление пользователя...');

                // Закрываем модальное окно
                bootstrap.Modal.getInstance(addUserModal).hide();

                // Обновляем данные
                loadUsers();

                // Добавляем лог
                addSystemLog('success', 'Новый пользователь успешно добавлен');
            });
        }
    }
}

// Добавление системного лога
function addSystemLog(type, message) {
    const logsContainer = document.getElementById('systemLogs');
    if (logsContainer) {
        const logEntry = document.createElement('div');
        logEntry.className = `log-entry ${type}`;
        logEntry.innerHTML = `<span>[${getCurrentTime()}]</span> ${message}`;

        // Добавляем в начало списка
        logsContainer.insertBefore(logEntry, logsContainer.firstChild);

        // Ограничиваем количество логов
        const maxLogs = 50;
        while (logsContainer.children.length > maxLogs) {
            logsContainer.removeChild(logsContainer.lastChild);
        }
    }
}

// РЕЖИМ СТАТИЧНЫХ ДАННЫХ: любые запросы имитируются локально
async function makeAjaxRequest(section, action, data = {}) {
    // Исключаем любые сетевые обращения. Возвращаем успешный мок-ответ
    await new Promise(r => setTimeout(r, 250));
    return { success: true, section, action, data };
}

// Обработка действий
async function handleAction(action, id) {
    const section = getCurrentSection();
    
    try {
        switch (action) {
            case 'edit':
                await editItem(section, id);
                break;
            case 'delete':
                await deleteItem(section, id);
                break;
            case 'status':
                await toggleStatus(section, id);
                break;
        }
    } catch (error) {
        console.error('Action failed:', error);
    }
}

// Обработка отправки форм
async function handleFormSubmit(form) {
    const section = getCurrentSection();
    const action = form.getAttribute('data-action') || 'add';
    const formData = new FormData(form);
    
    // Имитация успешной операции без перезагрузки
    await makeAjaxRequest(section, action, Object.fromEntries(formData));
    showNotification('Операция выполнена успешно (статические данные)', 'success');
    const modal = bootstrap.Modal.getInstance(form.closest('.modal'));
    if (modal) modal.hide();
    addSystemLog('info', `Локальная операция: ${section}/${action}`);
}

// Получение текущего раздела
function getCurrentSection() {
    const urlParams = new URLSearchParams(window.location.search);
    return urlParams.get('section') || 'overview';
}

// Редактирование элемента
async function editItem(section, id) {
    // Здесь можно открыть модальное окно редактирования
    console.log(`Редактирование ${section} с ID:`, id);
    addSystemLog('info', `Начато редактирование ${section} с ID ${id}`);
}

// Удаление элемента
async function deleteItem(section, id) {
    if (confirm(`Вы уверены, что хотите удалить этот ${section}?`)) {
        await makeAjaxRequest(section, 'delete', { id });
        showNotification(`${section} удален (статические данные)`, 'success');
        addSystemLog('warning', `${section} с ID ${id} удален`);
    }
}

// Переключение статуса
async function toggleStatus(section, id) {
    await makeAjaxRequest(section, 'status', { id });
    showNotification('Статус обновлен (статические данные)', 'success');
}

// Показать уведомление
function showNotification(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-danger' : 
                     type === 'success' ? 'alert-success' : 'alert-info';
    
    const alert = document.createElement('div');
    alert.className = `alert ${alertClass} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 
                           type === 'success' ? 'check-circle' : 'info-circle'}"></i> 
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Вставляем уведомление в начало main-content
    const mainContent = document.querySelector('.main-content');
    if (mainContent) {
        mainContent.insertBefore(alert, mainContent.firstChild);
        
        // Автоматически скрываем через 5 секунд
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

// Функции редактирования и удаления (для обратной совместимости)
function editServer(id) {
    handleAction('edit', id);
}

function deleteServer(id) {
    handleAction('delete', id);
}

function editUser(id) {
    handleAction('edit', id);
}

function deleteUser(id) {
    handleAction('delete', id);
}

function editInbound(id) {
    handleAction('edit', id);
}

function deleteInbound(id) {
    handleAction('delete', id);
}

function editOutbound(id) {
    handleAction('edit', id);
}

function deleteOutbound(id) {
    handleAction('delete', id);
}

function editRoutingRule(id) {
    handleAction('edit', id);
}

function deleteRoutingRule(id) {
    handleAction('delete', id);
}
