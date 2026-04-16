<?php

declare(strict_types=1);

/** Hospitales — PRE y POST con bancos separados (POST puede reemplazar ítems sin afectar el PRE). */
$hospitalesPre = [
        1 => [
            'text' => 'Llega a urgencias un joven de 22 años con antecedente de epilepsia, debido a que se le acabó la medicación y requiere reformulación, durante la atención en triage presenta pérdida súbita del tono postural, movimientos tónico-clónicos generalizados y supraversión de la mirada. ¿Qué debe hacer el personal de atención?',
            'options' => [
                'A' => 'Sujetarlo firmemente para evitar movimientos.',
                'B' => 'Colocar algo entre sus dientes para prevenir mordeduras.',
                'C' => 'Retirar objetos peligrosos alrededor, proteger la cabeza, mantener vía aérea y observar respiración.',
                'D' => 'Administrar sedantes inmediatamente para realizar intubación orotraqueal.',
                'E' => 'Remitir a valoración urgente por neurología',
            ],
        ],
        2 => [
            'text' => 'Si se identifica un consumo riesgoso de alcohol, ¿Qué intervención inicial puede ofrecer un profesional en primer nivel de atención?',
            'options' => [
                'A' => 'Informar los riesgos sin indagar motivación para cambiar.',
                'B' => 'Dar una “charla moral” enfatizando consecuencias graves.',
                'C' => 'Realizar una breve entrevista motivacional.',
                'D' => 'Exigir abandono inmediato del alcohol sin acompañamiento.',
                'E' => 'Prescribir benzodiacepinas de manera ambulatoria e iniciar desmonte.',
            ],
        ],
        3 => [
            'text' => 'Una mujer de 30 años fuma un paquete diario desde hace 10 años. Tras un abordaje inicial, expresa que en algún momento quisiera dejar de fumar. ¿Qué estrategia breve puede ofrecer el profesional del primer nivel para iniciar cesación?',
            'options' => [
                'A' => 'Sugerir que aumente cigarrillos antes de intentar dejar.',
                'B' => 'Iniciar estrategia de las 5 A.',
                'C' => 'Inciar estrategia de las 5 R.',
                'D' => 'Decirle que la cesación debe hacerse solo en niveles especializados.',
                'E' => 'Prescribir benzodiacepinas.',
            ],
        ],
        4 => [
            'text' => 'En urgencias llega un paciente claramente alterado, gritando, con postura tensa, gestos bruscos y desorientación. ¿Cuál es la primera estrategia recomendada para calmar la situación?',
            'options' => [
                'A' => 'Gritar órdenes firmes para que se calme.',
                'B' => 'Realizar contención farmacológicamente con benzodiacepinas, para realizar sujeción mecánica y poder realizar el interrogatorio',
                'C' => 'Mantener distancia segura, hablar con tono calmado y claro, mostrar empatía, iniciar desescalada verbal',
                'D' => 'Sujetarlo físicamente de inmediato.',
                'E' => 'Contener farmacológicamente como primera opción.',
            ],
        ],
        5 => [
            'text' => 'Una mujer de 20 años acude acompañada al servicio de urgencias debido a ingesta de 4 tabletas de ibuprofeno con intencionalidad suicida. Se encuentra emocionalmente desbordada, reconoce malestar intenso, pero en este momento está tranquila y accesible. En la evaluación inicial de TRIAGE no presenta lesiones que comprometan su vida y sus signos vitales son normales. ¿Cuál debe ser la conducta en el primer nivel de atención?',
            'options' => [
                'A' => 'Considerar triage 5 porque no tiene criterios de manejo en urgencias',
                'B' => 'Realizar lavado gástrico y dejar en observación.',
                'C' => 'Ingresar para manejo en servicio de urgencias y activar ruta de atención.',
                'D' => 'Derivar a cita prioritaria.',
                'E' => 'Dar recomendaciones generales y permitir que se retire sola.',
            ],
        ],
        6 => [
            'text' => 'Un hombre de 60 años con diagnóstico de cáncer de estómago en tratamiento paliativo consulta por dolor agudo muy intenso, descrito como 8/10 en la escala análoga del dolor. Su familiar informa que el paciente venía recibiendo morfina en casa formulada previamente, pero desde la noche anterior el dolor aumentó de manera marcada. Está consciente, orientado, y sin signos inmediatos de compromiso vital, pero visiblemente angustiado por el dolor. ¿Cuál es la conducta más adecuada en el primer nivel de atención?',
            'options' => [
                'A' => 'Decirle que continúe con la misma dosis en casa y esperar a que “le haga más efecto”.',
                'B' => 'Suspender completamente los analgésicos y observar la evolución durante varias horas.',
                'C' => 'Realizar analgésia escalonada, iniciando con acetaminofen y AINES orales',
                'D' => 'Iniciar opioides IV, con dosis equivalentes a dosificación oral manejada en casa, ajustar esquema según respuesta.',
                'E' => 'Iniciar con AINES parenterales y aumentar potencia analgésica según respuesta.',
            ],
        ],
        7 => [
            'text' => 'Paciente con síntomas depresivos leves, funcionalidad conservada y sin riesgo suicida. ¿Cuál es el manejo más adecuado?',
            'options' => [
                'A' => 'Hospitalización',
                'B' => 'Antidepresivo',
                'C' => 'Psicoterapia breve y seguimiento',
                'D' => 'Antipsicótico atípico',
                'E' => 'Terapia electroconvulsiva',
            ],
        ],
        8 => [
            'text' => 'En un paciente con diagnóstico de ansiedad leve, ¿Cuál es la primera línea de manejo en la baja complejidad?',
            'options' => [
                'A' => 'Benzodiacepinas de forma crónica',
                'B' => 'Antipsicóticos',
                'C' => 'Psicoeducación y técnicas de relajación',
                'D' => 'Hospitalización',
                'E' => 'Ansiolíticos como la difenhidramina',
            ],
        ],
        9 => [
            'text' => 'Niño de 8 años con inatención e hiperactividad que afectan su rendimiento escolar y la convivencia familiar. ¿Cuál criterio es indispensable para el diagnóstico?',
            'options' => [
                'A' => 'Inicio después de los 12 años',
                'B' => 'Síntomas solo en el hogar',
                'C' => 'Afectación en más de un entorno',
                'D' => 'Presencia obligatoria de agresividad',
                'E' => 'Uso previo de estimulantes',
            ],
        ],
        10 => [
            'text' => 'Paciente de 22 años con alucinaciones auditivas, ideas delirantes y deterioro funcional desde hace un mes. ¿Cuál es la conducta inicial más adecuada?',
            'options' => [
                'A' => 'Observación sin intervención',
                'B' => 'Iniciar antipsicótico',
                'C' => 'Psicoterapia',
                'D' => 'Tratar como ansiedad',
                'E' => 'Dar alta con seguimiento anual',
            ],
        ],
        11 => [
            'text' => '¿Qué conducta debe evitarse ante sospecha de trastorno bipolar?',
            'options' => [
                'A' => 'Remisión a psiquiatría',
                'B' => 'Psicoeducación al paciente',
                'C' => 'Uso de antidepresivo',
                'D' => 'Uso de estabilizador del estado de ánimo',
                'E' => 'Evaluación del riesgo',
            ],
        ],
        12 => [
            'text' => '¿Cuál es la primera línea de manejo del insomnio?',
            'options' => [
                'A' => 'Benzodiacepinas',
                'B' => 'Antipsicóticos',
                'C' => 'Hipnóticos de acción prolongada',
                'D' => 'Terapia no farmacológica.',
                'E' => 'Antihistamínicos',
            ],
        ],
        13 => [
            'text' => 'Paciente de 28 años consulta por ansiedad y tristeza. Durante la entrevista expresa que “a veces piensa que no valdría la pena seguir viviendo”, sin plan ni intento previo. Niega consumo de sustancias. ¿Cuál es la conducta correcta en el primer nivel?',
            'options' => [
                'A' => 'No profundizar para no inducir ideas',
                'B' => 'Evaluar riesgo suicida y explorar factores protectores',
                'C' => 'Hospitalizar inmediatamente',
                'D' => 'Minimizar la expresión por no haber plan',
                'E' => 'Remitir sin valoración',
            ],
        ],
        14 => [
            'text' => 'Paciente de 40 años, con ideación suicida y plan poco estructurado. Es valorado en el servicio de urgencias, durante la evaluación se identifica red de apoyo familiar activa y disposición a buscar ayuda. ¿Cómo se clasifica este hallazgo?',
            'options' => [
                'A' => 'Factor de riesgo',
                'B' => 'Factor precipitante',
                'C' => 'Factor contributivo',
                'D' => 'Factor de riesgo proximal',
                'E' => 'Factor protector',
            ],
        ],
        15 => [
            'text' => '¿Cuál es la herramienta de tamizaje que permite evaluar el riesgo de consumo de múltiples sustancias psicoactivas?',
            'options' => [
                'A' => 'AUDIT',
                'B' => 'CAGE',
                'C' => 'CIWA-Ar',
                'D' => 'ASSIST',
                'E' => 'APGAR Familiar',
            ],
        ],
        16 => [
            'text' => '¿Cuál es una velocidad de reducción gradual recomendada para pacientes con uso prolongado (≥1 año) de opioides?',
            'options' => [
                'A' => '10% por mes o más lento.',
                'B' => 'Detención abrupta (suspensión repentina).',
                'C' => 'Entre 10% al 20% cada mes.',
                'D' => '50% de la dosis original por semana.',
                'E' => '15% cada semana.',
            ],
        ],
        17 => [
            'text' => 'Según la guía ASAM, ¿Cuál es el ritmo de reducción de dosis inicial recomendado para el desmonte de benzodiacepinas en un paciente con dependencia física?',
            'options' => [
                'A' => 'Reducción del 5% al 10% de la dosis actual cada 2 a 4 semanas.',
                'B' => 'Reducción del 50% de la dosis cada mes.',
                'C' => 'Interrupción abrupta si el paciente se siente listo.',
                'D' => 'Reducción del 25% de la dosis cada semana.',
                'E' => 'Reducción del 15% al 20% cada 15 días.',
            ],
        ],
        18 => [
            'text' => 'Paciente de 62 años es llevado a urgencias por su familia por somnolencia marcada, dificultad para articular palabras y marcha inestable. Tiene antecedente de ansiedad crónica y se encuentra en tratamiento con clonazepam formulado hace varios meses. No se conoce la dosis ingerida. Signos vitales estables, respiración conservada. ¿Cuál debe ser la prioridad inicial del manejo en este escenario?',
            'options' => [
                'A' => 'Administrar flumazenil de forma inmediata',
                'B' => 'Realizar lavado gástrico',
                'C' => 'Evaluar y asegurar la vía aérea, respiración y circulación',
                'D' => 'Solicitar niveles séricos del medicamento',
                'E' => 'Evaluar riesgo de suicidio para definir criterios de remisión',
            ],
        ],
        19 => [
            'text' => 'Paciente de 42 años es llevado a urgencias por la policía tras presentar conducta alterada en vía pública. A la llegada se muestra irritable, con aumento del tono de voz, inquietud motora y dificultad para mantener la atención. Niega consumo de sustancias, pero el acompañante refiere ingesta reciente de alcohol. No hay signos de trauma ni compromiso neurológico evidente. Desde el primer nivel de atención, ¿Cuál debe ser el enfoque inicial más adecuado?',
            'options' => [
                'A' => 'Asumir que se trata de un trastorno psiquiátrico primario',
                'B' => 'Priorizar la evaluación médica para descartar causas orgánicas o tóxicas',
                'C' => 'Iniciar antipsicótico intramuscular de forma inmediata',
                'D' => 'Aplicar contención física preventiva',
                'E' => 'Dar alta sin intervención hasta que ceda la agitación',
            ],
        ],
        20 => [
            'text' => 'Durante una consulta en atención primaria, un paciente de 24 años expresa que en semanas recientes ha tenido pensamientos de hacerse daño, pero aclara que no tiene un plan definido. Un profesional en formación comenta: “Es mejor no preguntar mucho por suicidio, porque hablar del tema puede inducir la conducta”. ¿Cuál de las siguientes afirmaciones es correcta?',
            'options' => [
                'A' => 'Hablar sobre suicidio puede aumentar la probabilidad de que el paciente intente hacerse daño',
                'B' => 'Solo se debe indagar por suicidio cuando el paciente lo menciona de forma explícita',
                'C' => 'Preguntar de manera directa y respetuosa por ideación suicida permite identificar riesgo y no incrementa la conducta suicida',
                'D' => 'Indagar por suicidio debe evitarse en atención primaria y dejarse solo a especialistas',
                'E' => 'Explorar ideación suicida genera dependencia emocional del paciente hacia el profesional',
            ],
        ],
];

