function mostrarPaso(num) {
    ['paso1', 'paso2', 'paso3'].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        if (id === 'paso' + num) el.classList.remove('hidden');
        else el.classList.add('hidden');
    });
}

// Filtra los horarios según el día seleccionado (0=hoy,1=mañana,2=pasado)
function aplicarFiltroSlots(stepId, selectedDay) {
    const cont = document.getElementById('paso' + stepId);
    if (!cont) return;

    const now = new Date();
    const currentHour = now.getHours();
    const limitHour = currentHour + 2; // 2 horas
    const slots = cont.querySelectorAll('.slot');
    const statusHoy = cont.querySelector('.status-hoy');

    let visibles = 0;
    let firstRadio = null;

    slots.forEach(slot => {
        const startHour = parseInt(slot.dataset.hora, 10); 
        let show = true;

        if (selectedDay === 0) {
            // HOY: solo slots con al menos 2h de diferencia
            if (startHour < limitHour || startHour >= 21) {
                show = false;
            }
        } else {
            // MAÑANA / PASADO: todos
            show = true;
        }

        if (show) {
            slot.style.display = 'flex';
            visibles++;
            const r = slot.querySelector('input[type="radio"]');
            if (!firstRadio && r && !r.disabled) firstRadio = r;
        } else {
            slot.style.display = 'none';
        }
    });

    // Mensaje Hoy agotado
    if (statusHoy) {
        if (selectedDay === 0 && visibles === 0) {
            statusHoy.textContent = 'Hoy: Agotado';
            statusHoy.style.display = 'block';
        } else {
            statusHoy.style.display = 'none';
        }
    }

    
    if (stepId === 3 && firstRadio) {
        firstRadio.checked = true;
        actualizarSeleccionHorario();
    }
}


function actualizarSeleccionHorario() {
    const resumen = document.getElementById('resumenHorario');
    if (!resumen) return;

    const cont = document.getElementById('paso3');
    const slots = cont.querySelectorAll('.slot');
    slots.forEach(s => s.classList.remove('selected'));

    const checked = cont.querySelector('input[name="horario"]:checked');
    if (!checked) return;

    const slot = checked.closest('.slot');
    if (!slot) return;

    slot.classList.add('selected');
    const titulo = slot.querySelector('.slot-title').textContent.trim();
    const precio = slot.querySelector('.slot-price').textContent.trim();
    resumen.textContent = `${titulo} · ${precio}`;
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('formCheckout');


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

    // Tabs de día 
    const dayButtons = document.querySelectorAll('.day-button');
    const selectedDayByStep = { 1: 0, 3: 0 };

    dayButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            const step = parseInt(btn.dataset.step, 10);
            const dia  = parseInt(btn.dataset.dia, 10);

            selectedDayByStep[step] = dia;

        
            const cont = document.getElementById('paso' + step);
            cont.querySelectorAll('.day-button').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            aplicarFiltroSlots(step, dia);
        });
    });

    // Radios de horario 
    const radiosHorario = document.querySelectorAll('#paso3 input[name="horario"]');
    radiosHorario.forEach(r => r.addEventListener('change', actualizarSeleccionHorario));

    
    aplicarFiltroSlots(1, selectedDayByStep[1]); 
    aplicarFiltroSlots(3, selectedDayByStep[3]); 
    actualizarSeleccionHorario();
});


