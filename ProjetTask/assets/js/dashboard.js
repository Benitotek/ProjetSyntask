/**
 * dashboard.js - Script principal pour le backoffice
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialiser les composants du tableau de bord
    // Gestion des notifications
    initNotifications();

    // Gestion du drag and drop des tâches
    initTaskDragAndDrop();

    // Gestion des filtres de tâches
    initTaskFilters();

    // Initialisation des tooltips et popovers Bootstrap
    initBootstrapComponents();

    // Gestion des formulaires modaux (pour éviter les soumissions accidentelles)
    initModalForms();

    // Gestion du mode sombre
    initDarkMode();
    initSidebar();
    initDataTables();
    initSearchFilters();
    initTooltips();
    initFormValidation();
    initNotifications();
});

/**
 * Initialise les fonctionnalités de notifications
 */
function initNotifications() {
    // Charger le compteur de notifications non lues
    refreshNotificationCount();

    // Mettre à jour le compteur toutes les 60 secondes
    setInterval(refreshNotificationCount, 60000);

    // Charger les notifications récentes dans le dropdown
    const notificationDropdown = document.getElementById('notification-dropdown');
    if (notificationDropdown) {
        notificationDropdown.addEventListener('show.bs.dropdown', loadRecentNotifications);
    }
}

/**
 * Met à jour le compteur de notifications non lues
 */
function refreshNotificationCount() {
    const notificationBadge = document.getElementById('notification-badge');
    if (!notificationBadge) return;

    fetch('/notifications/api/count')
        .then(response => response.json())
        .then(data => {
            if (data.count > 0) {
                notificationBadge.textContent = data.count;
                notificationBadge.classList.remove('d-none');
            } else {
                notificationBadge.classList.add('d-none');
            }
        })
        .catch(error => console.error('Erreur lors du chargement des notifications:', error));
}

/**
 * Charge les notifications récentes dans le dropdown
 */
