(function () {
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));

  // Containers and context
  const container = $('.kanban-container');
  if (!container) return;
  const projectId = container.dataset.projectId;
  const board = $('.kanban-board');
  const readOnly = container.dataset.readOnly === 'true';

  // ----------------------
  // Toast helper
  // ----------------------
  function showToast(message, type = 'success') {
    let area = $('#toast-area');
    if (!area) {
      area = document.createElement('div');
      area.id = 'toast-area';
      area.className = 'toast-container position-fixed bottom-0 end-0 p-3';
      document.body.appendChild(area);
    }
    const toast = document.createElement('div');
    const color = (type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'danger');
    toast.className = `toast align-items-center text-bg-${color} border-0`;
    toast.setAttribute('role', 'status');
    toast.innerHTML = `
      <div class="d-flex">
        <div class="toast-body">${message}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Fermer"></button>
      </div>`;
    area.appendChild(toast);
    const t = new bootstrap.Toast(toast, { delay: 2500 });
    t.show();
    toast.addEventListener('hidden.bs.toast', () => toast.remove());
  }

  // ----------------------
  // Column count updater
  // ----------------------
  function updateColumnCounts() {
    $$('.kanban-column').forEach(col => {
      const count = col.querySelectorAll('.kanban-item').length;
      const badge = col.querySelector('.badge');
      if (badge) badge.textContent = count;
    });
  }

  // ----------------------
  // API helpers
  // ----------------------
  async function apiJson(url, options = {}) {
    const opts = {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...options,
    };
    const res = await fetch(url, opts);
    let data = null;
    try { data = await res.json(); } catch (_) {}
    if (!res.ok || (data && data.success === false)) {
      const msg = (data && (data.error || data.message)) || `HTTP ${res.status}`;
      throw new Error(msg);
    }
    return data;
  }

  // ----------------------
  // Column event helpers
  // ----------------------
  function attachAddTaskEvent(button) {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const columnId = this.dataset.columnId;
      console.log('Ajouter tâche pour colonne', columnId);
      // Ici tu peux ouvrir le modal de création de tâche
    });
  }

  function attachEditColumnEvent(button) {
    button.addEventListener('click', function(e) {
      e.preventDefault();
      const columnId = this.dataset.columnId;
      console.log('Modifier colonne', columnId);
      // Ici tu peux ouvrir le modal d'édition
    });
  }

  function attachDeleteColumnEvent(button) {
    button.addEventListener('click', async function(e) {
      e.preventDefault();
      const columnId = this.dataset.columnId;
      if (confirm('Supprimer cette colonne ?')) {
        try {
          await apiJson(`/api/project/${projectId}/tasklists/${columnId}/delete`, { method: 'POST' });
          document.querySelector(`.kanban-column[data-column-id="${columnId}"]`)?.remove();
          showToast('Colonne supprimée', 'success');
        } catch (err) {
          showToast(err.message, 'danger');
        }
      }
    });
  }

  function attachColumnEvents(columnElement) {
    const addBtn = columnElement.querySelector('.add-task-btn');
    const editBtn = columnElement.querySelector('.edit-column-btn');
    const deleteBtn = columnElement.querySelector('.delete-column-btn');

    if (addBtn) attachAddTaskEvent(addBtn);
    if (editBtn) attachEditColumnEvent(editBtn);
    if (deleteBtn) attachDeleteColumnEvent(deleteBtn);

    initDnDColumn(columnElement);
  }

  // ----------------------
  // Add Column (modal-driven)
  // ----------------------
  function initAddColumn() {
    const btnAddCol = $('#btn-add-column');
    if (!btnAddCol || readOnly) return;

    const modalEl = $('#column-modal');
    const form = $('#column-form');
    const saveBtn = $('#save-column');

    btnAddCol.addEventListener('click', () => {
      if (!modalEl) {
        const name = prompt('Nom de la colonne:', 'Nouvelle colonne');
        if (!name) return;
        createColumn({ name }).catch(e => showToast(e.message, 'danger'));
        return;
      }
      form.reset();
      form.elements.projectId.value = projectId;
      form.elements.columnId.value = '';
      $('#column-modal-title').textContent = 'Nouvelle colonne';
      new bootstrap.Modal(modalEl).show();
    });

    if (modalEl && saveBtn && form) {
      saveBtn.addEventListener('click', async () => {
        const formData = new FormData(form);
        const payload = {
          name: String(formData.get('nom') || '').trim(),
          color: String(formData.get('couleur') || '#dbeafe'),
        };
        if (!payload.name) {
          showToast('Le nom de la colonne est requis', 'warning');
          return;
        }
        try {
          const data = await createColumn(payload);
          bootstrap.Modal.getInstance(modalEl)?.hide();
          showToast('Colonne créée', 'success');

          // Ajouter dynamiquement la colonne dans le DOM
          const newCol = document.createElement('div');
          newCol.classList.add('kanban-column');
          newCol.dataset.columnId = data.id;
          newCol.innerHTML = `
            <div class="kanban-column-header" data-handle="column-drag">
              <span class="column-title">${data.nom}</span>
              <div class="column-actions">
                <button class="btn btn-sm btn-success add-task-btn" data-column-id="${data.id}">+</button>
                <button class="btn btn-sm btn-primary edit-column-btn" data-column-id="${data.id}">✎</button>
                <button class="btn btn-sm btn-danger delete-column-btn" data-column-id="${data.id}">🗑</button>
              </div>
            </div>
            <div class="kanban-list" data-column-id="${data.id}"></div>
          `;
          board.appendChild(newCol);
          attachColumnEvents(newCol);
        } catch (e) {
          showToast(e.message, 'danger');
        }
      });
    }
  }

  async function createColumn({ name, color = '#dbeafe' }) {
    return apiJson(`/api/project/${projectId}/tasklists/new`, {
      method: 'POST',
      body: JSON.stringify({ nom: name, couleur: color }),
    });
  }

  // ----------------------
  // Task Details (modal)
  // ----------------------
  function initTaskDetails() {
    const modalEl = $('#task-details-modal');
    if (!modalEl) return;

    document.addEventListener('click', async (e) => {
      const btn = e.target.closest('.btn-task-details');
      if (!btn) return;
      e.preventDefault();
      const id = btn.dataset.taskId;
      try {
        const data = await apiJson(`/api/task/${id}`, { method: 'GET' });
        const t = data.task;
        modalEl.querySelector('.modal-title').textContent = t.titre || `Tâche #${t.id}`;
        modalEl.querySelector('.task-details-body').innerHTML = `
          <div class="mb-2">
            ${t.priority ? `<span class="badge priority-${t.priority} me-2">${t.priorityLabel || t.priority}</span>` : ''}
            ${t.statut ? `<span class="badge statut-${t.statut}">${t.statutLabel || t.statut}</span>` : ''}
          </div>
          ${t.description ? `<div class="text-muted mb-2">${t.description}</div>` : ''}
          <div class="small"><i class="bi bi-calendar-event me-1"></i> ${t.dateButoir || '—'}</div>
          <div class="small"><i class="bi bi-person me-1"></i> ${t.assignedUser ? t.assignedUser.name : 'Non assignée'}</div>
        `;
        new bootstrap.Modal(modalEl).show();
      } catch (e) {
        showToast(e.message, 'danger');
      }
    });
  }

  // ----------------------
  // Drag & Drop
  // ----------------------
  function initDnD() {
    if (readOnly) return;

    $$('.kanban-list').forEach(listEl => {
      new Sortable(listEl, {
        group: 'kanban',
        handle: '[data-handle="task-drag"]',
        animation: 150,
        onEnd: async (evt) => {
          const item = evt.item;
          const taskId = item.dataset.taskId;
          const targetColumnId = evt.to.dataset.columnId;
          const targetPosition = evt.newIndex;
          try {
            await apiJson(`/api/task/${taskId}/move`, {
              method: 'POST',
              body: JSON.stringify({ columnId: parseInt(targetColumnId, 10), position: targetPosition }),
            });
            updateColumnCounts();
            showToast('Tâche déplacée', 'success');
          } catch (e) {
            showToast(e.message, 'danger');
            evt.from.insertBefore(item, evt.from.children[evt.oldIndex] || null);
          }
        }
      });
    });

    new Sortable(board, {
      animation: 150,
      handle: '[data-handle="column-drag"]',
      draggable: '.kanban-column',
      onEnd: async () => {
        const ids = $$('.kanban-column').map(el => parseInt(el.dataset.columnId, 10));
        try {
          await apiJson(`/api/project/${projectId}/tasklists/reorder`, {
            method: 'POST',
            body: JSON.stringify({ columns: ids }),
          });
          showToast('Colonnes réordonnées', 'success');
        } catch (e) {
          showToast(e.message, 'danger');
        }
      }
    });
  }

  function initDnDColumn(columnElement) {
    const listEl = columnElement.querySelector('.kanban-list');
    if (!listEl || readOnly) return;
    new Sortable(listEl, {
      group: 'kanban',
      handle: '[data-handle="task-drag"]',
      animation: 150,
      onEnd: async (evt) => {
        const item = evt.item;
        const taskId = item.dataset.taskId;
        const targetColumnId = evt.to.dataset.columnId;
        const targetPosition = evt.newIndex;
        try {
          await apiJson(`/api/task/${taskId}/move`, {
            method: 'POST',
            body: JSON.stringify({ columnId: parseInt(targetColumnId, 10), position: targetPosition }),
          });
          updateColumnCounts();
        } catch (e) {
          showToast(e.message, 'danger');
          evt.from.insertBefore(item, evt.from.children[evt.oldIndex] || null);
        }
      }
    });
  }

  // ----------------------
  // Initialization
  // ----------------------
  function initKanban() {
    initAddColumn();
    initTaskDetails();
    initDnD();
    updateColumnCounts();
    $$('.kanban-column').forEach(attachColumnEvents);
  }

  document.addEventListener('DOMContentLoaded', initKanban);
})();

