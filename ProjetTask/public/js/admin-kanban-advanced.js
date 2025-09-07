class AdminKanbanAdvanced {
    constructor() {
        this.sortableInstances = [];
        this.filters = {};
        this.searchTimeout = null;
        this.autoRefreshInterval = null;
        this.isLoading = false;
        this.apiBaseUrl = document.querySelector('.admin-kanban-container')?.dataset?.apiPrefix || '/api/kanban/';
        this.currentUserRole = document.querySelector('.admin-kanban-container')?.dataset?.userRole || 'ROLE_USER';
        
        // Initialisation
        this.init();
    }

    async init() {
        try {
            this.showLoading();
            await this.initializeKanban();
            this.initializeEventListeners();
            this.initializeFilters();
            this.initializeSearch();
            this.initializeRealTimeUpdates();
            this.initializeKeyboardShortcuts();
            await this.loadInitialData();
            this.hideLoading();
        } catch (error) {
            console.error('Erreur lors de l\'initialisation du Kanban:', error);
            this.showNotification('Erreur lors du chargement du tableau de bord', 'error');
            this.hideLoading();
        }
    }

    /**
     * Initialisation Kanban avec fonctionnalités avancées
     */
    initializeKanban() {
        const columns = document.querySelectorAll('.sortable.enhanced');

        columns.forEach(column => {
            const sortable = new Sortable(column, {
                group: 'kanban-admin',
                animation: 200,
                ghostClass: 'sortable-ghost-enhanced',
                chosenClass: 'sortable-chosen-enhanced',
                dragClass: 'sortable-drag-enhanced',

                onStart: (evt) => {
                    this.onDragStart(evt);
                },

                onEnd: (evt) => {
                    this.onDragEnd(evt);
                },

                onMove: (evt) => {
                    return this.onDragMove(evt);
                }
            });

            this.sortableInstances.push(sortable);
        });

        // Gestion du drop zone
        this.initializeDropZones();
    }

    /**
     * Gestion début de drag
     */
    onDragStart(evt) {
        const taskCard = evt.item;
        const taskId = taskCard.dataset.taskId;

        // Animation de début
        taskCard.classList.add('dragging');

        // Afficher les zones de drop
        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.classList.add('active');
        });

        // Stocker les données de la tâche
        this.dragData = {
            taskId: taskId,
            originalListId: evt.from.dataset.listId,
            originalPosition: evt.oldIndex
        };
    }

    /**
     * Gestion fin de drag
     */
    async onDragEnd(evt) {
        const taskCard = evt.item;
        const newListId = evt.to.dataset.listId;
        const newPosition = evt.newIndex;

        // Masquer les zones de drop
        document.querySelectorAll('.drop-zone').forEach(zone => {
            zone.classList.remove('active');
        });

        taskCard.classList.remove('dragging');

        // Vérifier si la position a changé
        if (newListId !== this.dragData.originalListId || newPosition !== this.dragData.originalPosition) {
            try {
                this.showLoading();
                await this.moveTask(this.dragData.taskId, newListId, newPosition);
                this.showNotification('Tâche déplacée avec succès', 'success');
            } catch (error) {
                console.error('Erreur lors du déplacement:', error);
                this.showNotification('Erreur lors du déplacement', 'error');
                // Rollback
                evt.from.insertBefore(taskCard, evt.from.children[this.dragData.originalPosition]);
            }
        }
    }

    /**
     * Validation du déplacement
     */
    onDragMove(evt) {
        const fromProject = evt.from.closest('.kanban-column').dataset.projectId;
        const toProject = evt.to.closest('.kanban-column').dataset.projectId;

        // Permettre le déplacement entre projets différents (fonctionnalité admin)
        return true;
    }

    /**
     * Appel API pour déplacer une tâche
     */
    async moveTask(taskId, newListId, newPosition) {
        const response = await fetch('/admin/kanban/move-task', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                taskId: parseInt(taskId),
                newListId: parseInt(newListId),
                newPosition: parseInt(newPosition)
            })
        });

        const result = await response.json();

        if (!result.success) {
            throw new Error(result.message);
        }

        // Mettre à jour les statistiques
        if (result.statistics) {
            this.updateStatistics(result.statistics);
        }

        return result;
    }

    /**
     * Initialisation des filtres avancés
     */
    initializeFilters() {
        const filters = ['projectFilter', 'userFilter', 'priorityFilter', 'statusFilter'];

        filters.forEach(filterId => {
            const filter = document.getElementById(filterId);
            if (filter) {
                filter.addEventListener('change', () => {
                    this.applyFilters();
                });
            }
        });

        // Filtres avancés toggle
        const toggleBtn = document.getElementById('toggleAdvancedFilters');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => {
                this.toggleAdvancedFilters();
            });
        }
    }

    /**
     * Application des filtres
     */
    applyFilters() {
        const filters = {
            project: document.getElementById('projectFilter')?.value || '',
            user: document.getElementById('userFilter')?.value || '',
            priority: document.getElementById('priorityFilter')?.value || 'all',
            status: document.getElementById('statusFilter')?.value || 'all'
        };

        // Filtrage côté client pour la fluidité
        this.filterTasksClientSide(filters);

        // Mise à jour de l'URL (optionnel)
        this.updateURLWithFilters(filters);

        // Mise à jour des compteurs
        this.updateTaskCounts();
    }

    /**
     * Filtrage côté client
     */
    filterTasksClientSide(filters) {
        const taskCards = document.querySelectorAll('.task-card.enhanced');

        taskCards.forEach(card => {
            let show = true;

            // Filtre projet
            if (filters.project && card.dataset.projectId !== filters.project) {
                show = false;
            }

            // Filtre priorité
            if (filters.priority !== 'all' && card.dataset.priority !== filters.priority) {
                show = false;
            }

            // Filtre statut
            if (filters.status !== 'all' && card.dataset.status !== filters.status) {
                show = false;
            }

            // Animation de filtrage
            if (show) {
                card.style.display = 'block';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.opacity = '1';
                }, 100);
            } else {
                card.style.opacity = '0';
                setTimeout(() => {
                    card.style.display = 'none';
                }, 200);
            }
        });
    }

    /**
     * Recherche globale
     */
    initializeSearch() {
        const searchInput = document.getElementById('globalSearch');
        if (!searchInput) return;

        searchInput.addEventListener('input', (e) => {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.performGlobalSearch(e.target.value);
            }, 300);
        });

        // Fermer les résultats en cliquant ailleurs
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideSearchResults();
            }
        });
    }

    /**
     * Exécution de la recherche globale
     */
    async performGlobalSearch(query) {
        if (query.length < 2) {
            this.hideSearchResults();
            return;
        }

        try {
            const response = await fetch(`/admin/kanban/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();

            this.showSearchResults(data.results);
        } catch (error) {
            console.error('Erreur de recherche:', error);
        }
    }

    /**
     * Affichage des résultats de recherche
     */
    showSearchResults(results) {
        const resultsContainer = document.getElementById('searchResults');
        if (!resultsContainer) return;

        let html = '<div class="search-results-content">';

        // Projets
        if (results.projects && results.projects.length > 0) {
            html += '<div class="search-category"><h4>🏗️ Projets</h4>';
            results.projects.forEach(project => {
                html += `
                    <div class="search-item" onclick="highlightProject(${project.id})">
                        <div class="search-item-title">${project.titre}</div>
                        <div class="search-item-desc">${project.description || ''}</div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Tâches
        if (results.tasks && results.tasks.length > 0) {
            html += '<div class="search-category"><h4>📋 Tâches</h4>';
            results.tasks.forEach(task => {
                html += `
                    <div class="search-item" onclick="highlightTask(${task.id})">
                        <div class="search-item-title">${task.title}</div>
                        <div class="search-item-desc">${task.description || ''}</div>
                    </div>
                `;
            });
            html += '</div>';
        }

        // Utilisateurs
        if (results.users && results.users.length > 0) {
            html += '<div class="search-category"><h4>👥 Utilisateurs</h4>';
            results.users.forEach(user => {
                html += `
                    <div class="search-item" onclick="highlightUser(${user.id})">
                        <div class="search-item-title">${user.prenom} ${user.nom}</div>
                        <div class="search-item-desc">${user.email}</div>
                    </div>
                `;
            });
            html += '</div>';
        }

        html += '</div>';

        resultsContainer.innerHTML = html;
        resultsContainer.classList.remove('hidden');
    }

    /**
     * Mise à jour en temps réel
     */
    initializeRealTimeUpdates() {
        // Auto-refresh toutes les 30 secondes
        this.autoRefreshInterval = setInterval(() => {
            this.refreshStatistics();
        }, 30000);

        // Indicateur temps réel
        this.updateLiveIndicator();
        setInterval(() => {
            this.updateLiveIndicator();
        }, 1000);
    }

    /**
     * Actualisation des statistiques
     */
    async refreshStatistics() {
        try {
            const response = await fetch('/admin/kanban/statistics');
            const data = await response.json();

            this.updateStatistics(data.statistics);
            this.updatePerformanceMetrics(data.performance);
            this.updateWorkloadDistribution(data.workload);
        } catch (error) {
            console.error('Erreur lors de la mise à jour:', error);
        }
    }

    /**
     * Mise à jour de l'indicateur temps réel
     */
    updateLiveIndicator() {
        const indicator = document.querySelector('.live-indicator');
        if (indicator) {
            indicator.style.animation = 'pulse 2s infinite';
        }
    }

    /**
     * Chargement des alertes
     */
    async loadAlerts() {
        try {
            const response = await fetch('/admin/kanban/alerts');
            const alerts = await response.json();

            this.displayAlerts(alerts);
        } catch (error) {
            console.error('Erreur lors du chargement des alertes:', error);
        }
    }

    /**
     * Affichage des alertes
     */
    displayAlerts(alerts) {
        const alertsContainer = document.getElementById('alertsContainer');
        if (!alertsContainer) return;

        let hasAlerts = false;
        let html = '<div class="alerts-content">';

        if (alerts.overdue.count > 0) {
            hasAlerts = true;
            html += `
                <div class="alert alert-danger">
                    <div class="alert-icon">⚠️</div>
                    <div class="alert-content">
                        <div class="alert-title">Tâches en retard</div>
                        <div class="alert-message">${alerts.overdue.count} tâches nécessitent une attention immédiate</div>
                    </div>
                    <button class="alert-action" onclick="showOverdueTasks()">Voir</button>
                </div>
            `;
        }

        if (alerts.due_soon.count > 0) {
            hasAlerts = true;
            html += `
                <div class="alert alert-warning">
                    <div class="alert-icon">🟡</div>
                    <div class="alert-content">
                        <div class="alert-title">Échéances proches</div>
                        <div class="alert-message">${alerts.due_soon.count} tâches arrivent à échéance</div>
                    </div>
                    <button class="alert-action" onclick="showDueSoonTasks()">Voir</button>
                </div>
            `;
        }

        html += '</div>';

        if (hasAlerts) {
            alertsContainer.innerHTML = html;
            alertsContainer.classList.remove('hidden');
        }
    }

    /**
     * Raccourcis clavier
     */
    initializeKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + K : Focus sur la recherche
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                document.getElementById('globalSearch')?.focus();
            }

            // Ctrl/Cmd + R : Refresh
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                this.refreshDashboard();
            }

            // Ctrl/Cmd + N : Nouvelle tâche
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                this.openQuickTaskModal();
            }

            // Escape : Fermer les modales/panels
            if (e.key === 'Escape') {
                this.closeAllModals();
            }
        });
    }

    /**
     * Création de tâche rapide
     */
    async createQuickTask(data) {
        try {
            const response = await fetch('/admin/kanban/quick-task', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                this.showNotification('Tâche créée avec succès', 'success');
                // Ajouter la tâche au DOM sans rechargement
                this.addTaskToBoard(result.task);
            } else {
                this.showNotification('Erreur lors de la création', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            this.showNotification('Erreur de connexion', 'error');
        }
    }

    /**
     * Notification système
     */
    showNotification(message, type = 'info', duration = 3000) {
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;

        const icons = {
            success: '✅',
            error: '❌',
            warning: '⚠️',
            info: 'ℹ️'
        };

        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${icons[type]}</span>
                <span class="notification-message">${message}</span>
            </div>
        `;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--color-${type === 'error' ? 'danger' : type === 'warning' ? 'warning' : type === 'success' ? 'success' : 'info'});
            color: white;
            padding: 16px 24px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-card);
            z-index: 10000;
            animation: slideInRight 0.3s ease;
            min-width: 300px;
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 300);
        }, duration);
    }

    // Méthodes utilitaires supplémentaires...

    updateStatistics(stats) {
        // Mise à jour des cartes statistiques
        Object.keys(stats).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"] .stat-number`);
            if (element) {
                element.textContent = stats[key];
            }
        });
    }

    updateTaskCounts() {
        const columns = document.querySelectorAll('.kanban-column.enhanced');
        columns.forEach(column => {
            const visibleTasks = column.querySelectorAll('.task-card.enhanced:not([style*="display: none"])');
            const counter = column.querySelector('.task-count');
            if (counter) {
                counter.textContent = visibleTasks.length;
            }
        });
    }

    refreshDashboard() {
        this.showNotification('Actualisation en cours...', 'info', 1000);
        location.reload();
    }
}

