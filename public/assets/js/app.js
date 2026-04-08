document.addEventListener('DOMContentLoaded', () => {
    const HOMO_FILTER_STORAGE_PREFIX = 'homoFilters:v1:';
    const homoFilterPaths = new Set([
        '/aoat',
        '/evaluaciones',
        '/pic',
        '/entrenamiento',
        '/planeacion',
        '/encuesta-opinion-aoat/listar',
        '/asistencia',
        '/admin/usuarios',
    ]);
    const homoFullPageFilterPaths = new Set(['/asistencia', '/admin/usuarios']);
    const homoAjaxFilterPaths = new Set([
        '/aoat',
        '/evaluaciones',
        '/pic',
        '/entrenamiento',
        '/planeacion',
        '/encuesta-opinion-aoat/listar',
    ]);

    const homoFilterFormsByPath = {
        '/aoat': ['[data-aoat-filters]'],
        '/evaluaciones': ['[data-eval-filters]'],
        '/pic': ['[data-pic-filters]'],
        '/entrenamiento': ['[data-entrenamiento-filters]'],
        '/planeacion': ['[data-planeacion-filters]'],
        '/encuesta-opinion-aoat/listar': ['[data-encuesta-filters]'],
        '/asistencia': ['#asi-filter-form'],
        '/admin/usuarios': ['form[action="/admin/usuarios"]'],
    };

    function homoFilterStorageKey(pathname) {
        return HOMO_FILTER_STORAGE_PREFIX + pathname;
    }

    function homoNormalizeQueryString(search) {
        const q = search.startsWith('?') ? search.slice(1) : search;
        const params = new URLSearchParams(q);
        params.delete('partial');
        return params.toString();
    }

    function homoSaveFiltersForPath(pathname, queryString) {
        if (!homoFilterPaths.has(pathname)) {
            return;
        }
        const norm = homoNormalizeQueryString(queryString || '');
        if (norm) {
            sessionStorage.setItem(homoFilterStorageKey(pathname), norm);
        } else {
            sessionStorage.removeItem(homoFilterStorageKey(pathname));
        }
    }

    function homoParamsGroupedByName(sp) {
        const map = new Map();
        sp.forEach((value, name) => {
            if (name === 'partial') {
                return;
            }
            if (!map.has(name)) {
                map.set(name, []);
            }
            map.get(name).push(value);
        });
        return map;
    }

    /** Valores de municipio desde query (municipality[], municipality, municipality[0]…). */
    function homoGetAllMunicipalityValues(sp) {
        const out = [];
        const seen = new Set();
        const push = (v) => {
            if (v === null || v === '') {
                return;
            }
            if (!seen.has(v)) {
                seen.add(v);
                out.push(v);
            }
        };
        sp.getAll('municipality[]').forEach(push);
        sp.getAll('municipality').forEach(push);
        sp.forEach((v, name) => {
            if (/^municipality\[\d+\]$/.test(name)) {
                push(v);
            }
        });
        return out;
    }

    function homoSyncFormFromSearchParams(form, sp) {
        if (!form) {
            return;
        }
        const grouped = homoParamsGroupedByName(sp);
        for (const [name, values] of grouped) {
            const el = form.elements.namedItem(name);
            if (!el) {
                continue;
            }
            if (el instanceof RadioNodeList) {
                const last = values[values.length - 1];
                for (let i = 0; i < el.length; i++) {
                    if (el[i].value === last) {
                        el[i].checked = true;
                    }
                }
            } else if (el instanceof HTMLSelectElement && el.multiple) {
                const want = new Set(values.filter(Boolean));
                for (let i = 0; i < el.options.length; i++) {
                    el.options[i].selected = want.has(el.options[i].value);
                }
            } else if (
                el instanceof HTMLSelectElement ||
                el instanceof HTMLInputElement ||
                el instanceof HTMLTextAreaElement
            ) {
                el.value = values[values.length - 1] ?? '';
            }
        }
    }

    const homoPendingAjaxRefresh = {};

    function homoTryRestoreFullPageFilters() {
        const path = window.location.pathname;
        if (!homoFullPageFilterPaths.has(path)) {
            return false;
        }
        const flashRaw = document.body.dataset.flash;
        if (flashRaw !== undefined && String(flashRaw).trim() !== '') {
            return false;
        }
        const stored = sessionStorage.getItem(homoFilterStorageKey(path));
        if (!stored) {
            return false;
        }
        if (homoNormalizeQueryString(window.location.search)) {
            return false;
        }
        window.location.replace(path + `?${stored}`);
        return true;
    }

    function homoRestoreAjaxFiltersFromStorage() {
        const path = window.location.pathname;
        if (!homoAjaxFilterPaths.has(path)) {
            return;
        }
        const stored = sessionStorage.getItem(homoFilterStorageKey(path));
        if (!stored) {
            return;
        }
        if (homoNormalizeQueryString(window.location.search)) {
            return;
        }
        const sp = new URLSearchParams(stored);
        window.history.replaceState({}, '', path + `?${stored}`);

        const sr = sp.get('subregion') || '';
        const muns = homoGetAllMunicipalityValues(sp);
        const muSingle = muns.length > 0 ? muns[0] : sp.get('municipality') || '';
        document.querySelectorAll('[data-subregion-select]').forEach((sel) => {
            sel.dataset.currentValue = sr;
        });
        document.querySelectorAll('[data-municipality-select]').forEach((sel) => {
            if (sel.multiple || sel.getAttribute('data-municipality-multi') === '1') {
                sel.dataset.currentValues = JSON.stringify(muns);
                delete sel.dataset.currentValue;
            } else {
                sel.dataset.currentValue = muSingle;
                delete sel.dataset.currentValues;
            }
        });

        const selectors = homoFilterFormsByPath[path] || [];
        selectors.forEach((sel) => {
            const form = document.querySelector(sel);
            homoSyncFormFromSearchParams(form, sp);
        });

        homoPendingAjaxRefresh[path] = true;
    }

    document.body.addEventListener('click', (e) => {
        const clearBtn = e.target.closest('[data-homo-filter-clear]');
        if (!clearBtn || !clearBtn.hasAttribute('href')) {
            return;
        }
        const pathKey = clearBtn.getAttribute('data-homo-filter-clear') || '';
        if (pathKey && homoFilterPaths.has(pathKey)) {
            sessionStorage.removeItem(homoFilterStorageKey(pathKey));
        }
    });

    const platformMinDate = '2026-01-01';

    document.querySelectorAll('input[type="date"]').forEach((input) => {
        if (!input.min || input.min < platformMinDate) {
            input.min = platformMinDate;
        }

        const syncDateValidity = () => {
            if (input.value && input.value < platformMinDate) {
                input.setCustomValidity('No se permiten fechas anteriores al 1 de enero de 2026.');
            } else {
                input.setCustomValidity('');
            }
        };

        syncDateValidity();
        input.addEventListener('input', syncDateValidity);
        input.addEventListener('change', syncDateValidity);
    });

    // Mensajes flash desde el backend
    const flash = document.body.dataset.flash;
    if (flash) {
        const data = JSON.parse(flash);
        if (data.title === 'Encuesta registrada') {
            const opinionSuccessHtml = `
                <div style="display:flex;flex-direction:column;align-items:center;gap:1rem;padding:0.25rem 0 0.5rem;">
                    <div style="display:flex;align-items:center;justify-content:center;gap:1rem;flex-wrap:wrap;">
                        <img src="/assets/img/logoAntioquia.png" alt="Gobernación de Antioquia" style="height:48px;width:auto;object-fit:contain;">
                        <img src="/assets/img/logoHomo.png" alt="Programa de Promoción y Prevención" style="height:48px;width:auto;object-fit:contain;">
                    </div>
                    <div style="display:flex;align-items:center;justify-content:center;width:78px;height:78px;border-radius:999px;background:rgba(54,163,98,0.12);border:1px solid rgba(54,163,98,0.18);">
                        <i class="bi bi-check-lg" style="font-size:2.2rem;color:#43a062;"></i>
                    </div>
                    <div style="text-align:center;">
                        <div style="font-size:1.4rem;font-weight:800;color:#24433d;">Encuesta registrada</div>
                        <div style="margin-top:0.75rem;color:#536b66;line-height:1.7;">
                            Tu opinión ha sido recibida por el <strong>Programa de Promoción y Prevención</strong>.<br>
                            La información será tratada únicamente con fines estadísticos.
                        </div>
                    </div>
                </div>
            `;

            Swal.fire({
                icon: null,
                title: '',
                html: opinionSuccessHtml,
                confirmButtonText: 'OK',
                confirmButtonColor: '#6f63f6',
                width: 540,
            });
            return;
        }

        Swal.fire({
            icon: data.type || 'info',
            title: data.title || '',
            text: data.message || '',
        });
    }

    if (homoTryRestoreFullPageFilters()) {
        return;
    }
    homoRestoreAjaxFiltersFromStorage();

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
        label.style.cursor = 'pointer';

        wrap.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) {
                return;
            }
            if (target.closest('a, button, input, select, textarea, label')) {
                return;
            }

            event.preventDefault();
            input.click();
            input.focus();
        });
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

    /** Limpieza de listeners del desplegable de municipios (checkboxes). */
    const homoMuniWidgetCleanup = new WeakMap();

    function homoUnmountMunicipalityMultiWidget(sel) {
        if (!(sel instanceof HTMLSelectElement) || sel.dataset.homoMuniWidget !== '1') {
            return;
        }
        const root = sel.closest('.homo-muni-multiselect');
        const cleanup = root ? homoMuniWidgetCleanup.get(root) : null;
        if (cleanup) {
            document.removeEventListener('click', cleanup.onDocClick);
            document.removeEventListener('keydown', cleanup.onKey);
            homoMuniWidgetCleanup.delete(root);
        }
        if (root && root.parentNode) {
            root.parentNode.insertBefore(sel, root);
            root.remove();
        }
        sel.classList.remove('homo-muni-native', 'visually-hidden');
        sel.removeAttribute('tabindex');
        delete sel.dataset.homoMuniWidget;
    }

    /**
     * Sustituye el &lt;select multiple&gt; por un botón + panel con casillas (clic sin Ctrl).
     */
    function homoMountMunicipalityMultiWidget(sel, emptyLabel) {
        if (!(sel instanceof HTMLSelectElement)) {
            return;
        }
        const isMulti = sel.multiple === true || sel.getAttribute('data-municipality-multi') === '1';
        if (!isMulti) {
            return;
        }

        homoUnmountMunicipalityMultiWidget(sel);

        const root = document.createElement('div');
        root.className = 'homo-muni-multiselect w-100';
        if (sel.classList.contains('form-select-sm')) {
            root.classList.add('homo-muni-multiselect--sm');
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'homo-muni-multiselect__toggle';
        btn.setAttribute('aria-expanded', 'false');
        btn.setAttribute('aria-haspopup', 'listbox');
        btn.setAttribute('title', 'Elija uno o varios municipios');

        const panel = document.createElement('div');
        panel.className = 'homo-muni-multiselect__panel';
        panel.setAttribute('role', 'listbox');
        panel.hidden = true;

        const parent = sel.parentNode;
        parent.insertBefore(root, sel);
        root.appendChild(btn);
        root.appendChild(panel);
        root.appendChild(sel);

        sel.classList.add('homo-muni-native', 'visually-hidden');
        sel.setAttribute('tabindex', '-1');
        sel.dataset.homoMuniWidget = '1';

        const chevronIcon = () => {
            const i = document.createElement('i');
            i.className = 'bi bi-chevron-down homo-muni-multiselect__chevron';
            i.setAttribute('aria-hidden', 'true');
            return i;
        };

        const updateButton = () => {
            const values = Array.from(sel.selectedOptions)
                .map((o) => o.value)
                .filter(Boolean);
            const labelEl = document.createElement('span');
            labelEl.className = 'text-truncate flex-grow-1 text-start';
            if (values.length === 0) {
                labelEl.textContent = emptyLabel;
                labelEl.classList.add('text-muted');
            } else if (values.length === 1) {
                labelEl.textContent = values[0];
            } else {
                labelEl.textContent = `${values.length} municipios`;
            }
            btn.replaceChildren(labelEl, chevronIcon());
            btn.disabled = sel.disabled;
        };

        const syncPanelFromSelect = () => {
            panel.innerHTML = '';
            Array.from(sel.options).forEach((opt) => {
                if (!opt.value) {
                    return;
                }
                const row = document.createElement('label');
                row.className = 'homo-muni-multiselect__option';
                const cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.checked = opt.selected;
                const text = document.createElement('span');
                text.textContent = opt.textContent || opt.value;
                row.appendChild(cb);
                row.appendChild(text);
                cb.addEventListener('change', () => {
                    opt.selected = cb.checked;
                    sel.dispatchEvent(new Event('change', { bubbles: true }));
                    updateButton();
                });
                panel.appendChild(row);
            });
        };

        const close = () => {
            root.classList.remove('is-open');
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        };

        const open = () => {
            syncPanelFromSelect();
            root.classList.add('is-open');
            panel.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
        };

        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (sel.disabled) {
                return;
            }
            if (root.classList.contains('is-open')) {
                close();
            } else {
                open();
            }
        });

        const onDocClick = (e) => {
            if (!(e.target instanceof Node)) {
                return;
            }
            if (!root.contains(e.target)) {
                close();
            }
        };

        const onKey = (e) => {
            if (e.key === 'Escape' && root.classList.contains('is-open')) {
                close();
            }
        };

        document.addEventListener('click', onDocClick);
        document.addEventListener('keydown', onKey);
        homoMuniWidgetCleanup.set(root, { onDocClick, onKey });

        updateButton();
    }

    // Subregión / Municipio dinámicos (Evaluaciones, AoAT, Asistencia, etc.)
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
                    const isNumberOnlyEdit = !!(form.dataset && form.dataset.numberOnlyEdit === '1');

                    const territoryRoot = subregionSelect.closest('[data-territory-filter]') || form;
                    const isTerritoryFilter = territoryRoot.hasAttribute('data-territory-filter');
                    const municipalityEmptyLabel = isTerritoryFilter
                        ? 'Todos los municipios'
                        : 'Seleccione el municipio de pertenencia';

                    const isMulti =
                        municipalitySelect.multiple === true ||
                        municipalitySelect.getAttribute('data-municipality-multi') === '1';

                    const parseSelectedMulti = () => {
                        try {
                            const raw = municipalitySelect.dataset.currentValues || '[]';
                            const parsed = JSON.parse(raw);
                            return Array.isArray(parsed) ? parsed.filter(Boolean) : [];
                        } catch {
                            return [];
                        }
                    };

                    // Llenar opciones de subregión
                    if (subregionSelect.options.length <= 1) {
                        Object.keys(data).forEach((subregion) => {
                            const option = document.createElement('option');
                            option.value = subregion;
                            option.textContent = subregion;
                            subregionSelect.appendChild(option);
                        });
                    }

                    const fillMunicipalities = (subregionValue, resetSelection) => {
                        if (isMulti) {
                            homoUnmountMunicipalityMultiWidget(municipalitySelect);
                        }

                        const selectedMulti = resetSelection ? [] : parseSelectedMulti();
                        const selectedSingle = resetSelection ? '' : municipalitySelect.dataset.currentValue || '';

                        if (isMulti) {
                            municipalitySelect.innerHTML = '';
                        } else {
                            municipalitySelect.innerHTML =
                                '<option value="">' + municipalityEmptyLabel + '</option>';
                        }
                        municipalitySelect.disabled = isNumberOnlyEdit || !subregionValue;

                        if (subregionValue && data[subregionValue]) {
                            data[subregionValue].forEach((municipio) => {
                                const option = document.createElement('option');
                                option.value = municipio;
                                option.textContent = municipio;
                                if (isMulti) {
                                    if (selectedMulti.includes(municipio)) {
                                        option.selected = true;
                                    }
                                } else if (municipio === selectedSingle) {
                                    option.selected = true;
                                }
                                municipalitySelect.appendChild(option);
                            });
                        }

                        if (isMulti) {
                            homoMountMunicipalityMultiWidget(municipalitySelect, municipalityEmptyLabel);
                        }
                    };

                    const currentSubregion = subregionSelect.dataset.currentValue || '';

                    if (currentSubregion) {
                        subregionSelect.value = currentSubregion;
                        fillMunicipalities(currentSubregion, false);
                    } else if (isMulti) {
                        fillMunicipalities('', true);
                    }

                    if (isNumberOnlyEdit) {
                        subregionSelect.disabled = true;
                        municipalitySelect.disabled = true;
                        if (isMulti && municipalitySelect.dataset.homoMuniWidget === '1') {
                            const t = municipalitySelect
                                .closest('.homo-muni-multiselect')
                                ?.querySelector('.homo-muni-multiselect__toggle');
                            if (t instanceof HTMLButtonElement) {
                                t.disabled = true;
                            }
                        }
                        return;
                    }

                    subregionSelect.addEventListener('change', () => {
                        const selected = subregionSelect.value;
                        municipalitySelect.dataset.currentValues = JSON.stringify([]);
                        municipalitySelect.dataset.currentValue = '';
                        fillMunicipalities(selected, true);
                    });
                });
            })
            .catch(() => {
                console.error('No se pudo cargar el listado de municipios.');
            });
    }

    document.querySelectorAll('#asi-filter-form[data-territory-filter] [data-asi-autosubmit]').forEach((el) => {
        el.addEventListener('change', () => {
            const f = document.getElementById('asi-filter-form');
            if (f) {
                f.requestSubmit();
            }
        });
    });

    // Validación en POST: exigir PRE existente por documento
    const postForms = document.querySelectorAll('form[data-phase="post"][data-test-key]');

    postForms.forEach((form) => {
        const allowPostWithoutPre = (form.dataset.testKey || '') === 'hospitales';
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
                        if (allowPostWithoutPre) {
                            if (submitButton) {
                                submitButton.disabled = false;
                            }
                        } else {
                            if (submitButton) {
                                submitButton.disabled = true;
                            }
                            showPreRequiredAlert();
                        }
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
            if (!preExists && !allowPostWithoutPre) {
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
    const planDetailButtons = [];
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

    const planeacionMonthOrder = [
        'enero',
        'febrero',
        'marzo',
        'abril',
        'mayo',
        'junio',
        'julio',
        'agosto',
        'septiembre',
        'octubre',
        'noviembre',
        'diciembre',
    ];
    const planeacionMonthIndex = planeacionMonthOrder.reduce((acc, month, index) => {
        acc[month] = index;
        return acc;
    }, {});

    const escapeHtml = (value) => String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    const buildPlanDetailHtml = (plan) => {
        const year = plan.year || '';
        const professional = plan.professional || '';
        const professionalRole = plan.professional_role || '';
        const subregion = plan.subregion || '';
        const municipality = plan.municipality || '';
        const months = Array.isArray(plan.months) ? [...plan.months] : [];

        months.sort((left, right) => {
            const leftKey = String(left && left.key ? left.key : '').toLowerCase();
            const rightKey = String(right && right.key ? right.key : '').toLowerCase();
            const leftIndex = Object.prototype.hasOwnProperty.call(planeacionMonthIndex, leftKey) ? planeacionMonthIndex[leftKey] : 99;
            const rightIndex = Object.prototype.hasOwnProperty.call(planeacionMonthIndex, rightKey) ? planeacionMonthIndex[rightKey] : 99;
            return leftIndex - rightIndex;
        });

        let html = `<div class="plan-detail-shell">
            <div class="plan-detail-header">
                <p class="plan-detail-meta"><strong>Año:</strong> ${escapeHtml(year)}</p>
                <p class="plan-detail-meta"><strong>Asesor:</strong> ${escapeHtml(professional || 'Sin nombre')}</p>
                <p class="plan-detail-meta"><strong>Subregion:</strong> ${escapeHtml(subregion)}</p>
                <p class="plan-detail-meta"><strong>Rol:</strong> ${escapeHtml(professionalRole || 'Sin rol')}</p>
                <p class="plan-detail-meta"><strong>Municipio:</strong> ${escapeHtml(municipality)}</p>
            </div>`;

        if (months.length === 0) {
            html += '<p class="plan-detail-empty mb-0">Aun no hay meses diligenciados en esta planeacion.</p></div>';
            return html;
        }

        html += '<div class="plan-detail-section"><div class="plan-detail-section-head"><span class="plan-detail-kicker">Meses planificados</span><span class="plan-detail-count-total">' + months.length + ' cargados</span></div>';
        html += '<div class="plan-detail-accordion">';

        months.forEach((month, index) => {
            const label = month.label || '';
            const topics = Array.isArray(month.topics) ? month.topics.filter((topic) => String(topic).trim() !== '') : [];
            const population = month.population || '';
            const summaryCount = topics.length > 0 ? `${topics.length} tema${topics.length === 1 ? '' : 's'}` : 'Sin temas';

            html += `<details class="plan-detail-item" ${index === 0 ? 'open' : ''}>
                <summary class="plan-detail-summary">
                    <span class="plan-detail-month">${escapeHtml(label)}</span>
                    <span class="plan-detail-count">${escapeHtml(summaryCount)}</span>
                </summary>
                <div class="plan-detail-body">`;

            if (topics.length > 0) {
                html += '<div class="plan-detail-block"><p class="plan-detail-label">Temas</p><ul class="plan-detail-topics">';
                topics.forEach((topic) => {
                    html += `<li>${escapeHtml(topic)}</li>`;
                });
                html += '</ul></div>';
            }

            if (population) {
                html += `<div class="plan-detail-block"><p class="plan-detail-label">Poblacion objetivo</p><p class="plan-detail-population">${escapeHtml(population)}</p></div>`;
            }

            html += '</div></details>';
        });

        html += '</div></div></div>';

        return html;
    };

    const bindPlanDetailButtons = (scope = document) => {
        const buttons = scope.querySelectorAll('[data-plan-details]');
        if (buttons.length === 0) {
            return;
        }

        buttons.forEach((btn) => {
            if (btn.dataset.planDetailBound === '1') {
                return;
            }

            btn.dataset.planDetailBound = '1';
            btn.addEventListener('click', () => {
                const raw = btn.getAttribute('data-plan');
                if (!raw) {
                    return;
                }

                let plan;
                try {
                    plan = JSON.parse(raw);
                } catch (e) {
                    console.error('No se pudo parsear el detalle de la planeacion.', e);
                    return;
                }

                Swal.fire({
                    title: 'Detalle de planeacion',
                    html: buildPlanDetailHtml(plan),
                    width: '64rem',
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        popup: 'plan-detail-modal',
                    },
                });
            });
        });
    };

    bindPlanDetailButtons();

    const buildTrainingDetailHtml = (data) => {
        const payload = data && typeof data.payload === 'object' ? data.payload : {};
        const topicSections = [
            ['suicidio', 'Suicidio'],
            ['violencias', 'Violencias'],
            ['adicciones', 'Adicciones'],
            ['otros_temas_salud_mental', 'Otros temas en salud mental'],
        ];

        const proposedTopics = [
            payload.tema_propuesto_1 || '',
            payload.tema_propuesto_2 || '',
            payload.tema_propuesto_3 || '',
            payload.tema_propuesto_4 || '',
        ].filter((value) => String(value).trim() !== '');

        let html = `<div class="training-detail-shell">
            <div class="training-detail-header">
                <p class="training-detail-meta"><strong>Profesional:</strong> ${escapeHtml(data.professional || 'Sin nombre')}</p>
                <p class="training-detail-meta"><strong>Correo:</strong> ${escapeHtml(data.email || 'Sin correo')}</p>
                <p class="training-detail-meta"><strong>Subregion:</strong> ${escapeHtml(data.subregion || '')}</p>
                <p class="training-detail-meta"><strong>Municipio:</strong> ${escapeHtml(data.municipality || '')}</p>
                <p class="training-detail-meta"><strong>Fecha registro:</strong> ${escapeHtml(data.created_at || '')}</p>
                <p class="training-detail-meta"><strong>Estado:</strong> ${escapeHtml(data.state || '')}</p>
            </div>
            <div class="training-detail-grid">`;

        topicSections.forEach(([key, label]) => {
            const values = Array.isArray(payload[key]) ? payload[key].filter((value) => String(value).trim() !== '') : [];
            html += `<section class="training-detail-card">
                <p class="training-detail-label">${escapeHtml(label)}</p>`;

            if (values.length > 0) {
                html += '<ul class="training-detail-list">';
                values.forEach((value) => {
                    html += `<li>${escapeHtml(value)}</li>`;
                });
                html += '</ul>';
            } else {
                html += '<p class="training-detail-empty">Sin informacion registrada.</p>';
            }

            html += '</section>';
        });

        html += `<section class="training-detail-card">
            <p class="training-detail-label">Temas propuestos</p>`;

        if (proposedTopics.length > 0) {
            html += '<ul class="training-detail-list">';
            proposedTopics.forEach((value) => {
                html += `<li>${escapeHtml(value)}</li>`;
            });
            html += '</ul>';
        } else {
            html += '<p class="training-detail-empty">No se propusieron temas adicionales.</p>';
        }

        html += `</section>
            <section class="training-detail-card training-detail-card--wide">
                <p class="training-detail-label">Justificacion</p>
                <p class="training-detail-copy">${escapeHtml(payload.justificacion_temas || 'Sin justificacion registrada.')}</p>
            </section>
        </div></div>`;

        return html;
    };

    const bindTrainingDetailButtons = (scope = document) => {
        const buttons = scope.querySelectorAll('[data-entrenamiento-details]');
        if (buttons.length === 0) {
            return;
        }

        buttons.forEach((btn) => {
            if (btn.dataset.trainingDetailBound === '1') {
                return;
            }

            btn.dataset.trainingDetailBound = '1';
            btn.addEventListener('click', () => {
                const raw = btn.getAttribute('data-entrenamiento');
                if (!raw) {
                    return;
                }

                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    console.error('No se pudo parsear el detalle del plan de entrenamiento.', e);
                    return;
                }

                Swal.fire({
                    title: 'Detalle del plan de entrenamiento',
                    html: buildTrainingDetailHtml(data),
                    width: '68rem',
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        popup: 'training-detail-modal',
                    },
                });
            });
        });
    };

    bindTrainingDetailButtons();

    const buildPicDetailHtml = (data) => {
        const payload = data && typeof data.payload === 'object' ? data.payload : {};
        const sections = [
            ['Zona de orientacion escolar', payload.zona_orientacion_escolar || '', payload.personas_zona_orientacion_escolar || ''],
            ['Centro de escucha', payload.centro_escucha || '', payload.personas_centro_escucha || ''],
            ['Zona de orientacion universitaria', payload.zona_orientacion_universitaria || '', payload.personas_zona_orientacion_universitaria || ''],
            ['Redes comunitarias activas', payload.redes_comunitarias_activas || '', payload.personas_red_comunitaria || ''],
        ];

        let html = `<div class="pic-detail-shell">
            <div class="pic-detail-header">
                <p class="pic-detail-meta"><strong>Profesional:</strong> ${escapeHtml(data.professional || 'Sin nombre')}</p>
                <p class="pic-detail-meta"><strong>Correo:</strong> ${escapeHtml(data.email || 'Sin correo')}</p>
                <p class="pic-detail-meta"><strong>Subregion:</strong> ${escapeHtml(data.subregion || '')}</p>
                <p class="pic-detail-meta"><strong>Municipio:</strong> ${escapeHtml(data.municipality || '')}</p>
                <p class="pic-detail-meta"><strong>Fecha registro:</strong> ${escapeHtml(data.created_at || '')}</p>
                <p class="pic-detail-meta"><strong>Estado:</strong> ${escapeHtml(data.state || '')}</p>
            </div>
            <div class="pic-detail-grid">`;

        sections.forEach(([label, answer, quantity]) => {
            const hasQuantity = String(answer) === 'Si';
            html += `<section class="pic-detail-card">
                <p class="pic-detail-label">${escapeHtml(label)}</p>
                <p class="pic-detail-answer">${escapeHtml(answer || 'Sin registrar')}</p>`;
            if (hasQuantity) {
                html += `<p class="pic-detail-count">Personas registradas: <strong>${escapeHtml(quantity || '0')}</strong></p>`;
            }
            html += '</section>';
        });

        html += '</div></div>';
        return html;
    };

    const bindPicDetailButtons = (scope = document) => {
        const buttons = scope.querySelectorAll('[data-pic-details]');
        if (buttons.length === 0) {
            return;
        }

        buttons.forEach((btn) => {
            if (btn.dataset.picDetailBound === '1') {
                return;
            }

            btn.dataset.picDetailBound = '1';
            btn.addEventListener('click', () => {
                const raw = btn.getAttribute('data-pic');
                if (!raw) {
                    return;
                }

                let data;
                try {
                    data = JSON.parse(raw);
                } catch (e) {
                    console.error('No se pudo parsear el detalle del PIC.', e);
                    return;
                }

                Swal.fire({
                    title: 'Detalle de seguimiento PIC',
                    html: buildPicDetailHtml(data),
                    width: '64rem',
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        popup: 'pic-detail-modal',
                    },
                });
            });
        });
    };

    bindPicDetailButtons();

    const formatTextBlock = (value) => escapeHtml(value ?? '').replace(/\n/g, '<br>');

    // Detalles de AoAT (respuestas completas)
    const aoatDetailButtons = [];
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

                const fechaActividad = data.activity_date || data.created_at || '';
                const fechaRegistro = data.created_at || '';
                const profesional = data.professional || '';
                const rol = data.professional_role || '';
                const subregion = data.subregion || '';
                const municipio = data.municipality || '';
                const estado = data.state || '';
                const payload = data.payload && typeof data.payload === 'object' ? data.payload : {};
                const canExportSingle = Boolean(data.can_export_single);
                const exportPdfUrl = data.export_pdf_url || '';
                const exportExcelUrl = data.export_excel_url || '';

                let html = `<div class="text-start"><p class="mb-0"><strong>Fecha de la actividad:</strong> ${escapeHtml(fechaActividad)}<br>
<strong>Fecha registro:</strong> ${escapeHtml(fechaRegistro)}<br>
<strong>Profesional:</strong> ${escapeHtml(profesional)}<br>
<strong>Rol:</strong> ${escapeHtml(rol)}<br>
<strong>Subregion:</strong> ${escapeHtml(subregion)}<br>
<strong>Municipio:</strong> ${escapeHtml(municipio)}<br>
<strong>Estado:</strong> ${escapeHtml(estado)}</p>`;

                if (canExportSingle && (exportPdfUrl || exportExcelUrl)) {
                    html += '<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">';
                    if (exportPdfUrl) {
                        html += `<a class="btn btn-sm btn-outline-danger" href="${escapeHtml(exportPdfUrl)}" target="_blank" rel="noopener">Exportar PDF</a>`;
                    }
                    if (exportExcelUrl) {
                        html += `<a class="btn btn-sm btn-outline-success" href="${escapeHtml(exportExcelUrl)}" target="_blank" rel="noopener">Exportar Excel</a>`;
                    }
                    html += '</div>';
                }

                const labelMap = {
                    proyecto: 'Proyecto',
                    aoat_number: 'Numero de la AoAT o actividad',
                    activity_date: 'Fecha de la actividad',
                    activity_type: 'Actividad que realizo',
                    activity_with: 'Con quien realizo la actividad',
                    subregion: 'Subregion que visito',
                    municipality: 'Municipio visitado',
                    prev_suicidio: 'Cualificacion temas en prevencion del suicidio',
                    prev_violencias: 'Cualificacion temas en prevencion de violencias',
                    prev_adicciones: 'Cualificacion temas en prevencion de adicciones',
                    salud_mental: 'Cualificacion temas de salud mental',
                    mesa_salud_mental: 'Mesa Municipal de Salud Mental y Prevencion de las Adicciones',
                    ppmsmypa: 'Politica Publica Municipal de Salud y Prevencion de las Adicciones (PPMSMYPA)',
                    safer: 'SAFER',
                    temas_hospital: 'Temas dictados en el hospital',
                    actividad_social: 'Actividades realizadas (Profesional social)',
                    'Motivo de devolución': 'Motivo de devolución',
                    'Comentarios de devolución': 'Comentarios de devolución',
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
                            valueHtml = '&bull; ' + value.map((v) => escapeHtml(String(v))).join('<br>&bull; ');
                        } else if (value !== null && value !== '') {
                            valueHtml = formatTextBlock(String(value));
                        } else {
                            return;
                        }
                        html += `<p class="mb-1"><strong>${escapeHtml(label)}:</strong><br>${valueHtml}</p>`;
                    });
                    html += '</div>';
                } else {
                    html += '<p class="text-muted small mb-0">No se encontraron respuestas adicionales en este registro.</p>';
                }

                html += '</div>';

                Swal.fire({
                    title: 'Detalle de AoAT',
                    html,
                    width: '60rem',
                    confirmButtonText: 'Cerrar',
                });
            });
        });
    }

    const bindAoatDetailButtons = (scope = document) => {
        const buttons = scope.querySelectorAll('[data-aoat-details]');
        if (buttons.length === 0) {
            return;
        }

        buttons.forEach((btn) => {
            if (btn.dataset.aoatDetailBound === '1') {
                return;
            }

            btn.dataset.aoatDetailBound = '1';
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

                const fechaActividad = data.activity_date || data.created_at || '';
                const fechaRegistro = data.created_at || '';
                const profesional = data.professional || '';
                const rol = data.professional_role || '';
                const subregion = data.subregion || '';
                const municipio = data.municipality || '';
                const estado = data.state || '';
                const payload = data.payload && typeof data.payload === 'object' ? data.payload : {};
                const canExportSingle = Boolean(data.can_export_single);
                const exportPdfUrl = data.export_pdf_url || '';
                const exportExcelUrl = data.export_excel_url || '';

                let html = `<div class="text-start"><p class="mb-0"><strong>Fecha de la actividad:</strong> ${escapeHtml(fechaActividad)}<br>
<strong>Fecha registro:</strong> ${escapeHtml(fechaRegistro)}<br>
<strong>Profesional:</strong> ${escapeHtml(profesional)}<br>
<strong>Rol:</strong> ${escapeHtml(rol)}<br>
<strong>Subregion:</strong> ${escapeHtml(subregion)}<br>
<strong>Municipio:</strong> ${escapeHtml(municipio)}<br>
<strong>Estado:</strong> ${escapeHtml(estado)}</p>`;

                if (canExportSingle && (exportPdfUrl || exportExcelUrl)) {
                    html += '<div class="d-flex flex-wrap gap-2 justify-content-center mt-3">';
                    if (exportPdfUrl) {
                        html += `<a class="btn btn-sm btn-outline-danger" href="${escapeHtml(exportPdfUrl)}" target="_blank" rel="noopener">Exportar PDF</a>`;
                    }
                    if (exportExcelUrl) {
                        html += `<a class="btn btn-sm btn-outline-success" href="${escapeHtml(exportExcelUrl)}" target="_blank" rel="noopener">Exportar Excel</a>`;
                    }
                    html += '</div>';
                }

                const labelMap = {
                    proyecto: 'Proyecto',
                    aoat_number: 'Numero de la AoAT o actividad',
                    activity_date: 'Fecha de la actividad',
                    activity_type: 'Actividad que realizo',
                    activity_with: 'Con quien realizo la actividad',
                    subregion: 'Subregion que visito',
                    municipality: 'Municipio visitado',
                    prev_suicidio: 'Cualificacion temas en prevencion del suicidio',
                    prev_violencias: 'Cualificacion temas en prevencion de violencias',
                    prev_adicciones: 'Cualificacion temas en prevencion de adicciones',
                    salud_mental: 'Cualificacion temas de salud mental',
                    mesa_salud_mental: 'Mesa Municipal de Salud Mental y Prevencion de las Adicciones',
                    ppmsmypa: 'Politica Publica Municipal de Salud y Prevencion de las Adicciones (PPMSMYPA)',
                    safer: 'SAFER',
                    temas_hospital: 'Temas dictados en el hospital',
                    actividad_social: 'Actividades realizadas (Profesional social)',
                    'Motivo de devolución': 'Motivo de devolución',
                    'Comentarios de devolución': 'Comentarios de devolución',
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
                            valueHtml = '&bull; ' + value.map((v) => escapeHtml(String(v))).join('<br>&bull; ');
                        } else if (value !== null && value !== '') {
                            valueHtml = formatTextBlock(String(value));
                        } else {
                            return;
                        }
                        html += `<p class="mb-1"><strong>${escapeHtml(label)}:</strong><br>${valueHtml}</p>`;
                    });
                    html += '</div>';
                } else {
                    html += '<p class="text-muted small mb-0">No se encontraron respuestas adicionales en este registro.</p>';
                }

                html += '</div>';
                Swal.fire({
                    title: 'Detalle de AoAT',
                    html,
                    width: '60rem',
                    confirmButtonText: 'Cerrar',
                });
            });
        });
    };

    bindAoatDetailButtons();

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
                html: `<p class="small mb-0">Profesional: <strong>${profesional}</strong></p>
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
            html: `<p class="small mb-2">Profesional: <strong>${profesional}</strong></p>
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
                    '<option value="Errores calidad del dato">Errores calidad del dato</option>' +
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

    document.body.addEventListener('click', async (e) => {
        const delBtn = e.target.closest('[data-aoat-delete]');
        if (!delBtn) {
            return;
        }
        e.preventDefault();
        const deleteForm = document.getElementById('aoat-delete-form');
        const deleteIdInput = document.getElementById('aoat-delete-id');
        if (!deleteForm || !deleteIdInput) {
            return;
        }
        const id = delBtn.getAttribute('data-aoat-id') || '';
        if (!id) {
            return;
        }

        const result = await Swal.fire({
            title: '¿Eliminar esta AoAT?',
            html: '<p class="small mb-0">Esta acción no se puede deshacer. El registro se eliminará de forma permanente.</p>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#c0392b',
        });

        if (!result.isConfirmed) {
            return;
        }

        deleteIdInput.value = id;
        deleteForm.submit();
    });

    const syncAoatBulkToolbar = (panel) => {
        if (!panel) {
            return;
        }
        const toolbar = panel.querySelector('[data-aoat-bulk-toolbar]');
        if (!toolbar) {
            return;
        }
        const checks = [...panel.querySelectorAll('[data-aoat-bulk-check]:checked')];
        const countEl = toolbar.querySelector('[data-aoat-bulk-count]');
        const btnAprobar = toolbar.querySelector('[data-aoat-bulk-aprobar]');
        const btnDevolver = toolbar.querySelector('[data-aoat-bulk-devolver]');
        const selectAll = panel.querySelector('[data-aoat-bulk-select-all]');

        if (countEl) {
            countEl.textContent = String(checks.length);
        }

        const hasSelection = checks.length > 0;
        const hasAsignada = checks.some((cb) => (cb.getAttribute('data-aoat-bulk-row-state') || '') === 'Asignada');

        if (btnAprobar) {
            btnAprobar.disabled = !hasSelection;
        }
        if (btnDevolver) {
            btnDevolver.disabled = !hasAsignada;
        }

        if (selectAll) {
            const all = [...panel.querySelectorAll('[data-aoat-bulk-check]')];
            if (all.length === 0) {
                selectAll.checked = false;
                selectAll.indeterminate = false;
            } else {
                const nChecked = all.filter((c) => c.checked).length;
                selectAll.checked = nChecked === all.length;
                selectAll.indeterminate = nChecked > 0 && nChecked < all.length;
            }
        }
    };

    document.body.addEventListener('change', (e) => {
        const target = e.target;
        if (!(target instanceof HTMLElement)) {
            return;
        }

        if (target.matches('[data-aoat-bulk-select-all]')) {
            const panel = target.closest('[data-aoat-results]');
            if (!panel) {
                return;
            }
            const on = target.checked;
            panel.querySelectorAll('[data-aoat-bulk-check]').forEach((cb) => {
                cb.checked = on;
            });
            syncAoatBulkToolbar(panel);
            return;
        }

        if (target.matches('[data-aoat-bulk-check]')) {
            const panel = target.closest('[data-aoat-results]');
            if (panel) {
                syncAoatBulkToolbar(panel);
            }
        }
    });

    document.body.addEventListener('click', async (e) => {
        const btnAprobar = e.target.closest('[data-aoat-bulk-aprobar]');
        const btnDevolver = e.target.closest('[data-aoat-bulk-devolver]');
        if (!btnAprobar && !btnDevolver) {
            return;
        }

        const panel = (btnAprobar || btnDevolver)?.closest('[data-aoat-results]');
        if (!panel) {
            return;
        }

        const bulkForm = document.getElementById('aoat-state-bulk-form');
        const bulkIdsWrap = document.getElementById('aoat-state-bulk-ids');
        const bulkState = document.getElementById('aoat-bulk-state-value');
        const bulkObs = document.getElementById('aoat-bulk-observation-value');
        const bulkMotive = document.getElementById('aoat-bulk-motive-value');
        if (!bulkForm || !bulkIdsWrap || !bulkState || !bulkObs || !bulkMotive) {
            return;
        }

        e.preventDefault();

        const checked = [...panel.querySelectorAll('[data-aoat-bulk-check]:checked')];
        if (checked.length === 0) {
            return;
        }

        if (btnAprobar) {
            const result = await Swal.fire({
                title: 'Aprobar AoAT en bloque',
                html: `<p class="small mb-0">Se aprobarán <strong>${checked.length}</strong> registro(s) seleccionado(s) que estén en estado <strong>Asignada</strong> o <strong>Realizado</strong> (los demás se omitirán en el servidor).</p>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, aprobar',
                cancelButtonText: 'Cancelar',
                width: '42rem',
            });

            if (!result.isConfirmed) {
                return;
            }

            bulkIdsWrap.innerHTML = '';
            checked.forEach((cb) => {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'ids[]';
                inp.value = cb.value;
                bulkIdsWrap.appendChild(inp);
            });
            bulkState.value = 'Aprobada';
            bulkObs.value = '';
            bulkMotive.value = '';
            bulkForm.submit();
            return;
        }

        const asignadaChecks = checked.filter((cb) => (cb.getAttribute('data-aoat-bulk-row-state') || '') === 'Asignada');
        if (asignadaChecks.length === 0) {
            await Swal.fire({
                icon: 'info',
                title: 'Sin registros en Asignada',
                text: 'La devolución masiva solo aplica a AoAT en estado Asignada.',
            });
            return;
        }

        const { value: formValues } = await Swal.fire({
            title: 'Devolver AoAT en bloque',
            html:
                `<p class="small text-start mb-2">Se devolverán <strong>${asignadaChecks.length}</strong> registro(s) en estado <strong>Asignada</strong>. Las filas en Realizado no se incluyen.</p>` +
                '<div class="mb-2 text-start small">Motivo y observación (mismos para todos los seleccionados en Asignada):</div>' +
                '<select id="swal-aoat-bulk-motive" class="form-select mb-2">' +
                '<option value="">Selecciona un motivo</option>' +
                '<option value="Sin Cargar en AoAT">Sin Cargar en AoAT</option>' +
                '<option value="Sin cargar en Drive">Sin cargar en Drive</option>' +
                '<option value="Errores calidad del dato">Errores calidad del dato</option>' +
                '</select>' +
                '<textarea id="swal-aoat-bulk-observation" class="form-control" rows="3" placeholder="Describe el motivo de la devolución"></textarea>',
            focusConfirm: false,
            preConfirm: () => {
                const motiveEl = document.getElementById('swal-aoat-bulk-motive');
                const obsEl = document.getElementById('swal-aoat-bulk-observation');
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
            confirmButtonText: 'Enviar devoluciones',
            width: '42rem',
        });

        if (!formValues) {
            return;
        }

        bulkIdsWrap.innerHTML = '';
        asignadaChecks.forEach((cb) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'ids[]';
            inp.value = cb.value;
            bulkIdsWrap.appendChild(inp);
        });
        bulkState.value = 'Devuelta';
        bulkObs.value = formValues.observation;
        bulkMotive.value = formValues.motive;
        bulkForm.submit();
    });

    // Filtros AJAX para Seguimiento PIC
    const picFilterForm = document.querySelector('[data-pic-filters]');
    const picResults = document.querySelector('[data-pic-results]');
    const picExportLinks = document.querySelectorAll('[data-pic-export-link]');

    if (picFilterForm && picResults) {
        let picFilterTimer = null;
        let picAbortController = null;

        const updatePicUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');

            const query = cleanParams.toString();
            const url = '/pic' + (query ? `?${query}` : '');
            window.history.replaceState({}, '', url);
            homoSaveFiltersForPath('/pic', '?' + query);

            picExportLinks.forEach((link) => {
                const format = link.getAttribute('data-pic-export-link') || 'excel';
                const exportParams = new URLSearchParams(cleanParams);
                exportParams.set('format', format);
                const exportQuery = exportParams.toString();
                link.setAttribute('href', '/pic/exportar' + (exportQuery ? `?${exportQuery}` : ''));
            });
        };

        const applyPicFilters = (page = 1) => {
            const formData = new FormData(picFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'results');

            if (picAbortController) {
                picAbortController.abort();
            }
            picAbortController = new AbortController();

            fetch('/pic?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                signal: picAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        picResults.innerHTML = data.html;
                        bindPicDetailButtons(picResults);
                        updatePicUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                });
        };

        const scheduleApplyPicFilters = () => {
            if (picFilterTimer !== null) {
                clearTimeout(picFilterTimer);
            }
            picFilterTimer = setTimeout(() => applyPicFilters(1), 300);
        };

        picFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyPicFilters(1);
        });

        const picSearchInput = picFilterForm.querySelector('input[name="q"]');
        const picStateSelect = picFilterForm.querySelector('select[name="state"]');
        const picRoleSelect = picFilterForm.querySelector('select[name="role"]');
        const picFromDateInput = picFilterForm.querySelector('input[name="from_date"]');
        const picToDateInput = picFilterForm.querySelector('input[name="to_date"]');
        const picSortInput = picFilterForm.querySelector('input[name="sort"]');
        const picDirInput = picFilterForm.querySelector('input[name="dir"]');

        if (picSearchInput) {
            picSearchInput.addEventListener('input', scheduleApplyPicFilters);
        }

        if (picStateSelect) {
            picStateSelect.addEventListener('change', () => applyPicFilters(1));
        }

        if (picRoleSelect) {
            picRoleSelect.addEventListener('change', () => applyPicFilters(1));
        }

        if (picFromDateInput) {
            picFromDateInput.addEventListener('change', () => applyPicFilters(1));
        }

        if (picToDateInput) {
            picToDateInput.addEventListener('change', () => applyPicFilters(1));
        }

        picFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () => applyPicFilters(1));
        picFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () => applyPicFilters(1));

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-pic-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) {
                    return;
                }

                const page = Number(pageLink.getAttribute('data-pic-page') || '1');
                if (!Number.isFinite(page) || page < 1) {
                    return;
                }

                applyPicFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-pic-sort]');
            if (!sortLink || !picSortInput || !picDirInput) {
                return;
            }

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-pic-sort') || 'created_at';
            const nextDir = sortLink.getAttribute('data-pic-dir') || 'asc';
            if (!nextSort) {
                return;
            }

            picSortInput.value = nextSort;
            picDirInput.value = nextDir;
            applyPicFilters(1);
        });

        if (homoPendingAjaxRefresh['/pic']) {
            applyPicFilters(1);
            delete homoPendingAjaxRefresh['/pic'];
        }
    }

    // Filtros AJAX para Entrenamiento
    const entrenamientoFilterForm = document.querySelector('[data-entrenamiento-filters]');
    const entrenamientoResults = document.querySelector('[data-entrenamiento-results]');
    const entrenamientoExportLink = document.querySelector('[data-entrenamiento-export-link]');
    const entrenamientoExportPdfLink = document.querySelector('[data-entrenamiento-export-pdf-link]');

    if (entrenamientoFilterForm && entrenamientoResults) {
        let entrenamientoFilterTimer = null;
        let entrenamientoAbortController = null;

        const updateEntrenamientoUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');

            const query = cleanParams.toString();
            const url = '/entrenamiento' + (query ? `?${query}` : '');
            window.history.replaceState({}, '', url);
            homoSaveFiltersForPath('/entrenamiento', '?' + query);

            if (entrenamientoExportLink) {
                entrenamientoExportLink.setAttribute('href', '/entrenamiento/exportar' + (query ? `?${query}` : ''));
            }
            if (entrenamientoExportPdfLink) {
                entrenamientoExportPdfLink.setAttribute('href', '/entrenamiento/exportar-pdf' + (query ? `?${query}` : ''));
            }
        };

        const applyEntrenamientoFilters = (page = 1) => {
            const formData = new FormData(entrenamientoFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'results');

            if (entrenamientoAbortController) {
                entrenamientoAbortController.abort();
            }
            entrenamientoAbortController = new AbortController();

            fetch('/entrenamiento?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                signal: entrenamientoAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        entrenamientoResults.innerHTML = data.html;
                        bindTrainingDetailButtons(entrenamientoResults);
                        updateEntrenamientoUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                });
        };

        const scheduleApplyEntrenamientoFilters = () => {
            if (entrenamientoFilterTimer !== null) {
                clearTimeout(entrenamientoFilterTimer);
            }
            entrenamientoFilterTimer = setTimeout(() => applyEntrenamientoFilters(1), 300);
        };

        entrenamientoFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyEntrenamientoFilters(1);
        });

        const entrenamientoSearchInput = entrenamientoFilterForm.querySelector('input[name="q"]');
        const entrenamientoStateSelect = entrenamientoFilterForm.querySelector('select[name="state"]');
        const entrenamientoFromDateInput = entrenamientoFilterForm.querySelector('input[name="from_date"]');
        const entrenamientoToDateInput = entrenamientoFilterForm.querySelector('input[name="to_date"]');
        const entrenamientoSortInput = entrenamientoFilterForm.querySelector('input[name="sort"]');
        const entrenamientoDirInput = entrenamientoFilterForm.querySelector('input[name="dir"]');

        if (entrenamientoSearchInput) {
            entrenamientoSearchInput.addEventListener('input', scheduleApplyEntrenamientoFilters);
        }

        if (entrenamientoStateSelect) {
            entrenamientoStateSelect.addEventListener('change', () => applyEntrenamientoFilters(1));
        }

        entrenamientoFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () =>
            applyEntrenamientoFilters(1)
        );
        entrenamientoFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () =>
            applyEntrenamientoFilters(1)
        );

        if (entrenamientoFromDateInput) {
            entrenamientoFromDateInput.addEventListener('change', () => applyEntrenamientoFilters(1));
        }

        if (entrenamientoToDateInput) {
            entrenamientoToDateInput.addEventListener('change', () => applyEntrenamientoFilters(1));
        }

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-entrenamiento-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) {
                    return;
                }

                const page = Number(pageLink.getAttribute('data-entrenamiento-page') || '1');
                if (!Number.isFinite(page) || page < 1) {
                    return;
                }

                applyEntrenamientoFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-entrenamiento-sort]');
            if (!sortLink || !entrenamientoSortInput || !entrenamientoDirInput) {
                return;
            }

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-entrenamiento-sort') || 'created_at';
            const nextDir = sortLink.getAttribute('data-entrenamiento-dir') || 'asc';
            if (!nextSort) {
                return;
            }

            entrenamientoSortInput.value = nextSort;
            entrenamientoDirInput.value = nextDir;
            applyEntrenamientoFilters(1);
        });

        if (homoPendingAjaxRefresh['/entrenamiento']) {
            applyEntrenamientoFilters(1);
            delete homoPendingAjaxRefresh['/entrenamiento'];
        }
    }

    // Filtros AJAX para Planeacion anual
    const planeacionFilterForm = document.querySelector('[data-planeacion-filters]');
    const planeacionResults = document.querySelector('[data-planeacion-results]');
    const planeacionExportLinks = document.querySelectorAll('[data-planeacion-export-link]');

    if (planeacionFilterForm && planeacionResults) {
        let planeacionFilterTimer = null;
        let planeacionAbortController = null;

        const updatePlaneacionUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');

            const query = cleanParams.toString();
            const url = '/planeacion' + (query ? `?${query}` : '');
            window.history.replaceState({}, '', url);
            homoSaveFiltersForPath('/planeacion', '?' + query);

            planeacionExportLinks.forEach((link) => {
                const format = link.getAttribute('data-planeacion-export-link') || 'excel';
                const exportParams = new URLSearchParams(cleanParams);
                exportParams.set('format', format);
                const exportQuery = exportParams.toString();
                link.setAttribute('href', '/planeacion/exportar' + (exportQuery ? `?${exportQuery}` : ''));
            });
        };

        const applyPlaneacionFilters = (page = 1) => {
            const formData = new FormData(planeacionFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'results');

            if (planeacionAbortController) {
                planeacionAbortController.abort();
            }
            planeacionAbortController = new AbortController();

            fetch('/planeacion?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                signal: planeacionAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        planeacionResults.innerHTML = data.html;
                        bindPlanDetailButtons(planeacionResults);
                        updatePlaneacionUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                });
        };

        const scheduleApplyPlaneacionFilters = () => {
            if (planeacionFilterTimer !== null) {
                clearTimeout(planeacionFilterTimer);
            }
            planeacionFilterTimer = setTimeout(() => applyPlaneacionFilters(1), 300);
        };

        planeacionFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyPlaneacionFilters(1);
        });

        const planeacionSearchInput = planeacionFilterForm.querySelector('input[name="q"]');
        const planeacionFromDateInput = planeacionFilterForm.querySelector('input[name="from_date"]');
        const planeacionToDateInput = planeacionFilterForm.querySelector('input[name="to_date"]');
        const planeacionSortInput = planeacionFilterForm.querySelector('input[name="sort"]');
        const planeacionDirInput = planeacionFilterForm.querySelector('input[name="dir"]');

        if (planeacionSearchInput) {
            planeacionSearchInput.addEventListener('input', scheduleApplyPlaneacionFilters);
        }

        if (planeacionFromDateInput) {
            planeacionFromDateInput.addEventListener('change', () => applyPlaneacionFilters(1));
        }

        if (planeacionToDateInput) {
            planeacionToDateInput.addEventListener('change', () => applyPlaneacionFilters(1));
        }

        planeacionFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () =>
            applyPlaneacionFilters(1)
        );
        planeacionFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () =>
            applyPlaneacionFilters(1)
        );

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-planeacion-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) {
                    return;
                }

                const page = Number(pageLink.getAttribute('data-planeacion-page') || '1');
                if (!Number.isFinite(page) || page < 1) {
                    return;
                }

                applyPlaneacionFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-planeacion-sort]');
            if (!sortLink || !planeacionSortInput || !planeacionDirInput) {
                return;
            }

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-planeacion-sort') || 'created_at';
            const nextDir = sortLink.getAttribute('data-planeacion-dir') || 'asc';
            if (!nextSort) {
                return;
            }

            planeacionSortInput.value = nextSort;
            planeacionDirInput.value = nextDir;
            applyPlaneacionFilters(1);
        });

        if (homoPendingAjaxRefresh['/planeacion']) {
            applyPlaneacionFilters(1);
            delete homoPendingAjaxRefresh['/planeacion'];
        }
    }

    // Filtros AJAX para Encuesta Opinión AoAT
    const encuestaFilterForm = document.querySelector('[data-encuesta-filters]');
    const encuestaResults = document.querySelector('[data-encuesta-results]');
    const encuestaExportLinks = document.querySelectorAll('[data-encuesta-export-link]');

    if (encuestaFilterForm && encuestaResults) {
        let encuestaFilterTimer = null;
        let encuestaAbortController = null;

        const updateEncuestaUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');

            const query = cleanParams.toString();
            const url = '/encuesta-opinion-aoat/listar' + (query ? `?${query}` : '');
            window.history.replaceState({}, '', url);
            homoSaveFiltersForPath('/encuesta-opinion-aoat/listar', '?' + query);

            encuestaExportLinks.forEach((link) => {
                const format = link.getAttribute('data-encuesta-export-link') || 'excel';
                const exportParams = new URLSearchParams(cleanParams);
                exportParams.set('format', format);
                link.setAttribute('href', '/encuesta-opinion-aoat/exportar' + (exportParams.toString() ? `?${exportParams.toString()}` : ''));
            });
        };

        const applyEncuestaFilters = (page = 1) => {
            const formData = new FormData(encuestaFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'results');

            if (encuestaAbortController) {
                encuestaAbortController.abort();
            }
            encuestaAbortController = new AbortController();

            fetch('/encuesta-opinion-aoat/listar?' + params.toString(), {
                headers: { Accept: 'application/json' },
                signal: encuestaAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        encuestaResults.innerHTML = data.html;
                        updateEncuestaUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') return;
                });
        };

        const scheduleEncuestaFilters = () => {
            if (encuestaFilterTimer !== null) clearTimeout(encuestaFilterTimer);
            encuestaFilterTimer = setTimeout(() => applyEncuestaFilters(1), 300);
        };

        encuestaFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyEncuestaFilters(1);
        });

        const encSortInput = encuestaFilterForm.querySelector('input[name="sort"]');
        const encDirInput  = encuestaFilterForm.querySelector('input[name="dir"]');

        encuestaFilterForm.querySelector('input[name="q"]')?.addEventListener('input', scheduleEncuestaFilters);
        encuestaFilterForm.querySelector('select[name="advisor"]')?.addEventListener('change', () => applyEncuestaFilters(1));
        encuestaFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () => applyEncuestaFilters(1));
        encuestaFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () => applyEncuestaFilters(1));
        encuestaFilterForm.querySelector('input[name="from_date"]')?.addEventListener('change', () => applyEncuestaFilters(1));
        encuestaFilterForm.querySelector('input[name="to_date"]')?.addEventListener('change', () => applyEncuestaFilters(1));
        encuestaExportLinks.forEach((link) => {
            link.addEventListener('click', () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'info',
                    title: 'Preparando descarga...',
                    text: 'La exportación puede tardar unos segundos según la cantidad de registros.',
                    showConfirmButton: false,
                    timer: 2600,
                    timerProgressBar: true,
                });
            });
        });

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-encuesta-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) return;
                const page = Number(pageLink.getAttribute('data-encuesta-page') || '1');
                if (!Number.isFinite(page) || page < 1) return;
                applyEncuestaFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-encuesta-sort]');
            if (!sortLink || !encSortInput || !encDirInput) return;

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-encuesta-sort') || 'created_at';
            const nextDir  = sortLink.getAttribute('data-encuesta-dir')  || 'desc';
            if (!nextSort) return;

            encSortInput.value = nextSort;
            encDirInput.value  = nextDir;
            applyEncuestaFilters(1);
        });

        if (homoPendingAjaxRefresh['/encuesta-opinion-aoat/listar']) {
            applyEncuestaFilters(1);
            delete homoPendingAjaxRefresh['/encuesta-opinion-aoat/listar'];
        }
    }

    // Filtros AJAX para Evaluaciones
    const evalFilterForm = document.querySelector('[data-eval-filters]');
    const evalResults = document.querySelector('[data-eval-results]');
    const evalExportLink = document.querySelector('[data-eval-export-link]');
    const evalExportPdfLink = document.querySelector('[data-eval-export-pdf-link]');

    [evalExportLink, evalExportPdfLink].forEach((link) => {
        if (!link) {
            return;
        }

        link.addEventListener('click', () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: 'Preparando descarga...',
                text: 'La exportación puede tardar unos segundos según la cantidad de registros.',
                showConfirmButton: false,
                timer: 2800,
                timerProgressBar: true,
            });
        });
    });

    if (evalFilterForm && evalResults) {
        let evalFilterTimer = null;
        let evalAbortController = null;

        const updateEvalUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');
            const query = cleanParams.toString();

            window.history.replaceState({}, '', '/evaluaciones' + (query ? `?${query}` : ''));
            homoSaveFiltersForPath('/evaluaciones', '?' + query);

            if (evalExportLink) {
                evalExportLink.setAttribute('href', '/evaluaciones/exportar-csv' + (query ? `?${query}` : ''));
            }
            if (evalExportPdfLink) {
                evalExportPdfLink.setAttribute('href', '/evaluaciones/exportar-pdf' + (query ? `?${query}` : ''));
            }
        };

        const applyEvalFilters = (page = 1) => {
            const formData = new FormData(evalFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'results');

            if (evalAbortController) {
                evalAbortController.abort();
            }
            evalAbortController = new AbortController();

            fetch('/evaluaciones?' + params.toString(), {
                headers: { Accept: 'application/json' },
                signal: evalAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        evalResults.innerHTML = data.html;
                        updateEvalUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                });
        };

        const scheduleApplyEvalFilters = () => {
            if (evalFilterTimer !== null) {
                clearTimeout(evalFilterTimer);
            }
            evalFilterTimer = setTimeout(() => applyEvalFilters(1), 300);
        };

        evalFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyEvalFilters(1);
        });

        const evalSortInput = evalFilterForm.querySelector('input[name="sort"]');
        const evalDirInput  = evalFilterForm.querySelector('input[name="dir"]');
        const evalSearchInput = evalFilterForm.querySelector('input[name="search"]');
        const evalImpactInput = evalFilterForm.querySelector('select[name="impact"]');

        evalFilterForm.querySelector('select[name="test_key"]')?.addEventListener('change', () => applyEvalFilters(1));
        evalSearchInput?.addEventListener('input', scheduleApplyEvalFilters);
        evalFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () => applyEvalFilters(1));
        evalFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () => applyEvalFilters(1));
        evalImpactInput?.addEventListener('change', () => applyEvalFilters(1));
        evalFilterForm.querySelector('select[name="phase"]')?.addEventListener('change', () => applyEvalFilters(1));
        evalFilterForm.querySelector('input[name="date_from"]')?.addEventListener('change', () => applyEvalFilters(1));
        evalFilterForm.querySelector('input[name="date_to"]')?.addEventListener('change', () => applyEvalFilters(1));

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-eval-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) return;
                const page = Number(pageLink.getAttribute('data-eval-page') || '1');
                if (!Number.isFinite(page) || page < 1) return;
                applyEvalFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-eval-sort]');
            if (!sortLink || !evalSortInput || !evalDirInput) return;

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-eval-sort') || 'municipality';
            const nextDir  = sortLink.getAttribute('data-eval-dir')  || 'asc';
            if (!nextSort) return;

            evalSortInput.value = nextSort;
            evalDirInput.value  = nextDir;
            applyEvalFilters(1);
        });

        if (homoPendingAjaxRefresh['/evaluaciones']) {
            applyEvalFilters(1);
            delete homoPendingAjaxRefresh['/evaluaciones'];
        }
    }

    // Filtros AJAX para AoAT
    const aoatFilterForm = document.querySelector('[data-aoat-filters]');
    const aoatResults = document.querySelector('[data-aoat-results]');
    const aoatExportLink = document.querySelector('[data-aoat-export-link]');

    if (aoatFilterForm && aoatResults) {
        let aoatFilterTimer = null;
        let aoatAbortController = null;

        const updateAoatUrl = (params) => {
            const cleanParams = new URLSearchParams(params);
            cleanParams.delete('partial');

            const query = cleanParams.toString();
            const url = '/aoat' + (query ? `?${query}` : '');
            window.history.replaceState({}, '', url);
            homoSaveFiltersForPath('/aoat', '?' + query);

            if (aoatExportLink) {
                aoatExportLink.setAttribute('href', '/aoat/exportar' + (query ? `?${query}` : ''));
            }
        };

        const applyAoatFilters = (page = 1) => {
            const formData = new FormData(aoatFilterForm);
            const params = new URLSearchParams(formData);
            params.set('page', String(page));
            params.set('partial', 'rows');

            if (aoatAbortController) {
                aoatAbortController.abort();
            }
            aoatAbortController = new AbortController();

            fetch('/aoat?' + params.toString(), {
                headers: {
                    Accept: 'application/json',
                },
                signal: aoatAbortController.signal,
            })
                .then((response) => response.json())
                .then((data) => {
                    if (typeof data.html === 'string') {
                        aoatResults.innerHTML = data.html;
                        bindAoatDetailButtons(aoatResults);
                        syncAoatBulkToolbar(aoatResults);
                        updateAoatUrl(params);
                    }
                })
                .catch((error) => {
                    if (error && error.name === 'AbortError') {
                        return;
                    }
                    // Si falla, el usuario puede recargar o usar el filtro con recarga completa (GET normal).
                });
        };

        const scheduleApplyAoatFilters = () => {
            if (aoatFilterTimer !== null) {
                clearTimeout(aoatFilterTimer);
            }
            aoatFilterTimer = setTimeout(() => applyAoatFilters(1), 300);
        };

        aoatFilterForm.addEventListener('submit', (event) => {
            event.preventDefault();
            applyAoatFilters(1);
        });

        const searchInput = aoatFilterForm.querySelector('input[name="q"]');
        const stateSelect = aoatFilterForm.querySelector('select[name="state"]');
        const fromDateInput = aoatFilterForm.querySelector('input[name="from_date"]');
        const toDateInput = aoatFilterForm.querySelector('input[name="to_date"]');
        const sortInput = aoatFilterForm.querySelector('input[name="sort"]');
        const dirInput = aoatFilterForm.querySelector('input[name="dir"]');

        if (searchInput) {
            searchInput.addEventListener('input', scheduleApplyAoatFilters);
        }

        if (stateSelect) {
            stateSelect.addEventListener('change', () => applyAoatFilters(1));
        }

        aoatFilterForm.querySelector('select[name="activity_type"]')?.addEventListener('change', () => applyAoatFilters(1));

        aoatFilterForm.querySelector('[data-subregion-select]')?.addEventListener('change', () => applyAoatFilters(1));
        aoatFilterForm.querySelector('[data-municipality-select]')?.addEventListener('change', () => applyAoatFilters(1));

        if (fromDateInput) {
            fromDateInput.addEventListener('change', () => applyAoatFilters(1));
        }

        if (toDateInput) {
            toDateInput.addEventListener('change', () => applyAoatFilters(1));
        }

        document.body.addEventListener('click', (event) => {
            const pageLink = event.target.closest('[data-aoat-page]');
            if (pageLink) {
                event.preventDefault();
                if (pageLink.closest('.disabled, .active')) {
                    return;
                }

                const page = Number(pageLink.getAttribute('data-aoat-page') || '1');
                if (!Number.isFinite(page) || page < 1) {
                    return;
                }

                applyAoatFilters(page);
                return;
            }

            const sortLink = event.target.closest('[data-aoat-sort]');
            if (!sortLink || !sortInput || !dirInput) {
                return;
            }

            event.preventDefault();
            const nextSort = sortLink.getAttribute('data-aoat-sort') || 'activity_date';
            const nextDir = sortLink.getAttribute('data-aoat-dir') || 'asc';
            if (!nextSort) {
                return;
            }

            sortInput.value = nextSort;
            dirInput.value = nextDir;
            applyAoatFilters(1);
        });

        if (homoPendingAjaxRefresh['/aoat']) {
            applyAoatFilters(1);
            delete homoPendingAjaxRefresh['/aoat'];
        }
    }

    const evalQrModalEl = document.getElementById('evalQrModal');
    if (evalQrModalEl && typeof QRCode !== 'undefined') {
        const titleEl = evalQrModalEl.querySelector('[data-eval-qr-modal-title]');
        const preHost = evalQrModalEl.querySelector('[data-eval-qr-pre]');
        const postHost = evalQrModalEl.querySelector('[data-eval-qr-post]');
        const preUrlInput = evalQrModalEl.querySelector('[data-eval-qr-url-pre]');
        const postUrlInput = evalQrModalEl.querySelector('[data-eval-qr-url-post]');
        const shareBtns = evalQrModalEl.querySelectorAll('[data-eval-qr-share]');

        const buildAbsoluteUrl = (path) => {
            if (!path) {
                return '';
            }
            try {
                return new URL(path, window.location.origin).href;
            } catch {
                return path;
            }
        };

        const clearQrHosts = () => {
            if (preHost) {
                preHost.innerHTML = '';
            }
            if (postHost) {
                postHost.innerHTML = '';
            }
        };

        evalQrModalEl.addEventListener('show.bs.modal', (event) => {
            const btn = event.relatedTarget;
            if (!btn || !btn.hasAttribute('data-eval-qr-open')) {
                return;
            }
            const name = btn.getAttribute('data-eval-name') || '';
            const prePath = btn.getAttribute('data-eval-pre-path') || '';
            const postPath = btn.getAttribute('data-eval-post-path') || '';
            const preUrl = buildAbsoluteUrl(prePath);
            const postUrl = buildAbsoluteUrl(postPath);

            evalQrModalEl.dataset.evalThemeName = name;

            if (titleEl) {
                titleEl.textContent = name ? `Códigos QR · ${name}` : 'Códigos QR';
            }
            if (preUrlInput) {
                preUrlInput.value = preUrl;
            }
            if (postUrlInput) {
                postUrlInput.value = postUrl;
            }

            shareBtns.forEach((el) => {
                el.classList.toggle('d-none', typeof navigator.share !== 'function');
            });

            clearQrHosts();

            if (preHost && preUrl) {
                new QRCode(preHost, {
                    text: preUrl,
                    width: 200,
                    height: 200,
                    correctLevel: QRCode.CorrectLevel.M,
                });
            }
            if (postHost && postUrl) {
                new QRCode(postHost, {
                    text: postUrl,
                    width: 200,
                    height: 200,
                    correctLevel: QRCode.CorrectLevel.M,
                });
            }
        });

        evalQrModalEl.addEventListener('hidden.bs.modal', () => {
            clearQrHosts();
        });

        evalQrModalEl.addEventListener('click', (e) => {
            const copyBtn = e.target.closest('[data-eval-qr-copy]');
            if (copyBtn) {
                const which = copyBtn.getAttribute('data-eval-qr-copy');
                const input = which === 'post' ? postUrlInput : preUrlInput;
                if (!input || !input.value) {
                    return;
                }
                navigator.clipboard.writeText(input.value).then(() => {
                    copyBtn.textContent = 'Copiado';
                    setTimeout(() => {
                        copyBtn.textContent = 'Copiar enlace';
                    }, 1800);
                }).catch(() => {});
                return;
            }

            const shareBtn = e.target.closest('[data-eval-qr-share]');
            if (shareBtn && typeof navigator.share === 'function') {
                const which = shareBtn.getAttribute('data-eval-qr-share');
                const input = which === 'post' ? postUrlInput : preUrlInput;
                const theme = evalQrModalEl.dataset.evalThemeName || 'Evaluación';
                if (!input || !input.value) {
                    return;
                }
                const title = which === 'post' ? `${theme} — POST` : `${theme} — PRE`;
                navigator.share({ title, text: title, url: input.value }).catch(() => {});
            }
        });
    }

    (function homoPersistCurrentUrlIfNeeded() {
        const path = window.location.pathname;
        if (!homoFilterPaths.has(path)) {
            return;
        }
        const q = homoNormalizeQueryString(window.location.search);
        if (q) {
            homoSaveFiltersForPath(path, '?' + q);
        }
    })();
});

