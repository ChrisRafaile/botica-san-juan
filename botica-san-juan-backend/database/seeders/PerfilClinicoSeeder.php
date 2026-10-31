<?php

namespace Database\Seeders;

use App\Models\InteraccionFarmacologica;
use App\Models\PrincipioActivo;
use Illuminate\Database\Seeder;

/**
 * Siembra del perfil clínico.
 * ---------------------------------------------------------------------------
 * DE DONDE SALE LA LISTA DE PRINCIPIOS
 *
 * No es una lista genérica copiada de cualquier sitio: son los principios que
 * APARECEN como nombre de producto en este catálogo, comprobados uno a uno
 * contra la base. Sembrar principios que la botica no vende llenaría la tabla
 * de ruido y no identificaría ni un producto más.
 *
 * LAS REGLAS NACEN SIN APROBAR, Y ESO ES INTENCIONADO
 *
 * Las interacciones de abajo son CANDIDATAS. Están entre las más descritas en
 * la literatura, pero quien las siembra es un programa, no un profesional
 * sanitario, y eso no basta para enseñárselas a un cliente de una botica.
 *
 * Todas entran con `aprobada_en = null`, de modo que el consultor NO las
 * muestra. Un químico farmacéutico tiene que revisar cada una contra la ficha
 * técnica vigente y aprobarla explícitamente. Hasta entonces existen para que
 * haya algo que revisar, no para que haya algo que mostrar.
 */
class PerfilClinicoSeeder extends Seeder
{
    /**
     * Principios activos presentes en el catálogo.
     *
     * El sinónimo sirve cuando el catálogo escribe el mismo principio de otra
     * forma; «ASPIRINA» y «ACIDO ACETILSALICILICO» son el caso claro.
     *
     * @var array<string, array<int, string>>
     */
    private const PRINCIPIOS = [
        'PARACETAMOL' => ['ACETAMINOFEN'],
        'IBUPROFENO' => [],
        'NAPROXENO' => [],
        'DICLOFENACO' => [],
        'ACIDO ACETILSALICILICO' => ['ASPIRINA', 'ACIDO ACETIL SALICILICO'],
        'AMOXICILINA' => [],
        'ACICLOVIR' => [],
        'ACETILCISTEINA' => ['N ACETILCISTEINA'],
        'OMEPRAZOL' => [],
        'METFORMINA' => [],
        'ENALAPRIL' => [],
        'CAPTOPRIL' => [],
        'LOSARTAN' => [],
        'FUROSEMIDA' => [],
        'CIPROFLOXACINO' => ['CIPROFLOXACINA'],
        'AZITROMICINA' => [],
        'CLARITROMICINA' => [],
        'ATORVASTATINA' => [],
        'LORATADINA' => [],
        'CETIRIZINA' => [],
        'DEXAMETASONA' => [],
        'PREDNISONA' => [],
        'CLOBETASOL' => [],
        'AMBROXOL' => [],
        'SALBUTAMOL' => [],
    ];

