// Загрузчик компонентов для Xeray UI

document.addEventListener('DOMContentLoaded', function() {
    const loads = [
        loadComponent('navbar-container', 'frontend/components/navbar.html'),
        loadComponent('sidebar-container', 'frontend/components/sidebar.html'),
        loadComponent('content-container', 'frontend/components/overview.html'),
        loadComponent('modals-container', 'frontend/components/modals.html')
    ];

    Promise.all(loads)
        .then(() => {
            const event = new CustomEvent('componentsLoaded');
            window.dispatchEvent(event);
        })
        .catch(() => {
            const event = new CustomEvent('componentsLoaded');
            window.dispatchEvent(event);
        });
});

// Функция загрузки компонента
function loadComponent(containerId, componentPath) {
    return fetch(componentPath)
        .then(response => {
            if (!response.ok) throw new Error(`Failed to load ${componentPath}`);
            return response.text();
        })
        .then(html => {
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = html;
                initComponentEvents(containerId);
            }
        })
        .catch(error => {
            console.error('Ошибка загрузки компонента:', error);
            const container = document.getElementById(containerId);
            if (container) {
                container.innerHTML = `<div class="alert alert-warning m-3">Не удалось загрузить компонент: ${componentPath}</div>`;
            }
        });
}

// Инициализация событий компонентов
function initComponentEvents(containerId) {
    switch(containerId) {
        case 'navbar-container':
            // Ивенты для навбара
            break;
        case 'sidebar-container':
            // Ивенты для сайдбара
            break;
        case 'content-container':
            // Ивенты для контента
            break;
        case 'modals-container':
            // Ивенты для модальных окон
            break;
    }
}