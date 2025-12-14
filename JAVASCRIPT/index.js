// FUNCIÓN PARA ACTUALIZAR CARRITO
async function actualizarCarrito(productoId, accion) {
    const formData = new FormData();
    formData.append("producto_id", productoId);
    formData.append("accion", accion);

    const response = await fetch("../PHP/carrito_actualizar.php", {
        method: "POST",
        body: formData
    });

    return await response.json();
}

document.addEventListener("DOMContentLoaded", () => {

    console.log("index.js cargado");

    const estaLogueado = document.body.dataset.logged === "1";

    const cards          = document.querySelectorAll(".producto-card");
    const cartTotalItems = document.getElementById("cartTotalItems");
    const cartTotalPrice = document.getElementById("cartTotalPrice");

    // ===== MODAL LOGIN =====
    const loginModal  = document.getElementById("loginModal");
    const modalGoLogin = document.getElementById("modalGoLogin");
    const modalClose   = document.getElementById("modalClose");

    function abrirModalLogin() {
        if (!loginModal) return;
        loginModal.classList.add("show");
        loginModal.setAttribute("aria-hidden", "false");
    }

    function cerrarModalLogin() {
        if (!loginModal) return;
        loginModal.classList.remove("show");
        loginModal.setAttribute("aria-hidden", "true");
    }

    if (modalGoLogin) {
        modalGoLogin.addEventListener("click", () => {
            window.location.href = "../PHP/login.php";
        });
    }
    if (modalClose) modalClose.addEventListener("click", cerrarModalLogin);

    // cerrar modal si das click fuera
    if (loginModal) {
        loginModal.addEventListener("click", (e) => {
            if (e.target === loginModal) cerrarModalLogin();
        });
    }

    function actualizarHeader(items, total) {
        if (!cartTotalItems || !cartTotalPrice) return;
        cartTotalItems.textContent = items + " artículo" + (items !== 1 ? "s" : "");
        cartTotalPrice.textContent = "$" + total.toFixed(2);
    }

    cards.forEach(card => {
        const productoId = parseInt(card.dataset.id, 10);
        const stock = parseInt(card.dataset.stock || "0", 10);

        const btnAgregar   = card.querySelector(".btn-agregar");
        const controlCant  = card.querySelector(".cantidad-control");
        const btnMas       = card.querySelector(".btn-mas");
        const btnMenos     = card.querySelector(".btn-menos");
        const spanCantidad = card.querySelector(".cantidad");

        let cantidadLocal = 0;

        if (btnAgregar && controlCant && btnMas && btnMenos && spanCantidad) {

            cantidadLocal = parseInt(spanCantidad.textContent, 10) || 0;

            // Si ya hay cantidad > 0, mostrar control
            if (cantidadLocal > 0) {
                btnAgregar.style.display = "none";
                controlCant.classList.remove("oculto");
            }

            // si no hay stock y NO está en carrito: bloquea agregar
            if (stock <= 0 && cantidadLocal <= 0) {
                btnAgregar.disabled = true;
                btnAgregar.textContent = "Sin stock";
            }

            // si no hay stock pero YA está en carrito: bloquea el "+"
            if (stock <= 0 && cantidadLocal > 0) {
                btnMas.disabled = true;
            }

            // CLIC AGREGAR
            btnAgregar.addEventListener("click", async (e) => {
                e.stopPropagation();

                if (!estaLogueado) {
                    abrirModalLogin();
                    return;
                }

                if (btnAgregar.disabled) return;

                const data = await actualizarCarrito(productoId, "add");

                if (!data.success) {
                    alert(data.message || "Error al agregar al carrito");
                    return;
                }

                cantidadLocal = data.cantidad;
                spanCantidad.textContent = cantidadLocal;

                btnAgregar.style.display = "none";
                controlCant.classList.remove("oculto");

                // si el backend ya dejó stock 0, bloquea el "+"
                const stockAhora = parseInt(card.dataset.stock || stock, 10);
                if (stockAhora <= 0) btnMas.disabled = true;

                actualizarHeader(data.total_items, data.total_carrito);
            });

            // CLIC +
            btnMas.addEventListener("click", async (e) => {
                e.stopPropagation();

                if (!estaLogueado) {
                    abrirModalLogin();
                    return;
                }

                // Si no hay stock, no permitas sumar
                if (stock <= 0 || btnMas.disabled) return;

                const data = await actualizarCarrito(productoId, "add");

                if (!data.success) {
                    alert(data.message || "Error al agregar");
                    return;
                }

                cantidadLocal = data.cantidad;
                spanCantidad.textContent = cantidadLocal;

                actualizarHeader(data.total_items, data.total_carrito);
            });

            // CLIC -
            btnMenos.addEventListener("click", async (e) => {
                e.stopPropagation();

                if (!estaLogueado) {
                    abrirModalLogin();
                    return;
                }

                const data = await actualizarCarrito(productoId, "remove");

                if (!data.success) {
                    alert(data.message || "Error al quitar");
                    return;
                }

                cantidadLocal = data.cantidad;

                if (cantidadLocal <= 0) {
                    controlCant.classList.add("oculto");
                    btnAgregar.style.display = "inline-block";
                    spanCantidad.textContent = "0";

                    // si stock sigue en 0, deja el agregar bloqueado
                    if (stock <= 0) {
                        btnAgregar.disabled = true;
                        btnAgregar.textContent = "Sin stock";
                    } else {
                        btnAgregar.disabled = false;
                        btnAgregar.textContent = "+ Agregar";
                    }
                } else {
                    spanCantidad.textContent = cantidadLocal;
                }

                actualizarHeader(data.total_items, data.total_carrito);
            });
        }
    });

    // ===== CARRUSEL =====
    const viewport = document.querySelector(".carrusel-viewport");
    const pista    = document.querySelector(".carrusel-pista");
    const btnIzq   = document.querySelector(".btn-carrusel-izq");
    const btnDer   = document.querySelector(".btn-carrusel-der");

    if (viewport && pista && btnIzq && btnDer) {
        function getDesplazamiento() {
            return viewport.clientWidth / 6;
        }

        btnDer.addEventListener("click", () => {
            viewport.scrollBy({ left: getDesplazamiento(), behavior: "smooth" });
        });

        btnIzq.addEventListener("click", () => {
            viewport.scrollBy({ left: -getDesplazamiento(), behavior: "smooth" });
        });
    }

    // ===== BUSCADOR =====
    const searchInput    = document.getElementById("searchInput");
    const suggestionsBox = document.getElementById("searchSuggestions");
    const searchNotFound = document.getElementById("searchNotFound");

    if (!searchInput || !suggestionsBox) {
        console.warn("Buscador: no se encontró searchInput o searchSuggestions");
        return;
    }

    function ocultarSugerencias() {
        suggestionsBox.innerHTML = "";
        suggestionsBox.style.display = "none";
    }

    function mostrarNoEncontrado() {
        if (!searchNotFound) return;
        searchNotFound.classList.add("show");
        setTimeout(() => searchNotFound.classList.remove("show"), 2000);
    }

    searchInput.addEventListener("input", async () => {
        const texto = searchInput.value.trim();

        if (texto.length < 1) {
            ocultarSugerencias();
            return;
        }

        try {
            const resp = await fetch("../PHP/buscar_productos.php?q=" + encodeURIComponent(texto));

            if (!resp.ok) {
                console.error("Error HTTP buscador:", resp.status);
                ocultarSugerencias();
                return;
            }

            const data = await resp.json();

            if (!Array.isArray(data) || data.length === 0) {
                ocultarSugerencias();
                return;
            }

            suggestionsBox.innerHTML = "";

            data.forEach(item => {
                const div = document.createElement("div");
                div.className = "suggestion-item";

                const spanTxt = document.createElement("span");
                spanTxt.className = "txt";
                const nombre = item.nombre || "";
                const marca  = item.marca || "";
                spanTxt.textContent = (marca ? marca + " " : "") + nombre;

                const spanIcon = document.createElement("span");
                spanIcon.className = "icon";
                spanIcon.textContent = "↗";

                div.appendChild(spanTxt);
                div.appendChild(spanIcon);

                div.addEventListener("click", () => {
                    const id = item.id;
                    if (id) {
                        window.location.href = "index.php?producto_id=" + encodeURIComponent(id);
                    }
                });

                suggestionsBox.appendChild(div);
            });

            suggestionsBox.style.display = "block";

        } catch (err) {
            console.error("Error buscando productos:", err);
            ocultarSugerencias();
        }
    });

    // ENTER: si no existe, mostrar "Producto no encontrado"
    searchInput.addEventListener("keydown", async (e) => {
        if (e.key !== "Enter") return;

        const texto = searchInput.value.trim();
        if (!texto) return;

        e.preventDefault();

        try {
            const resp = await fetch("../PHP/buscar_productos.php?q=" + encodeURIComponent(texto));
            if (!resp.ok) {
                mostrarNoEncontrado();
                return;
            }

            const data = await resp.json();

            if (!Array.isArray(data) || data.length === 0) {
                ocultarSugerencias();
                mostrarNoEncontrado();
                return;
            }

            // si hay resultados, manda al primero
            const primero = data[0];
            if (primero && primero.id) {
                window.location.href = "index.php?producto_id=" + encodeURIComponent(primero.id);
            } else {
                mostrarNoEncontrado();
            }

        } catch (err) {
            console.error(err);
            mostrarNoEncontrado();
        }
    });

    // Ocultar sugerencias al hacer clic fuera
    document.addEventListener("click", (e) => {
        if (!suggestionsBox.contains(e.target) && e.target !== searchInput) {
            ocultarSugerencias();
        }
    });
});



