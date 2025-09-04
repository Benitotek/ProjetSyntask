/**
 * main-dashboard.js - Script principal pour le tableau de bord
 */

document.addEventListener('DOMContentLoaded', function () {
    initCharts();
    initProjectStats();
    initRecentActivityList();
    initDashboardWidgets();
    initFilters();
});
function initCharts() {
    const projectProgressCtx = document.getElementById('chart-project-progress');
    if (projectProgressCtx) {
        new Chart(projectProgressCtx, { // Assurez-vous que Chart est chargé
            // Configuration ici
        });
    }

    fetch('/api/activity-data.json').then(response => {
        if (!response.ok) throw new Error('Erreur réseau');
        return response.json();
    }).then(data => {
        // Traiter les données ici
    }).catch(err => console.error('Erreur lors de la récupération des données d\'activité:', err));
}
/**
 * Initialise les graphiques du tableau de bord
 */
function initCharts() {
    // Graphique de progression des projects
    const projectProgressCtx = document.getElementById('chart-project-progress');

    if (projectProgressCtx && typeof Chart !== 'undefined') {
        new Chart(projectProgressCtx, {
            type: 'doughnut',
            data: {
                labels: ['En cours', 'Terminés', 'En attente'],
                datasets: [{
                    data: [
                        projectProgressCtx.dataset.inProgress || 0,
                        projectProgressCtx.dataset.completed || 0,
                        projectProgressCtx.dataset.pending || 0
                    ],
                    backgroundColor: [
                        '#4f46e5', // En cours
                        '#10b981', // Terminés
                        '#f59e0b'  // En attente
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = Math.round((value / total) * 100);
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }

    // Graphique des tâches par statut
    const taskstatutCtx = document.getElementById('chart-task-statut');

    if (taskstatutCtx && typeof Chart !== 'undefined') {
        new Chart(taskstatutCtx, {
            type: 'bar',
            data: {
                labels: ['En attente', 'En cours', 'Terminées'],
                datasets: [{
                    label: 'Tâches',
                    data: [
                        taskstatutCtx.dataset.pending || 0,
                        taskstatutCtx.dataset.inProgress || 0,
                        taskstatutCtx.dataset.completed || 0
                    ],
                    backgroundColor: [
                        '#f59e0b',
                        '#4f46e5',
                        '#10b981'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    }
    async function loadActivityData() {
        try {
            const response = await fetch('/api/activity-data.json');

            // Vérifier le type de contenu
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('La réponse n\'est pas du JSON');
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            // Traiter les données

        } catch (error) {
            console.error('Erreur lors du chargement des données d\'activité:', error);
            // Afficher un message à l'utilisateur ou utiliser des données par défaut
        }
    }
    // Graphique d'activité sur les derniers jours
    const activityChartCtx = document.getElementById('chart-activity');

    if (activityChartCtx && typeof Chart !== 'undefined') {
        // Récupérer les données d'activité via l'API

        fetch('/api/activity-data.json'), {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }
            .then(response => response.json())
            .then(data => {
                new Chart(activityChartCtx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [
                            {
                                label: 'Tâches créées',
                                data: data.taskCreated,
                                borderColor: '#4f46e5',
                                backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                fill: true,
                                tension: 0.3,
                                borderWidth: 2
                            },
                            {
                                label: 'Tâches terminées',
                                data: data.taskCompleted,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                                fill: true,
                                tension: 0.3,
                                borderWidth: 2
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top'
                            },
                            tooltip: {
                                mode: 'index',
                                intersect: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    precision: 0
                                }
                            }
                        }
                    }
                });
            })
            .catch(error => {
                console.error('Erreur lors du chargement des données d\'activité:', error);
                activityChartCtx.parentNode.innerHTML = `
                <div class="chart-error text-center">
                    <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                    <p>Impossible de charger les données d'activité</p>
                </div>
            `;
            });
    }
}

/**
 * Initialise les statistiques des projects
 */
function initProjectStats() {
    const projectStats = document.getElementById('project-stats');

    if (projectStats) {
        // Récupérer les statistiques via l'API si elles ne sont pas déjà incluses
        if (!projectStats.dataset.loaded) {
            fetch('/api/dashboard/project-stats', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    projectStats.querySelector('.total-value').textContent = data.total;
                    projectStats.querySelector('.completed-value').textContent = data.completed;
                    projectStats.querySelector('.in-progress-value').textContent = data.inProgress;
                    projectStats.querySelector('.delayed-value').textContent = data.delayed;

                    // Calculer le pourcentage de complétion
                    const completionPercentage = Math.round((data.completed / data.total) * 100) || 0;

                    const progressBar = projectStats.querySelector('.progress-bar');
                    progressBar.style.width = `${completionPercentage}%`;
                    progressBar.setAttribute('aria-valuenow', completionPercentage);
                    projectStats.querySelector('.completion-percentage').textContent = `${completionPercentage}%`;

                    projectStats.dataset.loaded = 'true';
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des statistiques des projects:', error);
                    projectStats.innerHTML = `
                    <div class="alert alert-danger">
                        Impossible de charger les statistiques des projects
                    </div>
                `;
                });
        }
    }
}

/**
 * Initialise la liste des activités récentes
 */
function initRecentActivityList() {
    const activityList = document.getElementById('recent-activities');

    if (activityList && !activityList.dataset.loaded) {
        fetch('/api/dashboard/recent-activities', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.activities && data.activities.length > 0) {
                    activityList.innerHTML = '';

                    data.activities.forEach(activity => {
                        activityList.innerHTML += `
                        <div class="activity-item">
                            <div class="activity-icon ${activity.type}">
                                <i class="fas fa-${getActivityIcon(activity.type)}"></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-title">
                                    <a href="${activity.url}" class="user-link">${activity.user}</a> 
                                    ${activity.action}
                                    <a href="${activity.targetUrl}" class="target-link">${activity.target}</a>
                                </div>
                                <div class="activity-time">${activity.time}</div>
                            </div>
                        </div>
                    `;
                    });

                    activityList.dataset.loaded = 'true';
                } else {
                    activityList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-history fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Aucune activité récente</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des activités récentes:', error);
                activityList.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p>Impossible de charger les activités récentes</p>
                </div>
            `;
            });
    }
}

/**
 * Renvoie l'icône appropriée pour un type d'activité
 */
function getActivityIcon(type) {
    switch (type) {
        case 'task-create':
            return 'plus-circle';
        case 'task-complete':
            return 'check-circle';
        case 'task-update':
            return 'edit';
        case 'project-create':
            return 'folder-plus';
        case 'project-update':
            return 'folder-open';
        case 'project-complete':
            return 'clipboard-check';
        case 'comment':
            return 'comment';
        case 'user-join':
            return 'user-plus';
        case 'document':
            return 'file-alt';
        default:
            return 'circle';
    }
}

/**
 * Initialise les widgets du tableau de bord
 */
function initDashboardWidgets() {
    // Initialiser les dates d'échéance
    const dueDatesList = document.getElementById('upcoming-due-dates');

    if (dueDatesList && !dueDatesList.dataset.loaded) {
        fetch('/api/dashboard/upcoming-due-dates', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.dueDates && data.dueDates.length > 0) {
                    dueDatesList.innerHTML = '';

                    data.dueDates.forEach(dueDate => {
                        const isOverdue = new Date(dueDate.date) < new Date() && !dueDate.completed;

                        dueDatesList.innerHTML += `
                        <div class="due-date-item ${isOverdue ? 'overdue' : ''}">
                            <div class="due-date-icon ${dueDate.type}">
                                <i class="fas fa-${dueDate.type === 'task' ? 'tasks' : 'project-diagram'}"></i>
                            </div>
                            <div class="due-date-content">
                                <div class="due-date-title">
                                    <a href="${dueDate.url}">${dueDate.title}</a>
                                </div>
                                <div class="due-date-info">
                                    ${isOverdue ? '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> En retard</span>' : ''}
                                    <span class="due-date-time">
                                        <i class="fas fa-calendar-alt"></i> ${dueDate.formattedDate}
                                    </span>
                                </div>
                            </div>
                            <div class="due-date-statut">
                                <span class="badge ${dueDate.completed ? 'badge-success' : isOverdue ? 'badge-danger' : 'badge-primary'}">
                                    ${dueDate.statut}
                                </span>
                            </div>
                        </div>
                    `;
                    });

                    dueDatesList.dataset.loaded = 'true';
                } else {
                    dueDatesList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-calendar-check fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Aucune échéance à venir</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des échéances:', error);
                dueDatesList.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p>Impossible de charger les échéances</p>
                </div>
            `;
            });
    }

    // Initialiser la liste des tâches assignées
    const assignedTasksList = document.getElementById('assigned-tasks');

    if (assignedTasksList && !assignedTasksList.dataset.loaded) {
        fetch('/api/dashboard/assigned-tasks', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.tasks && data.tasks.length > 0) {
                    assignedTasksList.innerHTML = '';

                    data.tasks.forEach(task => {
                        assignedTasksList.innerHTML += `
                        <div class="task-item">
                            <div class="task-checkbox">
                                <input type="checkbox" class="form-check-input task-complete-checkbox" 
                                    id="task-${task.id}" data-task-id="${task.id}" ${task.completed ? 'checked' : ''}>
                                <label for="task-${task.id}" class="form-check-label ${task.completed ? 'text-muted text-decoration-line-through' : ''}">
                                    ${task.title}
                                </label>
                            </div>
                            <div class="task-meta">
                                <span class="badge badge-${getPriorityClass(task.priority)}">${task.priority}</span>
                                ${task.dueDate ? `<span class="task-due-date ${task.isOverdue ? 'overdue' : ''}">
                                    <i class="fas fa-calendar-alt"></i> ${task.dueDate}
                                </span>` : ''}
                            </div>
                        </div>
                    `;
                    });

                    // Ajouter les écouteurs d'événements pour les checkboxes
                    assignedTasksList.querySelectorAll('.task-complete-checkbox').forEach(checkbox => {
                        checkbox.addEventListener('change', function () {
                            const taskId = this.dataset.taskId;
                            const completed = this.checked;

                            // Mettre à jour visuellement sans attendre la réponse API
                            const label = document.querySelector(`label[for="task-${taskId}"]`);
                            if (completed) {
                                label.classList.add('text-muted', 'text-decoration-line-through');
                            } else {
                                label.classList.remove('text-muted', 'text-decoration-line-through');
                            }

                            // Appeler l'API pour mettre à jour le statut
                            fetch(`/api/task/${taskId}/toggle-complete`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                                },
                                body: JSON.stringify({ completed: completed })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        showToast(completed ? 'Tâche marquée comme terminée' : 'Tâche marquée comme non terminée', 'success');
                                    } else {
                                        // ANNULER le changement visuel en cas d'erreur
                                        this.checked = !completed;
                                        if (!completed) {
                                            label.classList.add('text-muted', 'text-decoration-line-through');
                                        } else {
                                            label.classList.remove('text-muted', 'text-decoration-line-through');
                                        }

                                        showToast(data.message || 'Erreur lors de la mise à jour du statut', 'error');
                                    }
                                })
                                .catch(error => {
                                    console.error('Erreur lors de la mise à jour du statut:', error);

                                    // ANNULER le changement visuel
                                    this.checked = !completed;
                                    if (!completed) {
                                        label.classList.add('text-muted', 'text-decoration-line-through');
                                    } else {
                                        label.classList.remove('text-muted', 'text-decoration-line-through');
                                    }

                                    showToast('Erreur lors de la mise à jour du statut', 'error');
                                });
                        });
                    });

                    assignedTasksList.dataset.loaded = 'true';
                } else {
                    assignedTasksList.innerHTML = `
                    <div class="text-center py-4">
                        <i class="fas fa-check-square fa-2x text-muted mb-2"></i>
                        <p class="text-muted">Aucune tâche assignée</p>
                    </div>
                `;
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement des tâches assignées:', error);
                assignedTasksList.innerHTML = `
                <div class="text-center py-4 text-danger">
                    <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                    <p>Impossible de charger les tâches assignées</p
                    </div>
            `;
            });
    }
}

/**
 * Renvoie la classe CSS pour une priorité donnée
 */
function getPriorityClass(priority) {
    switch (priority.toUpperCase()) {
        case 'HAUTE':
            return 'danger';
        case 'MOYENNE':
            return 'warning';
        case 'BASSE':
            return 'success';
        default:
            return 'secondary';
    }
}

/**
 * Initialise les filtres du tableau de bord
 */
function initFilters() {
    const periodFilter = document.getElementById('dashboard-period-filter');

    if (periodFilter) {
        periodFilter.addEventListener('change', function () {
            const period = this.value;
            const url = new URL(window.location.href);

            // Mettre à jour le paramètre de période dans l'URL
            if (period === 'all') {
                url.searchParams.delete('period');
            } else {
                url.searchParams.set('period', period);
            }

            // Recharger la page avec le nouveau filtre
            window.location.href = url.toString();
        });
    }

    const projectFilter = document.getElementById('dashboard-project-filter');

    if (projectFilter) {
        projectFilter.addEventListener('change', function () {
            const projectId = this.value;
            const url = new URL(window.location.href);

            // Mettre à jour le paramètre de project dans l'URL
            if (projectId === 'all') {
                url.searchParams.delete('project');
            } else {
                url.searchParams.set('project', projectId);
            }

            // Recharger la page avec le nouveau filtre
            window.location.href = url.toString();
        });
    }
}
