/**  
 * 🎯 SynTask Kanban Roles Manager  
 * Système de gestion Kanban avec droits par rôles et drag & drop  
 */

class SynTaskKanbanRoles {
    constructor() {
        this.config = window.SYNTASK_CONFIG || {};
        this.userRole = this.config.userRole;
        this.permissions = this.config.permissions || {};
        this.userId = this.config.userId;

        // États  
        this.isDragging = false;
        this.draggedElement = null;
        this.sortableInstances = [];

        // Cache  
        this.usersCache = new Map();
        this.tasksCache = new Map();

        this.init();
    }

    /**  
     * 🚀 Initialisation principale  
     */
    init() {
        console.log('🎯 SynTask Kanban Roles - Initialisation', {
            role: this.userRole,
            permissions: this.permissions
        });

        this.initializeEventListeners();
        this.initializeDragAndDrop();
        this.initializeFilters();
        this.initializeSearch();
        this.loadUsers();
        this.startAutoRefresh();

        // Initialisation spécifique au rôle  
        this.initializeRoleSpecificFeatures();
    }

    /**  
     * 🎭 Fonctionnalités spécifiques au rôle  
     */
    initializeRoleSpecificFeatures() {
        switch (this.userRole) {
            case 'ADMIN':
                this.initAdminFeatures();
                break;
            case 'DIRECTEUR':
                this.initDirecteurFeatures();
                break;
            case 'CHEF_PROJET':
                this.initChefProjetFeatures();
                break;
            case 'EMPLOYE':
                this.initEmployeFeatures();
                break;
        }
    }

    /**  
     * 👑 Fonctionnalités Admin  
     */
    initAdminFeatures() {
        this.enableCrossProjectMovement();
        this.enableUserManagement();
        this.enableProjectCreation();
        this.enableDataExport();
        this.initAdvancedFilters();
    }

    /**  
     * 🎯 Fonctionnalités Directeur  
     */
    initDirecteurFeatures() {
        this.enableCrossProjectMovement();
        this.enableUserPromotion();
        this.enableProjectCreation();
        this.initManagementDashboard();
    }

    /**  
     * 👨‍💼 Fonctionnalités Chef de Projet  
     */
    initChefProjetFeatures() {
        this.enableTeamManagement();
        this.enableTaskAssignment();
        this.initTeamPerformance();
        this.loadManagedProjects();
    }

    /**  
     * 👤 Fonctionnalités Employé  
     */
    initEmployeFeatures() {
        this.enablePersonalTaskManagement();
        this.initTimeTracking();
        this.enableQuickActions();
        this.loadPersonalTasks();
    }

    /**  
     * 🖱️ Initialisation Drag & Drop avec SortableJS  
     */
    initializeDragAndDrop() {
        const sortableContainers = document.querySelectorAll('.sortable');

        sortableContainers.forEach(container => {
            const listId = container.dataset.listId;
            const canManage = this.canManageList(container);

            if (canManage) {
                const sortable = new Sortable(container, {
                    group: {
                        name: 'kanban-tasks',
                        pull: this.canPullFromList(container),
                        put: this.canPutToList.bind(this)
                    },
                    animation: 200,
                    ghostClass: 'task-ghost',
                    chosenClass: 'task-chosen',
                    dragClass: 'task-drag',

                    onStart: this.onDragStart.bind(this),
                    onEnd: this.onDragEnd.bind(this),
                    onAdd: this.onTaskMoved.bind(this),
                    onUpdate: this.onTaskReordered.bind(this),

                    filter: '.no-drag',
                    preventOnFilter: true
                });

                this.sortableInstances.push(sortable);
            }
        });

        // Drag & Drop pour assignation d'utilisateurs  
        this.initializeUserDragDrop();
    }

    /**  
     * 👥 Drag & Drop des utilisateurs  
     */
    initializeUserDragDrop() {
        // Rendre les utilisateurs draggables  
        this.makeDraggableUsers();

        // Zones de drop pour projets  
        this.initializeProjectDropZones();

        // Zones de drop pour tâches  
        this.initializeTaskDropZones();
    }

