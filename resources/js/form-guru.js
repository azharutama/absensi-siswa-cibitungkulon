function toggleKelasSection() {
    const roleSelect = document.getElementById("role");
    const kelasSection = document.getElementById("kelas-section");

    if (roleSelect && kelasSection) {
        if (roleSelect.value === "guru") {
            kelasSection.classList.remove("hidden");
        } else {
            kelasSection.classList.add("hidden");
        }
    }
}

document.addEventListener("DOMContentLoaded", toggleKelasSection);

// Ekspos fungsi ke window global agar bisa dipanggil lewat atribut onchange="toggleKelasSection()" di Blade
window.toggleKelasSection = toggleKelasSection;