function loadRecentNotifications() {
    const notificationContent = document.getElementById('notification-content');
    if (!notificationContent) return;

    // Afficher un indicateur de chargement
    notificationContent.innerHTML = `
        <div class="text-center p-3">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
        </div>
    `;

    fetch('/notifications/api/recent')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                // Afficher les notifications récentes
                let html = '';
                data.notifications.forEach(notification => {
                    const unreadClass = notification.estLue ? '' : 'unread';
                    html += `
                        <a href="${notification.lien || '#'}" class="dropdown-item notification-item ${unreadClass}">
                            <div class="notification-icon">
                                <i class="fas ${notification.iconClass}"></i>
                            </div>
                            <div class="notification-content">
                                <div class="notification-title">${notification.titre}</div>
                                <div class="notification-meta">${notification.dateCreation}</div>
                            </div>
                        </a>
                    `;
                });
                html += `
                    <div class="dropdown-divider"></div>
                    <a href="/notifications" class="dropdown-item text-center">
                        <i class="fas fa-bell me-1"></i> Voir toutes les notifications
                    </a>
                `;
                notificationContent.innerHTML = html;
            } else {
                // Aucune notification
                notificationContent.innerHTML = `
                    <div class="text-center p-3">
                        <i class="fas fa-bell-slash text-muted mb-2"></i>
                        <p class="mb-0 text-muted">Aucune notification</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Erreur lors du chargement des notifications:', error);
            notificationContent.innerHTML = `
                <div class="text-center p-3 text-danger">
                    <i class="fas fa-exclamation-circle mb-2"></i>
                    <p class="mb-0">Erreur lors du chargement des notifications</p>
                </div>
            `;
        });
}

/**
 * Initialise le drag and drop des tâches
 */
function initTaskDragAndDrop() {
    const taskLists = document.querySelectorAll('.task-list');
    if (taskLists.length === 0) return;

    // Vérifier si on est sur une page qui supporte le drag & drop
    const dragEnabled = document.querySelector('.kanban-board');
    if (!dragEnabled) return;

    // Initialiser le drag & drop pour chaque colonne de tâches
    taskLists.forEach(list => {
        const listId = list.getAttribute('data-list-id');
        const projectId = list.getAttribute('data-project-id');

        new Sortable(list, {
            group: 'tasks',
            animation: 150,
            ghostClass: 'task-ghost',
            chosenClass: 'task-chosen',
            dragClass: 'task-drag',
            onEnd: function (evt) {
                if (evt.from === evt.to) {
                    // Réorganisation dans la même liste
                    updateTaskPositions(evt.to.getAttribute('data-list-id'), Array.from(evt.to.children));
                } else {
                    // Déplacement vers une autre liste
                    const taskId = evt.item.getAttribute('data-task-id');
                    const newListId = evt.to.getAttribute('data-list-id');
                    const position = Array.from(evt.to.children).indexOf(evt.item);

                    moveTaskToList(taskId, newListId, position);
                    updateTaskPositions(evt.from.getAttribute('data-list-id'), Array.from(evt.from.children));
                    updateTaskPositions(evt.to.getAttribute('data-list-id'), Array.from(evt.to.children));
                }
            }
        });
    });
}

/**
 * Met à jour les positions des tâches dans une liste
 */
function updateTaskPositions(listId, tasks) {
    const positions = tasks.map((task, index) => ({
        taskId: task.getAttribute('data-task-id'),
        position: index
    }));

    fetch(`/task-list/${listId}/update-positions`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken()
        },
        body: JSON.stringify({ positions })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                console.error('Erreur lors de la mise à jour des positions:', data.error);
                showToast('Erreur lors de la mise à jour des positions.', 'danger');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la mise à jour des positions:', error);
            showToast('Erreur lors de la mise à jour des positions.', 'danger');
        });
}

/**
 * Déplace une tâche vers une autre liste
 */
function moveTaskToList(taskId, newListId, position) {
    fetch(`/task/${taskId}/move`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': getCSRFToken()
        },
        body: JSON.stringify({
            listId: newListId,
            position: position
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le statut de la tâche dans l'interface
                if (data.newStatus) {
                    const taskItem = document.querySelector(`[data-task-id="${taskId}"]`);
                    if (taskItem) {
                        const statusBadge = taskItem.querySelector('.task-status');
                        if (statusBadge) {
                            // Mettre à jour le badge de statut avec les nouvelles informations
                            statusBadge.textContent = data.newStatusLabel;
                            statusBadge.className = `task-status badge bg-${data.newStatusColor}`;
                        }
                    }
                }
            } else {
                console.error('Erreur lors du déplacement de la tâche:', data.error);
                showToast('Erreur lors du déplacement de la tâche.', 'danger');
                // Recharger la page pour rétablir l'état initial
                location.reload();
            }
        })
        .catch(error => {
            console.error('Erreur lors du déplacement de la tâche:', error);
            showToast('Erreur lors du déplacement de la tâche.', 'danger');
            // Recharger la page pour rétablir l'état initial
            location.reload();
        });
}

/**
 * Initialise les filtres de tâches
 */
function initTaskFilters() {
    const filterForm = document.getElementById('task-filter-form');
    if (!filterForm) return;

    // Appliquer les filtres lorsque les valeurs changent
    filterForm.querySelectorAll('select, input').forEach(input => {
        input.addEventListener('change', function () {
            filterForm.submit();
        });
    });

    // Bouton de réinitialisation des filtres
    const resetButton = document.getElementById('reset-filters');
    if (resetButton) {
        resetButton.addEventListener('click', function (e) {
            e.preventDefault();
            filterForm.querySelectorAll('select, input').forEach(input => {
                if (input.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
            });
            filterForm.submit();
        });
    }
}

/**
 * Initialise les composants Bootstrap
 */
function initBootstrapComponents() {
    // Initialiser les tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialiser les popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Initialise les formulaires dans les modaux
 */
function initModalForms() {
    document.querySelectorAll('.modal form').forEach(form => {
        form.addEventListener('keypress', function (e) {
            if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA') {
                // Empêcher la soumission du formulaire lors de l'appui sur Entrée sauf dans les textareas
                e.preventDefault();
            }
        });
    });
}

/**
 * Initialise la gestion du mode sombre
 */
function initDarkMode() {
    const darkModeToggle = document.getElementById('dark-mode-toggle');
    if (!darkModeToggle) return;

    // Vérifier la préférence de l'utilisateur
    const isDarkMode = localStorage.getItem('darkMode') === 'true';

    // Appliquer le mode sombre si activé
    if (isDarkMode) {
        document.body.classList.add('dark-mode');
        darkModeToggle.querySelector('.light-icon').classList.add('d-none');
        darkModeToggle.querySelector('.dark-icon').classList.remove('d-none');
    }

    // Gérer le changement de mode
    darkModeToggle.addEventListener('click', function () {
        document.body.classList.toggle('dark-mode');
        const isDark = document.body.classList.contains('dark-mode');

        // Sauvegarder la préférence
        localStorage.setItem('darkMode', isDark);

        // Mettre à jour les icônes
        darkModeToggle.querySelector('.light-icon').classList.toggle('d-none', isDark);
        darkModeToggle.querySelector('.dark-icon').classList.toggle('d-none', !isDark);
    });
}

/**
 * Récupère le token CSRF depuis les méta-données
 */
function getCSRFToken() {
    const metaToken = document.querySelector('meta[name="csrf-token"]');
    return metaToken ? metaToken.getAttribute('content') : '';
}

/**
 * Affiche une notification toast
 */
function showToast(message, type = 'info') {
    // Créer un élément toast
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        // Créer un conteneur si nécessaire
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
            </div>
        </div>
    `;

    // const toastContainer = document.getElementById('toast-container');
    // toastContainer.insertAdjacentHTML('beforeend', toastHtml);

    // Afficher le toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 3000
    });

    toast.show();

    // Supprimer l'élément une fois caché
    toastElement.addEventListener('hidden.bs.toast', function () {
        this.remove();
    });
}
/**
 * Gestion de la sidebar responsive
 */