    makeDraggableUsers() {
        document.addEventListener('click', (e) => {
            if (e.target.closest('.btn') && e.target.textContent.includes('👥')) {
                this.showUsersPanel();
            }
        });
    }

    /**  
     * 📋 Gestion des listes selon les droits  
     */
    canManageList(container) {
        const isManaged = container.dataset.canManage === 'true';
        const projectId = container.dataset.projectId;

        switch (this.userRole) {
            case 'ADMIN':
            case 'DIRECTEUR':
                return true;

            case 'CHEF_PROJET':
                return isManaged || this.isManagedProject(projectId);

            case 'EMPLOYE':
                return this.canEmployeManageList(container);

            default:
                return false;
        }
    }

    canPullFromList(container) {
        return this.canManageList(container) ? 'clone' : false;
    }

    canPutToList(to, from, dragEl, event) {
        const targetContainer = to.el;
        const sourceContainer = from.el;

        // Même liste = toujours OK  
        if (targetContainer === sourceContainer) return true;

        const targetProjectId = targetContainer.dataset.projectId;
        const sourceProjectId = sourceContainer.dataset.projectId;

        // Mouvement entre projets  
        if (targetProjectId !== sourceProjectId) {
            return this.permissions.canMoveTasksBetweenProjects;
        }

        return this.canManageList(targetContainer);
    }

    /**  
     * 🎯 Événements de drag & drop  
     */
    onDragStart(evt) {
        this.isDragging = true;
        this.draggedElement = evt.item;

        // Afficher les zones de drop valides  
        this.highlightValidDropZones(evt.item);

        // Log de l'action  
        console.log('🔄 Drag started:', {
            taskId: evt.item.dataset.taskId,
            fromList: evt.from.dataset.listId
        });
    }

    onDragEnd(evt) {
        this.isDragging = false;
        this.draggedElement = null;

        // Cacher les zones de drop  
        this.hideDropZones();
    }

    async onTaskMoved(evt) {
        const taskId = parseInt(evt.item.dataset.taskId);
        const newListId = parseInt(evt.to.dataset.listId);
        const oldListId = parseInt(evt.from.dataset.listId);
        const newPosition = evt.newIndex;

        try {
            const response = await this.moveTask(taskId, newListId, newPosition);

            if (response.success) {
                this.showNotification('✅ Tâche déplacée avec succès', 'success');

                // Mettre à jour les compteurs  
                this.updateColumnCounts();

                // Log de l'activité  
                if (response.crossProject) {
                    this.logActivity('task_cross_project_move', {
                        taskId, oldListId, newListId
                    });
                }
            } else {
                // Annuler le déplacement en cas d'erreur  
                evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                this.showNotification('❌ ' + response.message, 'error');
            }
        } catch (error) {
            console.error('Erreur déplacement tâche:', error);
            evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
            this.showNotification('❌ Erreur lors du déplacement', 'error');
        }
    }

    /**  
     * 🌐 API Calls  
     */
    async moveTask(taskId, newListId, newPosition) {
        const response = await fetch('/kanban/move-task', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify({
                taskId,
                newListId,
                newPosition
            })
        });

