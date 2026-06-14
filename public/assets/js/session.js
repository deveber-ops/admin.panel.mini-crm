// Конфигурационные константы
const SESSION_WARNING_TIME = 300; // 5 минут в секундах
const CHECK_INTERVAL = 30000; // 30 секунд для проверки сессии
const REQUEST_TIMEOUT = 5000; // 5 секунд таймаут запроса

// Глобальные переменные
let sessionInterval;
let warningInterval;
let remainingTime = 0;
let warningModal = null;
let notificationModal = null;
let isChecking = false;
let lastCheckTime = 0;
let isInitialized = false;

// Основные функции

function initializeSessionManager() {
    // Инициализация модальных окон
    notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));
    warningModal = new bootstrap.Modal(document.getElementById('sessionWarningModal'));

    // Проверка поддержки уведомлений
    if ('Notification' in window && Notification.permission !== 'granted') {
        showNotificationRequestModal();
    }

    // Запуск проверки сессии
    initSessionChecker();

    // Настройка обработчиков активности
    setupActivityListeners();

    // Настройка обработчиков кнопок
    document.getElementById('allowNotificationsBtn').addEventListener('click', requestNotificationPermission);
    document.getElementById('continueSessionBtn').addEventListener('click', extendSession);
    document.getElementById('logoutNowBtn').addEventListener('click', handleSessionExpired);
}

function initSessionChecker() {
    if (isInitialized) return;
    isInitialized = true;
    
    checkAuthStatus();
    sessionInterval = setInterval(checkAuthStatus, CHECK_INTERVAL);
}

async function checkAuthStatus() {
    if (isChecking || (Date.now() - lastCheckTime < CHECK_INTERVAL / 2)) {
        return;
    }

    isChecking = true;
    lastCheckTime = Date.now();

    try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), REQUEST_TIMEOUT);

        const response = await fetch('/checkAuth', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({}),
            signal: controller.signal
        });

        clearTimeout(timeoutId);

        if (response.status === 401) {
            handleSessionExpired();
            return;
        }

        if (response.ok) {
            const data = await response.json();
            if (data?.seconds) {
                
                if (data.seconds < SESSION_WARNING_TIME && data.seconds > 0) {
                    showSessionWarning(data.seconds);
                }
            }
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            console.error('Ошибка проверки авторизации:', error);
        }
    } finally {
        isChecking = false;
    }
}

function showNotificationRequestModal() {
    notificationModal.show();
}

async function requestNotificationPermission() {
    try {
        const permission = await Notification.requestPermission();
        
        if (permission === 'granted') {
            ShowMsg('Разрешение получено. Приятной работы!', 'success');
            notificationModal.hide();
        } else {
            ShowMsg('Для продолжения работы разрешите уведомления!', 'warning');
        }
    } catch (error) {
        ShowMsg('Ошибка запроса, обратитесь к администратору!', 'error');
    }
}

function showSessionWarning(seconds) {
    updateWarningTimer(seconds);
    
    if (document.hidden && Notification.permission === 'granted') {
        new Notification('Сессия скоро закончится', {
            body: formatNotificationTime(seconds),
            requireInteraction: true
        });
    }
    
    if (!warningModal._isShown) {
        warningModal.show();
        startWarningTimer();
    }
}

function startWarningTimer() {
    if (warningInterval) clearInterval(warningInterval);
    
    warningInterval = setInterval(() => {
        if (!warningModal._isShown) {
            clearInterval(warningInterval);
            return;
        }
        
        const timeString = document.getElementById('warningTimer').textContent;
        const currentSeconds = parseTimeString(timeString);
        
        if (currentSeconds > 0) {
            updateWarningTimer(currentSeconds - 1);
        } else {
            clearInterval(warningInterval);
            handleSessionExpired();
        }
    }, 1000);
}

function parseTimeString(timeString) {
    if (timeString.includes('ч.')) {
        const parts = timeString.split(' ');
        const hours = parseInt(parts[0]);
        const minutes = parseInt(parts[1]);
        const seconds = parseInt(parts[2]);
        return (hours * 3600) + (minutes * 60) + seconds;
    } else {
        const parts = timeString.split(' ');
        const minutes = parseInt(parts[0]);
        const seconds = parseInt(parts[1]);
        return (minutes * 60) + seconds;
    }
}

function updateWarningTimer(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    let formattedTime;
    
    if (hours > 0) {
        formattedTime = `${hours}ч. ${minutes.toString().padStart(2, '0')}мин. ${seconds.toString().padStart(2, '0')}сек.`;
    } else {
        formattedTime = `${minutes}мин. ${seconds.toString().padStart(2, '0')}сек.`;
    }
    
    document.getElementById('warningTimer').textContent = formattedTime;
}

function formatNotificationTime(totalSeconds) {
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    if (hours > 0) {
        return `${hours}ч ${minutes}мин ${seconds}сек`;
    }
    return `${minutes}мин ${seconds}сек`;
}

async function extendSession() {
    warningModal.hide();
    try {
        const response = await fetch('/updateSession', {
            method: 'POST'
        });
        const data = await response.json();
        
        if (data.success) {
            checkAuthStatus();
        }
    } catch (error) {
        console.error('Ошибка продления сессии:', error);
    }
}

function handleSessionExpired() {
    if (sessionInterval) clearInterval(sessionInterval);
    if (warningInterval) clearInterval(warningInterval);
    window.location.href = '/logout';
}

function setupActivityListeners() {
    const events = ['click', 'mousemove', 'keypress', 'scroll'];
    const handler = () => {
        if (remainingTime > 0) {
            checkAuthStatus();
        }
    };

    events.forEach(event => {
        document.addEventListener(event, handler, { passive: true });
    });
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
    if (typeof bootstrap !== 'undefined') {
        initializeSessionManager();
    } else {
        console.error('Bootstrap не загружен!');
    }
});