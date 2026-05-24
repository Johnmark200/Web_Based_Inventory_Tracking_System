document.querySelectorAll('[data-confirm-delete]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const message = link.getAttribute('data-confirm-delete') || 'Continue?';
        if (!window.confirm(message)) {
            event.preventDefault();
        }
    });
});

document.querySelectorAll('[data-restock-message]').forEach((button) => {
    button.addEventListener('click', () => {
        window.alert(button.getAttribute('data-restock-message') || 'Stock needs to be restocked.');
    });
});
