/**
 * search.js - Gestion de la recherche globale dans l'application
 */
document.addEventListener('DOMContentLoaded', function () {
    initGlobalSearch();
});

/**
 * Initialise la recherche globale dans l'application
 */
function initGlobalSearch() {
    const searchInput = document.querySelector('.topbar-search-input');

    if (searchInput) {
        let searchTimeout;
        let searchResults;

        // Créer le conteneur de résultats s'il n'existe pas
        if (!document.getElementById('search-results')) {
            searchResults = document.createElement('div');
            searchResults.id = 'search-results';
            searchResults.className = 'search-results';
            document.body.appendChild(searchResults);
        } else {
            searchResults = document.getElementById('search-results');
        }

        // Gérer la saisie de recherche
        searchInput.addEventListener('keyup', function (e) {
            // Effacer le timeout précédent
            clearTimeout(searchTimeout);

            const query = this.value.trim();

            // Cacher les résultats si la recherche est vide
            if (query.length === 0) {
                hideSearchResults();
                return;
            }

            // Rechercher après un délai pour éviter trop de requêtes
            searchTimeout = setTimeout(() => {
                if (query.length >= 2) {
                    performSearch(query);
                }
            }, 300);
        });

        // Montrer les résultats au focus
        searchInput.addEventListener('focus', function () {
            const query = this.value.trim();

            if (query.length >= 2 && !searchResults.classList.contains('show')) {
                performSearch(query);
            }
        });

        // Fermer les résultats si on clique ailleurs
        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                hideSearchResults();
            }
        });

        // Navigation au clavier dans les résultats
        searchInput.addEventListener('keydown', function (e) {
            if (!searchResults.classList.contains('show')) return;

            const results = searchResults.querySelectorAll('.search-result-item');
            if (results.length === 0) return;

            let focusedItem = searchResults.querySelector('.search-result-item.focused');
            let focusedIndex = -1;

            if (focusedItem) {
                focusedIndex = Array.from(results).indexOf(focusedItem);
            }

            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (focusedIndex < results.length - 1) {
                        if (focusedItem) focusedItem.classList.remove('focused');
                        results[focusedIndex + 1].classList.add('focused');
                        results[focusedIndex + 1].scrollIntoView({ block: 'nearest' });
                    }
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    if (focusedIndex > 0) {
                        if (focusedItem) focusedItem.classList.remove('focused');
                        results[focusedIndex - 1].classList.add('focused');
                        results[focusedIndex - 1].scrollIntoView({ block: 'nearest' });
                    }
                    break;
                case 'Enter':
                    e.preventDefault();
                    if (focusedItem) {
                        window.location.href = focusedItem.href;
                    }
                    break;
                case 'Escape':
                    hideSearchResults();
                    break;
            }
        });
    }
}

/**
 * Effectue la recherche et affiche les résultats
 */
