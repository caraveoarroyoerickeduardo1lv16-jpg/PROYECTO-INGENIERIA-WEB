function mostrarPaso(num) {
    ['paso1', 'paso2', 'paso3'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === 'paso' + num) el.classList.remove('hidden');
        else el.classList.add('hidden');
    });
}

function limpiarSoloLetras(str) {
    return (str || '').replace(/[0-9]/g, '').replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s.\-]/g, '');
}

function forzarSoloLetrasInput(input) {
    if (!input) return;

    input.addEventListener('keydown', (e) => {
        const k = e.key;
        if (/^\d$/.test(k)) e.preventDefault();
    });

    input.addEventListener('input', () => {
        const limpio = limpiarSoloLetras(input.value);
        if (input.value !== limpio) input.value = limpio;
    });

    input.addEventListener('paste', (e) => {
        e.preventDefault();
        const txt = (e.clipboardData || window.clipboardData).getData('text');
        input.value = limpiarSoloLetras(txt);
        input.dispatchEvent(new Event('input'));
    });
}

function aplicarFiltroSlots(stepId, selectedDay) {
    const cont = document.getElementById('paso' + stepId);
    if (!cont) return;

    const now = new Date();
    const currentHour = now.getHours();
    const limitHour = currentHour + 2;

    const slots = cont.querySelectorAll('.slot');
    const statusHoy = cont.querySelector('.status-hoy');

    let visibles = 0;
    let firstRadio = null;

    slots.forEach(slot => {
        const startHour = parseInt(slot.dataset.hora, 10);
        let show = true;

        if (selectedDay === 0) {
            if (startHour < limitHour || startHour >= 21) show = false;
        } else {
            show = true;
        }

        const r = slot.querySelector('input[type="radio"]');

        if (show) {
            slot.style.display = 'flex';
            visibles++;

            if (stepId === 3 && r) {
                r.disabled = false;
                if (!firstRadio) firstRadio = r;
            }
        } else {
            slot.style.display = 'none';

            if (stepId === 3 && r) {
                r.checked = false;
                r.disabled = true;
            }
        }
    });

    if (statusHoy) {
        if (selectedDay === 0 && visibles === 0) {
            statusHoy.textContent = 'Hoy: Agotado';
            statusHoy.style.display = 'block';
        } else {
            statusHoy.style.display = 'none';
        }
    }

    if (stepId === 3) {
        const btnContinuar = document.getElementById('btnContinuarPago');
        if (btnContinuar) {
            btnContinuar.disabled = (visibles === 0);
        }
    }

    if (stepId === 3) {
        const cont3 = document.getElementById('paso3');
        if (!cont3) return;

        const yaSeleccionado = cont3.querySelector('input[name="horario"]:checked');

        if (!yaSeleccionado && firstRadio) {
            firstRadio.checked = true;
        }

        actualizarSeleccionHorario();
    }
}

function actualizarSeleccionHorario() {
    const resumen = document.getElementById('resumenHorario');
    if (!resumen) return;

    const cont = document.getElementById('paso3');
    if (!cont) return;

    const slots = cont.querySelectorAll('.slot');
    slots.forEach(s => s.classList.remove('selected'));

    const checked = cont.querySelector('input[name="horario"]:checked');
    if (!checked) {
        resumen.textContent = 'Selecciona un horario · $49.00';
        return;
    }

    const slot = checked.closest('.slot');
    if (!slot) return;

    slot.classList.add('selected');
    const tituloEl = slot.querySelector('.slot-title');
    const precioEl = slot.querySelector('.slot-price');
    const titulo = tituloEl ? tituloEl.textContent.trim() : '';
    const precio = precioEl ? precioEl.textContent.trim() : '';
    resumen.textContent = `${titulo} · ${precio}`;
}

function formatMoney(n) {
    const num = Number(n || 0);
    return num.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function actualizarCarrito(accion, productoId) {
    const resp = await fetch('../PHP/carrito_accion.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: accion, producto_id: productoId })
    });

    const data = await resp.json();
    if (!data || !data.ok) {
        alert((data && data.error) ? data.error : 'No se pudo actualizar el carrito');
        return null;
    }
    return data;
}