function initSidebar() {
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');
        });

        // Fermer la sidebar si on clique en dehors
        document.addEventListener('click', function (event) {
            if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target) && sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
    }

    // Marquer le lien actif dans la sidebar
    const currentPath = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('.sidebar-link');

    sidebarLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href === currentPath || (href !== '/' && currentPath.startsWith(href))) {
            link.classList.add('active');
        }
    });
}

/**
 * Initialiser les tables de données avec recherche et pagination
 */
function initDataTables() {
    const tables = document.querySelectorAll('.data-table');

    tables.forEach(table => {
        if (typeof $.fn.DataTable === 'function') {
            $(table).DataTable({
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/fr-FR.json'
                },
                responsive: true,
                pageLength: 10,
                lengthMenu: [[5, 10, 25, 50, -1], [5, 10, 25, 50, "Tous"]],
                dom: '<"table-responsive"<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>>',
                initComplete: function () {
                    const api = this.api();
                    api.columns().every(function () {
                        const column = this;
                        const header = $(column.header());

                        if (header.hasClass('filterable')) {
                            const select = $('<select class="form-select form-select-sm"><option value="">Tout</option></select>')
                                .appendTo(header)
                                .on('click', function (e) {
                                    e.stopPropagation();
                                })
                                .on('change', function () {
                                    const val = $.fn.dataTable.util.escapeRegex($(this).val());
                                    column.search(val ? '^' + val + '$' : '', true, false).draw();
                                });

                            column.data().unique().sort().each(function (d) {
                                select.append('<option value="' + d + '">' + d + '</option>');
                            });
                        }
                    });
                }
            });
        } else {
            console.warn('DataTables non disponible. Veuillez inclure la bibliothèque.');

            // Fallback pour recherche simple si DataTables n'est pas disponible
            const searchInput = document.querySelector('#table-search');
            if (searchInput) {
                searchInput.addEventListener('keyup', function () {
                    const searchValue = this.value.toLowerCase();
                    const rows = table.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        let found = false;
                        const cells = row.querySelectorAll('td');

                        cells.forEach(cell => {
                            if (cell.textContent.toLowerCase().includes(searchValue)) {
                                found = true;
                            }
                        });

                        row.style.display = found ? '' : 'none';
                    });
                });
            }
        }
    });
}

/**
 * Initialiser les filtres de recherche pour les tables sans DataTables
 */
