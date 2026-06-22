document.addEventListener("DOMContentLoaded", function () {
    const searchInput = document.getElementById("search-input");
    const btnReset = document.getElementById("btn-reset");

    function toggleResetButton() {
        if (searchInput && btnReset) {
            // .value mengambil apa yang diketik pengguna
            if (searchInput.value.trim().length > 0) {
                btnReset.classList.remove("hidden");
            } else {
                btnReset.classList.add("hidden");
            }
        }
    }

    if (searchInput) {
        searchInput.addEventListener("input", toggleResetButton);

        toggleResetButton();
    }
});
