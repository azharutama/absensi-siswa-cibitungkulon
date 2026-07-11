let returnFocusElement = null;
let bodyHadOverflowHidden = false;

function modalElements() {
    return {
        modal: document.getElementById("global-delete-modal"),
        form: document.getElementById("delete-modal-form"),
        cancelButton: document.getElementById("delete-modal-cancel"),
    };
}

function openDeleteModal(actionUrl) {
    const { modal, form, cancelButton } = modalElements();

    if (modal && form) {
        const action = new URL(actionUrl, window.location.href);

        if (action.origin !== window.location.origin) {
            return;
        }

        returnFocusElement = document.activeElement instanceof HTMLElement
            ? document.activeElement
            : null;
        bodyHadOverflowHidden = document.body.classList.contains("overflow-hidden");
        form.setAttribute("action", action.href);
        modal.classList.remove("hidden");
        modal.setAttribute("aria-hidden", "false");
        document.body.classList.add("overflow-hidden");
        window.requestAnimationFrame(() => cancelButton?.focus());
    }
}

function closeDeleteModal() {
    const { modal, form } = modalElements();

    if (modal) {
        modal.classList.add("hidden");
        modal.setAttribute("aria-hidden", "true");
        form?.removeAttribute("action");

        if (!bodyHadOverflowHidden) {
            document.body.classList.remove("overflow-hidden");
        }

        returnFocusElement?.focus();
        returnFocusElement = null;
        bodyHadOverflowHidden = false;
    }
}

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const openTrigger = event.target.closest("[data-open-delete-modal]");

    if (openTrigger instanceof HTMLElement && openTrigger.dataset.deleteAction) {
        openDeleteModal(openTrigger.dataset.deleteAction);
        return;
    }

    if (event.target.closest("[data-close-delete-modal]") || event.target.matches("[data-delete-modal-backdrop]")) {
        closeDeleteModal();
    }
});

document.addEventListener("keydown", (event) => {
    const { modal } = modalElements();

    if (!modal || modal.classList.contains("hidden")) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        closeDeleteModal();
        return;
    }

    if (event.key !== "Tab") {
        return;
    }

    const focusable = Array.from(
        modal.querySelectorAll('button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'),
    ).filter((element) => element instanceof HTMLElement && element.offsetParent !== null);

    if (focusable.length === 0) {
        event.preventDefault();
        modal.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && (document.activeElement === first || !modal.contains(document.activeElement))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (document.activeElement === last || !modal.contains(document.activeElement))) {
        event.preventDefault();
        first.focus();
    }
});