// public/js/kanban.js avant les modifications du 20/08/2025
// import Sortable from 'sortablejs';
// (function() {
// const board = document.querySelector('.kanban-board');
// if (!board) return;

// const readOnly = board.closest('[data-project-archived]')?.dataset.projectArchived === 'true';

// function showToast(message, type='success') {
// const area = document.getElementById('toast-area');
// if (!area) return;
// const toast = document.createElement('div');
// toast.className = `toast align-items-center text-bg-${type} border-0`;
// toast.role = 'status';
// toast.ariaLive = 'polite';
// toast.innerHTML = <div class="d-flex"><div class="toast-body">${message}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>;
// area.appendChild(toast);
// // eslint-disable-next-line no-undef
// const t = new bootstrap.Toast(toast, { delay: 2200 });
// t.show();
// toast.addEventListener('hidden.bs.toast', () => toast.remove());
// }

// if (!readOnly) {
// // Handler: Ajouter une colonne
// document.querySelector('[data-action="kanban:new-column"]')?.addEventListener('click', async (e) => {
// e.preventDefault();
// const name = prompt('Nom de la colonne:', 'À faire');
// if (!name) return;
// const url = board.dataset.newColumnUrl;
// try {
// const res = await fetch(url, { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify({ name }) });
// const data = await res.json();
// if (!res.ok || !data.success) throw new Error(data.error || 'Erreur création colonne');
// // rafraîchissement léger: recharger la page ou insérer le DOM de la colonne (ici simple reload)
// location.reload();
// } catch (err) {
// showToast(err.message, 'danger');
// }
// });

// // Handler: ouvrir le modal “Nouvelle tâche” depuis une colonne
// document.addEventListener('click', (e) => {
// const btn = e.target.closest('[data-action="task:new"]');
// if (!btn) return;
// const columnId = btn.getAttribute('data-column-id');
// const input = document.getElementById('newTaskListId');
// if (input) input.value = columnId;
// const modalEl = document.getElementById('modalNewTask');
// if (modalEl) {
// const modal = new bootstrap.Modal(modalEl);
// modal.show();
// }
// });