        return await response.json();
    }

    async assignUserToProject(userId, projectId) {
        const response = await fetch('/kanban/assign-user-project', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify({
                userId,
                projectId
            })
        });

        return await response.json();
    }

    async assignUserToTask(userId, taskId) {
        const response = await fetch('/kanban/assign-user-task', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': this.config.csrfToken
            },
            body: JSON.stringify({
                userId,
                taskId
            })
        });

        return await response.json();
    }

    async refreshData() {
        const response = await fetch('/kanban/refresh-data?' + new URLSearchParams(this.getCurrentFilters()));
        return await response.json();
    }

    /**  
     * 👥 Gestion du panel utilisateurs  
     */
    showUsersPanel() {
        const panel = document.getElementById('usersPanel');
        if (panel) {
            panel.classList.remove('hidden');
            this.loadAssignableUsers();
        }
    }

    hideUsersPanel() {
        const panel = document.getElementById('usersPanel');
        if (panel) {
            panel.classList.add('hidden');
        }
    }

    async loadAssignableUsers() {
        try {
            const response = await fetch('/kanban/assignable-users');
            const data = await response.json();

            const usersList = document.getElementById('usersList');
            if (usersList && data.users) {
                usersList.innerHTML = data.users.map(user => `  
                    <div class="user-item draggable-user"   
                         data-user-id="${user.id}"  
                         draggable="true">  
                        <div class="user-avatar">${user.initials || user.nom[0]}${user.prenom[0]}</div>  
                        <div class="user-info">  
                            <div class="user-name">${user.prenom} ${user.nom}</div>  
                            <div class="user-role role-${user.role.toLowerCase()}">${user.role}</div>  
                        </div>  
                        <div class="user-actions">  
                            <button class="assign-btn" onclick="quickAssignUser(${user.id})" title="Assignment rapide">  
                                ➕  
                            </button>  
                        </div>  
                    </div>  
                `).join('');

                this.initializeDraggableUsers();
            }
        } catch (error) {
            console.error('Erreur chargement utilisateurs:', error);
        }
    }

    initializeDraggableUsers() {
        const draggableUsers = document.querySelectorAll('.draggable-user');

        draggableUsers.forEach(user => {
            user.addEventListener('dragstart', (e) => {
                e.dataTransfer.setData('text/plain', user.dataset.userId);
                e.dataTransfer.setData('user/id', user.dataset.userId);
                user.classList.add('dragging');
            });

            user.addEventListener('dragend', (e) => {
                user.classList.remove('dragging');
            });
        });
    }

    /**  
     * 🎯 Zones de drop pour projets et tâches  
     */
    initializeProjectDropZones() {
        const dropZones = document.querySelectorAll('.user-drop-zone');

        dropZones.forEach(zone => {
            zone.addEventListener('dragover', this.handleDragOver.bind(this));
            zone.addEventListener('drop', this.handleProjectDrop.bind(this));
            zone.addEventListener('dragenter', this.handleDragEnter.bind(this));
            zone.addEventListener('dragleave', this.handleDragLeave.bind(this));
        });
    }

    initializeTaskDropZones() {
        const taskCards = document.querySelectorAll('.task-card');

        taskCards.forEach(card => {
            if (this.canAssignToTask(card)) {
                card.addEventListener('dragover', this.handleDragOver.bind(this));
                card.addEventListener('drop', this.handleTaskDrop.bind(this));
                card.addEventListener('dragenter', this.handleTaskDragEnter.bind(this));
                card.addEventListener('dragleave', this.handleTaskDragLeave.bind(this));
            }
        });
    }

    handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    }

    handleDragEnter(e) {
        e.preventDefault();
        e.target.closest('.user-drop-zone, .task-card').classList.add('drag-over');
    }

    handleDragLeave(e) {
        e.target.closest('.user-drop-zone, .task-card').classList.remove('drag-over');
    }

    async handleProjectDrop(e) {
        e.preventDefault();
        const userId = e.dataTransfer.getData('user/id');
        const dropZone = e.target.closest('.user-drop-zone');
        const projectId = dropZone.closest('.kanban-column').dataset.projectId;

        dropZone.classList.remove('drag-over');

        if (userId && projectId) {
            try {
                const result = await this.assignUserToProject(userId, projectId);
                if (result.success) {
                    this.showNotification('✅ Utilisateur assigné au projet', 'success');
                    this.refreshProjectMembers(projectId);
                } else {
                    this.showNotification('❌ ' + result.message, 'error');
                }
            } catch (error) {
                this.showNotification('❌ Erreur lors de l\'assignation', 'error');
            }
        }
    }

    async handleTaskDrop(e) {
        e.preventDefault();
        const userId = e.dataTransfer.getData('user/id');
        const taskCard = e.target.closest('.task-card');
        const taskId = taskCard.dataset.taskId;

        taskCard.classList.remove('drag-over');

        if (userId && taskId && this.canAssignToTask(taskCard)) {
            try {
                const result = await this.assignUserToTask(userId, taskId);
                if (result.success) {
                    this.showNotification('✅ Utilisateur assigné à la tâche', 'success');
                    this.refreshTaskAssignees(taskId);
                } else {
                    this.showNotification('❌ ' + result.message, 'error');
                }
            } catch (error) {
                this.showNotification('❌ Erreur lors de l\'assignation', 'error');
            }
        }
    }

    handleTaskDragEnter(e) {
        e.preventDefault();
        if (this.canAssignToTask(e.target.closest('.task-card'))) {
            e.target.closest('.task-card').classList.add('task-drag-over');
        }
    }

    handleTaskDragLeave(e) {
        e.target.closest('.task-card').classList.remove('task-drag-over');
    }

    /**  
     * 🔐 Vérifications des droits  
     */
    canAssignToTask(taskCard) {
        const isManaged = taskCard.dataset.isManaged === 'true';

        switch (this.userRole) {
            case 'ADMIN':
            case 'DIRECTEUR':
                return true;
            case 'CHEF_PROJET':
                return isManaged || this.permissions.canAssignToOwnProjects;
            default:
                return false;
        }
    }

    canEmployeManageList(container) {
        // Employé peut seulement déplacer ses propres tâches dans la même liste  
        return container.dataset.listId && this.hasPersonalTasksInList(container);
    }

    hasPersonalTasksInList(container) {
        const myTasks = container.querySelectorAll('.task-card[data-is-mine="true"]');
        return myTasks.length > 0;
    }

    isManagedProject(projectId) {
        return this.config.managedProjects && this.config.managedProjects.includes(parseInt(projectId));
    }

    /**  
     * 🔍 Recherche et filtres  
     */
    initializeSearch() {
        const searchInput = document.getElementById('globalSearch');
        if (searchInput) {
            let searchTimeout;

            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.performSearch(e.target.value);
                }, 300);
            });
        }
    }

    async performSearch(query) {
        if (query.length < 2) {
            this.clearSearchHighlight();
            return;
        }

        const searchResults = await this.searchTasks(query);
        this.displaySearchResults(searchResults);
        this.highlightSearchResults(query);
    }

    async searchTasks(query) {
        // Recherche côté client pour la réactivité  
        const taskCards = document.querySelectorAll('.task-card');
        const results = [];

        taskCards.forEach(card => {
            const title = card.querySelector('.task-title').textContent.toLowerCase();
            const description = card.querySelector('.task-description')?.textContent.toLowerCase() || '';

            if (title.includes(query.toLowerCase()) || description.includes(query.toLowerCase())) {
                results.push({
                    taskId: card.dataset.taskId,
                    title: card.querySelector('.task-title').textContent,
                    element: card
                });
            }
        });

        return results;
    }

    initializeFilters() {
        const filterSelects = document.querySelectorAll('.filter-select');

        filterSelects.forEach(select => {
            select.addEventListener('change', () => {
                this.applyFilters();
            });
        });
    }

    applyFilters() {
        const filters = this.getCurrentFilters();
        const taskCards = document.querySelectorAll('.task-card');

        taskCards.forEach(card => {
            const isVisible = this.shouldShowTask(card, filters);
            card.style.display = isVisible ? '' : 'none';
        });

        this.updateColumnCounts();
    }

    shouldShowTask(taskCard, filters) {
        // Filtres par projet  
        if (filters.project_id && taskCard.dataset.projectId !== filters.project_id) {
            return false;
        }

        // Filtres par priorité  
        if (filters.priority !== 'all' && taskCard.dataset.priority !== filters.priority) {
            return false;
        }

        // Filtres par statut  
        if (filters.status !== 'all' && taskCard.dataset.status !== filters.status) {
            return false;
        }

        // Filtre spécial pour employé (mes tâches seulement)  
        if (this.userRole === 'EMPLOYE' && filters.myTasksOnly && taskCard.dataset.isMine !== 'true') {
            return false;
        }

        return true;
    }

    getCurrentFilters() {
        const projectFilter = document.getElementById('projectFilter');
        const priorityFilter = document.getElementById('priorityFilter');
        const statusFilter = document.getElementById('statusFilter');
        const myTasksOnly = document.querySelector('.toggle-btn[data-view="my-tasks"]')?.classList.contains('active');

        return {
            project_id: projectFilter?.value || '',
            priority: priorityFilter?.value || 'all',
            status: statusFilter?.value || 'all',
            myTasksOnly: myTasksOnly || false
        };
    }

    /**  
     * 📊 Mise à jour des compteurs et statistiques  
     */
    updateColumnCounts() {
        document.querySelectorAll('.kanban-column').forEach(column => {
            const visibleTasks = column.querySelectorAll('.task-card:not([style*="display: none"])');
            const countElement = column.querySelector('.task-count .count-number, .task-count');

            if (countElement) {
                countElement.textContent = visibleTasks.length;
            }

            // Mise à jour spécifique pour employé  
            if (this.userRole === 'EMPLOYE') {
                const myTasksCount = column.querySelectorAll('.task-card[data-is-mine="true"]:not([style*="display: none"])');
                const myTasksCounter = column.querySelector('.my-tasks-count');

                if (myTasksCounter) {
                    myTasksCounter.textContent = `(${myTasksCount.length} miennes)`;
                    myTasksCounter.style.display = myTasksCount.length > 0 ? 'inline' : 'none';
                }
            }
        });
    }

    /**  
     * 🔄 Auto-refresh et temps réel  
     */
    startAutoRefresh() {
        if (this.permissions.liveUpdates !== false) {
            setInterval(() => {
                this.refreshDataSilently();
            }, 30000); // 30 secondes  
        }
    }

    async refreshDataSilently() {
        try {
            const response = await this.refreshData();
            if (response.success) {
                // Mettre à jour seulement les données qui ont changé  
                this.updateChangedTasks(response.data.tasks);
            }
        } catch (error) {
            console.log('Auto-refresh silencieux échoué:', error);
        }
    }

    /**  
     * 🎨 Interface utilisateur et notifications  
     */
    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `  
            <div class="notification-content">  
                <span class="notification-message">${message}</span>  
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">&times;</button>  
            </div>  
        `;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.classList.add('show');
        }, 10);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    highlightValidDropZones(draggedItem) {
        const taskId = draggedItem.dataset.taskId;
        const currentProjectId = draggedItem.dataset.projectId;

        document.querySelectorAll('.user-drop-zone, .tasks-container').forEach(zone => {
            const zoneProjectId = zone.closest('.kanban-column').dataset.projectId;

            if (this.canDropTaskInZone(taskId, currentProjectId, zoneProjectId)) {
                zone.classList.add('valid-drop-zone');
            } else {
                zone.classList.add('invalid-drop-zone');
            }
        });
    }

    hideDropZones() {
        document.querySelectorAll('.valid-drop-zone, .invalid-drop-zone').forEach(zone => {
            zone.classList.remove('valid-drop-zone', 'invalid-drop-zone');
        });
    }

    canDropTaskInZone(taskId, fromProjectId, toProjectId) {
        // Même projet = toujours OK si on a les droits sur la liste  
        if (fromProjectId === toProjectId) return true;

        // Projets différents = besoin de droits spéciaux  
        return this.permissions.canMoveTasksBetweenProjects;
    }

    /**  
     * ⚡ Actions rapides selon le rôle  
     */
    initializeQuickActions() {
        // Actions rapides pour tous  
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 'n':
                        e.preventDefault();
                        this.openCreateTaskModal();
                        break;
                    case 'f':
                        e.preventDefault();
                        document.getElementById('globalSearch')?.focus();
                        break;
                    case 'r':
                        e.preventDefault();
                        this.refreshDashboard();
                        break;
                }
            }
        });

        // Actions spécifiques au rôle  
        this.initializeRoleSpecificShortcuts();
    }

    initializeRoleSpecificShortcuts() {
        if (this.userRole === 'EMPLOYE') {
            document.addEventListener('keydown', (e) => {
                if (e.altKey) {
                    switch (e.key) {
                        case 'm':
                            e.preventDefault();
                            this.switchEmployeView('my-tasks');
                            break;
                        case 'k':
                            e.preventDefault();
                            this.switchEmployeView('kanban');
                            break;
                        case 'c':
                            e.preventDefault();
                            this.switchEmployeView('calendar');
                            break;
                    }
                }
            });
        }
    }

    /**  
     * 🎛️ Méthodes utilitaires  
     */
    refreshDashboard() {
        location.reload();
    }

    openCreateTaskModal() {
        // Implémentation selon votre système de modales existant  
        if (window.openTaskModal) {
            window.openTaskModal();
        }
    }

    logActivity(action, data) {
        console.log(`📝 Activity logged: ${action}`, data);
        // Ici vous pouvez envoyer les logs à votre backend  
    }
}

