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