// // Handler: créer la tâche (depuis modal)
// document.querySelector('[data-action="task:create"]')?.addEventListener('click', async () => {
// const form = document.getElementById('form-new-task');
// if (!form) return;
// const url = document.querySelector('[data-action="task:create"]').dataset.newTaskUrl;
// const payload = {
// title: form.title.value,
// priorite: form.priorite.value,
// dateDeFin: form.dateDeFin.value || null,
// taskListId: parseInt(form.taskListId.value, 10)
// };
// if (!payload.title || !payload.taskListId) {
// showToast('Titre et colonne requis', 'danger');
// return;
// }
// try {
// const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
// const data = await res.json();
// if (!res.ok || !data.success) throw new Error(data.error || 'Erreur création tâche');
// showToast('Tâche créée', 'success');
// // Option A: insérer la carte sans reload (à implémenter)
// // Option B (simple): reload
// location.reload();
// } catch (err) {
// showToast(err.message, 'danger');
// }
// });

// }

// // ... gardez vos initialisations Sortable existantes (drag tasks, drag columns) ...
// })();
// Test Version 2 - 3 a voir  du 02/.07/2025
/**
 * Initialise l'action pour ajouter une tâche
 */
// function initAddTask() {
//     // Écouter les clics sur les boutons d'ajout de tâche
//     document.querySelectorAll('.btn-add-task').forEach(button => {
//         button.addEventListener('click', function() {
//             const columnId = this.dataset.columnId;
//             const formContainer = document.getElementById('addTaskFormContainer');
            
//             // Charger le formulaire via AJAX
//             fetch(`/task/new/${columnId}`, {
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 }
//             })
//             .then(response => response.text())
//             .then(html => {
//                 formContainer.innerHTML = html;
                
//                 // Initialiser le formulaire pour l'envoi AJAX
//                 initTaskForm(formContainer.querySelector('form'), document.getElementById('addTaskModal'));
                
//                 // Initialiser le datepicker si présent
//                 initDatepicker();
                
//                 // Afficher le modal
//                 const modal = new bootstrap.Modal(document.getElementById('addTaskModal'));
//                 modal.show();
//             })
//             .catch(error => console.error('Erreur lors du chargement du formulaire:', error));
//         });
//     });
// }

// /**
//  * Initialise l'action pour éditer une tâche
//  */
// function initEditTask() {
//     // Écouter les clics sur les boutons d'édition de tâche
//     document.querySelectorAll('.btn-edit-task').forEach(button => {
//         button.addEventListener('click', function() {
//             const taskId = this.dataset.taskId;
//             const formContainer = document.getElementById('editTaskFormContainer');
            
//             // Charger le formulaire via AJAX
//             fetch(`/task/${taskId}/edit`, {
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 }
//             })
//             .then(response => response.text())
//             .then(html => {
//                 formContainer.innerHTML = html;
                
//                 // Initialiser le formulaire pour l'envoi AJAX
//                 initTaskForm(formContainer.querySelector('form'), document.getElementById('editTaskModal'));
                
//                 // Initialiser le datepicker si présent
//                 initDatepicker();
                
//                 // Afficher le modal
//                 const modal = new bootstrap.Modal(document.getElementById('editTaskModal'));
//                 modal.show();
//             })
//             .catch(error => console.error('Erreur lors du chargement du formulaire:', error));
//         });
//     });
// }

// /**
//  * Initialise l'action pour supprimer une tâche
//  */
// function initDeleteTask() {
//     const deleteTaskModal = document.getElementById('deleteTaskModal');
//     if (!deleteTaskModal) return;
    
//     // Écouter les clics sur les boutons de suppression de tâche
//     document.querySelectorAll('.btn-delete-task').forEach(button => {
//         button.addEventListener('click', function() {
//             const taskId = this.dataset.taskId;
//             const taskTitle = this.dataset.taskTitle;
            
//             // Mettre à jour le modal avec les informations de la tâche
//             document.getElementById('deleteTaskTitle').textContent = taskTitle;
            
//             // Configurer le formulaire de suppression
//             const form = document.getElementById('deleteTaskForm');
//             form.action = `/task/${taskId}`;
            
//             // Générer un token CSRF
//             fetch(`/generate-csrf-token?id=delete${taskId}`)
//                 .then(response => response.json())
//                 .then(data => {
//                     form.querySelector('input[name="_token"]').value = data.token;
//                 })
//                 .catch(error => console.error('Erreur lors de la génération du token CSRF:', error));
            
//             // Afficher le modal
//             const modal = new bootstrap.Modal(deleteTaskModal);
//             modal.show();
//         });
//     });
    
//     // Soumission du formulaire de suppression
//     const deleteForm = document.getElementById('deleteTaskForm');
//     if (deleteForm) {
//         deleteForm.addEventListener('submit', function(event) {
//             event.preventDefault();
            
//             fetch(this.action, {
//                 method: 'POST',
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 },
//                 body: new FormData(this)
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     // Fermer le modal
//                     bootstrap.Modal.getInstance(deleteTaskModal).hide();
                    
//                     // Supprimer la tâche du DOM
//                     const taskId = this.action.split('/').pop();
//                     document.querySelector(`.kanban-task[data-task-id="${taskId}"]`).remove();
                    
//                     // Mettre à jour les compteurs de tâches
//                     updateTaskCounters();
                    
//                     // Afficher un message de succès
//                     showToast('Tâche supprimée avec succès', 'success');
//                 } else {
//                     showToast(data.error || 'Erreur lors de la suppression de la tâche', 'error');
//                 }
//             })
//             .catch(error => {
//                 console.error('Erreur lors de la requête:', error);
//                 showToast('Erreur lors de la suppression de la tâche', 'error');
//             });
//         });
//     }
// }

// /**
//  * Initialise un formulaire de tâche pour l'envoi AJAX
//  */
// function initTaskForm(form, modal) {
//     if (!form) return;
    
//     form.addEventListener('submit', function(event) {
//         event.preventDefault();
        