/**  
 * 👑 Manager spécialisé Admin  
 */
class AdminKanbanManager extends SynTaskKanbanRoles {
    constructor(config) {
        super();
        this.assignableUsers = config.assignableUsers || [];
        this.initAdminSpecificFeatures();
    }

    initAdminSpecificFeatures() {
        this.initUserManagement();
        this.initDataExport();
        this.initBulkActions();
    }

    initUserManagement() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[onclick*="promoteToChefProjet"]')) {
                this.handleUserPromotion(e.target);
            }
        });
    }

    async handleUserPromotion(button) {
        const userId = button.dataset.userId;
        const projectId = button.dataset.projectId;

        if (confirm('Voulez-vous vraiment promouvoir cet utilisateur en chef de projet ?')) {
            try {
                const result = await fetch('/kanban/promote-chef-projet', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.config.csrfToken
                    },
                    body: JSON.stringify({ userId, projectId })
                });

                const response = await result.json();
                if (response.success) {
                    this.showNotification('✅ Utilisateur promu avec succès', 'success');
                    this.refreshDashboard();
                }
            } catch (error) {
                this.showNotification('❌ Erreur lors de la promotion', 'error');
            }
        }
    }

    initDataExport() {
        window.exportData = () => {
            window.open('/kanban/export-data?format=excel');
        };
    }

    initBulkActions() {
        // Actions en lot pour admin
        this.selectedTasks = new Set();

        document.addEventListener('click', (e) => {
            if (e.target.matches('.task-card') && e.ctrlKey) {
                this.toggleTaskSelection(e.target);
            }
        });
    }
}

