document.addEventListener('DOMContentLoaded', () => {
    const statutField = document.querySelector('#project_statut');
    const budgetField = document.querySelector('#project_budget');

    if (statutField) {
        statutField.addEventListener('change', () => {
            if (statutField.value === 'ARRETER') {
                alert('Attention, ce projet sera marqué comme arrêté.');
            }
        });
    }

    if (budgetField) {
        budgetField.addEventListener('input', () => {
            const value = parseFloat(budgetField.value);
            if (!isNaN(value) && value > 10000) {
                budgetField.style.borderColor = 'orange';
            } else {
                budgetField.style.borderColor = '';
            }
        });
    }
});
