
async function actualizarCarrito(productoId, accion) {
    const formData = new FormData();
    formData.append("producto_id", productoId);
    formData.append("accion", accion); // add | remove | delete

    const response = await fetch("../PHP/carrito_actualizar.php", {
        method: "POST",
        body: formData
    });

    return await response.json();
}

document.addEventListener("DOMContentLoaded", () => {

    const headerItems = document.querySelector(".header-items");
    const headerPrice = document.querySelector(".header-price");

    function actualizarHeaderYResumen(totalItems, totalCarrito) {
        if (headerItems) {
            headerItems.textContent =
                totalItems + " artículo" + (totalItems !== 1 ? "s" : "");
        }
        if (headerPrice) {
            headerPrice.textContent = "$" + totalCarrito.toFixed(2);
        }

        const subtotalP = document.querySelector(".subtotal");
        if (subtotalP) {
            // Texto "Subtotal X artículos"
            const textNode = Array.from(subtotalP.childNodes)
                .find(n => n.nodeType === Node.TEXT_NODE);
            if (textNode) {
                textNode.textContent = `Subtotal (${totalItems} artículos)`;
            }
            const montoSpan = subtotalP.querySelector("span");
            if (montoSpan) {
                montoSpan.textContent = "$" + totalCarrito.toFixed(2);
            }
        }

        const totalSpan = document.querySelector(".total strong:last-child");
        if (totalSpan) {
            totalSpan.textContent = "$" + totalCarrito.toFixed(2);
        }
    }


    // MANEJO DE ITEMS DEL CARRITO
    
    const items = document.querySelectorAll(".item");
    const contProductos = document.querySelector(".productos");

    items.forEach(item => {
        const btnMas = item.querySelector(".btn-mas");
        const btnMenos = item.querySelector(".btn-menos");
        const btnEliminar = item.querySelector(".btn-eliminar");
        const spanCantidad = item.querySelector(".cantidad");
        const divPrecio = item.querySelector(".precio");

        if (!btnMas || !btnMenos || !btnEliminar || !spanCantidad || !divPrecio) {
            return;
        }

        const productoId = parseInt(btnMas.dataset.id, 10);

        
        function obtenerPrecioUnitario() {
            const cantActual = parseInt(spanCantidad.textContent, 10);
            const textoPrecio = divPrecio.textContent.replace("$", "").replace(/,/g, "");
            const subtotalActual = parseFloat(textoPrecio) || 0;
            if (cantActual <= 0) return 0;
            return subtotalActual / cantActual;
        }

        //  BOTÓN + 
        btnMas.addEventListener("click", async () => {
            try {
                const data = await actualizarCarrito(productoId, "add");
                if (!data.success) {
                    alert(data.message || "Error al agregar.");
                    return;
                }

                const precioUnit = obtenerPrecioUnitario();
                spanCantidad.textContent = data.cantidad;

                if (precioUnit > 0) {
                    const nuevoSubtotal = precioUnit * data.cantidad;
                    divPrecio.textContent = "$" + nuevoSubtotal.toFixed(2);
                }

                actualizarHeaderYResumen(data.total_items, data.total_carrito);
            } catch (err) {
                console.error(err);
                alert("Error de conexión con el servidor.");
            }
        });

        // BOTÓN -
        btnMenos.addEventListener("click", async () => {
            try {
                const data = await actualizarCarrito(productoId, "remove");
                if (!data.success) {
                    alert(data.message || "Error al quitar.");
                    return;
                }

                const precioUnit = obtenerPrecioUnitario();

                if (data.cantidad <= 0) {
                    item.remove();
                } else {
                    spanCantidad.textContent = data.cantidad;
                    if (precioUnit > 0) {
                        const nuevoSubtotal = precioUnit * data.cantidad;
                        divPrecio.textContent = "$" + nuevoSubtotal.toFixed(2);
                    }
                }

                actualizarHeaderYResumen(data.total_items, data.total_carrito);

                if (data.total_items <= 0 && contProductos) {
                    contProductos.innerHTML = "<p>No tienes productos en el carrito.</p>";
                }

            } catch (err) {
                console.error(err);
                alert("Error de conexión con el servidor.");
            }
        });

        // BOTÓN ELIMINAR
        btnEliminar.addEventListener("click", async () => {
            if (!confirm("¿Eliminar este producto del carrito?")) return;

            try {
                const data = await actualizarCarrito(productoId, "delete");
                if (!data.success) {
                    alert(data.message || "Error al eliminar.");
                    return;
                }

                item.remove();
                actualizarHeaderYResumen(data.total_items, data.total_carrito);

                if (data.total_items <= 0 && contProductos) {
                    contProductos.innerHTML = "<p>No tienes productos en el carrito.</p>";
                }

            } catch (err) {
                console.error(err);
                alert("Error de conexión con el servidor.");
            }
        });
    });
});

