function initializeGuruForm(form) {
    const roleSelect = form.querySelector("[data-role-select]");
    const kelasSection = form.querySelector("[data-kelas-section]");

    if (!(roleSelect instanceof HTMLSelectElement) || !(kelasSection instanceof HTMLElement)) {
        return;
    }

    const toggleKelasSection = () => {
        const isGuru = roleSelect.value === "guru";

        kelasSection.classList.toggle("hidden", !isGuru);
        kelasSection.setAttribute("aria-hidden", isGuru ? "false" : "true");
    };

    roleSelect.addEventListener("change", toggleKelasSection);
    toggleKelasSection();
}

document.querySelectorAll("[data-guru-form]").forEach((form) => {
    if (form instanceof HTMLFormElement) {
        initializeGuruForm(form);
    }
});