/**
 * 👨‍💼 Manager spécialisé Chef de Projet
 */
class ChefProjetKanbanManager extends SynTaskKanbanRoles {
    constructor(config) {
        super();
        this.managedProjects = config.managedProjects || [];
        this.teamMembers = config.teamMembers || [];
        this.initChefProjetFeatures();
    }

    initChefProjetFeatures() {
        this.initTeamManagement();
        this.initTaskAssignmentFeatures();
        this.loadTeamPerformance();
    }

    initTeamManagement() {
        window.showMyTeam = () => {
            document.getElementById('teamPanel').classList.remove('hidden');
        };

        window.closeTeamPanel = () => {
            document.getElementById('teamPanel').classList.add('hidden');
        };

        window.manageColumnTeam = (listId) => {
            this.openTeamAssignmentModal(listId);
        };
    }

    initTaskAssignmentFeatures() {
        window.assignTaskTeam = (taskId) => {
            this.openTaskAssignmentModal(taskId);
        };

        window.quickAssignTeam = (taskId) => {
            this.showQuickAssignMenu(taskId);
        };
    }
}

/**
 * 👤 Manager spécialisé Employé
 */
class EmployeKanbanManager extends SynTaskKanbanRoles {
    constructor(config) {
        super();
        this.assignedTasks = new Set(config.assignedTasks || []);
        this.activeTimers = new Map();
        this.initEmployeFeatures();
    }

