function openModal() {
    document.getElementById('addUserModal').classList.add('active');
}
function closeModal() {
    document.getElementById('addUserModal').classList.remove('active');
}
document.getElementById('addUserModal').addEventListener('click', function (e) {
    if (e.target === this) {
        closeModal();
    }
});
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
// Gestion de l'aperçu d'avatar
function previewAvatar(input) {
    const preview = document.getElementById('avatarPreview');
    const previewImg = document.getElementById('previewImage');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewImg.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function removeAvatar() {
    const preview = document.getElementById('avatarPreview');
    const input = document.getElementById('avatar');
    const previewImg = document.getElementById('previewImage');
    
    preview.style.display = 'none';
    input.value = '';
    previewImg.src = '';
}

// Fonctions de gestion des utilisateurs
function viewUser(userId) {
    // Rediriger vers la page de profil utilisateur
    window.location.href = `/admin/users/${userId}/view`;
}

function editUser(userId) {
    // Ouvrir le modal en mode édition et charger les données
    fetch(`/admin/users/${userId}/data`)
        .then(response => response.json())
        .then(user => {
            document.getElementById('modalTitle').textContent = 'Modifier l\'utilisateur';
            document.getElementById('submitText').textContent = 'Mettre à jour';
            document.getElementById('userForm').action = `/admin/users/${userId}/edit`;
            
            // Remplir les champs du formulaire
            document.getElementById('prenom').value = user.prenom;
            document.getElementById('nom').value = user.nom;
            document.getElementById('email').value = user.email;
            document.getElementById('role').value = user.role;
            document.getElementById('statut').value = user.statut;
            document.getElementById('description').value = user.description || '';
            
            if (user.avatar) {
                document.getElementById('previewImage').src = `/uploads/avatars/${user.avatar}`;
                document.getElementById('avatarPreview').style.display = 'block';
            }
            
            openModal();
        })
        .catch(error => {
            console.error('Erreur lors du chargement des données utilisateur:', error);
            showAlert('Erreur lors du chargement des données', 'error');
        });
}

// Modal de suppression
function deleteUser(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('confirmDeleteBtn').onclick = function() {
        confirmDelete(userId);
    };
    document.getElementById('deleteModal').classList.add('active');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}

function confirmDelete(userId) {
    fetch(`/admin/users/${userId}/delete`, {
        method: 'DELETE',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Supprimer la ligne du tableau
            const row = document.querySelector(`tr[data-user-id="${userId}"]`);
            if (row) {
                row.remove();
            }
            showAlert('Utilisateur supprimé avec succès', 'success');
        } else {
            showAlert(data.message || 'Erreur lors de la suppression', 'error');
        }
        closeDeleteModal();
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur lors de la suppression', 'error');
        closeDeleteModal();
    });
}

// Gestion des dropdowns
function toggleDropdown(userId) {
    const dropdown = document.getElementById(`dropdown-${userId}`);
    
    // Fermer tous les autres dropdowns
    document.querySelectorAll('.dropdown-content').forEach(d => {
        if (d.id !== `dropdown-${userId}`) {
            d.classList.remove('show');
        }
    });
    
    dropdown.classList.toggle('show');
}

// Fermer les dropdowns quand on clique ailleurs
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-content').forEach(dropdown => {
            dropdown.classList.remove('show');
        });
    }
});

// Actions utilisateur
function resetPassword(userId) {
    if (confirm('Voulez-vous vraiment réinitialiser le mot de passe de cet utilisateur ?')) {
        fetch(`/admin/users/${userId}/reset-password`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'error');
        })
        .catch(error => {
            showAlert('Erreur lors de la réinitialisation', 'error');
        });
    }
}

function sendActivationEmail(userId) {
    fetch(`/admin/users/${userId}/send-activation`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        showAlert(data.message, data.success ? 'success' : 'error');
    })
    .catch(error => {
        showAlert('Erreur lors de l\'envoi de l\'email', 'error');
    });
}

