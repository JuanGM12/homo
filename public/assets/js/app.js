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

    // AoAT: enlazar cada <label> con su checkbox/radio (clic en todo el texto marca la opción)
    let aoatFieldSeq = 0;
    document.querySelectorAll('.aoat-form .form-check').forEach((wrap) => {
        const input = wrap.querySelector('input[type="checkbox"], input[type="radio"]');
        const label = wrap.querySelector('.form-check-label');
        if (!input || !label) {
            return;
        }
        if (!input.id) {
            input.id = `aoat-field-${aoatFieldSeq++}`;
        }
        label.setAttribute('for', input.id);
    });

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

    // Subregión / Municipio dinámicos (Evaluaciones, AoAT, Planeación, etc.)
    const subregionSelects = document.querySelectorAll('[data-subregion-select]');

    if (subregionSelects.length > 0) {
        fetch('/assets/js/municipios.json')
            .then((response) => response.json())
            .then((data) => {
                subregionSelects.forEach((subregionSelect) => {
                    const form = subregionSelect.closest('form') || document;
                    const municipalitySelect = form.querySelector('[data-municipality-select]');
                    if (!municipalitySelect) {
                        return;
                    }

                    const isTerritoryFilter = form.hasAttribute && form.hasAttribute('data-territory-filter');
                    const municipalityEmptyLabel = isTerritoryFilter
                        ? 'Todos los municipios'
                        : 'Seleccione el municipio de pertenencia';

                    // Llenar opciones de subregión
                    if (subregionSelect.options.length <= 1) {
                        Object.keys(data).forEach((subregion) => {
                            const option = document.createElement('option');
                            option.value = subregion;
                            option.textContent = subregion;
                            subregionSelect.appendChild(option);
                        });
                    }

                    const fillMunicipalities = (subregionValue, selectedMunicipality) => {
                        municipalitySelect.innerHTML =
                            '<option value="">' + municipalityEmptyLabel + '</option>';
                        municipalitySelect.disabled = !subregionValue;

                        if (subregionValue && data[subregionValue]) {
                            data[subregionValue].forEach((municipio) => {
                                const option = document.createElement('option');
                                option.value = municipio;
                                option.textContent = municipio;
                                if (municipio === selectedMunicipality) {
                                    option.selected = true;
                                }
                                municipalitySelect.appendChild(option);
                            });
                        }
                    };

                    const currentSubregion = subregionSelect.dataset.currentValue || '';
                    const currentMunicipality = municipalitySelect.dataset.currentValue || '';

                    if (currentSubregion) {
                        subregionSelect.value = currentSubregion;
                        fillMunicipalities(currentSubregion, currentMunicipality);
                    }

                    subregionSelect.addEventListener('change', () => {
                        const selected = subregionSelect.value;
                        fillMunicipalities(selected, '');
                    });
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
        const firstNameInput = form.querySelector('input[name="first_name"]');
        const lastNameInput = form.querySelector('input[name="last_name"]');
        const subregionSelect = form.querySelector('select[name="subregion"]');
        const municipalitySelect = form.querySelector('select[name="municipality"]');
        const professionInput = form.querySelector('input[name="profession"]');
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
                        if (firstNameInput) firstNameInput.value = '';
                        if (lastNameInput) lastNameInput.value = '';
                        if (subregionSelect) subregionSelect.value = '';
                        if (municipalitySelect) municipalitySelect.value = '';
                        if (professionInput) professionInput.value = '';
                        if (submitButton) {
                            submitButton.disabled = true;
                        }
                        showPreRequiredAlert();
                    } else {
                        preExists = true;
                        const pre = data.pre || {};
                        if (firstNameInput && pre.first_name) firstNameInput.value = pre.first_name;
                        if (lastNameInput && pre.last_name) lastNameInput.value = pre.last_name;
                        if (professionInput && pre.profession) professionInput.value = pre.profession;

                        // Intentar rellenar subregión/municipio igual al PRE
                        if (subregionSelect && pre.subregion) {
                            subregionSelect.value = pre.subregion;
                            subregionSelect.dispatchEvent(new Event('change'));
                        }
                        if (municipalitySelect && pre.municipality) {
                            setTimeout(() => {
                                municipalitySelect.value = pre.municipality;
                            }, 50);
                        }

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

    // Detalles de planeación anual (modal con SweetAlert)
    const planDetailButtons = document.querySelectorAll('[data-plan-details]');
    if (planDetailButtons.length > 0) {
        planDetailButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const raw = btn.getAttribute('data-plan');
                if (!raw) {
                    return;
                }

                let plan;
                try {
                    plan = JSON.parse(raw);
                } catch (e) {
                    console.error('No se pudo parsear el detalle de la planeación.', e);
                    return;
                }

                const year = plan.year || '';
                const subregion = plan.subregion || '';
                const municipality = plan.municipality || '';
                const months = Array.isArray(plan.months) ? plan.months : [];

                let html = `<p><strong>Año:</strong> ${year}<br><strong>Subregión:</strong> ${subregion}<br><strong>Municipio:</strong> ${municipality}</p>`;

                if (months.length > 0) {
                    html += '<div class="text-start"><hr><h6 class="fw-semibold mb-2">Meses planificados</h6>';
                    html += '<div class="small">';
                    months.forEach((m) => {
                        const label = m.label || '';
                        const topics = Array.isArray(m.topics) ? m.topics.join('<br>• ') : '';
                        const population = m.population || '';
                        html += `<p class="mb-2"><strong>${label}</strong><br>`;
                        if (topics) {
                            html += `Temas:<br>• ${topics}<br>`;
                        }
                        if (population) {
                            html += `<span class="text-muted">Población objetivo:</span> ${population}`;
                        }
                        html += '</p>';
                    });
                    html += '</div></div>';
                } else {
                    html += '<p class="text-muted small mb-0">Aún no hay meses diligenciados en esta planeación.</p>';
                }

                Swal.fire({
                    title: 'Detalle de planeación',
                    html,
                    width: '60rem',
                    confirmButtonText: 'Cerrar',
                });
            });
        });
    }

    // Detalles de AoAT (respuestas completas)
    const aoatDetailButtons = document.querySelectorAll('[data-aoat-details]');
    if (aoatDetailButtons.length > 0) {
        aoatDetailButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                const raw = btn.getAttribute('data-aoat');
                if (!raw) return;

                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    console.error('No se pudo parsear el detalle de la AoAT.', e);
                    return;
                }

                const id = data.id || '';
                const fecha = data.created_at || '';
                const profesional = data.professional || '';
                const rol = data.professional_role || '';
                const subregion = data.subregion || '';
                const municipio = data.municipality || '';
                const estado = data.state || '';
                const payload = data.payload && typeof data.payload === 'object' ? data.payload : {};

                let html = `<p><strong>Fecha registro:</strong> ${fecha}<br>
<strong>Profesional:</strong> ${profesional}<br>
<strong>Rol:</strong> ${rol}<br>
<strong>Subregión:</strong> ${subregion}<br>
<strong>Municipio:</strong> ${municipio}<br>
<strong>Estado:</strong> ${estado}</p>`;

                const labelMap = {
                    proyecto: 'Proyecto',
                    aoat_number: 'Número de la AoAT o actividad',
                    activity_date: 'Fecha de la actividad',
                    activity_type: 'Actividad que realizó',
                    activity_with: 'Con quién realizó la actividad',
                    subregion: 'Subregión que visitó',
                    municipality: 'Municipio visitado',
                    prev_suicidio: 'Cualificación temas en prevención del suicidio',
                    prev_violencias: 'Cualificación temas en prevención de violencias',
                    prev_adicciones: 'Cualificación temas en prevención de adicciones',
                    salud_mental: 'Cualificación temas de salud mental',
                    mesa_salud_mental: 'Mesa Municipal de Salud Mental y Prevención de las Adicciones',
                    ppmsmypa: 'Política Pública Municipal de Salud y Prevención de las Adicciones (PPMSMYPA)',
                    safer: 'SAFER',
                    temas_hospital: 'Temas dictados en el hospital',
                    actividad_social: 'Actividades realizadas (Profesional social)',
                };

                const entries = Object.entries(payload);
                if (entries.length > 0) {
                    html += '<hr><div class="text-start small"><h6 class="fw-semibold mb-2">Respuestas del formulario</h6>';
                    entries.forEach(([key, value]) => {
                        const label = labelMap[key] || key.replace(/_/g, ' ');
                        let valueHtml = '';
                        if (Array.isArray(value)) {
                            if (value.length === 0) {
                                return;
                            }
                            valueHtml = '• ' + value.map((v) => String(v)).join('<br>• ');
                        } else if (value !== null && value !== '') {
                            valueHtml = String(value);
                        } else {
                            return;
                        }
                        html += `<p class="mb-1"><strong>${label}:</strong><br>${valueHtml}</p>`;
                    });
                    html += '</div>';
                } else {
                    html += '<p class="text-muted small mb-0">No se encontraron respuestas adicionales en este registro.</p>';
                }

                Swal.fire({
                    title: 'Detalle de AoAT',
                    html,
                    width: '60rem',
                    confirmButtonText: 'Cerrar',
                });
            });
        });
    }

    // Auditoría AoAT + aprobación desde Realizado (delegación: sigue funcionando tras filtros AJAX)
    document.body.addEventListener('click', async (e) => {
        const approveBtn = e.target.closest('[data-aoat-approve-realizado]');
        const auditBtn = e.target.closest('[data-aoat-audit]');
        if (!approveBtn && !auditBtn) {
            return;
        }

        const stateForm = document.getElementById('aoat-state-form');
        const inputId = document.getElementById('aoat-state-id');
        const inputState = document.getElementById('aoat-state-value');
        const inputObs = document.getElementById('aoat-state-observation');
        const inputMotive = document.getElementById('aoat-state-motive');
        if (!stateForm || !inputId || !inputState || !inputObs || !inputMotive) {
            return;
        }

        e.preventDefault();

        if (approveBtn) {
            const raw = approveBtn.getAttribute('data-aoat');
            if (!raw) return;

            let data;
            try {
                data = JSON.parse(raw);
            } catch (err) {
                console.error('No se pudo parsear el detalle de la AoAT.', err);
                return;
            }

            const id = data.id || '';
            const profesional = data.professional || '';

            const result = await Swal.fire({
                title: 'Aprobar revisión de AoAT',
                html: `<p class="small mb-0">Profesional: <strong>${profesional}</strong><br>ID registro: <strong>${id}</strong></p>
<p class="small mt-2 mb-0">El profesional marcó el registro como <strong>Realizado</strong> tras los ajustes. ¿Confirmas la <strong>aprobación final</strong>?</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar',
                width: '40rem',
            });

            if (!result.isConfirmed) {
                return;
            }

            inputId.value = String(id);
            inputState.value = 'Aprobada';
            inputObs.value = '';
            inputMotive.value = '';
            stateForm.submit();
            return;
        }

        const raw = auditBtn.getAttribute('data-aoat');
        if (!raw) return;

        let data;
        try {
            data = JSON.parse(raw);
        } catch (err) {
            console.error('No se pudo parsear el detalle de la AoAT para auditoría.', err);
            return;
        }

        const id = data.id || '';
        const profesional = data.professional || '';

        const { value: action } = await Swal.fire({
            title: 'Cambiar estado de AoAT',
            html: `<p class="small mb-2">Profesional: <strong>${profesional}</strong><br>ID registro: <strong>${id}</strong></p>
<p class="small mb-1">Selecciona la acción a realizar:</p>`,
            input: 'radio',
            inputOptions: {
                Aprobada: 'Marcar como Aprobada (se cierra el registro)',
                Devuelta: 'Marcar como Devuelta (se notifica al profesional)',
            },
            inputValidator: (value) => (!value ? 'Debes seleccionar una opción.' : null),
            confirmButtonText: 'Continuar',
            showCancelButton: true,
            cancelButtonText: 'Cancelar',
            width: '40rem',
        });

        if (!action) {
            return;
        }

        let observation = '';
        let motive = '';

        if (action === 'Devuelta') {
            const { value: formValues } = await Swal.fire({
                title: 'Devolver AoAT',
                html:
                    '<div class="mb-2 text-start small">Indica el motivo y la observación para devolver este registro.</div>' +
                    '<select id="swal-aoat-motive" class="form-select mb-2">' +
                    '<option value="">Selecciona un motivo</option>' +
                    '<option value="Sin Cargar en AoAT">Sin Cargar en AoAT</option>' +
                    '<option value="Sin cargar en Drive">Sin cargar en Drive</option>' +
                    '</select>' +
                    '<textarea id="swal-aoat-observation" class="form-control" rows="3" placeholder="Describe el motivo de la devolución"></textarea>',
                focusConfirm: false,
                preConfirm: () => {
                    const motiveEl = document.getElementById('swal-aoat-motive');
                    const obsEl = document.getElementById('swal-aoat-observation');
                    const m = motiveEl ? motiveEl.value : '';
                    const o = obsEl ? obsEl.value.trim() : '';
                    if (!m || !o) {
                        Swal.showValidationMessage('Debes seleccionar un motivo y escribir una observación.');
                        return null;
                    }
                    return { motive: m, observation: o };
                },
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                confirmButtonText: 'Enviar devolución',
                width: '40rem',
            });

            if (!formValues) {
                return;
            }

            observation = formValues.observation;
            motive = formValues.motive;
        }

        inputId.value = String(id);
        inputState.value = action;
        inputObs.value = observation;
        inputMotive.value = motive;

        stateForm.submit();
    });

    // Filtros AJAX para AoAT
    const aoatFilterForm = document.querySelector('[data-aoat-filters]');
    const aoatTbody = document.querySelector('[data-aoat-tbody]');

    if (aoatFilterForm && aoatTbody) {
        let aoatFilterTimer = null;

        const applyAoatFilters = () => {
            const formData = new FormData(aoatFilterForm);
            const params = new URLSearchParams(formData);
            params.set('partial', 'rows');

            fetch('/aoat?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                },
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        aoatTbody.innerHTML = data.html;
                    }
                })
                .catch(() => {
                    // Si falla, el usuario puede recargar o usar el filtro con recarga completa (GET normal).
                });
        };

        const scheduleApplyAoatFilters = () => {
            if (aoatFilterTimer !== null) {
                clearTimeout(aoatFilterTimer);
            }
            aoatFilterTimer = setTimeout(applyAoatFilters, 300);
        };

        aoatFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyAoatFilters();
        });

        const searchInput = aoatFilterForm.querySelector('input[name="q"]');
        const stateSelect = aoatFilterForm.querySelector('select[name="state"]');

        if (searchInput) {
            searchInput.addEventListener('input', scheduleApplyAoatFilters);
        }

        if (stateSelect) {
            stateSelect.addEventListener('change', applyAoatFilters);
        }
    }
});

