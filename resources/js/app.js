import Alpine from "alpinejs";

window.Alpine = Alpine;

document.addEventListener("submit", (event) => {
    if (event.defaultPrevented) {
        return;
    }

    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== "post") {
        return;
    }

    const submitButton = form.querySelector('button[type="submit"], input[type="submit"]');

    if (!submitButton || submitButton.disabled) {
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

Alpine.start();