function performSearch(query) {
    const searchResults = document.getElementById('search-results');

    // Afficher un état de chargement
    searchResults.innerHTML = `
        <div class="search-results-header">Recherche en cours...</div>
        <div class="search-results-loading">
            <div class="spinner"></div>
        </div>
    `;
    showSearchResults();

    // Effectuer la recherche via l'API
    fetch(`/api/search?q=${encodeURIComponent(query)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            // Construire les résultats
            let resultsHtml = `<div class="search-results-header">Résultats pour "${query}"</div>`;

            if (data.results && data.results.length > 0) {
                resultsHtml += '<div class="search-results-list">';

                // Grouper les résultats par type
                const groupedResults = {
                    projects: data.results.filter(r => r.type === 'project'),
                    tasks: data.results.filter(r => r.type === 'task'),
                    users: data.results.filter(r => r.type === 'user'),
                    documents: data.results.filter(r => r.type === 'document')
                };

                // Afficher les projets
                if (groupedResults.projects.length > 0) {
                    resultsHtml += `
                    <div class="search-results-category">
                        <div class="search-results-category-title">
                            <i class="fas fa-project-diagram"></i> Projets
                        </div>
                        <div class="search-results-category-items">
                `;

                    groupedResults.projects.forEach(result => {
                        resultsHtml += createResultItem(result);
                    });

                    resultsHtml += `
                        </div>
                    </div>
                `;
                }

                // Afficher les tâches
                if (groupedResults.tasks.length > 0) {
                    resultsHtml += `
                    <div class="search-results-category">
                        <div class="search-results-category-title">
                            <i class="fas fa-tasks"></i> Tâches
                        </div>
                        <div class="search-results-category-items">
                `;

                    groupedResults.tasks.forEach(result => {
                        resultsHtml += createResultItem(result);
                    });

                    resultsHtml += `
                        </div>
                    </div>
                `;
                }

                // Afficher les utilisateurs
                if (groupedResults.users.length > 0) {
                    resultsHtml += `
                    <div class="search-results-category">
                        <div class="search-results-category-title">
                            <i class="fas fa-users"></i> Utilisateurs
                        </div>
                        <div class="search-results-category-items">
                `;

                    groupedResults.users.forEach(result => {
                        resultsHtml += createResultItem(result);
                    });

                    resultsHtml += `
                        </div>
                    </div>
                `;
                }

                // Afficher les documents
                if (groupedResults.documents.length > 0) {
                    resultsHtml += `
                    <div class="search-results-category">
                        <div class="search-results-category-title">
                            <i class="fas fa-file-alt"></i> Documents
                        </div>
                        <div class="search-results-category-items">
                `;

                    groupedResults.documents.forEach(result => {
                        resultsHtml += createResultItem(result);
                    });

                    resultsHtml += `
                        </div>
                    </div>
                `;
                }

                resultsHtml += '</div>';

                // Ajouter un lien vers les résultats complets
                resultsHtml += `
                <div class="search-results-footer">
                    <a href="/search?q=${encodeURIComponent(query)}" class="search-results-more">
                        Afficher tous les résultats
                    </a>
                </div>
            `;
            } else {
                resultsHtml += `
                <div class="search-results-empty">
                    <i class="fas fa-search fa-2x mb-2"></i>
                    <p>Aucun résultat trouvé pour "${query}"</p>
                    <p class="text-muted">Essayez avec des termes différents ou plus généraux</p>
                </div>
            `;
            }

            searchResults.innerHTML = resultsHtml;

            // Ajouter des écouteurs d'événements pour la navigation au survol
            searchResults.querySelectorAll('.search-result-item').forEach(item => {
                item.addEventListener('mouseover', function () {
                    searchResults.querySelectorAll('.search-result-item.focused').forEach(i => i.classList.remove('focused'));
                    this.classList.add('focused');
                });

                item.addEventListener('mouseout', function () {
                    this.classList.remove('focused');
                });
            });
        })
        .catch(error => {
            console.error('Erreur lors de la recherche:', error);
            searchResults.innerHTML = `
            <div class="search-results-header">Erreur</div>
            <div class="search-results-error">
                <i class="fas fa-exclamation-circle fa-2x mb-2"></i>
                <p>Impossible d'effectuer la recherche</p>
                <p class="text-muted">Veuillez réessayer plus tard</p>
            </div>
        `;
        });
}

/**
 * Crée un élément de résultat de recherche HTML
 */
function createResultItem(result) {
    let iconClass;

    switch (result.type) {
        case 'project':
            iconClass = 'fa-project-diagram';
            break;
        case 'task':
            iconClass = 'fa-tasks';
            break;
        case 'user':
            iconClass = 'fa-user';
            break;
        case 'document':
            iconClass = 'fa-file-alt';
            break;
        default:
            iconClass = 'fa-circle';
    }

    return `
        <a href="${result.url}" class="search-result-item">
            <div class="search-result-icon">
                <i class="fas ${iconClass}"></i>
            </div>
            <div class="search-result-content">
                <div class="search-result-title">${result.title}</div>
                <div class="search-result-details">${result.details || ''}</div>
            </div>
            ${result.badge ? `<div class="search-result-badge ${result.badgeType || ''}">${result.badge}</div>` : ''}
        </a>
    `;
}

/**
 * Affiche les résultats de recherche
 */
function showSearchResults() {
    const searchInput = document.querySelector('.topbar-search-input');
    const searchResults = document.getElementById('search-results');

    if (!searchResults.classList.contains('show')) {
        searchResults.classList.add('show');

        // Positionner les résultats
        const inputRect = searchInput.getBoundingClientRect();
        searchResults.style.top = (inputRect.bottom + window.scrollY) + 'px';
        searchResults.style.left = (inputRect.left + window.scrollX) + 'px';
        searchResults.style.width = inputRect.width + 'px';
    }
}

/**
 * Cache les résultats de recherche
 */
function hideSearchResults() {
    const searchResults = document.getElementById('search-results');

    if (searchResults && searchResults.classList.contains('show')) {
        searchResults.classList.remove('show');
    }
}
