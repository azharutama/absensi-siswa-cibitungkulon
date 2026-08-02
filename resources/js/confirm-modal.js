let returnFocusElement = null;
let bodyHadOverflowHidden = false;
let pendingForm = null;

function modalElements() {
    return {
        modal: document.getElementById("global-confirm-modal"),
        form: document.getElementById("confirm-modal-form"),
        cancelButton: document.getElementById("confirm-modal-cancel"),
        title: document.getElementById("confirm-modal-title"),
        description: document.getElementById("confirm-modal-description"),
        iconContainer: document.getElementById("confirm-modal-icon-container"),
        icon: document.getElementById("confirm-modal-icon"),
        submitButton: document.getElementById("confirm-modal-submit"),
    };
}

function openConfirmModal(options = {}) {
    const { modal, form, cancelButton, title, description, iconContainer, icon, submitButton } = modalElements();

    if (!modal || !form) {
        return;
    }

    const {
        message = "Apakah Anda yakin ingin melanjutkan?",
        title: modalTitle = "Konfirmasi",
        confirmText = "Konfirmasi",
        confirmColor = "blue",
        formAction = null,
        formMethod = "POST",
        pendingForm: formToSubmit = null,
    } = options;

    const colorClasses = {
        red: {
            bg: "bg-red-100",
            text: "text-red-600",
            buttonBg: "bg-red-600",
            buttonHover: "hover:bg-red-500",
            focusRing: "focus:ring-red-500",
        },
        blue: {
            bg: "bg-blue-100",
            text: "text-blue-600",
            buttonBg: "bg-blue-600",
            buttonHover: "hover:bg-blue-500",
            focusRing: "focus:ring-blue-500",
        },
        amber: {
            bg: "bg-amber-100",
            text: "text-amber-600",
            buttonBg: "bg-amber-600",
            buttonHover: "hover:bg-amber-500",
            focusRing: "focus:ring-amber-500",
        },
        green: {
            bg: "bg-green-100",
            text: "text-green-600",
            buttonBg: "bg-green-600",
            buttonHover: "hover:bg-green-500",
            focusRing: "focus:ring-green-500",
        },
    };

    const colors = colorClasses[confirmColor] || colorClasses.blue;

    if (title) title.textContent = modalTitle;
    if (description) description.textContent = message;
    if (submitButton) {
        submitButton.textContent = confirmText;
        submitButton.className = submitButton.className.replace(/bg-\S+/, colors.buttonBg);
        submitButton.className = submitButton.className.replace(/hover:bg-\S+/, colors.buttonHover);
        submitButton.className = submitButton.className.replace(/focus:ring-\S+/, colors.focusRing);
    }
    if (iconContainer) {
        iconContainer.className = iconContainer.className.replace(/bg-\S+/, colors.bg);
        iconContainer.className = iconContainer.className.replace(/text-\S+/, colors.text);
    }

    pendingForm = formToSubmit;

    form.setAttribute("method", formMethod);

    if (formAction) {
        let action;
        try {
            action = new URL(formAction, window.location.href);
        } catch {
            return;
        }
        if (action.origin !== window.location.origin) {
            return;
        }
        form.setAttribute("action", action.href);
    } else {
        form.removeAttribute("action");
    }

    returnFocusElement = document.activeElement instanceof HTMLElement
        ? document.activeElement
        : null;
    bodyHadOverflowHidden = document.body.classList.contains("overflow-hidden");
    modal.classList.remove("hidden");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("overflow-hidden");
    window.requestAnimationFrame(() => cancelButton?.focus());
}

function closeConfirmModal(confirmed = false) {
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

    if (confirmed && pendingForm) {
        const formToSubmit = pendingForm;
        pendingForm = null;
        formToSubmit.removeAttribute("data-confirm-message");
        formToSubmit.removeAttribute("data-confirm-title");
        formToSubmit.removeAttribute("data-confirm-text");
        formToSubmit.removeAttribute("data-confirm-color");
        formToSubmit.submit();
    }

    pendingForm = null;
}

document.addEventListener("submit", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.id !== "confirm-modal-form") {
        return;
    }

    event.preventDefault();
    closeConfirmModal(true);
});

document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) {
        return;
    }

    const openTrigger = event.target.closest("[data-open-delete-modal]");

    if (openTrigger instanceof HTMLElement && openTrigger.dataset.deleteAction) {
        openConfirmModal({
            message: "Apakah Anda benar-benar yakin ingin menghapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan.",
            title: "Konfirmasi Hapus",
            confirmText: "Ya, Hapus Data",
            confirmColor: "red",
            formAction: openTrigger.dataset.deleteAction,
            formMethod: "POST",
        });
        return;
    }

    const confirmTrigger = event.target.closest("[data-confirm-message]");

    if (confirmTrigger instanceof HTMLElement) {
        const message = confirmTrigger.dataset.confirmMessage || "Apakah Anda yakin ingin melanjutkan?";
        const title = confirmTrigger.dataset.confirmTitle || "Konfirmasi";
        const confirmText = confirmTrigger.dataset.confirmText || "Konfirmasi";
        const confirmColor = confirmTrigger.dataset.confirmColor || "blue";
        const formAction = confirmTrigger.dataset.confirmAction || null;
        const formMethod = confirmTrigger.dataset.confirmMethod || "POST";

        const form = confirmTrigger.closest("form");

        openConfirmModal({
            message,
            title,
            confirmText,
            confirmColor,
            formAction,
            formMethod,
            pendingForm: form || null,
        });
        return;
    }

    if (event.target.closest("[data-close-confirm-modal]") || event.target.matches("[data-confirm-modal-backdrop]")) {
        closeConfirmModal(false);
    }
});

document.addEventListener("keydown", (event) => {
    const { modal } = modalElements();

    if (!modal || modal.classList.contains("hidden")) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        closeConfirmModal(false);
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