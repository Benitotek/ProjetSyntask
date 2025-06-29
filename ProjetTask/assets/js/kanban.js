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
    document.querySelectorAll('.task-statut-select').forEach(select => {
        select.addEventListener('change', function () {
            const taskId = this.getAttribute('data-task-id');
            const newstatut = this.value;

            fetch(`/tasks/${taskId}/update-statut`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `statut=${newstatut}`
            }).then(response => response.json()).then(data => {
                if (data.success) { // Mettre à jour la classe CSS de la carte
                    const card = this.closest('.kanban-card');
                    card.className = card.className.replace(/statut-\w+/g, '');

                    if (newstatut === 'EN-ATTENTE')
                        card.classList.add('statut-pending');
                    else if (newstatut === 'EN-COURS')
                        card.classList.add('statut-progress');
                    else if (newstatut === 'TERMINE')
                        card.classList.add('statut-completed');



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

