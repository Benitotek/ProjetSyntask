// Test Version 2 - 3 a voir  du 02/.07/2025
/**
 * Initialise l'action pour ajouter une tâche
 */
function initAddTask() {
    // Écouter les clics sur les boutons d'ajout de tâche
    document.querySelectorAll('.btn-add-task').forEach(button => {
        button.addEventListener('click', function() {
            const columnId = this.dataset.columnId;
            const formContainer = document.getElementById('addTaskFormContainer');
            
            // Charger le formulaire via AJAX
            fetch(`/task/new/${columnId}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                formContainer.innerHTML = html;
                
                // Initialiser le formulaire pour l'envoi AJAX
                initTaskForm(formContainer.querySelector('form'), document.getElementById('addTaskModal'));
                
                // Initialiser le datepicker si présent
                initDatepicker();
                
                // Afficher le modal
                const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
                modal.show();
            })
            .catch(error => console.error('Erreur lors du chargement du formulaire:', error));
        });
    });
}

/**
 * Initialise l'action pour éditer une tâche
 */
function initEditTask() {
    // Écouter les clics sur les boutons d'édition de tâche
    document.querySelectorAll('.btn-edit-task').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.dataset.taskId;
            const formContainer = document.getElementById('editTaskFormContainer');
            
            // Charger le formulaire via AJAX
            fetch(`/task/${taskId}/edit`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.text())
            .then(html => {
                formContainer.innerHTML = html;
                
                // Initialiser le formulaire pour l'envoi AJAX
                initTaskForm(formContainer.querySelector('form'), document.getElementById('editTaskModal'));
                
                // Initialiser le datepicker si présent
                initDatepicker();
                
                // Afficher le modal
                const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
                modal.show();
            })
            .catch(error => console.error('Erreur lors du chargement du formulaire:', error));
        });
    });
}

/**
 * Initialise l'action pour supprimer une tâche
 */
function initDeleteTask() {
    const deleteTaskModal = document.getElementById('deleteTaskModal');
    if (!deleteTaskModal) return;
    
    // Écouter les clics sur les boutons de suppression de tâche
    document.querySelectorAll('.btn-delete-task').forEach(button => {
        button.addEventListener('click', function() {
            const taskId = this.dataset.taskId;
            const taskTitle = this.dataset.taskTitle;
            
            // Mettre à jour le modal avec les informations de la tâche
            document.getElementById('deleteTaskTitle').textContent = taskTitle;
            
            // Configurer le formulaire de suppression
            const form = document.getElementById('deleteTaskForm');
            form.action = `/task/${taskId}`;
            
            // Générer un token CSRF
            fetch(`/generate-csrf-token?id=delete${taskId}`)
                .then(response => response.json())
                .then(data => {
                    form.querySelector('input[name="_token"]').value = data.token;
                })
                .catch(error => console.error('Erreur lors de la génération du token CSRF:', error));
            
            // Afficher le modal
            const modal = new bootstrap.Modal(deleteTaskModal);
            modal.show();
        });
    });
    
    // Soumission du formulaire de suppression
    const deleteForm = document.getElementById('deleteTaskForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            fetch(this.action, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new FormData(this)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fermer le modal
                    bootstrap.Modal.getInstance(deleteTaskModal).hide();
                    
                    // Supprimer la tâche du DOM
                    const taskId = this.action.split('/').pop();
                    document.querySelector(`.kanban-task[data-task-id="${taskId}"]`).remove();
                    
                    // Mettre à jour les compteurs de tâches
                    updateTaskCounters();
                    
                    // Afficher un message de succès
                    showToast('Tâche supprimée avec succès', 'success');
                } else {
                    showToast(data.error || 'Erreur lors de la suppression de la tâche', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête:', error);
                showToast('Erreur lors de la suppression de la tâche', 'error');
            });
        });
    }
}

/**
 * Initialise un formulaire de tâche pour l'envoi AJAX
 */
function initTaskForm(form, modal) {
    if (!form) return;
    
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        
        fetch(this.dataset.action || this.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new FormData(this)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fermer le modal
                bootstrap.Modal.getInstance(modal).hide();
                
                // Recharger la page pour afficher les changements
                window.location.reload();
            } else {
                showToast(data.error || 'Erreur lors de l\'enregistrement de la tâche', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur lors de la requête:', error);
            showToast('Erreur lors de l\'enregistrement de la tâche', 'error');
        });
    });
}

/**
 * Initialise l'action pour assigner une tâche à un utilisateur
 */
function initAssignTask() {
    const assignTaskModal = document.getElementById('assignTaskModal');
    if (!assignTaskModal) return;
    
    // Variable pour stocker l'ID de la tâche en cours d'assignation
    let currentTaskId = null;
    
    // Écouter les clics sur les boutons d'assignation de tâche
    document.querySelectorAll('.btn-assign-task').forEach(button => {
        button.addEventListener('click', function() {
            currentTaskId = this.dataset.taskId;
            
            // Afficher le modal
            const modal = new bootstrap.Modal(assignTaskModal);
            modal.show();
        });
    });
    
    // Écouter les clics sur les utilisateurs dans la liste
    document.querySelectorAll('.user-item').forEach(userItem => {
        userItem.addEventListener('click', function() {
            if (!currentTaskId) return;
            
            const userId = this.dataset.userId;
            
            // Assigner la tâche à l'utilisateur via AJAX
            fetch(`/task/${currentTaskId}/assign/${userId}`, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Fermer le modal
                    bootstrap.Modal.getInstance(assignTaskModal).hide();
                    
                    // Mettre à jour l'affichage de la tâche
                    const taskElement = document.querySelector(`.kanban-task[data-task-id="${currentTaskId}"]`);
                    if (taskElement) {
                        const assignedElement = taskElement.querySelector('.task-assigned');
                        if (assignedElement) {
                            assignedElement.innerHTML = `
                                <div class="assigned-user" title="${data.userName}">
                                    <span class="user-avatar">${data.userName.split(' ').map(n => n[0]).join('').toUpperCase()}</span>
                                    <span class="user-name">${data.userName}</span>
                                </div>
                            `;
                        }
                    }
                    
                    showToast(`Tâche assignée à ${data.userName}`, 'success');
                } else {
                    showToast(data.error || 'Erreur lors de l\'assignation de la tâche', 'error');
                }
            })
            .catch(error => {
                console.error('Erreur lors de la requête:', error);
                showToast('Erreur lors de l\'assignation de la tâche', 'error');
            });
        });
    });
}

/**
 * Initialise les datepickers dans les formulaires
 */
function initDatepicker() {
    const datepickers = document.querySelectorAll('.datepicker');
    if (datepickers.length > 0) {
        datepickers.forEach(input => {
            // Utiliser flatpickr ou autre bibliothèque de datepicker
            // Exemple avec flatpickr :
            if (typeof flatpickr === 'function') {
                flatpickr(input, {
                    dateFormat: "Y-m-d",
                    altInput: true,
                    altFormat: "d/m/Y",
                    locale: "fr"
                });
            }
        });
    }
}

/**
 * Affiche un message toast
 */
function showToast(message, type = 'info') {
    // Créer un élément toast s'il n'existe pas
    let toastContainer = document.querySelector('.toast-container');
    
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Créer le toast
    const toastId = 'toast-' + Date.now();
    const toastHtml = `
        <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'}" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
    // Afficher le toast
    const toastElement = document.getElementById(toastId);
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 5000
    });
    
    toast.show();
    
    // Supprimer le toast du DOM après sa disparition
    toastElement.addEventListener('hidden.bs.toast', function() {
        this.remove();
    });
}



// Version 1 a voir et pas test date du >01/.07/2025

// // Initialiser Sortable pour le drag & drop
// document.addEventListener('DOMContentLoaded', function () {
//     const columns = document.querySelectorAll('.kanban-tasks');

//     columns.forEach(column => {
//         new Sortable(column, {
//             group: 'kanban',
//             animation: 150,
//             onEnd: function (evt) {
//                 const taskId = evt.item.getAttribute('data-task-id');
//                 const newColumnId = evt.to.getAttribute('data-column-id');
//                 const newPosition = evt.newIndex;

//                 // Envoyer la mise à jour au serveur
//                 fetch(`/tasks/${taskId}/move`, {
//                     method: 'POST',
//                     headers: {
//                         'Content-Type': 'application/json'
//                     },
//                     body: JSON.stringify(
//                         { columnId: newColumnId, position: newPosition }
//                     )
//                 }).then(response => response.json()).then(data => {
//                     if (!data.success) {
//                         console.error('Erreur lors du déplacement de la tâche');
//                         // Remettre l'élément à sa position originale
//                         evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
//                     }
//                 });
//             }
//         });
//     });

//     // Gestion du changement de statut des tâches
//     document.querySelectorAll('.task-statut-select').forEach(select => {
//         select.addEventListener('change', function () {
//             const taskId = this.getAttribute('data-task-id');
//             const newstatut = this.value;

//             fetch(`/tasks/${taskId}/update-statut`, {
//                 method: 'POST',
//                 headers: {
//                     'Content-Type': 'application/x-www-form-urlencoded'
//                 },
//                 body: `statut=${newstatut}`
//             }).then(response => response.json()).then(data => {
//                 if (data.success) { // Mettre à jour la classe CSS de la carte
//                     const card = this.closest('.kanban-card');
//                     card.className = card.className.replace(/statut-\w+/g, '');

//                     if (newstatut === 'EN-ATTENTE')
//                         card.classList.add('statut-pending');
//                     else if (newstatut === 'EN-COURS')
//                         card.classList.add('statut-progress');
//                     else if (newstatut === 'TERMINE')
//                         card.classList.add('statut-completed');



//                 } else {
//                     alert('Erreur lors de la mise à jour du statut');
//                 }
//             });
//         });
//     });
// });
 
// // Ajoutez la fonction addColumn en dehors du gestionnaire d'événements DOMContentLoaded
// function addColumn() {
//     const form = document.getElementById('addColumnForm');
//     const formData = new FormData(form);

//     fetch(`/task-lists/new/{{ project.id }}`, {
//         method: 'POST',
//         body: formData
//     }).then(response => response.json()).then(data => {
//         if (data.success) {
//             location.reload();
//         } else {
//             alert('Erreur lors de la création de la colonne');
//         }
//     });
// }
