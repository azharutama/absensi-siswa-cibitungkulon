import Alpine from "alpinejs";

window.Alpine = Alpine;

let loadingTimer = null;

function showPageLoading() {
    window.clearTimeout(loadingTimer);

    loadingTimer = window.setTimeout(() => {
        const loading = document.getElementById("page-loading");

        if (loading) {
            loading.classList.remove("hidden");
        }
    }, 120);
}

function hidePageLoading() {
    window.clearTimeout(loadingTimer);

    const loading = document.getElementById("page-loading");

    if (loading) {
        loading.classList.add("hidden");
    }
}

function isNavigableInternalLink(link) {
    if (!(link instanceof HTMLAnchorElement)) {
        return false;
    }

    if (!link.href || link.target === "_blank" || link.hasAttribute("download") || link.dataset.noLoading !== undefined) {
        return false;
    }

    const url = new URL(link.href);

    return url.origin === window.location.origin
        && !(url.pathname === window.location.pathname && url.hash);
}

document.addEventListener("submit", (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;

    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

    showPageLoading();

    if (form.method.toLowerCase() !== "post" || !submitButton || submitButton.disabled) {
        return;
    }

    if (submitButton instanceof HTMLButtonElement) {
        submitButton.dataset.originalText = submitButton.textContent.trim();
        submitButton.textContent = "Memproses...";
    } else {
        submitButton.dataset.originalText = submitButton.value;
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

window.addEventListener("pageshow", hidePageLoading);
window.addEventListener("load", hidePageLoading);
window.addEventListener("popstate", hidePageLoading);

Alpine.start();
