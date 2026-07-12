function initializeSearchForm(form) {
    const searchInput = form.querySelector("[data-search-input]");
    const resetButton = form.querySelector("[data-search-reset]");

    if (!(searchInput instanceof HTMLInputElement) || !(resetButton instanceof HTMLElement)) {
        return;
    }

    const toggleResetButton = () => {
        resetButton.classList.toggle("hidden", searchInput.value.trim().length === 0);
    };

    searchInput.addEventListener("input", toggleResetButton);
    toggleResetButton();
}

document.querySelectorAll("[data-search-form]").forEach((form) => {
    if (form instanceof HTMLFormElement) {
        initializeSearchForm(form);
    }
});