function initSearchFilters() {
    const searchInputs = document.querySelectorAll('.table-search-input');

    searchInputs.forEach(input => {
        if (input.dataset.target) {
            const targetTable = document.querySelector(input.dataset.target);

            if (targetTable) {
                input.addEventListener('keyup', function () {
                    const searchValue = this.value.toLowerCase();
                    const rows = targetTable.querySelectorAll('tbody tr');

                    rows.forEach(row => {
                        let found = false;
                        const cells = row.querySelectorAll('td');

                        cells.forEach(cell => {
                            if (cell.textContent.toLowerCase().includes(searchValue)) {
                                found = true;
                            }
                        });

                        row.style.display = found ? '' : 'none';
                    });
                });
            }
        }
    });

    // Filtres par statut
    const statutFilters = document.querySelectorAll('.statut-filter');

    statutFilters.forEach(filter => {
        filter.addEventListener('click', function (e) {
            e.preventDefault();

            const targetTable = document.querySelector(this.dataset.target);
            const statut = this.dataset.statut;

            if (targetTable) {
                // Marquer le filtre actif
                document.querySelectorAll('.statut-filter').forEach(f => {
                    f.classList.remove('active');
                });
                this.classList.add('active');

                // Filtrer les lignes
                const rows = targetTable.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    if (statut === 'all') {
                        row.style.display = '';
                    } else {
                        const statutCell = row.querySelector('.statut-cell');
                        if (statutCell && statutCell.dataset.statut === statut) {
                            row.style.display = '';
                        } else {
                            row.style.display = 'none';
                        }
                    }
                });
            }
        });
    });
}

/**
 * Initialiser les tooltips pour les éléments d'interface
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');

    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    } else {
        console.warn('Bootstrap Tooltip non disponible');
    }
}

/**
 * Validation des formulaires côté client
 */
function initFormValidation() {
    const forms = document.querySelectorAll('.needs-validation');

    forms.forEach(form => {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            form.classList.add('was-validated');
        }, false);
    });
}

/**
 * Système de notifications et toasts
 */
function initNotifications() {
    // Initialiser les toasts Bootstrap s'ils existent
    const toastElList = document.querySelectorAll('.toast');

    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const toastList = [...toastElList].map(toastEl => new bootstrap.Toast(toastEl));
        toastList.forEach(toast => toast.show());
    }
}

/**
 * Afficher un message toast personnalisé
 * @param {string} message - Message à afficher
 * @param {string} type - Type du toast (success, error, info, warning)
 * @param {number} duration - Durée d'affichage en ms (défaut: 5000)
 */
