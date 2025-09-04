// Configuration Sortable.js pour le drag & drop
document.addEventListener('DOMContentLoaded', function () {
    initializeKanban();
    initializeFilters();
    initializeModals();
});

/**
 * Initialise le système Kanban avec Sortable.js
 */
function initializeKanban() {
    const columns = document.querySelectorAll('.sortable');

    columns.forEach(column => {
        new Sortable(column, {
            group: 'kanban',
            animation: 150,
            ghostClass: 'sortable-ghost',
            dragClass: 'sortable-drag',

            onEnd: function (evt) {
                const taskId = evt.item.dataset.taskId;
                const newListId = evt.to.dataset.listId;
                const newPosition = evt.newIndex;

                // Appel API pour déplacer la tâche
                moveTask(taskId, newListId, newPosition);
            },

            onStart: function (evt) {
                // Animation de début de drag
                evt.item.style.transform = 'rotate(5deg)';
            }
        });
    });
}

/**
 * Déplace une tâche via API
 */
function moveTask(taskId, newListId, newPosition) {
    const data = {
        taskId: parseInt(taskId),
        newListId: parseInt(newListId),
        newPosition: parseInt(newPosition)
    };

    fetch('/admin/kanban/move-task', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showNotification('Tâche déplacée avec succès', 'success');
                updateTaskCounts();
            } else {
                showNotification('Erreur lors du déplacement', 'error');
                location.reload(); // Rollback en rechargeant
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showNotification('Erreur de connexion', 'error');
            location.reload();
        });
}

/**
 * Initialise les filtres
 */
function initializeFilters() {
    const projectFilter = document.getElementById('projectFilter');
    const userFilter = document.getElementById('userFilter');
    const priorityFilter = document.getElementById('priorityFilter');

    [projectFilter, userFilter, priorityFilter].forEach(filter => {
        if (filter) {
            filter.addEventListener('change', applyFilters);
        }
    });
}

/**
 * Applique les filtres aux tâches
 */
function applyFilters() {
    const projectId = document.getElementById('projectFilter').value;
    const userId = document.getElementById('userFilter').value;
    const priority = document.getElementById('priorityFilter').value;

    const taskCards = document.querySelectorAll('.task-card');

    taskCards.forEach(card => {
        let show = true;

        // Filtre par projet
        if (projectId && card.dataset.projectId !== projectId) {
            show = false;
        }

        // Filtre par priorité
        if (priority && !card.classList.contains(`priority-${priority.toLowerCase()}`)) {
            show = false;
        }

        // Filtre par utilisateur (nécessite data attribute ou logique plus complexe)
        // TODO: Implémenter le filtre utilisateur

        card.style.display = show ? 'block' : 'none';
    });

    updateTaskCounts();
}

/**
 * Met à jour les compteurs de tâches
 */
function updateTaskCounts() {
    const columns = document.querySelectorAll('.kanban-column');

    columns.forEach(column => {
        const visibleTasks = column.querySelectorAll('.task-card:not([style*="display: none"])');
        const counter = column.querySelector('.task-count');

        if (counter) {
            counter.textContent = visibleTasks.length;
        }
    });
}

/**
 * Ouvre les détails d'une tâche
 */
function openTaskDetails(taskId) {
    // TODO: Implémenter modal de détails
    console.log('Ouvrir tâche:', taskId);
}

/**
 * Édite une tâche
 */
function editTask(taskId) {
    // TODO: Implémenter modal d'édition
    console.log('Éditer tâche:', taskId);
}

/**
 * Supprime une tâche
 */
function deleteTask(taskId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette tâche ?')) {
        // TODO: Implémenter suppression
        console.log('Supprimer tâche:', taskId);
    }
}

/**
 * Ajoute rapidement une tâche
 */
function openQuickAddTask(listId) {
    const title = prompt('Titre de la nouvelle tâche :');
    if (title) {
        // TODO: Implémenter création rapide
        console.log('Créer tâche rapide:', title, 'dans liste:', listId);
    }
}

/**
 * Ouvre modal de création de tâche
 */
function openCreateTaskModal() {
    // TODO: Implémenter modal de création
    console.log('Ouvrir modal création tâche');
}

/**
 * Ouvre modal de création de projet
 */
function openCreateProjectModal() {
    // TODO: Implémenter modal de création projet
    console.log('Ouvrir modal création projet');
}

/**
 * Initialise les modales
 */
function initializeModals() {
    // TODO: Implémenter gestion des modales
}

/**
 * Affiche une notification
 */
function showNotification(message, type = 'info') {
    // Créer l'élément notification
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? 'var(--color-success)' :
            type === 'error' ? 'var(--color-danger)' :
                'var(--color-info)'};
        color: white;
        padding: 16px 24px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 1000;
        animation: slideIn 0.3s ease;
    `;
    notification.textContent = message;

    // Ajouter au DOM
    document.body.appendChild(notification);

    // Supprimer après 3 secondes
    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// CSS pour les animations des notifications
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(style);