$hospitalesPost = [
    1 => [
        'text' => 'Tras una convulsión autolimitada, un familiar consulta qué debe hacer. ¿Cuál es una recomendación adecuada desde el primer nivel?',
        'options' => [
            'A' => 'Que la persona se levante inmediatamente y continúe sus actividades normales.',
            'B' => 'Que permanezca sola hasta que se sienta mejor.',
            'C' => 'Vigilar su respiración, acompañarla, asegurar un entorno seguro y permitir descanso.',
            'D' => 'Administrar líquidos o estimulantes para "despertarla" rápido.',
            'E' => 'Repetirle la dosis de medicamentos orales para evitar que vuelva a convulsionar.',
        ],
    ],
    2 => [
        'text' => 'En una consulta rutinaria, un hombre de 38 años refiere que "sale a tomar con amigos todos los fines de semana" y admite "beber bastante". No hay signos físicos graves. ¿Cuál sería la mejor estrategia para detectar consumo riesgoso en este entorno?',
        'options' => [
            'A' => 'Enviar exámenes de laboratorio.',
            'B' => 'Aplicar un cuestionario breve de tamizaje, como el AUDIT-C.',
            'C' => 'Aplicar un cuestionario breve de tamizaje, como el Fagerstrom.',
            'D' => 'Esperar a que el paciente consulte por un problema concreto.',
            'E' => 'Enviar a urgencias de inmediato por alto riesgo de abstinencia.',
        ],
    ],
    3 => [
        'text' => 'Una persona de 45 años con antecedente de diabetes, fuma medio paquete de cigarrillos al día desde hace 20 años, en la consulta de riesgo cardiovascular usted le recomienda cesar el consumo de cigarrillo, el paciente responde que cree que no es capaz de dejar de fumar porque en el pasado lo intentó y se sintió muy mal. ¿Qué estrategia breve se puede ofrecer desde el primer nivel para iniciar cesación?',
        'options' => [
            'A' => 'Negar ayuda si no manifiesta claro deseo de dejar.',
            'B' => 'Iniciar estrategia de las 5 A.',
            'C' => 'Iniciar estrategia de las 5 R.',
            'D' => 'Aplicar una herramienta de tamizaje para ver si requiere cesación de consumo de tabaco',
            'E' => 'Sugerirle que aumente el consumo de cigarrillos.',
        ],
    ],
    4 => [
        'text' => 'Un hombre de 32 años con antecedente conocido de esquizofrenia llega al servicio con marcada agitación, discurso desorganizado, ideas delirantes y conducta impredecible. No responde a intervenciones verbales y representa un riesgo para sí mismo y para otros. No ha tomado su medicación en varios días. ¿Cuál es el manejo farmacológico inicial más adecuado en un entorno de baja complejidad, priorizando seguridad y buenas prácticas?',
        'options' => [
            'A' => 'Administrar benzodiacepinas',
            'B' => 'Administrar un antipsicótico con una benzodiacepina',
            'C' => 'Usar antidepresivos para tranquilizarlo.',
            'D' => 'Evitar cualquier medicación y esperar a que se calme por sí solo.',
            'E' => 'Administrar sedación profunda.',
        ],
    ],
    5 => $hospitalesPre[5],
    6 => [
        'text' => 'Un adulto de 55 años acude con dolor lumbar moderado tras un esfuerzo físico. En el primer nivel, la evaluación adecuada del dolor debe incluir:',
        'options' => [
            'A' => 'Solo examen físico, debido a que los recursos son escasos.',
            'B' => 'Incluir en la valoración el uso de una escala simple (numérica, visual análoga o similar) para cuantificar intensidad.',
            'C' => 'Solicitar imágenes inmediatamente antes de tratar.',
            'D' => 'Prescribir reposo absoluto sin evaluar intensidad.',
            'E' => 'Prescribir morfina debido a que la región lumbar es un punto crítico y puede tener una hernia discal.',
        ],
    ],
    7 => [
        'text' => 'Paciente de 45 años, consulta por tristeza persistente, anhedonia, fatiga y alteraciones del sueño desde hace 6 semanas. No hay consumo de sustancias ni duelo reciente. ¿Cuál es el paso inicial más adecuado en el primer nivel?',
        'options' => [
            'A' => 'Iniciar antipsicótico',
            'B' => 'Aplicar tamizaje (PHQ-9 o Whooley)',
            'C' => 'Remitir inmediatamente a psiquiatría',
            'D' => 'Solicitar neuroimagen',
            'E' => 'Iniciar benzodiacepina',
        ],
    ],
    8 => [
        'text' => 'Paciente con preocupación excesiva diaria, dificultad para controlar la ansiedad, tensión muscular e insomnio desde hace más de 6 meses. El diagnóstico más probable es:',
        'options' => [
            'A' => 'Trastorno de pánico',
            'B' => 'Trastorno depresivo persistente',
            'C' => 'Trastorno de ansiedad generalizada',
            'D' => 'Fobia social',
            'E' => 'Trastorno adaptativo',
        ],
    ],
    9 => [
        'text' => 'Niño de 8 años con inatención e hiperactividad que afectan su rendimiento escolar y la convivencia familiar. Desde la baja complejidad, el abordaje inicial más adecuado es:',
        'options' => [
            'A' => 'Iniciar metilfenidato sin evaluación',
            'B' => 'Psicoeducación y remisión para evaluación multidisciplinaria',
            'C' => 'Prescribir benzodiacepinas',
            'D' => 'Antipsicóticos de mantenimiento',
            'E' => 'Hospitalización preventiva',
        ],
    ],
    10 => [
        'text' => 'Paciente de 30 años consulta por conducta extraña, lenguaje poco coherente y suspicacia marcada. Un familiar refiere abandono laboral y aislamiento progresivo en los últimos 3 meses. No hay consumo de sustancias ni antecedente médico agudo. ¿Cuál es el objetivo principal del abordaje inicial en el primer nivel de atención?',
        'options' => [
            'A' => 'Confirmar el diagnóstico definitivo de esquizofrenia',
            'B' => 'Identificar riesgo, descartar causas médicas y garantizar remisión oportuna',
            'C' => 'Iniciar psicoterapia cognitivo-conductual exclusiva',
            'D' => 'Evitar la participación de la familia',
            'E' => 'Postergar la intervención hasta valoración especializada',
        ],
    ],
    11 => [
        'text' => 'Paciente de 34 años, con antecedente de "depresiones recurrentes" tratadas. Consulta por una semana de ánimo eufórico, verborrea, aumento marcado de energía, disminución de la necesidad de dormir (2–3 horas), gastos excesivos y conflictos laborales. No consumo de sustancias. ¿Cuál es el elemento clínico que obliga a sospechar trastorno afectivo bipolar y no depresión recurrente?',
        'options' => [
            'A' => 'La duración breve de los síntomas',
            'B' => 'La disminución de la necesidad de dormir sin fatiga',
            'C' => 'El antecedente de episodios depresivos',
            'D' => 'La presencia de estrés laboral',
            'E' => 'La ausencia de síntomas psicóticos',
        ],
    ],
    12 => [
        'text' => 'Paciente de 52 años consulta por dificultad para conciliar el sueño y despertares frecuentes desde hace 3 meses. Refiere uso nocturno de celular, consumo de café en la tarde y preocupación constante por no dormir. Funcionalidad diurna conservada. ¿Cuál es el diagnóstico más probable?',
        'options' => [
            'A' => 'Apnea obstructiva del sueño',
            'B' => 'Insomnio crónico',
            'C' => 'Trastorno depresivo mayor',
            'D' => 'Narcolepsia',
            'E' => 'Trastorno psicótico',
        ],
    ],
    13 => $hospitalesPre[13],
    14 => $hospitalesPre[14],
    15 => [
        'text' => '¿Qué intervenciones farmacológicas o no farmacológicas están indicadas en una persona con riesgo moderado de consumo de sustancias psicoactivas?',
        'options' => [
            'A' => 'Intervenciones breves',
            'B' => 'Valoración por equipo multidisciplinario',
            'C' => 'Benzodiacepinas',
            'D' => 'Hospitalización',
            'E' => 'Antipsicóticos',
        ],
    ],
    16 => [
        'text' => '¿Cuál es el obstáculo principal para reducir o descontinuar opioides en pacientes con dolor crónico no oncológico que han desarrollado dependencia fisiológica?',
        'options' => [
            'A' => 'Resistencia del paciente a usar tratamientos no opioides.',
            'B' => 'Falta de acceso a clínicas de dolor especializadas.',
            'C' => 'La larga vida media de los opioides prescritos.',
            'D' => 'El síndrome de abstinencia a opioides (SAO).',
            'E' => 'Falta de disponibilidad de metadona.',
        ],
    ],
    17 => [
        'text' => '¿Con qué rapidez puede desarrollarse la dependencia física a las benzodiacepinas, incluso cuando se usan en dosis terapéuticas?',
        'options' => [
            'A' => 'En tan solo dos semanas de uso continuo.',
            'B' => 'Únicamente después de 6 meses de uso continuo.',
            'C' => 'Solo si se toman dosis superiores a las prescritas.',
            'D' => 'Nunca se desarrolla dependencia física, solo psicológica.',
            'E' => 'Después de 3 meses de uso continuo.',
        ],
    ],
    18 => [
        'text' => 'Paciente de 47 años consulta a urgencias acompañado por un familiar, quien refiere que el paciente fue encontrado confuso y somnoliento en su habitación. Tiene antecedente de dolor crónico lumbar y está en manejo con tramadol y diazepam formulados por diferentes servicios. A la valoración presenta lenguaje lento, pupilas normales, frecuencia respiratoria de 14/min y signos vitales estables. ¿Cuál es el aspecto clínico más relevante que debe considerarse en este caso desde el primer nivel de atención?',
        'options' => [
            'A' => 'Confirmar el diagnóstico mediante niveles séricos antes de cualquier decisión',
            'B' => 'Identificar posible uso concomitante de opioides y benzodiacepinas',
            'C' => 'Administrar antídotos de forma sistemática',
            'D' => 'Realizar lavado gástrico independientemente del tiempo de ingesta',
            'E' => 'Dar alta inmediata si los signos vitales son normales',
        ],
    ],
    19 => [
        'text' => 'En el contexto de un paciente agitado, que inicia repentinamente con aumento de la agitación y comienza a amenazar al personal, sin responder a desescalamiento verbal. ¿Cuál es el objetivo principal del uso de medicación en este contexto?',
        'options' => [
            'A' => 'Castigar la conducta disruptiva',
            'B' => 'Dormir al paciente',
            'C' => 'Reducir la agitación para garantizar seguridad',
            'D' => 'Confirmar un diagnóstico psiquiátrico',
            'E' => 'Evitar la participación de la familia',
        ],
    ],
    20 => [
        'text' => 'Durante una consulta en atención primaria, un paciente de 24 años expresa que en semanas recientes ha tenido pensamientos de hacerse daño, pero aclara que no tiene un plan definido. Un profesional en formación comenta: "Es mejor no preguntar mucho por suicidio, porque hablar del tema puede inducir la conducta". ¿Cuál de las siguientes afirmaciones es correcta?',
        'options' => [
            'A' => 'Hablar sobre suicidio puede aumentar la probabilidad de que el paciente intente hacerse daño',
            'B' => 'Solo se debe indagar por suicidio cuando el paciente lo menciona de forma explícita',
            'C' => 'Preguntar de manera directa y respetuosa por ideación suicida permite identificar riesgo y no incrementa la conducta suicida',
            'D' => 'Indagar por suicidio debe evitarse en atención primaria y dejarse solo a especialistas',
            'E' => 'Explorar ideación suicida genera dependencia emocional del paciente hacia el profesional',
        ],
    ],
];

return [
    'hospitales' => [
        'pre' => $hospitalesPre,
        'post' => $hospitalesPost,
    ],
];