function setText(selList, txt) {
    selList.forEach(sel => {
        document.querySelectorAll(sel).forEach(el => { el.textContent = txt; });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formCheckout');

    forzarSoloLetrasInput(document.getElementById('coloniaInput'));
    forzarSoloLetrasInput(document.getElementById('ciudadInput'));
    forzarSoloLetrasInput(document.getElementById('estadoInput'));

    const btnAgregarDireccion = document.getElementById('btnAgregarDireccion');
    if (btnAgregarDireccion) {
        btnAgregarDireccion.addEventListener('click', (e) => {
            e.preventDefault();
            mostrarPaso(2);
        });
    }

    const btnVolverPaso1 = document.getElementById('btnVolverPaso1');
    if (btnVolverPaso1) {
        btnVolverPaso1.addEventListener('click', () => {
            mostrarPaso(1);
        });
    }

    const btnVolverPaso2 = document.getElementById('btnVolverPaso2');
    if (btnVolverPaso2) {
        btnVolverPaso2.addEventListener('click', () => {
            mostrarPaso(2);
        });
    }

    const dayButtons = document.querySelectorAll('.day-button');
    const selectedDayByStep = { 1: 0, 3: 0 };

    dayButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.dataset.step, 10);
            const dia  = parseInt(btn.dataset.dia, 10);

            selectedDayByStep[step] = dia;

            const cont = document.getElementById('paso' + step);
            if (cont) {
                cont.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            }

            aplicarFiltroSlots(step, dia);
        });
    });

    const radiosHorario = document.querySelectorAll('#paso3 input[name="horario"]');
    radiosHorario.forEach(r => r.addEventListener('change', actualizarSeleccionHorario));

    const btnContinuar = document.getElementById('btnContinuarPago');
    if (btnContinuar) {
        btnContinuar.addEventListener('click', (e) => {
            if (btnContinuar.disabled) {
                e.preventDefault();
                alert("Hoy está agotado. Selecciona Mañana o Pasado mañana.");
                return;
            }

            const checked = document.querySelector('#paso3 input[name="horario"]:checked');
            if (!checked) {
                e.preventDefault();
                alert("Selecciona un horario válido para continuar.");
            }
        });
    }

    if (document.getElementById('paso1')) aplicarFiltroSlots(1, selectedDayByStep[1]);
    if (document.getElementById('paso3')) {
        aplicarFiltroSlots(3, selectedDayByStep[3]);
        actualizarSeleccionHorario();
    }

    document.body.addEventListener('click', async (e) => {
        const btnMas = e.target.closest('.btn-mas');
        const btnMenos = e.target.closest('.btn-menos');
        const btnEliminar = e.target.closest('.btn-eliminar');

        if (!btnMas && !btnMenos && !btnEliminar) return;

        const btn = btnMas || btnMenos || btnEliminar;
        const productoId = parseInt(btn.dataset.id, 10);
        if (!productoId) return;

        let accion = '';
        if (btnMas) accion = 'sumar';
        if (btnMenos) accion = 'restar';
        if (btnEliminar) accion = 'eliminar';

        if (accion === 'eliminar') {
            const ok = confirm('¿Seguro que quieres eliminar este producto del carrito?');
            if (!ok) return;
        }

        btn.disabled = true;

        try {
            const data = await actualizarCarrito(accion, productoId);
            if (!data) return;

            const item = btn.closest('.item');

            if (data.deleted) {
                if (item) item.remove();
            } else {
                if (item) {
                    const cantEl = item.querySelector('.cantidad');
                    if (cantEl) cantEl.textContent = String(data.cantidad);

                    const precioEl = item.querySelector('.precio');
                    if (precioEl) precioEl.textContent = '$' + formatMoney(data.subtotal);
                }
            }

            setText(['.header-items'], `${data.total_items} artículo${data.total_items === 1 ? '' : 's'}`);
            setText(['.header-price'], '$' + formatMoney(data.total_carrito));

            const subtotalP = document.querySelector('.subtotal');
            if (subtotalP) {
                const span = subtotalP.querySelector('span');
                if (span) span.textContent = '$' + formatMoney(data.total_carrito);
                subtotalP.childNodes.forEach(n => {
                    if (n.nodeType === Node.TEXT_NODE) {
                        n.textContent = `Subtotal (${data.total_items} artículos) `;
                    }
                });
            }

            const totalStrong = document.querySelector('.total strong:last-child');
            if (totalStrong) totalStrong.textContent = '$' + formatMoney(data.total_carrito);

            const contadorH1 = document.querySelector('main.contenedor p');
            if (contadorH1) contadorH1.textContent = `${data.total_items} artículos`;

            const productosSection = document.querySelector('section.productos');
            const quedanItems = document.querySelectorAll('section.productos article.item').length;

            if (productosSection && quedanItems === 0) {
                productosSection.innerHTML = '<p>No tienes productos en el carrito.</p>';
            }
        } finally {
            btn.disabled = false;
        }
    });
});

