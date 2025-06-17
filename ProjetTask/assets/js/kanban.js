// Initialiser Sortable pour le drag & drop
document.addEventListener('DOMContentLoaded', function () {
    const columns = document.querySelectorAll('.kanban-tasks');

    columns.forEach(column => {
        new Sortable(column, {
            group: 'kanban',
            animation: 150,
            onEnd: function (evt) {
                const taskId = evt.item.getAttribute('data-task-id');
                const newColumnId = evt.to.getAttribute('data-column-id');
                const newPosition = evt.newIndex;

                // Envoyer la mise à jour au serveur
                fetch(`/tasks/${taskId}/move`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(
                        { columnId: newColumnId, position: newPosition }
                    )
                }).then(response => response.json()).then(data => {
                    if (!data.success) {
                        console.error('Erreur lors du déplacement de la tâche');
                        // Remettre l'élément à sa position originale
                        evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex]);
                    }
                });
            }
        });
    });

    // Gestion du changement de statut des tâches
    document.querySelectorAll('.task-status-select').forEach(select => {
        select.addEventListener('change', function () {
            const taskId = this.getAttribute('data-task-id');
            const newStatus = this.value;

            fetch(`/tasks/${taskId}/update-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `status=${newStatus}`
            }).then(response => response.json()).then(data => {
                if (data.success) { // Mettre à jour la classe CSS de la carte
                    const card = this.closest('.kanban-card');
                    card.className = card.className.replace(/status-\w+/g, '');

                    if (newStatus === 'EN-ATTENTE')
                        card.classList.add('status-pending');
                    else if (newStatus === 'EN-COURS')
                        card.classList.add('status-progress');
                    else if (newStatus === 'TERMINE')
                        card.classList.add('status-completed');



                } else {
                    alert('Erreur lors de la mise à jour du statut');
                }
            });
        });
    });
});
 
// Ajoutez la fonction addColumn en dehors du gestionnaire d'événements DOMContentLoaded
function addColumn() {
    const form = document.getElementById('addColumnForm');
    const formData = new FormData(form);

    fetch(`/task-lists/new/{{ project.id }}`, {
        method: 'POST',
        body: formData
    }).then(response => response.json()).then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erreur lors de la création de la colonne');
        }
    });
}

