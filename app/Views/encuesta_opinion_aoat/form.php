<?php
/** @var array $advisors */
?>
<section class="mb-5">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
        <div>
            <h1 class="section-title mb-1">Encuesta de Opinión de AoAT</h1>
            <p class="section-subtitle mb-0">
                Su opinión nos ayuda a mejorar la calidad de las asesorías y asistencias técnicas.
            </p>
        </div>
    </div>

    <?php if (empty($advisors)): ?>
        <div class="alert alert-warning border-0 shadow-sm">
            No hay asesores disponibles en este momento. Contacte al administrador.
        </div>
    <?php else: ?>
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="post" action="/encuesta-opinion-aoat" id="form-encuesta-aoat">
                <div class="row g-3 mb-4">
                    <div class="col-12">
                        <label for="advisor_user_id" class="form-label">Seleccione el nombre del asesor con el que realizó la actividad <span class="text-danger">*</span></label>
                        <select name="advisor_user_id" id="advisor_user_id" class="form-select" required>
                            <option value="">Seleccione el asesor</option>
                            <?php foreach ($advisors as $a): ?>
                                <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars((string) $a['name'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="actividad" class="form-label">Actividad <span class="text-danger">*</span></label>
                        <input type="text" name="actividad" id="actividad" class="form-control" required maxlength="500" placeholder="Descripción de la actividad">
                    </div>
                    <div class="col-md-6">
                        <label for="lugar" class="form-label">Lugar <span class="text-danger">*</span></label>
                        <input type="text" name="lugar" id="lugar" class="form-control" required maxlength="300">
                    </div>
                    <div class="col-md-6">
                        <label for="activity_date" class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="activity_date" id="activity_date" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label for="subregion" class="form-label">Seleccione la subregión de pertenencia <span class="text-danger">*</span></label>
                        <select name="subregion" id="subregion" class="form-select" required data-subregion-select>
                            <option value="">Seleccione la subregión de pertenencia</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="municipality" class="form-label">Seleccione el municipio de pertenencia <span class="text-danger">*</span></label>
                        <select name="municipality" id="municipality" class="form-select" required data-municipality-select disabled>
                            <option value="">Seleccione el municipio de pertenencia</option>
                        </select>
                    </div>
                </div>

                <p class="fw-semibold mb-2">
                    Marque su respuesta en una escala de valores de 1 a 5, donde 1 es el mínimo grado de satisfacción y 5 es el máximo, según su grado de satisfacción por el servicio recibido. <span class="text-danger">*</span>
                </p>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50%">Ítem</th>
                                <th class="text-center">1</th>
                                <th class="text-center">2</th>
                                <th class="text-center">3</th>
                                <th class="text-center">4</th>
                                <th class="text-center">5</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $items = [
                                'score_objetivos' => 'Cumplimiento de los objetivos programados',
                                'score_claridad' => 'Claridad y organización del asesor en el desarrollo de la asesoría o asistencia técnica',
                                'score_pertinencia' => 'Pertinencia de los temas asesorados, como apoyo para la gestión local',
                                'score_ayudas' => 'Manejo apropiado de las ayudas y materiales utilizados en el desarrollo de las actividades',
                                'score_relacion' => 'Relación profesional del asesor con el usuario del servicio',
                                'score_puntualidad' => 'Puntualidad del asesor con los horarios establecidos para la actividad',
                            ];
                            foreach ($items as $name => $label):
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php for ($v = 1; $v <= 5; $v++): ?>
                                        <td class="text-center">
                                            <input type="radio" name="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" value="<?= $v ?>" required class="form-check-input">
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mb-4">
                    <label for="comments" class="form-label">Recomendaciones o comentarios sobre la asesoría</label>
                    <textarea name="comments" id="comments" class="form-control" rows="4" placeholder="Recuerde que su opinión servirá para la prestación de un mejor servicio"></textarea>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>
                        Enviar encuesta
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>
</section>
