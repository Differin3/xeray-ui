// Xeray UI - API функции

const API_BASE_URL = '../api';

// Базовый запрос к API
async function apiRequest(endpoint, options = {}) {
    try {
        const url = `${API_BASE_URL}/${endpoint}`;
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                ...options.headers
            },
            ...options
        });

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return await response.json();
    } catch (error) {
        console.error('API request failed:', error);
        throw error;
    }
}

// Получение списка серверов
export async function getServers() {
    return apiRequest('servers');
}

// Добавление сервера
export async function addServer(serverData) {
    return apiRequest('servers', {
        method: 'POST',
        body: JSON.stringify(serverData)
    });
}

// Обновление сервера
export async function updateServer(serverId, serverData) {
    return apiRequest(`servers/${serverId}`, {
        method: 'PUT',
        body: JSON.stringify(serverData)
    });
}

// Удаление сервера
export async function deleteServer(serverId) {
    return apiRequest(`servers/${serverId}`, {
        method: 'DELETE'
    });
}

// Получение списка пользователей
export async function getUsers() {
    return apiRequest('users');
}

// Добавление пользователя
export async function addUser(userData) {
    return apiRequest('users', {
        method: 'POST',
        body: JSON.stringify(userData)
    });
}

// Обновление пользователя
export async function updateUser(userId, userData) {
    return apiRequest(`users/${userId}`, {
        method: 'PUT',
        body: JSON.stringify(userData)
    });
}

// Удаление пользователя
export async function deleteUser(userId) {
    return apiRequest(`users/${userId}`, {
        method: 'DELETE'
    });
}

// Получение списка inbounds
export async function getInbounds() {
    return apiRequest('inbounds');
}

// Добавление inbound
export async function addInbound(inboundData) {
    return apiRequest('inbounds', {
        method: 'POST',
        body: JSON.stringify(inboundData)
    });
}

// Обновление inbound
export async function updateInbound(inboundId, inboundData) {
    return apiRequest(`inbounds/${inboundId}`, {
        method: 'PUT',
        body: JSON.stringify(inboundData)
    });
}

// Удаление inbound
export async function deleteInbound(inboundId) {
    return apiRequest(`inbounds/${inboundId}`, {
        method: 'DELETE'
    });
}

// Получение списка outbounds
export async function getOutbounds() {
    return apiRequest('outbounds');
}

// Добавление outbound
export async function addOutbound(outboundData) {
    return apiRequest('outbounds', {
        method: 'POST',
        body: JSON.stringify(outboundData)
    });
}

// Обновление outbound
export async function updateOutbound(outboundId, outboundData) {
    return apiRequest(`outbounds/${outboundId}`, {
        method: 'PUT',
        body: JSON.stringify(outboundData)
    });
}

// Удаление outbound
export async function deleteOutbound(outboundId) {
    return apiRequest(`outbounds/${outboundId}`, {
        method: 'DELETE'
    });
}

// Получение статистики
export async function getStats() {
    return apiRequest('stats');
}

// Получение системных логов
export async function getLogs() {
    return apiRequest('logs');
}

// Получение настроек
export async function getSettings() {
    return apiRequest('settings');
}

// Обновление настроек
export async function updateSettings(settingsData) {
    return apiRequest('settings', {
        method: 'PUT',
        body: JSON.stringify(settingsData)
    });
}
