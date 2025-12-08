
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

    // Saber si el usuario está logueado (lo manda PHP en el <body>)
    const estaLogueado = document.body.dataset.logged === "1";

    const cards          = document.querySelectorAll(".producto-card");
    const cartTotalItems = document.getElementById("cartTotalItems");
    const cartTotalPrice = document.getElementById("cartTotalPrice");

    function actualizarHeader(items, total) {
        if (!cartTotalItems || !cartTotalPrice) return;
        cartTotalItems.textContent =
            items + " artículo" + (items !== 1 ? "s" : "");
        cartTotalPrice.textContent = "$" + total.toFixed(2);
    }

    cards.forEach(card => {
        const productoId = parseInt(card.dataset.id, 10);

        const btnAgregar   = card.querySelector(".btn-agregar");
        const controlCant  = card.querySelector(".cantidad-control");
        const btnMas       = card.querySelector(".btn-mas");
        const btnMenos     = card.querySelector(".btn-menos");
        const spanCantidad = card.querySelector(".cantidad");

        let cantidadLocal = 0;

    
        if (btnAgregar && controlCant && btnMas && btnMenos && spanCantidad) {

            cantidadLocal = parseInt(spanCantidad.textContent, 10) || 0;

            // Si ya hay cantidad > 0 venimos del carrito, mostrar control
            if (cantidadLocal > 0) {
                btnAgregar.style.display = "none";
                controlCant.classList.remove("oculto");
            }

            
            btnAgregar.addEventListener("click", async (e) => {
                e.stopPropagation(); 

                // Si no hay logueado, mostrar advertencia y NO redirigir
                if (!estaLogueado) {
                    alert("Por favor inicia sesión para agregar productos al carrito.");
                    return;
                }

                const data = await actualizarCarrito(productoId, "add");

                if (!data.success) {
                    alert(data.message || "Error al agregar al carrito");
                    return;
                }

                cantidadLocal = data.cantidad;
                spanCantidad.textContent = cantidadLocal;

                btnAgregar.style.display = "none";
                controlCant.classList.remove("oculto");

                actualizarHeader(data.total_items, data.total_carrito);
            });

            // CLIC EN +
            btnMas.addEventListener("click", async (e) => {
                e.stopPropagation();

                if (!estaLogueado) {
                    alert("Por favor inicia sesión para modificar el carrito.");
                    return;
                }

                const data = await actualizarCarrito(productoId, "add");

                if (!data.success) {
                    alert(data.message || "Error al agregar");
                    return;
                }

                cantidadLocal = data.cantidad;
                spanCantidad.textContent = cantidadLocal;

                actualizarHeader(data.total_items, data.total_carrito);
            });

            // CLIC EN -
            btnMenos.addEventListener("click", async (e) => {
                e.stopPropagation();

                if (!estaLogueado) {
                    alert("Por favor inicia sesión para modificar el carrito.");
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
                } else {
                    spanCantidad.textContent = cantidadLocal;
                }

                actualizarHeader(data.total_items, data.total_carrito);
            });
        }
    });


    // CARRUSEL DE PRODUCTOS

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

    
    const searchInput    = document.getElementById("searchInput");
    const suggestionsBox = document.getElementById("searchSuggestions");

    if (!searchInput || !suggestionsBox) {
        console.warn("Buscador: no se encontró searchInput o searchSuggestions");
        return;
    }

    function ocultarSugerencias() {
        suggestionsBox.innerHTML = "";
        suggestionsBox.style.display = "none";
    }

    searchInput.addEventListener("input", async () => {
        const texto = searchInput.value.trim();

        if (texto.length < 1) {
            ocultarSugerencias();
            return;
        }

        try {
            const resp = await fetch(
                "../PHP/buscar_productos.php?q=" + encodeURIComponent(texto)
            );

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

    // Ocultar al hacer clic fuera
    document.addEventListener("click", (e) => {
        if (!suggestionsBox.contains(e.target) &&
            e.target !== searchInput) {
            ocultarSugerencias();
        }
    });
});


