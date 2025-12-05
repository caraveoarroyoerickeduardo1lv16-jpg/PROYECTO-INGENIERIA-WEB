//Mostrar mensaje de error si viene ?error=1 en la URL
document.addEventListener("DOMContentLoaded", () => {

    const params   = new URLSearchParams(window.location.search);
    const errorBox = document.getElementById("errorBox");

    if (params.get("error") === "1") {
        errorBox.textContent = "Correo o contraseña incorrectos.";
        errorBox.style.display = "block";
    }
});