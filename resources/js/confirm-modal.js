function openDeleteModal(actionUrl) {
    const modal = document.getElementById("global-delete-modal");
    const form = document.getElementById("delete-modal-form");

    if (modal && form) {
        form.setAttribute("action", actionUrl);
        modal.classList.remove("hidden");
    }
}

function closeDeleteModal() {
    const modal = document.getElementById("global-delete-modal");
    if (modal) {
        modal.classList.add("hidden");
    }
}
