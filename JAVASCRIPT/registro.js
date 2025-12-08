

document.addEventListener("DOMContentLoaded", () => {

    const form       = document.getElementById("registerForm");
    const errorBox   = document.getElementById("errorBox");
    const successBox = document.getElementById("successBox");

    if (!form) return;

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        errorBox.style.display = "none";
        successBox.style.display = "none";

        const formData = new FormData(form);

        try {
            const resp = await fetch("../PHP/register.php", {
                method: "POST",
                body: formData
            });

            const data = await resp.json();

            if (!data.success) {
                errorBox.textContent = data.message || "Error al registrar.";
                errorBox.style.display = "block";
                return;
            }

            successBox.textContent = "Registro exitoso. Redirigiendo...";
            successBox.style.display = "block";

            // REDIRECCIÓN CORRECTA A /PHP/login.php
            setTimeout(() => {
                window.location.href = "../PHP/login.php";
            }, 1200);

        } catch (err) {
            console.error(err);
            errorBox.textContent = "Error al conectar con el servidor.";
            errorBox.style.display = "block";
        }
    });

});
