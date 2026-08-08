function initializeSearchForm(form) {
    const searchInput = form.querySelector("[data-search-input]");
    const resetButton = form.querySelector("[data-search-reset]");

    if (!(searchInput instanceof HTMLInputElement)) {
        return;
    }

    const toggleResetButton = () => {
        if (resetButton instanceof HTMLElement) {
            resetButton.classList.toggle("hidden", searchInput.value.trim().length === 0);
        }
    };

    let debounceTimer = null;

    searchInput.addEventListener("input", () => {
        toggleResetButton();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => form.submit(), 500);
    });
    toggleResetButton();
}

document.querySelectorAll("[data-search-form]").forEach((form) => {
    if (form instanceof HTMLFormElement) {
        initializeSearchForm(form);
    }
});
