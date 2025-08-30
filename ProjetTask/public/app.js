/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
// import './styles/app.css';
import Sortable from 'sortablejs';
import './styles/app.css';
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('sortable-list');
    if (el) {
        new Sortable(el, {
            animation: 150,
            ghostClass: 'blue-background-class'
        });
        console.log("✅ SortableJS est bien initialisé !");
    }
});