//         fetch(this.dataset.action || this.action, {
//             method: 'POST',
//             headers: {
//                 'X-Requested-With': 'XMLHttpRequest'
//             },
//             body: new FormData(this)
//         })
//         .then(response => response.json())
//         .then(data => {
//             if (data.success) {
//                 // Fermer le modal
//                 bootstrap.Modal.getInstance(modal).hide();
                
//                 // Recharger la page pour afficher les changements
//                 window.location.reload();
//             } else {
//                 showToast(data.error || 'Erreur lors de l\'enregistrement de la tâche', 'error');
//             }
//         })
//         .catch(error => {
//             console.error('Erreur lors de la requête:', error);
//             showToast('Erreur lors de l\'enregistrement de la tâche', 'error');
//         });
//     });
// }

// /**
//  * Initialise l'action pour assigner une tâche à un utilisateur
//  */
// function initAssignTask() {
//     const assignTaskModal = document.getElementById('assignTaskModal');
//     if (!assignTaskModal) return;
    
//     // Variable pour stocker l'ID de la tâche en cours d'assignation
//     let currentTaskId = null;
    
//     // Écouter les clics sur les boutons d'assignation de tâche
//     document.querySelectorAll('.btn-assign-task').forEach(button => {
//         button.addEventListener('click', function() {
//             currentTaskId = this.dataset.taskId;
            
//             // Afficher le modal
//             const modal = new bootstrap.Modal(assignTaskModal);
//             modal.show();
//         });
//     });
    
//     // Écouter les clics sur les utilisateurs dans la liste
//     document.querySelectorAll('.user-item').forEach(userItem => {
//         userItem.addEventListener('click', function() {
//             if (!currentTaskId) return;
            
//             const userId = this.dataset.userId;
            
//             // Assigner la tâche à l'utilisateur via AJAX
//             fetch(`/task/${currentTaskId}/assign/${userId}`, {
//                 method: 'POST',
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest',
//                     'Content-Type': 'application/json'
//                 }
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     // Fermer le modal
//                     bootstrap.Modal.getInstance(assignTaskModal).hide();
                    
//                     // Mettre à jour l'affichage de la tâche
//                     const taskElement = document.querySelector(`.kanban-task[data-task-id="${currentTaskId}"]`);
//                     if (taskElement) {
//                         const assignedElement = taskElement.querySelector('.task-assigned');
//                         if (assignedElement) {
//                             assignedElement.innerHTML = `
//                                 <div class="assigned-user" title="${data.userName}">
//                                     <span class="user-avatar">${data.userName.split(' ').map(n => n[0]).join('').toUpperCase()}</span>
//                                     <span class="user-name">${data.userName}</span>
//                                 </div>
//                             `;
//                         }
//                     }
                    
//                     showToast(`Tâche assignée à ${data.userName}`, 'success');
//                 } else {
//                     showToast(data.error || 'Erreur lors de l\'assignation de la tâche', 'error');
//                 }
//             })
//             .catch(error => {
//                 console.error('Erreur lors de la requête:', error);
//                 showToast('Erreur lors de l\'assignation de la tâche', 'error');
//             });
//         });
//     });
// }

// /**
//  * Initialise les datepickers dans les formulaires
//  */
// function initDatepicker() {
//     const datepickers = document.querySelectorAll('.datepicker');
//     if (datepickers.length > 0) {
//         datepickers.forEach(input => {
//             // Utiliser flatpickr ou autre bibliothèque de datepicker
//             // Exemple avec flatpickr :
//             if (typeof flatpickr === 'function') {
//                 flatpickr(input, {
//                     dateFormat: "Y-m-d",
//                     altInput: true,
//                     altFormat: "d/m/Y",
//                     locale: "fr"
//                 });
//             }
//         });
//     }
// }

// /**
//  * Affiche un message toast
//  */
// function showToast(message, type = 'info') {
//     // Créer un élément toast s'il n'existe pas
//     let toastContainer = document.querySelector('.toast-container');
    
//     if (!toastContainer) {
//         toastContainer = document.createElement('div');
//         toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
//         document.body.appendChild(toastContainer);
//     }
    
//     // Créer le toast
//     const toastId = 'toast-' + Date.now();
//     const toastHtml = `
//         <div id="${toastId}" class="toast align-items-center text-white bg-${type === 'success' ? 'success' : type === 'error' ? 'danger' : 'primary'}" role="alert" aria-live="assertive" aria-atomic="true">
//             <div class="d-flex">
//                 <div class="toast-body">
//                     ${message}
//                 </div>
//                 <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
//             </div>
//         </div>
//     `;
    
//     toastContainer.insertAdjacentHTML('beforeend', toastHtml);
    
//     // Afficher le toast
//     const toastElement = document.getElementById(toastId);
//     const toast = new bootstrap.Toast(toastElement, {
//         autohide: true,
//         delay: 5000
//     });
    
//     toast.show();
    
//     // Supprimer le toast du DOM après sa disparition
//     toastElement.addEventListener('hidden.bs.toast', function() {
//         this.remove();
//     });
// }
// /**
//  * kanban.js - Gestion du Kanban avec drag & drop
//  */

// document.addEventListener('DOMContentLoaded', function() {
//     initKanban();
//     initAddTask();
//     initEditTask();
//     initDeleteTask();
//     initAssignTask();
//     initTaskSearch();
//     initDatepicker();
// });

// /**
//  * Initialise le Kanban et le système de drag & drop
//  */
// function initKanban() {
//     // S'assurer que Sortable.js est chargé
//     if (typeof Sortable === 'undefined') {
//         console.error('Erreur: Sortable.js est requis pour le Kanban. Veuillez l\'inclure dans votre page.');
//         return;
//     }
    
//     // Initialiser le drag & drop pour les colonnes
//     const kanbanBoard = document.querySelector('.kanban-board');
//     if (kanbanBoard) {
//         Sortable.create(kanbanBoard, {
//             animation: 150,
//             handle: '.column-header',
//             draggable: '.kanban-column',
//             ghostClass: 'kanban-column-ghost',
//             chosenClass: 'kanban-column-chosen',
//             dragClass: 'kanban-column-drag',
//             onEnd: function(evt) {
//                 const columns = Array.from(kanbanBoard.querySelectorAll('.kanban-column'));
//                 const columnIds = columns.map(col => col.dataset.columnId);
                
