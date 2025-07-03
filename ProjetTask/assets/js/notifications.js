/**
 * notifications.js - Gestion des notifications et alertes en temps réel
 */

let notificationsSocket;

document.addEventListener('DOMContentLoaded', function () {
    initNotificationBadge();
    initNotificationDropdown();

    // Tentative de connexion WebSocket si l'utilisateur est connecté
    if (document.querySelector('.topbar-user')) {
        initNotificationSocket();
    }
});

/**
 * Initialise le badge de notification et sa mise à jour
 */
function initNotificationBadge() {
    // Mettre à jour périodiquement le compteur de notifications
    setInterval(updateNotificationCount, 60000); // Toutes les minutes
}

/**
 * Met à jour le compteur de notifications non lues
 */
function updateNotificationCount() {
    fetch('/api/notifications/count', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            const badge = document.querySelector('.badge-notification');

            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                } else {
                    const notificationIcon = document.querySelector('.topbar-action[title="Notifications"]');
                    if (notificationIcon) {
                        notificationIcon.innerHTML += `<span class="badge bg-danger badge-notification">${data.count}</span>`;
                    }
                }
            } else {
                if (badge) {
                    badge.remove();
                }
            }
        })
        .catch(error => {
            console.error('Erreur lors de la récupération du nombre de notifications:', error);
        });
}

/**
 * Initialise le dropdown des notifications
 */
function initNotificationDropdown() {
    const notificationIcon = document.querySelector('.topbar-action[title="Notifications"]');

    if (notificationIcon) {
        notificationIcon.addEventListener('click', function (e) {
            e.preventDefault();

            const dropdown = document.querySelector('#notification-dropdown');

            // Si le dropdown existe déjà, le basculer
            if (dropdown) {
                dropdown.classList.toggle('show');
                return;
            }

            // Créer le dropdown
            const newDropdown = document.createElement('div');
            newDropdown.id = 'notification-dropdown';
            newDropdown.className = 'dropdown-menu dropdown-menu-end notification-dropdown';
            newDropdown.innerHTML = `
                <div class="dropdown-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Notifications</h6>
                    <a href="/notifications" class="text-muted small">Voir tout</a>
                </div>
                <div class="notification-list">
                    <div class="text-center py-3">
                        <div class="spinner"></div>
                        <p class="text-muted mt-2">Chargement des notifications...</p>
                    </div>
                </div>
            `;

            document.body.appendChild(newDropdown);
            newDropdown.classList.add('show');
            newDropdown.style.position = 'absolute';

            // Positionner le dropdown
            const rect = notificationIcon.getBoundingClientRect();
            newDropdown.style.top = (rect.bottom + window.scrollY) + 'px';
            newDropdown.style.right = (window.innerWidth - rect.right) + 'px';

            // Charger les notifications
            fetch('/api/notifications/recent', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const notificationList = newDropdown.querySelector('.notification-list');

                    if (data.notifications && data.notifications.length > 0) {
                        notificationList.innerHTML = '';

                        data.notifications.forEach(notification => {
                            notificationList.innerHTML += `
                            <a href="${notification.url}" class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                                <div class="notification-icon ${notification.type}">
                                    <i class="fas fa-${getNotificationIcon(notification.type)}"></i>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">${notification.title}</div>
                                    <div class="notification-text">${notification.message}</div>
                                    <div class="notification-time">${notification.time}</div>
                                </div>
                                <button class="notification-mark-read" title="Marquer comme lu">
                                    <i class="fas fa-check"></i>
                                </button>
                            </a>
                        `;
                        });

                        // Ajouter les gestionnaires d'événements
                        newDropdown.querySelectorAll('.notification-mark-read').forEach(button => {
                            button.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();

                                const notificationItem = this.closest('.notification-item');
                                const notificationId = notificationItem.dataset.id;

                                markNotificationAsRead(notificationId, notificationItem);
                            });
                        });
                    } else {
                        notificationList.innerHTML = `
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-bell-slash fa-2x mb-2"></i>
                            <p>Aucune notification</p>
                        </div>
                    `;
                    }

                    // Ajouter lien pour marquer toutes comme lues
                    if (data.unreadCount > 0) {
                        newDropdown.innerHTML += `
                        <div class="dropdown-footer">
                            <button class="btn btn-sm btn-light w-100" id="mark-all-read">
                                Marquer tout comme lu
                            </button>
                        </div>
                    `;

                        document.getElementById('mark-all-read').addEventListener('click', function () {
                            markAllNotificationsAsRead();
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des notifications:', error);
                    const notificationList = newDropdown.querySelector('.notification-list');
                    notificationList.innerHTML = `
                    <div class="text-center py-3 text-danger">
                        <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                        <p>Erreur lors du chargement des notifications</p>
                    </div>
                `;
                });

            // Fermer le dropdown si on clique ailleurs
            document.addEventListener('click', function closeDropdown(e) {
                if (!newDropdown.contains(e.target) && !notificationIcon.contains(e.target)) {
                    newDropdown.classList.remove('show');
                    document.removeEventListener('click', closeDropdown);

                    // Supprimer le dropdown après fermeture (pour le recréer à jour la prochaine fois)
                    setTimeout(() => {
                        if (newDropdown.parentNode) {
                            newDropdown.parentNode.removeChild(newDropdown);
                        }
                    }, 300);
                }
            });
        });
    }
}

