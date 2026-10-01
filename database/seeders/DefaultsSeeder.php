<?php

namespace Database\Seeders;

use App\Enums\Network;
use App\Enums\PostFormat;
use App\Models\BrandProfile;
use App\Models\CalendarEvent;
use App\Models\ContentPillar;
use App\Models\NetworkStrategy;
use Illuminate\Database\Seeder;

class DefaultsSeeder extends Seeder
{
    /**
     * Valores iniciales de marca, estrategias, pilares y calendario (spec §4).
     */
    public function run(): void
    {
        BrandProfile::current()->update([
            'tone_of_voice' => 'Cercano, profesional y optimista. Hablamos de tú a tú con la comunidad educativa, sin tecnicismos innecesarios, con orgullo por la educación venezolana.',
            'audience' => 'Docentes, directivos y personal administrativo de colegios privados en Venezuela; también representantes interesados en la gestión escolar.',
            'base_hashtags' => ['#Sabere', '#EducacionVenezuela', '#GestionEscolar', '#Docentes'],
            'cta' => 'Conoce cómo Saberé simplifica la gestión de tu colegio',
            'url' => 'https://sabere.com',
            'palette' => [
                'dark' => '#1A2A42',
                'light' => '#F8F5F0',
                'accent' => '#27C2A5',
                'purple' => '#7854D2',
            ],
        ]);

        $strategies = [
            [
                'network' => Network::LinkedIn,
                'posts_per_week' => 3,
                'preferred_formats' => [PostFormat::Image->value],
                'focus' => 'Tono profesional: casos de uso, datos del sector educativo, visión de producto.',
            ],
            [
                'network' => Network::Instagram,
                'posts_per_week' => 4,
                'preferred_formats' => [PostFormat::Carousel->value, PostFormat::Reel->value, PostFormat::Image->value],
                'focus' => 'Tono cercano: tips para docentes y representantes, funcionalidades de Saberé en 3 pasos, frases.',
            ],
            [
                'network' => Network::TikTok,
                'posts_per_week' => 3,
                'preferred_formats' => [PostFormat::Video->value],
                'focus' => 'Tono ágil: "¿sabías que…?", problemas del día a día escolar resueltos con Saberé.',
            ],
        ];

        foreach ($strategies as $strategy) {
            NetworkStrategy::updateOrCreate(
                ['network' => $strategy['network']->value],
                [
                    'posts_per_week' => $strategy['posts_per_week'],
                    'preferred_formats' => $strategy['preferred_formats'],
                    'focus' => $strategy['focus'],
                ]
            );
        }

        $pillars = [
            [
                'name' => 'Tips para docentes',
                'description' => 'Consejos prácticos para el aula y la gestión diaria del docente.',
                'weight' => 30,
            ],
            [
                'name' => 'Funcionalidades de Saberé',
                'description' => 'Funcionalidades de la plataforma explicadas de forma sencilla.',
                'weight' => 30,
            ],
            [
                'name' => 'Educación en Venezuela',
                'description' => 'Datos, contexto y conversaciones sobre el sector educativo venezolano.',
                'weight' => 20,
            ],
            [
                'name' => 'Testimonios y comunidad',
                'description' => 'Historias de colegios, docentes y representantes que usan Saberé.',
                'weight' => 20,
            ],
        ];

        foreach ($pillars as $pillar) {
            ContentPillar::updateOrCreate(['name' => $pillar['name']], $pillar);
        }

        // Feriados venezolanos fijos (recurrentes cada año)
        $fixed = [
            ['01-01', 'Año Nuevo', true],
            ['01-15', 'Día del Maestro', false],
            ['04-19', 'Declaración de la Independencia', false],
            ['05-01', 'Día del Trabajador', false],
            ['06-24', 'Batalla de Carabobo', false],
            ['07-05', 'Día de la Independencia', false],
            ['07-24', 'Natalicio de Simón Bolívar', false],
            ['10-12', 'Día de la Resistencia Indígena', false],
            ['12-24', 'Nochebuena', true],
            ['12-25', 'Navidad', true],
            ['12-31', 'Fin de Año', true],
        ];

        foreach ($fixed as [$monthDay, $name, $noPosting]) {
            CalendarEvent::updateOrCreate(
                ['date' => "2026-{$monthDay}", 'name' => $name],
                ['recurring' => true, 'no_posting' => $noPosting]
            );
        }

        // Fechas variables (carnaval y semana santa) como fechas puntuales
        $movable = [
            ['2026-02-16', 'Carnaval (lunes)'],
            ['2026-02-17', 'Carnaval (martes)'],
            ['2026-04-02', 'Jueves Santo'],
            ['2026-04-03', 'Viernes Santo'],
            ['2027-02-08', 'Carnaval (lunes)'],
            ['2027-02-09', 'Carnaval (martes)'],
            ['2027-03-25', 'Jueves Santo'],
            ['2027-03-26', 'Viernes Santo'],
        ];

        foreach ($movable as [$date, $name]) {
            CalendarEvent::updateOrCreate(
                ['date' => $date, 'name' => $name],
                ['recurring' => false, 'no_posting' => false]
            );
        }
    }
}