//                 // Envoyer l'ordre des colonnes au serveur
//                 updateColumnOrder(columnIds);
//             }
//         });
//     }
    
//     // Initialiser le drag & drop pour les tâches dans chaque colonne
//     const taskContainers = document.querySelectorAll('.column-tasks');
//     taskContainers.forEach(container => {
//         Sortable.create(container, {
//             animation: 150,
//             group: 'tasks',
//             draggable: '.kanban-task',
//             ghostClass: 'kanban-task-ghost',
//             chosenClass: 'kanban-task-chosen',
//             dragClass: 'kanban-task-drag',
//             onEnd: function(evt) {
//                 // Si la tâche a changé de colonne
//                 if (evt.from !== evt.to) {
//                     const taskId = evt.item.dataset.taskId;
//                     const newColumnId = evt.to.closest('.kanban-column').dataset.columnId;
                    
//                     // Mettre à jour le statut de la tâche dans la BD
//                     updateTaskColumn(taskId, newColumnId);
//                 }
                
//                 // Mettre à jour l'ordre des tâches dans la colonne
//                 const tasks = Array.from(evt.to.querySelectorAll('.kanban-task'));
//                 const taskIds = tasks.map(task => task.dataset.taskId);
                
//                 updateTaskOrder(evt.to.closest('.kanban-column').dataset.columnId, taskIds);
//             }
//         });
//     });
// }

// /**
//  * Met à jour l'ordre des colonnes dans la base de données
//  */
// function updateColumnOrder(columnIds) {
//     fetch('/api/tasklist/reorder', {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'X-Requested-With': 'XMLHttpRequest',
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//         },
//         body: JSON.stringify({ columns: columnIds })
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success) {
//             showToast('Ordre des colonnes mis à jour', 'success');
//         } else {
//             showToast('Erreur lors de la mise à jour de l\'ordre des colonnes', 'error');
//         }
//     })
//     .catch(error => {
//         console.error('Erreur lors de la requête:', error);
//         showToast('Erreur lors de la mise à jour de l\'ordre des colonnes', 'error');
//     });
// }

// /**
//  * Met à jour la colonne d'une tâche (son statut)
//  */
// function updateTaskColumn(taskId, columnId) {
//     fetch(`/api/task/${taskId}/move`, {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'X-Requested-With': 'XMLHttpRequest',
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//         },
//         body: JSON.stringify({ columnId: columnId })
//     })
//     .then(response => response.json())
//         .then(data => {
//             if (data.success) {
//                 // Mettre à jour les compteurs de tâches
//                 updateTaskCounters();
//                 showToast('Tâche déplacée avec succès', 'success');
//             } else {
//                 // En cas d'erreur, recharger la page pour rétablir l'état correct
//                 showToast('Erreur lors du déplacement de la tâche', 'error');
//                          setTimeout(() => window.location.reload(), 2000);
//         }
//     })
//     .catch(error => {
//         console.error('Erreur lors de la requête:', error);
//         showToast('Erreur lors du déplacement de la tâche', 'error');
//         setTimeout(() => window.location.reload(), 2000);
//     });
// }

// /**
//  * Met à jour l'ordre des tâches dans une colonne
//  */
// function updateTaskOrder(columnId, taskIds) {
//     fetch(`/api/column/${columnId}/tasks/reorder`, {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'X-Requested-With': 'XMLHttpRequest',
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//         },
//         body: JSON.stringify({ tasks: taskIds })
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success) {
//             // Pas besoin de notification pour cette opération fréquente
//         } else {
//             showToast('Erreur lors de la mise à jour de l\'ordre des tâches', 'error');
//         }
//     })
//     .catch(error => {
//         console.error('Erreur lors de la requête:', error);
//     });
// }

// /**
//  * Met à jour les compteurs de tâches dans chaque colonne
//  */
// function updateTaskCounters() {
//     document.querySelectorAll('.kanban-column').forEach(column => {
//         const taskCount = column.querySelectorAll('.kanban-task').length;
//         const counterElement = column.querySelector('.kanban-column-count');
        
//         if (counterElement) {
//             counterElement.textContent = taskCount;
//         }
//     });
// }

// /**
//  * Initialise l'ajout de nouvelles tâches
//  */
// function initAddTask() {
//     const addButtons = document.querySelectorAll('.btn-add-task');
    
//     addButtons.forEach(button => {
//         button.addEventListener('click', function() {
//             const columnId = this.closest('.kanban-column').dataset.columnId;
//             const projectId = document.querySelector('.kanban-container').dataset.projectId;
            
//             // Pré-remplir le formulaire avec la colonne et le project
//             const form = document.querySelector('#task-form');
//             if (form) {
//                 form.reset();
//                 form.querySelector('[name="columnId"]').value = columnId;
//                 form.querySelector('[name="projectId"]').value = projectId;
                
//                 // Réinitialiser l'ID de tâche pour indiquer qu'il s'agit d'une nouvelle tâche
//                 form.querySelector('[name="taskId"]').value = '';
                
//                 // Changer le titre du modal
//                 document.querySelector('#task-modal-title').textContent = 'Nouvelle tâche';
                
//                 // Afficher le modal
//                 const modal = new bootstrap.Modal(document.getElementById('task-modal'));
//                 modal.show();
//             }
//         });
//     });
    
//     // Gérer la soumission du formulaire
//     const taskForm = document.querySelector('#task-form');
//     if (taskForm) {
//         taskForm.addEventListener('submit', function(e) {
//             e.preventDefault();
            
//             const formData = new FormData(this);
//             const taskId = formData.get('taskId');
//             const url = taskId ? `/api/task/${taskId}/update` : '/api/task/create';
            
//             fetch(url, {
//                 method: 'POST',
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest',
//                     'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//                 },
//                 body: formData
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     // Fermer le modal
//                     const modal = bootstrap.Modal.getInstance(document.getElementById('task-modal'));
//                     modal.hide();
                    
//                     // Si c'est une nouvelle tâche, l'ajouter à la colonne
//                     if (!taskId) {
//                         const columnTasks = document.querySelector(`.kanban-column[data-column-id="${formData.get('columnId')}"] .column-tasks`);
//                         columnTasks.innerHTML += createTaskHtml(data.task);
//                         updateTaskCounters();
//                     } else {
//                         // Sinon, mettre à jour la tâche existante
//                         const taskElement = document.querySelector(`.kanban-task[data-task-id="${taskId}"]`);
//                         if (taskElement) {
//                             taskElement.outerHTML = createTaskHtml(data.task);
//                         }
//                     }
                    
//                     showToast(taskId ? 'Tâche mise à jour' : 'Tâche créée', 'success');
                    
//                     // Réinitialiser les gestionnaires d'événements
//                     initEditTask();
//                     initDeleteTask();
//                     initAssignTask();
//                 } else {
//                     showToast(data.message || 'Erreur lors de l\'enregistrement de la tâche', 'error');
//                 }
//             })
//             .catch(error => {
//                 console.error('Erreur lors de la requête:', error);
//                 showToast('Erreur lors de l\'enregistrement de la tâche', 'error');
//             });
//         });
//     }
// }

// /**
//  * Initialise l'édition de tâches existantes
//  */
// function initEditTask() {
//     const editButtons = document.querySelectorAll('.btn-edit-task');
    
//     editButtons.forEach(button => {
//         button.addEventListener('click', function(e) {
//             e.preventDefault();
//             e.stopPropagation();
            
//             const taskId = this.closest('.kanban-task').dataset.taskId;
            
//             // Charger les détails de la tâche
//             fetch(`/api/task/${taskId}`, {
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 }
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     const form = document.querySelector('#task-form');
//                     if (form) {
//                         // Remplir le formulaire avec les données de la tâche
//                         form.querySelector('[name="taskId"]').value = data.task.id;
//                         form.querySelector('[name="titre"]').value = data.task.titre;
//                         form.querySelector('[name="description"]').value = data.task.description || '';
//                         form.querySelector('[name="priority"]').value = data.task.priority;
//                         form.querySelector('[name="dateButoir"]').value = data.task.dateButoir || '';
//                         form.querySelector('[name="columnId"]').value = data.task.columnId;
//                         form.querySelector('[name="projectId"]').value = data.task.projectId;
                        
