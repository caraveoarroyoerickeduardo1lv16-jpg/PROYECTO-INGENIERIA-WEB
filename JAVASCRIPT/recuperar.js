document.addEventListener("DOMContentLoaded", () => {
    const btn = document.getElementById("btnCopy");
    const pass = document.getElementById("passText");

    if (!btn || !pass) return;

    btn.addEventListener("click", async () => {
        try {
            await navigator.clipboard.writeText(pass.textContent);
            btn.textContent = "¡Copiada!";
            setTimeout(() => (btn.textContent = "Copiar contraseña"), 1500);
        } catch {
            alert("No se pudo copiar automáticamente, copia manualmente.");
        }
    });
});