    initEmployeFeatures() {
        this.initPersonalTaskManagement();
        this.initTimeTracking();
        this.initEmployeViews();
        this.initQuickEmployeActions();
    }

    initPersonalTaskManagement() {
        // Filtrage automatique des tâches personnelles
        window.filterMyTasks = () => {
            this.toggleMyTasksFilter();
        };

        window.updateMyTasksCounts = () => {
            this.updateMyTasksCounts();
        };
    }

    initTimeTracking() {
        window.startWork = (taskId) => {
            this.startTaskTimer(taskId);
        };

        window.pauseWork = (taskId) => {
            this.pauseTaskTimer(taskId);
        };

        window.completeWork = (taskId) => {
            this.completeTaskWithTimer(taskId);
        };
    }

    initEmployeViews() {
        window.switchEmployeView = (view) => {
            this.switchView(view);
        };

        // Vue par défaut selon les préférences
        const savedView = localStorage.getItem('employe_preferred_view') || 'kanban';
        this.switchView(savedView);
    }

    switchView(viewName) {
        // Cacher toutes les vues
        document.querySelectorAll('.kanban-view, .my-tasks-view, .calendar-view').forEach(view => {
            view.classList.add('hidden');
        });

        // Désactiver tous les boutons
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Activer la vue demandée
        const targetView = document.getElementById(viewName + 'View') || document.querySelector(`[data-view="${viewName}"]`);
        const targetButton = document.querySelector(`[data-view="${viewName}"]`);

        if (targetView) {
            targetView.classList.remove('hidden');
        }
        if (targetButton) {
            targetButton.classList.add('active');
        }

        // Sauvegarder la préférence
        localStorage.setItem('employe_preferred_view', viewName);

        // Actions spécifiques par vue
        if (viewName === 'my-tasks') {
            this.loadMyTasksOnly();
        } else if (viewName === 'calendar') {
            this.initTaskCalendar();
        }
    }

