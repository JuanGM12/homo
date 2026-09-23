document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.CRONOGRAMA_CONFIG || {};
    const canViewAll = !!cfg.canViewAll;
    const isAdmin = !!cfg.isAdmin;
    const currentUserId = Number(cfg.currentUserId || 0);
    const professionals = Array.isArray(cfg.professionals) ? cfg.professionals : [];
    const hourOptions = Array.isArray(cfg.hourOptions) ? cfg.hourOptions : [];
    const mesesNombre = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

    let mesActual = '';
    let anoActual = '';
    let actividadesMap = {};
    let municipiosData = {};

    const esc = (s) => String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/"/g, '&quot;');

    const pad = (n) => String(n).padStart(2, '0');
    const getMesParam = () => `${anoActual}-${pad(mesActual)}`;

    const timeToMinutes = (value) => {
        const m = /^(\d{1,2}):(\d{2})/.exec(String(value || ''));
        if (!m) {
            return null;
        }
        return Number(m[1]) * 60 + Number(m[2]);
    };

    const minutesToTime = (mins) => {
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    };

    const fillHoraFin = (startValue, selected) => {
        const fin = document.getElementById('actHoraFin');
        if (!fin) {
            return;
        }
        const startMin = timeToMinutes(startValue);
        fin.innerHTML = '<option value="">Seleccione</option>';
        if (startMin === null) {
            fin.disabled = true;
            return;
        }
        const fromMin = startMin + 120;
        const lastMin = 22 * 60;
        for (let mins = fromMin; mins <= lastMin; mins += 30) {
            const hour = minutesToTime(mins);
            const opt = document.createElement('option');
            opt.value = hour;
            opt.textContent = hour;
            if (selected && selected === hour) {
                opt.selected = true;
            }
            fin.appendChild(opt);
        }
        fin.disabled = false;
    };

    const filterQuery = () => {
        const parts = [];
        const sub = document.getElementById('calFiltroSubregion')?.value || '';
        const mun = document.getElementById('calFiltroMunicipio')?.value || '';
        const rol = document.getElementById('calFiltroRol')?.value || '';
        const uid = document.getElementById('calFiltroUsuario')?.value || '';
        if (sub) parts.push('subregion=' + encodeURIComponent(sub));
        if (mun) parts.push('municipality=' + encodeURIComponent(mun));
        if (rol) parts.push('role=' + encodeURIComponent(rol));
        if (uid) parts.push('usuario_id=' + encodeURIComponent(uid));
        return parts.length ? '&' + parts.join('&') : '';
    };

    const cargarActividades = () => {
        fetch('/cronograma/actividades?mes=' + getMesParam() + filterQuery())
            .then((r) => r.json())
            .then((data) => {
                actividadesMap = {};
                (data.actividades || []).forEach((a) => {
                    const f = a.fecha;
                    if (!actividadesMap[f]) {
                        actividadesMap[f] = [];
                    }
                    actividadesMap[f].push(a);
                });
                pintarGrid();
            })
            .catch(() => pintarGrid());
    };

    const pintarGrid = () => {
        const grid = document.getElementById('calGrid');
        if (!grid) {
            return;
        }
        const year = parseInt(anoActual, 10);
        const month = parseInt(mesActual, 10) - 1;
        const primerDia = new Date(year, month, 1);
        let inicioSemana = primerDia.getDay() - 1;
        if (inicioSemana < 0) {
            inicioSemana = 6;
        }
        const ultimoDia = new Date(year, month + 1, 0).getDate();
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        let html = '';
        const totalCeldas = inicioSemana + ultimoDia;
        const filas = Math.ceil(totalCeldas / 7);
        let dia = 1 - inicioSemana;

        for (let i = 0; i < filas * 7; i++) {
            const d = new Date(year, month, dia);
            const fechaStr = `${year}-${pad(month + 1)}-${pad(d.getDate())}`;
            const esOtroMes = d.getMonth() !== month;
            const esHoy = d.getTime() === hoy.getTime();
            const eventos = esOtroMes ? [] : (actividadesMap[fechaStr] || []);
            html += `<div class="calendario-celda${esOtroMes ? ' otro-mes' : ''}${esHoy ? ' hoy' : ''}" data-fecha="${esOtroMes ? '' : fechaStr}">`;
            html += `<div class="dia-num">${esOtroMes ? '' : d.getDate()}</div>`;
            if (eventos.length > 0) {
                html += '<div class="calendario-eventos">';
                eventos.slice(0, 2).forEach((ev, idx) => {
                    const tituloRaw = ev.titulo || ev.tema_label || '';
                    const hora = ev.hora ? `${ev.hora} · ` : '';
                    const tituloCorto = hora + (tituloRaw.length > 22 ? tituloRaw.substring(0, 22) + '…' : tituloRaw);
                    let titleAttr = (ev.hora ? ev.hora + ' · ' : '') + tituloRaw;
                    if (ev.usuario_nombre) titleAttr += ' · ' + ev.usuario_nombre;
                    if (ev.municipio_nombre) titleAttr += ' · ' + ev.municipio_nombre;
                    html += `<span class="evento-pill${idx === 1 ? ' evento-pill-alt' : ''}" title="${esc(titleAttr)}">`;
                    html += `<span class="evento-pill-titulo">${esc(tituloCorto)}</span>`;
                    if (canViewAll && (ev.usuario_nombre || ev.municipio_nombre)) {
                        const partes = [];
                        if (ev.usuario_nombre) partes.push(ev.usuario_nombre);
                        if (ev.municipio_nombre) partes.push(ev.municipio_nombre);
                        html += `<span class="evento-pill-meta">${esc(partes.join(' · '))}</span>`;
                    }
                    html += '</span>';
                });
                if (eventos.length > 2) {
                    html += `<span class="evento-pill mas"><span class="evento-pill-titulo">+${eventos.length - 2} más</span></span>`;
                }
                html += '</div>';
            }
            html += '</div>';
            dia += 1;
        }
        grid.innerHTML = html;
        const titulo = document.getElementById('calMesTitulo');
        if (titulo) {
            titulo.textContent = `${mesesNombre[month]} ${year}`;
        }
        grid.querySelectorAll('.calendario-celda[data-fecha]').forEach((cel) => {
            cel.addEventListener('click', () => {
                const f = cel.getAttribute('data-fecha');
                if (f) {
                    abrirModalDia(f);
                }
            });
        });
    };

    const resetForm = (fecha) => {
        document.getElementById('actFecha').value = fecha;
        document.getElementById('actId').value = '';
        document.getElementById('actHoraInicio').value = '';
        fillHoraFin('', '');
        document.getElementById('actTema').value = '';
        document.getElementById('actTemaOtro').value = '';
        document.getElementById('wrapTemaOtro').classList.add('d-none');
        document.getElementById('actPoblacion').value = '';
        document.querySelectorAll('input[name="activity_type"]').forEach((el) => {
            el.checked = false;
        });
        const sub = document.getElementById('actSubregion');
        const mun = document.getElementById('actMunicipio');
        if (sub) {
            sub.value = '';
            sub.dispatchEvent(new Event('change'));
        }
        if (mun) {
            mun.value = '';
        }
    };

    const abrirModalDia = (fecha) => {
        const [y, m, d] = fecha.split('-');
        document.getElementById('modalDiaFechaTexto').textContent = `${Number(d)} de ${mesesNombre[Number(m) - 1]} de ${y}`;
        resetForm(fecha);

        const lista = document.getElementById('modalDiaLista');
        const formWrap = document.getElementById('modalDiaFormWrap');
        const btnNuevaWrap = document.getElementById('modalDiaBtnNueva');
        const actividades = actividadesMap[fecha] || [];

        if (actividades.length === 0) {
            lista.innerHTML = '<p class="small text-muted mb-0">Sin actividades este día. Añade una abajo.</p>';
            formWrap.classList.remove('d-none');
            btnNuevaWrap.classList.add('d-none');
        } else {
            lista.innerHTML = actividades.map((a) => {
                const partes = [a.usuario_nombre, a.usuario_rol, a.municipio_nombre].filter(Boolean);
                const hora = a.hora || '';
                const titulo = esc(a.titulo || a.tema_label || '');
                return `<div class="actividad-item" data-id="${a.id}"><div class="titulo-item">${hora ? '[' + esc(hora) + '] ' : ''}${titulo}</div><div class="meta-item">${esc(partes.join(' · '))}</div></div>`;
            }).join('');
            formWrap.classList.add('d-none');
            btnNuevaWrap.classList.remove('d-none');
            lista.querySelectorAll('.actividad-item').forEach((el) => {
                el.addEventListener('click', () => verDetalle(Number(el.getAttribute('data-id'))));
            });
        }
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDia')).show();
    };

    const verDetalle = (id) => {
        fetch('/cronograma/detalle?id=' + encodeURIComponent(String(id)))
            .then((r) => r.json())
            .then((a) => {
                if (a.error) {
                    return;
                }
                document.getElementById('detalleTitulo').textContent = a.titulo || '—';
                document.getElementById('detalleCuerpo').innerHTML =
                    `<p class="small mb-1"><strong>Fecha:</strong> ${esc(a.fecha || '')}${a.hora ? ' · ' + esc(a.hora) : ''}</p>` +
                    `<p class="small mb-1"><strong>Tipo:</strong> ${esc(a.activity_type_label || a.activity_type || '')}</p>` +
                    `<p class="small mb-1"><strong>Tema:</strong> ${esc(a.tema_label || '')}</p>` +
                    `<p class="small mb-1"><strong>Población atendida:</strong> ${esc(a.poblacion_atendida || '')}</p>` +
                    `<p class="small mb-1"><strong>Subregión:</strong> ${esc(a.subregion || '')}</p>` +
                    `<p class="small mb-1"><strong>Municipio:</strong> ${esc(a.municipio_nombre || '')}</p>` +
                    `<p class="small text-muted mb-0">${esc(a.usuario_nombre || '')} · ${esc(a.usuario_rol || '')}</p>`;

                const btnDel = document.getElementById('btnEliminarActividad');
                const canDelete = isAdmin || Number(a.user_id) === currentUserId;
                btnDel.classList.toggle('d-none', !canDelete);
                btnDel.onclick = () => {
                    Swal.fire({
                        title: '¿Eliminar esta actividad?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                    }).then((result) => {
                        if (!result.isConfirmed) {
                            return;
                        }
                        const body = new URLSearchParams();
                        body.set('id', String(id));
                        fetch('/cronograma/eliminar', { method: 'POST', body })
                            .then((r) => r.json())
                            .then((data) => {
                                if (data.ok) {
                                    bootstrap.Modal.getInstance(document.getElementById('modalDetalle'))?.hide();
                                    bootstrap.Modal.getInstance(document.getElementById('modalDia'))?.hide();
                                    cargarActividades();
                                    Swal.fire({ icon: 'success', title: 'Actividad eliminada' });
                                }
                            });
                    });
                };
                bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDetalle')).show();
            });
    };

    document.getElementById('btnNuevaEnDia')?.addEventListener('click', function () {
        document.getElementById('modalDiaFormWrap').classList.remove('d-none');
        this.closest('#modalDiaBtnNueva').classList.add('d-none');
    });
    document.getElementById('btnCerrarForm')?.addEventListener('click', () => {
        document.getElementById('modalDiaFormWrap').classList.add('d-none');
        const f = document.getElementById('actFecha').value;
        const actividades = actividadesMap[f] || [];
        document.getElementById('modalDiaBtnNueva').classList.toggle('d-none', actividades.length === 0);
    });
    document.getElementById('actHoraInicio')?.addEventListener('change', function () {
        fillHoraFin(this.value, '');
    });
    document.getElementById('actTema')?.addEventListener('change', function () {
        document.getElementById('wrapTemaOtro').classList.toggle('d-none', this.value !== 'Otro');
    });

    document.getElementById('formActividad')?.addEventListener('submit', function (e) {
        e.preventDefault();
        const start = document.getElementById('actHoraInicio').value;
        const end = document.getElementById('actHoraFin').value;
        const startMin = timeToMinutes(start);
        const endMin = timeToMinutes(end);
        if (startMin === null || endMin === null || endMin - startMin < 120) {
            Swal.fire({ icon: 'warning', title: 'Rango de hora', text: 'El rango debe ser de mínimo 2 horas.' });
            return;
        }
        const params = new URLSearchParams(new FormData(this));
        fetch('/cronograma/guardar', { method: 'POST', body: params })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok) {
                    bootstrap.Modal.getInstance(document.getElementById('modalDia'))?.hide();
                    cargarActividades();
                } else {
                    Swal.fire({ icon: 'error', title: 'No se pudo guardar', text: data.mensaje || 'Revisa el formulario.' });
                }
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error de conexión' }));
    });

    document.querySelector('.calendario-prev')?.addEventListener('click', () => {
        let m = parseInt(mesActual, 10);
        let a = parseInt(anoActual, 10);
        m -= 1;
        if (m < 1) {
            m = 12;
            a -= 1;
        }
        mesActual = String(m);
        anoActual = String(a);
        cargarActividades();
    });
    document.querySelector('.calendario-next')?.addEventListener('click', () => {
        let m = parseInt(mesActual, 10);
        let a = parseInt(anoActual, 10);
        m += 1;
        if (m > 12) {
            m = 1;
            a += 1;
        }
        mesActual = String(m);
        anoActual = String(a);
        cargarActividades();
    });

    const fillFilterMunicipios = (subregion) => {
        const selMun = document.getElementById('calFiltroMunicipio');
        if (!selMun) {
            return;
        }
        const current = selMun.value;
        selMun.innerHTML = '<option value="">Todos los municipios</option>';
        const list = subregion && municipiosData[subregion] ? municipiosData[subregion] : Object.values(municipiosData).flat();
        const unique = [...new Set(list)];
        unique.sort().forEach((name) => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === current) {
                opt.selected = true;
            }
            selMun.appendChild(opt);
        });
    };

    const setupFilters = () => {
        const selSub = document.getElementById('calFiltroSubregion');
        const selMun = document.getElementById('calFiltroMunicipio');
        const selRol = document.getElementById('calFiltroRol');
        const selUsuario = document.getElementById('calFiltroUsuario');
        if (!selSub && !selMun && !selRol && !selUsuario) {
            return;
        }

        const syncUsuarios = () => {
            if (!selUsuario) {
                return;
            }
            const rol = selRol?.value || '';
            const current = selUsuario.value;
            selUsuario.innerHTML = '<option value="">Todos los profesionales</option>';
            professionals.forEach((u) => {
                if (rol && (u.role || '') !== rol) {
                    return;
                }
                const opt = document.createElement('option');
                opt.value = String(u.id);
                opt.textContent = u.role ? `${u.name} — ${u.role}` : u.name;
                if (String(u.id) === current) {
                    opt.selected = true;
                }
                selUsuario.appendChild(opt);
            });
        };

        if (selSub && selSub.dataset.filterBound !== '1') {
            selSub.dataset.filterBound = '1';
            selSub.addEventListener('change', () => {
                fillFilterMunicipios(selSub.value);
                cargarActividades();
            });
        }
        if (selMun && selMun.dataset.filterBound !== '1') {
            selMun.dataset.filterBound = '1';
            selMun.addEventListener('change', cargarActividades);
        }
        if (selRol && selRol.dataset.filterBound !== '1') {
            selRol.dataset.filterBound = '1';
            selRol.addEventListener('change', () => {
                syncUsuarios();
                cargarActividades();
            });
        }
        if (selUsuario && selUsuario.dataset.filterBound !== '1') {
            selUsuario.dataset.filterBound = '1';
            selUsuario.addEventListener('change', cargarActividades);
        }
        syncUsuarios();
    };

    const buildExportUrl = (kind) => {
        const base = kind === 'csv' ? '/cronograma/exportar' : '/cronograma/exportar-pdf';
        const rango = document.getElementById('exportarRango')?.checked;
        if (rango) {
            const d = document.getElementById('exportarDesde')?.value || '';
            const h = document.getElementById('exportarHasta')?.value || '';
            if (!d || !h) {
                return '';
            }
            return `${base}?fecha_desde=${encodeURIComponent(d)}&fecha_hasta=${encodeURIComponent(h)}${filterQuery()}`;
        }
        return `${base}?mes=${getMesParam()}${filterQuery()}`;
    };

    document.getElementById('calExportar')?.addEventListener('click', (e) => {
        e.preventDefault();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalExportar')).show();
    });
    document.querySelectorAll('input[name="exportarTipo"]').forEach((radio) => {
        radio.addEventListener('change', function () {
            document.getElementById('exportarRangoCampos').classList.toggle('d-none', this.value !== 'rango');
        });
    });

    const bindExportLink = (id, kind) => {
        document.getElementById(id)?.addEventListener('click', function (e) {
            if (document.getElementById('exportarRango')?.checked) {
                const d = document.getElementById('exportarDesde')?.value || '';
                const h = document.getElementById('exportarHasta')?.value || '';
                if (!d || !h) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', text: 'Indica las fechas desde y hasta.' });
                    return;
                }
                if (d > h) {
                    e.preventDefault();
                    Swal.fire({ icon: 'warning', text: 'La fecha "Desde" debe ser anterior a "Hasta".' });
                    return;
                }
            }
            this.href = buildExportUrl(kind);
        });
    };
    bindExportLink('btnDescargarPdf', 'pdf');
    bindExportLink('btnDescargarCsv', 'csv');

    const hoy = new Date();
    anoActual = String(hoy.getFullYear());
    mesActual = String(hoy.getMonth() + 1);

    setupFilters();

    fetch('/assets/js/municipios.json')
        .then((r) => r.json())
        .then((data) => {
            municipiosData = data && typeof data === 'object' ? data : {};
            const selSub = document.getElementById('calFiltroSubregion');
            if (selSub) {
                Object.keys(municipiosData).forEach((sub) => {
                    const opt = document.createElement('option');
                    opt.value = sub;
                    opt.textContent = sub;
                    selSub.appendChild(opt);
                });
                fillFilterMunicipios('');
            }
            setupFilters();
            cargarActividades();
        })
        .catch(() => {
            setupFilters();
            cargarActividades();
        });
});