// Fonctions globales pour les callbacks
window.adminKanban = new AdminKanbanAdvanced();

// Fonctions d'interaction
function highlightTask(taskId) {
    const taskCard = document.querySelector(`[data-task-id="${taskId}"]`);
    if (taskCard) {
        taskCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
        taskCard.classList.add('highlighted');
        setTimeout(() => taskCard.classList.remove('highlighted'), 3000);
    }
    adminKanban.hideSearchResults();
}

function highlightProject(projectId) {
    const projectColumns = document.querySelectorAll(`[data-project-id="${projectId}"]`);
    projectColumns.forEach(column => {
        column.classList.add('highlighted');
        setTimeout(() => column.classList.remove('highlighted'), 3000);
    });
    adminKanban.hideSearchResults();
}

function openTaskDetailsModal(taskId) {
    // Implémentation de la modale de détails
    console.log('Ouvrir détails tâche:', taskId);
}

function addQuickTask(listId) {
    adminKanban.openQuickTaskModal(listId);
}

// Styles CSS pour les animations
const styles = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .highlighted {
        animation: highlight 3s ease;
        border: 2px solid var(--color-accent-500) !important;
    }
    
    @keyframes highlight {
        0%, 100% { box-shadow: none; }
        50% { box-shadow: 0 0 20px var(--color-accent-500); }
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = styles;
document.head.appendChild(styleSheet);