    async loadMyTasksOnly() {
        const myTasks = document.querySelectorAll('.task-card[data-is-mine="true"]');
        const myTasksGrid = document.querySelector('.my-tasks-grid');

        if (myTasksGrid) {
            // Organiser par statut
            ['EN_ATTENTE', 'EN_COURS', 'TERMINER'].forEach(status => {
                const column = document.querySelector(`[data-status="${status}"] .my-tasks-list`);
                const statusTasks = Array.from(myTasks).filter(task => task.dataset.status === status);

                if (column) {
                    column.innerHTML = statusTasks.map(task => task.outerHTML).join('');
                }
            });
        }
    }

    startTaskTimer(taskId) {
        const timerElement = document.getElementById(`timer-${taskId}`);
        const timeDisplay = document.getElementById(`time-${taskId}`);

        if (!this.activeTimers.has(taskId)) {
            const startTime = Date.now();
            const timer = setInterval(() => {
                const elapsed = Math.floor((Date.now() - startTime) / 1000);
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                timeDisplay.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            }, 1000);

            this.activeTimers.set(taskId, { timer, startTime });

            if (timerElement) {
                timerElement.classList.add('active');
            }
        }
    }

    initQuickEmployeActions() {
        window.toggleQuickActions = () => {
            const fab = document.getElementById('fabActions');
            fab.classList.toggle('hidden');
        };

        window.quickUpdateStatus = () => {
            this.showQuickStatusUpdate();
        };

        window.quickRequestHelp = () => {
            this.openHelpRequestModal();
        };
    }
}

// Initialisation globale
document.addEventListener('DOMContentLoaded', function () {
    const config = window.SYNTASK_CONFIG || {};

    if (!window.kanbanManager) {
        switch (config.userRole) {
            case 'ADMIN':
                window.kanbanManager = new AdminKanbanManager(config);
                break;
            case 'DIRECTEUR':
                window.kanbanManager = new AdminKanbanManager(config);
                break;
            case 'CHEF_PROJET':
                window.kanbanManager = new ChefProjetKanbanManager(config);
                break;
            case 'EMPLOYE':
                window.kanbanManager = new EmployeKanbanManager(config);
                break;
            default:
                window.kanbanManager = new SynTaskKanbanRoles();
        }
    }
});

// Fonctions globales
window.openTaskDetailsModal = function (taskId) {
    // Intégration avec votre système de modal existant
    if (window.taskModal) {
        window.taskModal.open(taskId);
    } else {
        window.location.href = `/task/${taskId}/details`;
    }
};

window.refreshDashboard = function () {
    if (window.kanbanManager) {
        window.kanbanManager.refreshDashboard();
    } else {
        location.reload();
    }
};

window.toggleUserMenu = function () {
    const dropdown = document.getElementById('userDropdown');
    dropdown.classList.toggle('hidden');
};

window.closeUsersPanel = function () {
    if (window.kanbanManager) {
        window.kanbanManager.hideUsersPanel();
    }
};