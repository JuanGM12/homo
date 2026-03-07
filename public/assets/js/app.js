document.addEventListener('DOMContentLoaded', () => {
    // Mensajes flash desde el backend
    const flash = document.body.dataset.flash;
    if (flash) {
        const data = JSON.parse(flash);
        Swal.fire({
            icon: data.type || 'info',
            title: data.title || '',
            text: data.message || '',
        });
    }

    // Interacción: frase del mes
    const fraseButtons = document.querySelectorAll('[data-frase-mes]');
    fraseButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
            Swal.fire({
                icon: 'info',
                title: 'Frase del mes',
                text: '“Los sueños no se cumplen, sino que se trabajan”.',
                confirmButtonText: 'Seguir trabajando',
            });
        });
    });

    // Subregión / Municipio dinámicos en Evaluaciones
    const subregionSelect = document.querySelector('[data-subregion-select]');
    const municipalitySelect = document.querySelector('[data-municipality-select]');

    if (subregionSelect && municipalitySelect) {
        fetch('/assets/js/municipios.json')
            .then((response) => response.json())
            .then((data) => {
                // Llenar opciones de subregión
                Object.keys(data).forEach((subregion) => {
                    const option = document.createElement('option');
                    option.value = subregion;
                    option.textContent = subregion;
                    subregionSelect.appendChild(option);
                });

                subregionSelect.addEventListener('change', () => {
                    const selected = subregionSelect.value;
                    municipalitySelect.innerHTML = '<option value="">Seleccione el municipio de pertenencia</option>';
                    municipalitySelect.disabled = !selected;

                    if (selected && data[selected]) {
                        data[selected].forEach((municipio) => {
                            const option = document.createElement('option');
                            option.value = municipio;
                            option.textContent = municipio;
                            municipalitySelect.appendChild(option);
                        });
                    }
                });
            })
            .catch(() => {
                console.error('No se pudo cargar el listado de municipios.');
            });
    }

    // Validación en POST: exigir PRE existente por documento
    const postForms = document.querySelectorAll('form[data-phase="post"][data-test-key]');

    postForms.forEach((form) => {
        const docInput = form.querySelector('input[name="document_number"]');
        const submitButton = form.querySelector('button[type="submit"]');
        let preExists = false;

        if (!docInput) {
            return;
        }

        const showPreRequiredAlert = () => {
            Swal.fire({
                icon: 'error',
                title: 'PRE - TEST no encontrado',
                text: 'Para diligenciar el POST - TEST debes haber completado primero el PRE - TEST con el mismo número de documento.',
            });
        };

        const validateDocument = () => {
            const value = docInput.value.trim();

            if (!value) {
                preExists = false;
                if (submitButton) {
                    submitButton.disabled = false;
                }
                return;
            }

            if (!/^[0-9]+$/.test(value)) {
                preExists = false;
                if (submitButton) {
                    submitButton.disabled = true;
                }
                Swal.fire({
                    icon: 'error',
                    title: 'Documento no válido',
                    text: 'El número de documento debe contener solo números.',
                });
                return;
            }

            const params = new URLSearchParams();
            params.set('test_key', form.dataset.testKey || '');
            params.set('document_number', value);

            fetch('/evaluaciones/check-pre', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    Accept: 'application/json',
                },
                body: params.toString(),
            })
                .then((response) => response.json())
                .then((data) => {
                    if (!data.exists) {
                        preExists = false;
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        showPreRequiredAlert();
                    } else {
                        preExists = true;
                        if (submitButton) {
                            submitButton.disabled = false;
                        }
                    }
                })
                .catch(() => {
                    // En caso de error de red, mantenemos la validación del backend
                    preExists = false;
                });
        };

        docInput.addEventListener('blur', validateDocument);

        form.addEventListener('submit', (event) => {
            if (!preExists) {
                event.preventDefault();
                showPreRequiredAlert();
            }
        });
    });

    // Confirmación para desactivar usuarios (módulo admin)
    const deactivateButtons = document.querySelectorAll('[data-user-deactivate]');
    const deactivateForm = document.getElementById('deactivate-user-form');
    const deactivateInput = document.getElementById('deactivate-user-id');

    if (deactivateButtons.length > 0 && deactivateForm && deactivateInput) {
        deactivateButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const userId = btn.getAttribute('data-user-id');
                const userName = btn.getAttribute('data-user-name') || '';

                Swal.fire({
                    icon: 'warning',
                    title: 'Desactivar usuario',
                    html: `¿Seguro que deseas desactivar al usuario <strong>${userName}</strong>?<br><span class="text-muted">Podrás reactivarlo más adelante editando su registro.</span>`,
                    showCancelButton: true,
                    confirmButtonText: 'Sí, desactivar',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '#d33',
                }).then((result) => {
                    if (result.isConfirmed && userId) {
                        deactivateInput.value = userId;
                        deactivateForm.submit();
                    }
                });
            });
        });
    }

    // Sidebar responsive (sesión iniciada)
    const layout = document.querySelector('.app-layout');
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    const sidebarBackdrop = document.querySelector('.app-sidebar-backdrop');

    const closeSidebar = () => {
        if (layout) {
            layout.classList.remove('app-sidebar-open');
        }
    };

    if (sidebarToggle && layout) {
        sidebarToggle.addEventListener('click', () => {
            layout.classList.toggle('app-sidebar-open');
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', closeSidebar);
    }

    // Cerrar sidebar al navegar en enlaces (en móvil)
    const sidebarLinks = document.querySelectorAll('.app-sidebar-link');
    sidebarLinks.forEach((link) => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                closeSidebar();
            }
        });
    });
});