//                         // Changer le titre du modal
//                         document.querySelector('#task-modal-title').textContent = 'Modifier la tâche';
                        
//                         // Afficher le modal
//                         const modal = new bootstrap.Modal(document.getElementById('task-modal'));
//                         modal.show();
//                     }
//                 } else {
//                     showToast('Erreur lors du chargement de la tâche', 'error');
//                 }
//             })
//             .catch(error => {
//                 console.error('Erreur lors de la requête:', error);
//                 showToast('Erreur lors du chargement de la tâche', 'error');
//             });
//         });
//     });
// }

// /**
//  * Initialise la suppression de tâches
//  */
// function initDeleteTask() {
//     const deleteButtons = document.querySelectorAll('.btn-delete-task');
    
//     deleteButtons.forEach(button => {
//         button.addEventListener('click', function(e) {
//             e.preventDefault();
//             e.stopPropagation();
            
//             const taskElement = this.closest('.kanban-task');
//             const taskId = taskElement.dataset.taskId;
//             const taskTitle = taskElement.querySelector('.kanban-task-title').textContent.trim();
            
//             confirmAction(
//                 'Supprimer la tâche',
//                 `Êtes-vous sûr de vouloir supprimer la tâche "${taskTitle}" ?`,
//                 function() {
//                     fetch(`/api/task/${taskId}/delete`, {
//                         method: 'POST',
//                         headers: {
//                             'Content-Type': 'application/json',
//                             'X-Requested-With': 'XMLHttpRequest',
//                             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//                         }
//                     })
//                     .then(response => response.json())
//                     .then(data => {
//                         if (data.success) {
//                             // Supprimer la tâche du DOM avec animation
//                             taskElement.style.opacity = '0';
//                             setTimeout(() => {
//                                 taskElement.remove();
//                                 updateTaskCounters();
//                             }, 300);
                            
//                             showToast('Tâche supprimée', 'success');
//                         } else {
//                             showToast(data.message || 'Erreur lors de la suppression de la tâche', 'error');
//                         }
//                     })
//                     .catch(error => {
//                         console.error('Erreur lors de la requête:', error);
//                         showToast('Erreur lors de la suppression de la tâche', 'error');
//                     });
//                 }
//             );
//         });
//     });
// }

// /**
//  * Initialise l'assignation des utilisateurs aux tâches
//  */
// function initAssignTask() {
//     const assignButtons = document.querySelectorAll('.btn-assign-task');
    
//     assignButtons.forEach(button => {
//         button.addEventListener('click', function(e) {
//             e.preventDefault();
//             e.stopPropagation();
            
//             const taskId = this.closest('.kanban-task').dataset.taskId;
            
//             // Charger la liste des utilisateurs disponibles
//             fetch(`/api/users/available?task=${taskId}`, {
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 }
//             })
//             .then(response => response.json())
//             .then(data => {
//                 const userList = document.querySelector('#assign-modal .user-list');
//                 userList.innerHTML = '';
                
//                 if (data.users && data.users.length > 0) {
//                     data.users.forEach(user => {
//                         userList.innerHTML += `
//                             <div class="user-item" data-user-id="${user.id}" data-task-id="${taskId}">
//                                 <div class="user-avatar">${user.initials}</div>
//                                 <div class="user-info">
//                                     <div class="user-name">${user.name}</div>
//                                     <div class="user-email">${user.email}</div>
//                                 </div>
//                             </div>
//                         `;
//                     });
                    