function showToast(message, type = 'info', duration = 5000) {
    const toastContainer = document.querySelector('.toast-container');

    // Créer le conteneur de toasts s'il n'existe pas
    if (!toastContainer) {
        const container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.setAttribute('role', 'alert');

    // Définir l'icône en fonction du type
    let icon = 'info-circle';
    if (type === 'success') icon = 'check-circle';
    if (type === 'error') icon = 'exclamation-circle';
    if (type === 'warning') icon = 'exclamation-triangle';

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas fa-${icon}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-message">${message}</div>
        </div>
        <button type="button" class="toast-close">
            <i class="fas fa-times"></i>
        </button>
    `;

    document.querySelector('.toast-container').appendChild(toast);

    // Gérer le bouton de fermeture
    toast.querySelector('.toast-close').addEventListener('click', function () {
        toast.remove();
    });

    // Supprimer automatiquement après la durée spécifiée
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => {
            toast.remove();
        }, 300);
    }, duration);
}

/**
 * Gestion des actions AJAX pour les opérations du backoffice
 */
function ajaxAction(url, method = 'POST', data = {}, successCallback = null, errorCallback = null) {
    // Ajouter le token CSRF si disponible
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        data._token = csrfToken.content;
    }

    // Afficher un indicateur de chargement
    const loadingOverlay = document.createElement('div');
    loadingOverlay.className = 'loading-overlay';
    loadingOverlay.innerHTML = '<div class="spinner"></div>';
    document.body.appendChild(loadingOverlay);

    // Effectuer la requête AJAX
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erreur réseau: ' + response.statut);
            }
            return response.json();
        })
        .then(data => {
            // Supprimer l'indicateur de chargement
            loadingOverlay.remove();

            if (data.success) {
                if (successCallback) {
                    successCallback(data);
                } else {
                    showToast(data.message || 'Opération réussie', 'success');
                }
            } else {
                if (errorCallback) {
                    errorCallback(data);
                } else {
                    showToast(data.message || 'Une erreur est survenue', 'error');
                }
            }
        })
        .catch(error => {
            // Supprimer l'indicateur de chargement
            loadingOverlay.remove();

            console.error('Erreur AJAX:', error);

            if (errorCallback) {
                errorCallback({ message: error.message });
            } else {
                showToast('Une erreur est survenue: ' + error.message, 'error');
            }
        });
}

/**
 * Gestion des confirmations pour les actions destructives
 */
function confirmAction(title, message, callback) {
    // Utiliser SweetAlert2 si disponible
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Confirmer',
            cancelButtonText: 'ANNULERRr'
        }).then((result) => {
            if (result.isConfirmed) {
                callback();
            }
        });
    } else {
        // Fallback sur confirm natif
        if (confirm(message)) {
            callback();
        }
    }
}

/**
 * Mise à jour en temps réel du statut des tâches et projects
 */
function updatestatut(element, url, statutValue) {
    const initialstatut = element.dataset.statut;
    const initialText = element.textContent;

    // Ajouter une classe de chargement
    element.classList.add('btn-loading');

    // Appeler l'API pour mettre à jour le statut
    ajaxAction(url, 'POST', {
        statut: statutValue
    }, function (data) {
        // Succès
        element.dataset.statut = statutValue;
        element.textContent = data.statutLabel || statutValue;

        // Mettre à jour les classes de statut
        element.className = 'badge'; // Réinitialiser les classes

        // Ajouter la classe appropriée selon le statut
        if (statutValue === 'EN_COURS') {
            element.classList.add('badge-primary');
        } else if (statutValue === 'TERMINER') {
            element.classList.add('badge-success');
        } else if (statutValue === 'EN_ATTENTE') {
            element.classList.add('badge-warning');
        }

        // Mettre à jour d'autres éléments de l'interface si nécessaire
        const parentRow = element.closest('tr');
        if (parentRow) {
            parentRow.classList.remove(`statut-${initialstatut}`);
            parentRow.classList.add(`statut-${statutValue}`);
        }

        showToast('Statut mis à jour avec succès', 'success');
    }, function (error) {
        // Erreur - restaurer l'état initial
        element.textContent = initialText;
        element.classList.remove('btn-loading');

        showToast('Erreur lors de la mise à jour du statut', 'error');
    });
}

/**
 * Gestion de l'assignation des utilisateurs
 */
function initUserAssignment() {
    const assignButtons = document.querySelectorAll('.btn-assign');

    assignButtons.forEach(button => {
        button.addEventListener('click', function () {
            const targetId = this.dataset.target;
            const entityType = this.dataset.type; // 'task' ou 'project'
            const entityId = this.dataset.id;

            // Charger la liste des utilisateurs disponibles via AJAX
            fetch(`/api/users/available?${entityType}=${entityId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    const userList = document.querySelector(`#${targetId} .user-list`);
                    userList.innerHTML = '';

                    data.users.forEach(user => {
                        userList.innerHTML += `
                        <div class="user-item" data-user-id="${user.id}" data-entity-id="${entityId}" data-entity-type="${entityType}">
                            <div class="user-avatar">${user.initials}</div>
                            <div class="user-info">
                                <div class="user-name">${user.name}</div>
                                <div class="user-email">${user.email}</div>
                            </div>
                        </div>
                    `;
                    });

                    // Ajouter les gestionnaires d'événements pour l'assignation
                    document.querySelectorAll('.user-item').forEach(item => {
                        item.addEventListener('click', function () {
                            const userId = this.dataset.userId;
                            const entityId = this.dataset.entityId;
                            const entityType = this.dataset.entityType;

                            assignUser(entityType, entityId, userId);
                        });
                    });

                    // Afficher le modal
                    const modal = new bootstrap.Modal(document.getElementById(targetId));
                    modal.show();
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des utilisateurs:', error);
                    showToast('Erreur lors du chargement des utilisateurs', 'error');
                });
        });
    });
}

/**
 * Assigner un utilisateur à une entité (tâche ou project)
 */
function assignUser(entityType, entityId, userId) {
    ajaxAction(`/api/${entityType}/${entityId}/assign`, 'POST', {
        userId: userId
    }, function (data) {
        // Fermer le modal
        const modal = bootstrap.Modal.getInstance(document.querySelector('.modal.show'));
        if (modal) modal.hide();

        // Mettre à jour l'UI
        const assigneeElement = document.querySelector(`.${entityType}-assignee[data-${entityType}-id="${entityId}"]`);
        if (assigneeElement) {
            assigneeElement.innerHTML = `
                <div class="user-avatar">${data.user.initials}</div>
                <div class="user-name">${data.user.name}</div>
            `;
        }

        showToast(`Utilisateur assigné avec succès`, 'success');
    });
}