/**
 * Marque une notification comme lue
 */
function markNotificationAsRead(notificationId, notificationElement) {
    fetch(`/api/notifications/${notificationId}/read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'interface
                notificationElement.classList.remove('unread');
                updateNotificationCount();
            }
        })
        .catch(error => {
            console.error('Erreur lors du marquage de la notification:', error);
        });
}

/**
 * Marque toutes les notifications comme lues
 */
function markAllNotificationsAsRead() {
    fetch('/api/notifications/mark-all-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'interface
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                });

                const badge = document.querySelector('.badge-notification');
                if (badge) {
                    badge.remove();
                }

                const markAllButton = document.getElementById('mark-all-read');
                if (markAllButton) {
                    markAllButton.parentNode.remove();
                }

                showToast('Toutes les notifications ont été marquées comme lues', 'success');
            }
        })
        .catch(error => {
            console.error('Erreur lors du marquage des notifications:', error);
            showToast('Erreur lors du marquage des notifications', 'error');
        });
}

/**
 * Initialise la connexion WebSocket pour les notifications en temps réel
 */
function initNotificationSocket() {
    // Vérifier si la fonctionnalité WebSocket est disponible
    if (!window.WebSocket) {
        console.warn('WebSocket n\'est pas supporté par ce navigateur');
        return;
    }

    // Créer la connexion WebSocket
    const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
    const wsUrl = `${protocol}//${window.location.host}/ws/notifications`;

    notificationsSocket = new WebSocket(wsUrl);

    notificationsSocket.onopen = function () {
        console.log('Connexion WebSocket établie pour les notifications');
    };

    notificationsSocket.onmessage = function (event) {
        try {
            const data = JSON.parse(event.data);

            if (data.type === 'notification') {
                // Afficher la notification
                showRealTimeNotification(data.notification);

                // Mettre à jour le compteur
                updateNotificationCount();
            }
        } catch (error) {
            console.error('Erreur lors du traitement du message WebSocket:', error);
        }
    };

    notificationsSocket.onclose = function () {
        console.log('Connexion WebSocket fermée pour les notifications');

        // Tenter de se reconnecter après un délai
        setTimeout(initNotificationSocket, 5000);
    };

    notificationsSocket.onerror = function (error) {
        console.error('Erreur WebSocket:', error);
    };
}

/**
 * Affiche une notification en temps réel
 */
function showRealTimeNotification(notification) {
    // Afficher une notification push si supportée par le navigateur
    if ('Notification' in window && Notification.permission === 'granted') {
        const notif = new Notification(notification.title, {
            body: notification.message,
            icon: '/img/logo.png'
        });

        notif.onclick = function () {
            window.focus();
            window.location.href = notification.url;
        };
    }

    // Afficher également un toast
    showToast(notification.message, getNotificationTypeForToast(notification.type), 10000, notification.title);
}

/**
 * Récupère l'icône à afficher pour un type de notification
 */
function getNotificationIcon(type) {
    switch (type) {
        case 'task':
            return 'tasks';
        case 'project':
            return 'project-diagram';
        case 'user':
            return 'user';
        case 'comment':
            return 'comment';
        case 'system':
            return 'cog';
        case 'alert':
            return 'exclamation-triangle';
        default:
            return 'bell';
    }
}

/**
 * Convertit le type de notification en type de toast
 */
function getNotificationTypeForToast(type) {
    switch (type) {
        case 'alert':
            return 'error';
        case 'system':
            return 'info';
        case 'task':
        case 'project':
            return 'success';
        default:
            return 'info';
    }
}