//                     // Ajouter option pour désassigner
//                     userList.innerHTML += `
//                         <div class="user-item unassign" data-user-id="0" data-task-id="${taskId}">
//                             <div class="user-avatar"><i class="fas fa-user-slash"></i></div>
//                             <div class="user-info">
//                                 <div class="user-name">Désassigner</div>
//                                 <div class="user-email">Retirer l'utilisateur de cette tâche</div>
//                             </div>
//                         </div>
//                     `;
//                 } else {
//                     userList.innerHTML = '<div class="text-center text-muted py-3">Aucun utilisateur disponible</div>';
//                 }
                
//                 // Ajouter les gestionnaires d'événements pour l'assignation
//                 document.querySelectorAll('.user-item').forEach(item => {
//                     item.addEventListener('click', function() {
//                         const userId = this.dataset.userId;
//                         const taskId = this.dataset.taskId;
                        
//                         // Appel API pour assigner/désassigner
//                         fetch(`/api/task/${taskId}/assign`, {
//                             method: 'POST',
//                             headers: {
//                                 'Content-Type': 'application/json',
//                                 'X-Requested-With': 'XMLHttpRequest',
//                                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//                             },
//                             body: JSON.stringify({ userId: userId })
//                         })
//                         .then(response => response.json())
//                         .then(data => {
//                             if (data.success) {
//                                 // Fermer le modal
//                                 const modal = bootstrap.Modal.getInstance(document.getElementById('assign-modal'));
//                                 modal.hide();
                                
//                                 // Mettre à jour l'affichage de la tâche
//                                 const taskAssignee = document.querySelector(`.kanban-task[data-task-id="${taskId}"] .kanban-task-assignee`);
                                
//                                 if (userId === '0') {
//                                     // Désassignation
//                                     taskAssignee.innerHTML = '<span class="unassigned">Non assignée</span>';
//                                 } else {
//                                     // Assignation
//                                     taskAssignee.innerHTML = `
//                                         <div class="kanban-task-avatar">${data.user.initials}</div>
//                                         <div>${data.user.name}</div>
//                                     `;
//                                 }
                                
//                                 showToast(userId === '0' ? 'Tâche désassignée' : 'Tâche assignée', 'success');
//                             } else {
//                                 showToast(data.message || 'Erreur lors de l\'assignation', 'error');
//                             }
//                         })
//                         .catch(error => {
//                             console.error('Erreur lors de la requête:', error);
//                             showToast('Erreur lors de l\'assignation', 'error');
//                         });
//                     });
//                 });
                
//                 // Afficher le modal
//                 const modal = new bootstrap.Modal(document.getElementById('assign-modal'));
//                 modal.show();
//             })
//             .catch(error => {
//                 console.error('Erreur lors du chargement des utilisateurs:', error);
//                 showToast('Erreur lors du chargement des utilisateurs', 'error');
//             });
//         });
//     });
// }

// /**
//  * Crée le HTML pour une tâche
//  */
// function createTaskHtml(task) {
//     // DéTERMINERr la classe de priorité
//     let priorityClass = 'low';
//     if (task.priority === 'HAUTE') priorityClass = 'high';
//     else if (task.priority === 'MOYENNE') priorityClass = 'medium';
    
//     // Formater la date d'échéance
//     let dueDateHtml = '';
//     if (task.dateButoir) {
//         const dueDate = new Date(task.dateButoir);
//         const formattedDate = dueDate.toLocaleDateString('fr-FR');
//         const isOverdue = dueDate < new Date() && task.statut !== 'TERMINER';
        
//         dueDateHtml = `
//             <div class="kanban-task-due ${isOverdue ? 'overdue' : ''}">
//                 <i class="fas fa-calendar-alt"></i> ${formattedDate}
//             </div>
//         `;
//     }
    
//     // Préparer l'affichage de l'assigné
//     let assigneeHtml = '<span class="unassigned">Non assignée</span>';
//     if (task.assignedUser) {
//         const initials = task.assignedUser.prenom.charAt(0) + task.assignedUser.nom.charAt(0);
//         assigneeHtml = `
//             <div class="kanban-task-avatar">${initials}</div>
//             <div>${task.assignedUser.prenom} ${task.assignedUser.nom}</div>
//         `;
//     }
    
//     return `
//         <div class="kanban-task" data-task-id="${task.id}">
//             <div class="kanban-task-header">
//                 <h4 class="kanban-task-title">${task.titre}</h4>
//                 <span class="kanban-task-priority ${priorityClass}">${task.priority}</span>
//             </div>
//             ${task.description ? `<div class="kanban-task-description">${task.description}</div>` : ''}
//             <div class="kanban-task-meta">
//                 ${dueDateHtml}
//                 <div class="kanban-task-assignee">
//                     ${assigneeHtml}
//                 </div>
//             </div>
//             <div class="task-actions">
//                 <button class="btn btn-action btn-action-primary btn-assign-task" title="Assigner">
//                     <i class="fas fa-user-plus"></i>
//                 </button>
//                 <button class="btn btn-action btn-action-warning btn-edit-task" title="Modifier">
//                     <i class="fas fa-edit"></i>
//                 </button>
//                 <button class="btn btn-action btn-action-danger btn-delete-task" title="Supprimer">
//                     <i class="fas fa-trash"></i>
//                 </button>
//             </div>
//         </div>
//     `;
// }

// /**
//  * Initialise la recherche de tâches
//  */
// function initTaskSearch() {
//     const searchInput = document.querySelector('#task-search');
    
//     if (searchInput) {
//         searchInput.addEventListener('keyup', function() {
//             const searchValue = this.value.toLowerCase();
//             const tasks = document.querySelectorAll('.kanban-task');
            
//             tasks.forEach(task => {
//                 const title = task.querySelector('.kanban-task-title').textContent.toLowerCase();
//                 const description = task.querySelector('.kanban-task-description')?.textContent.toLowerCase() || '';
                
//                 if (title.includes(searchValue) || description.includes(searchValue)) {
//                     task.style.display = '';
//                 } else {
//                     task.style.display = 'none';
//                 }
//             });
//         });
//     }
// }

// /**
//  * Initialise les sélecteurs de date
//  */
// function initDatepicker() {
//     const dateInputs = document.querySelectorAll('.datepicker');
    