function toggleUserStatus(userId) {
    fetch(`/admin/users/${userId}/toggle-status`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Mettre à jour l'affichage du statut
            location.reload();
        }
        showAlert(data.message, data.success ? 'success' : 'error');
    })
    .catch(error => {
        showAlert('Erreur lors de la modification du statut', 'error');
    });
}

// Gestion de la recherche
document.getElementById('searchUsers')?.addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    
    rows.forEach(row => {
        const userName = row.querySelector('.user-name').textContent.toLowerCase();
        const userEmail = row.querySelector('.email-link').textContent.toLowerCase();
        
        if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Gestion du tri du tableau
function sortTable(columnIndex) {
    const table = document.querySelector('.users-table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr:not(.no-users)'));
    
    // Déterminer l'ordre de tri
    const isAscending = !table.dataset.sortOrder || table.dataset.sortOrder === 'desc';
    table.dataset.sortOrder = isAscending ? 'asc' : 'desc';
    
    rows.sort((a, b) => {
        const cellA = a.cells[columnIndex].textContent.trim();
        const cellB = b.cells[columnIndex].textContent.trim();
        
        const result = cellA.localeCompare(cellB, undefined, { numeric: true });
        return isAscending ? result : -result;
    });
    
    // Réorganiser les lignes
    rows.forEach(row => tbody.appendChild(row));
    
    // Mettre à jour les icônes de tri
    document.querySelectorAll('.sort-icon').forEach(icon => {
        icon.className = 'fas fa-sort sort-icon';
    });
    const currentIcon = table.querySelector(`th:nth-child(${columnIndex + 1}) .sort-icon`);
    currentIcon.className = `fas fa-sort-${isAscending ? 'up' : 'down'} sort-icon`;
}

// Gestion de la sélection multiple
function toggleAllUsers(checkbox) {
    const userCheckboxes = document.querySelectorAll('.user-checkbox');
    userCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateBulkActions();
}

function updateBulkActions() {
    const selectedCheckboxes = document.querySelectorAll('.user-checkbox:checked');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    
    if (selectedCheckboxes.length > 0) {
        bulkActions.style.display = 'flex';
        selectedCount.textContent = selectedCheckboxes.length;
    } else {
        bulkActions.style.display = 'none';
    }
}

// Écouter les changements sur les checkboxes
document.addEventListener('change', function(e) {
    if (e.target.classList.contains('user-checkbox')) {
        updateBulkActions();
    }
});

// Actions groupées
function exportSelected() {
    const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedIds.length === 0) return;
    
    // Créer un lien de téléchargement
    const url = `/admin/users/export?ids=${selectedIds.join(',')}`;
    window.location.href = url;
}

function deactivateSelected() {
    const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedIds.length === 0) return;
    
    if (confirm(`Voulez-vous vraiment désactiver ${selectedIds.length} utilisateur(s) ?`)) {
        fetch('/admin/users/bulk-deactivate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                location.reload();
            }
        });
    }
}

function deleteSelected() {
    const selectedIds = Array.from(document.querySelectorAll('.user-checkbox:checked'))
        .map(cb => cb.value);
    
    if (selectedIds.length === 0) return;
    
    if (confirm(`Voulez-vous vraiment supprimer ${selectedIds.length} utilisateur(s) ? Cette action est irréversible.`)) {
        fetch('/admin/users/bulk-delete', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ ids: selectedIds })
        })
        .then(response => response.json())
        .then(data => {
            showAlert(data.message, data.success ? 'success' : 'error');
            if (data.success) {
                location.reload();
            }
        });
    }
}

// Fonction utilitaire pour afficher les alertes
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-triangle' : 'info-circle'}"></i>
        ${message}
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    const mainContent = document.querySelector('.main-content');
    mainContent.insertBefore(alertDiv, mainContent.firstChild);
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Initialisation au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser les tooltips si nécessaire
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(element => {
        // Ajouter des événements pour les tooltips personnalisés si souhaité
    });
});