    /**
     * Interacciones candidatas, pendientes de aprobación profesional.
     *
     * @var array<int, array{0:string, 1:string, 2:string, 3:string, 4:string}>
     */
    private const CANDIDATAS = [
        [
            'ACIDO ACETILSALICILICO', 'IBUPROFENO', 'moderada',
            'Dos antiinflamatorios a la vez suman riesgo de molestia e irritación gástrica sin sumar alivio equivalente.',
            'No tomarlos juntos sin indicación. Consultar al químico farmacéutico qué conviene en cada caso.',
        ],
        [
            'IBUPROFENO', 'NAPROXENO', 'moderada',
            'Dos antiinflamatorios del mismo grupo: se acumula el efecto adverso, no el terapéutico.',
            'Elegir uno. Consultar al químico farmacéutico antes de combinarlos.',
        ],
        [
            'DICLOFENACO', 'IBUPROFENO', 'moderada',
            'Duplicidad de antiinflamatorio: mismo mecanismo, riesgo gástrico sumado.',
            'Elegir uno. Consultar al químico farmacéutico.',
        ],
        [
            'ENALAPRIL', 'IBUPROFENO', 'moderada',
            'Los antiinflamatorios pueden restar efecto al antihipertensivo y afectar la función renal en tratamientos prolongados.',
            'Avisar al químico farmacéutico si el tratamiento con enalapril es continuo.',
        ],
        [
            'CAPTOPRIL', 'IBUPROFENO', 'moderada',
            'Los antiinflamatorios pueden restar efecto al antihipertensivo y afectar la función renal en tratamientos prolongados.',
            'Avisar al químico farmacéutico si el tratamiento con captopril es continuo.',
        ],
        [
            'LOSARTAN', 'IBUPROFENO', 'moderada',
            'Los antiinflamatorios pueden restar efecto al antihipertensivo y afectar la función renal en tratamientos prolongados.',
            'Avisar al químico farmacéutico si el tratamiento con losartán es continuo.',
        ],
        [
            'ATORVASTATINA', 'CLARITROMICINA', 'grave',
            'La claritromicina puede elevar la concentración de la estatina y con ella el riesgo de daño muscular.',
            'No combinar sin indicación médica. Derivar al químico farmacéutico antes de dispensar.',
        ],
        [
            'CIPROFLOXACINO', 'OMEPRAZOL', 'leve',
            'La absorción del antibiótico puede verse alterada según el momento de la toma.',
            'Separar las tomas. Consultar la pauta con el químico farmacéutico.',
        ],
        [
            'FUROSEMIDA', 'IBUPROFENO', 'moderada',
            'Los antiinflamatorios pueden reducir el efecto diurético y afectar la función renal.',
            'Avisar al químico farmacéutico si el tratamiento con furosemida es continuo.',
        ],
        [
            'DEXAMETASONA', 'IBUPROFENO', 'moderada',
            'Corticoide y antiinflamatorio juntos aumentan el riesgo de irritación gástrica.',
            'Consultar al químico farmacéutico; puede hacer falta protección gástrica.',
        ],
        [
            'IBUPROFENO', 'PREDNISONA', 'moderada',
            'Corticoide y antiinflamatorio juntos aumentan el riesgo de irritación gástrica.',
            'Consultar al químico farmacéutico; puede hacer falta protección gástrica.',
        ],
    ];

    private const FUENTE =
        'Candidata sembrada por el sistema a partir de interacciones ampliamente descritas. '.
        'PENDIENTE de contraste con la ficha técnica vigente y de aprobación por el químico farmacéutico.';

    public function run(): void
    {
        $ids = [];

        foreach (self::PRINCIPIOS as $nombre => $sinonimos) {
            $ids[$nombre] = PrincipioActivo::updateOrCreate(
                ['nombre' => $nombre],
                ['sinonimos' => $sinonimos],
            )->id;
        }

        foreach (self::CANDIDATAS as [$a, $b, $severidad, $efecto, $recomendacion]) {
            [$menor, $mayor] = InteraccionFarmacologica::ordenarPar($ids[$a], $ids[$b]);

            InteraccionFarmacologica::updateOrCreate(
                ['principio_menor_id' => $menor, 'principio_mayor_id' => $mayor],
                [
                    'severidad' => $severidad,
                    'efecto' => $efecto,
                    'recomendacion' => $recomendacion,
                    'fuente' => self::FUENTE,
                    /* Sin aprobar. El consultor no las muestra hasta que un
                       químico farmacéutico las firme. */
                    'aprobada_en' => null,
                    'aprobada_por_id' => null,
                ],
            );
        }

        $this->command?->info(sprintf(
            'Perfil clínico: %d principios activos y %d interacciones CANDIDATAS (ninguna aprobada todavía).',
            count(self::PRINCIPIOS),
            count(self::CANDIDATAS),
        ));
    }
}