//     if (typeof flatpickr === 'function') {
//         dateInputs.forEach(input => {
//             flatpickr(input, {
//                 dateFormat: 'Y-m-d',
//                 locale: 'fr',
//                 altInput: true,
//                 altFormat: 'j F Y',
//                 minDate: 'today'
//             });
//         });
//     }
// }
// /**
//  * kanban.js - Gestion du tableau Kanban pour les projects
//  */

// document.addEventListener('DOMContentLoaded', function() {
//     initKanban();
//     initKanbanModals();
// });

// /**
//  * Initialise les fonctionnalités du tableau Kanban
//  */
// function initKanban() {
//     // Rendre les cartes déplaçables
//     const kanbanCards = document.querySelectorAll('.kanban-card');
//     const kanbanColumns = document.querySelectorAll('.kanban-column');
    
//     let draggedCard = null;
    
//     // Ajouter les événements de drag and drop pour chaque carte
//     kanbanCards.forEach(card => {
//         card.setAttribute('draggable', true);
        
//         card.addEventListener('dragstart', function(e) {
//             draggedCard = this;
//             setTimeout(() => {
//                 this.classList.add('dragging');
//             }, 0);
//         });
        
//         card.addEventListener('dragend', function(e) {
//             this.classList.remove('dragging');
//             draggedCard = null;
            
//             // Actualiser les compteurs
//             updateColumnCounts();
//         });
//     });
    
//     // Ajouter les événements pour les colonnes
//     kanbanColumns.forEach(column => {
//         column.addEventListener('dragover', function(e) {
//             e.preventDefault();
//             this.classList.add('dragging-over');
//         });
        
//         column.addEventListener('dragleave', function(e) {
//             this.classList.remove('dragging-over');
//         });
        
//         column.addEventListener('drop', function(e) {
//             e.preventDefault();
//             this.classList.remove('dragging-over');
            
//             if (draggedCard) {
//                 const cardsContainer = this.querySelector('.kanban-cards');
//                 cardsContainer.appendChild(draggedCard);
                
//                 // Envoyer les données au serveur
//                 updateTaskstatut(draggedCard.dataset.taskId, this.dataset.statut);
//             }
//         });
//     });
    
//     // Événements pour le bouton d'ajout de carte
//     document.querySelectorAll('.kanban-add-card').forEach(button => {
//         button.addEventListener('click', function() {
//             const statut = this.closest('.kanban-column').dataset.statut;
//             const projectId = document.getElementById('kanban-board').dataset.projectId;
            
//             // Ouvrir le modal de création de tâche avec le statut prédéfini
//             const modal = new bootstrap.Modal(document.getElementById('task-modal'));
            
//             // Remplir le formulaire
//             document.getElementById('task_statut').value = statut;
//             document.getElementById('task_project').value = projectId;
            
//             modal.show();
//         });
//     });
    
//     // Événements pour l'ouverture des détails d'une tâche
//     document.querySelectorAll('.kanban-card').forEach(card => {
//         card.addEventListener('click', function(e) {
//             // Ne pas déclencher si on est en train de glisser-déposer
//             if (e.target.closest('.kanban-card-actions')) {
//                 return;
//             }
            
//             const taskId = this.dataset.taskId;
//             window.location.href = `/task/${taskId}`;
//         });
//     });
// }

// /**
//  * Met à jour les compteurs de cartes dans chaque colonne
//  */
// function updateColumnCounts() {
//     document.querySelectorAll('.kanban-column').forEach(column => {
//         const count = column.querySelectorAll('.kanban-card').length;
//         column.querySelector('.kanban-column-count').textContent = count;
//     });
// }

// /**
//  * Met à jour le statut d'une tâche via une requête AJAX
//  */
// function updateTaskstatut(taskId, newstatut) {
//     fetch(`/api/task/${taskId}/statut`, {
//         method: 'POST',
//         headers: {
//             'Content-Type': 'application/json',
//             'X-Requested-With': 'XMLHttpRequest',
//             'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
//         },
//         body: JSON.stringify({ statut: newstatut })
//     })
//     .then(response => response.json())
//     .then(data => {
//         if (data.success) {
//             showToast('Statut de la tâche mis à jour', 'success');
//         } else {
//             showToast(data.message || 'Erreur lors de la mise à jour du statut', 'error');
//             // Recharger la page pour restaurer l'état précédent
//             setTimeout(() => window.location.reload(), 2000);
//         }
//     })
//     .catch(error => {
//         console.error('Erreur:', error);
//         showToast('Erreur lors de la mise à jour du statut', 'error');
//         // Recharger la page pour restaurer l'état précédent
//         setTimeout(() => window.location.reload(), 2000);
//     });
// }

// /**
//  * Initialise les modals pour la création et l'édition de tâches
//  */
// function initKanbanModals() {
//     // Modal de création de tâche
//     const taskModal = document.getElementById('task-modal');
    
//     if (taskModal) {
//         taskModal.addEventListener('hidden.bs.modal', function() {
//             // Réinitialiser le formulaire
//             document.getElementById('task-form').reset();
//         });
        
//         // Soumission du formulaire
//         document.getElementById('task-form').addEventListener('submit', function(e) {
//             e.preventDefault();
            
//             const formData = new FormData(this);
            
//             fetch(this.action, {
//                 method: 'POST',
//                 body: formData,
//                 headers: {
//                     'X-Requested-With': 'XMLHttpRequest'
//                 }
//             })
//             .then(response => response.json())
//             .then(data => {
//                 if (data.success) {
//                     // Fermer le modal
//                     bootstrap.Modal.getInstance(taskModal).hide();
                    
//                     showToast('Tâche créée avec succès', 'success');
                    
//                     // Recharger la page après un court délai
//                     setTimeout(() => window.location.reload(), 1000);
//                 } else {
//                     showToast(data.message || 'Erreur lors de la création de la tâche', 'error');
//                 }
//             })
//             .catch(error => {
//                 console.error('Erreur:', error);
//                 showToast('Erreur lors de la création de la tâche', 'error');
//             });
//         });
//     }
// }



