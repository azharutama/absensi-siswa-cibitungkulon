import Alpine from "alpinejs";
import { openConfirmModal, closeConfirmModal } from "./confirm-modal.js";
import "./form-guru.js";
import "./search.js";

window.Alpine = Alpine;

let loadingTimer = null;

function showPageLoading() {
    window.clearTimeout(loadingTimer);

    loadingTimer = window.setTimeout(() => {
        const loading = document.getElementById("page-loading");

        if (loading) {
            loading.classList.remove("hidden");
            loading.setAttribute("aria-hidden", "false");
        }
    }, 120);
}

function hidePageLoading() {
    window.clearTimeout(loadingTimer);

    const loading = document.getElementById("page-loading");

    if (loading) {
        loading.classList.add("hidden");
        loading.setAttribute("aria-hidden", "true");
    }
}

function restoreSubmitButtons() {
    document.querySelectorAll("[data-submit-original-html], [data-submit-original-value]").forEach((submitButton) => {
        if (submitButton instanceof HTMLButtonElement) {
            submitButton.innerHTML = submitButton.dataset.submitOriginalHtml ?? submitButton.innerHTML;
        } else if (submitButton instanceof HTMLInputElement) {
            submitButton.value = submitButton.dataset.submitOriginalValue ?? submitButton.value;
        }

        delete submitButton.dataset.submitOriginalHtml;
        delete submitButton.dataset.submitOriginalValue;
        submitButton.disabled = false;
        submitButton.classList.remove("opacity-60", "cursor-not-allowed");
    });
}

function isNavigableInternalLink(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (!link.href || link.target === "_blank" || link.hasAttribute("download") || link.dataset.noLoading !== undefined) {
        return false;
    }

    let url;

    try {
        url = new URL(link.href);
    } catch {
        return false;
    }

    return url.origin === window.location.origin
        && !(
            url.pathname === window.location.pathname
            && url.search === window.location.search
            && url.hash
        );
}

document.addEventListener("click", (event) => {
    if (event.target instanceof Element && event.target.closest("[data-print-page]")) {
        window.print();
    }
});

document.addEventListener("submit", (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.dataset.confirmMessage) {
        return;
    }

    event.preventDefault();

    openConfirmModal({
        message: form.dataset.confirmMessage,
        title: form.dataset.confirmTitle || "Konfirmasi",
        confirmText: form.dataset.confirmText || "Konfirmasi",
        confirmColor: form.dataset.confirmColor || "blue",
        pendingForm: form,
    });
});

document.addEventListener("submit", (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitButton = event.submitter instanceof HTMLButtonElement || event.submitter instanceof HTMLInputElement
        ? event.submitter
        : form.querySelector('button[type="submit"], input[type="submit"]');

    showPageLoading();

    if (form.method.toLowerCase() !== "post" || !submitButton || submitButton.disabled) {
        return;
    }

    if (submitButton instanceof HTMLButtonElement) {
        submitButton.dataset.submitOriginalHtml = submitButton.innerHTML;
        submitButton.textContent = "Memproses...";
    } else {
        submitButton.dataset.submitOriginalValue = submitButton.value;
        submitButton.value = "Memproses...";
    }

    submitButton.disabled = true;
    submitButton.classList.add("opacity-60", "cursor-not-allowed");
});

document.addEventListener("click", (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return;
    }

    if (!(event.target instanceof Element)) {
        return;
    }

    const link = event.target.closest("a");

    if (isNavigableInternalLink(link)) {
        showPageLoading();
    }
});

window.addEventListener("pageshow", () => {
    hidePageLoading();
    restoreSubmitButtons();
});
window.addEventListener("load", hidePageLoading);
window.addEventListener("popstate", () => {
    hidePageLoading();
    restoreSubmitButtons();
});

Alpine.start();
