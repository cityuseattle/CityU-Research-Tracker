<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProgramsSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $programs = [
            [
                'name'        => 'PhD Programme in Computer Science',
                'school'      => 'Department of Computer Science',
                'description' => 'Research-based doctoral programme covering algorithms, artificial intelligence, software engineering, and systems.',
                'is_active'   => true,
            ],
            [
                'name'        => 'MPhil Programme in Computer Science',
                'school'      => 'Department of Computer Science',
                'description' => 'Research-based master\'s programme for advanced study and original research in computer science.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Information Systems',
                'school'      => 'Department of Information Systems',
                'description' => 'Doctoral programme focusing on business analytics, data management, digital transformation, and enterprise systems.',
                'is_active'   => true,
            ],
            [
                'name'        => 'MPhil Programme in Information Systems',
                'school'      => 'Department of Information Systems',
                'description' => 'Research-based master\'s programme in information systems management and digital innovation.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Electronic Engineering',
                'school'      => 'Department of Electronic Engineering',
                'description' => 'Doctoral research covering communications, photonics, signal processing, and semiconductor devices.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Biomedical Engineering',
                'school'      => 'Department of Biomedical Engineering',
                'description' => 'Interdisciplinary doctoral programme bridging engineering and life sciences for medical device and health-tech research.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Mathematics',
                'school'      => 'Department of Mathematics',
                'description' => 'Research programme in pure mathematics, applied mathematics, and statistics.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Physics',
                'school'      => 'Department of Physics',
                'description' => 'Doctoral research in condensed matter physics, quantum computing, and photonic materials.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Mechanical Engineering',
                'school'      => 'Department of Mechanical Engineering',
                'description' => 'Research programme in thermodynamics, robotics, advanced manufacturing, and materials science.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Architecture and Civil Engineering',
                'school'      => 'Department of Architecture and Civil Engineering',
                'description' => 'Doctoral programme covering structural engineering, sustainable construction, and urban planning.',
                'is_active'   => true,
            ],
            [
                'name'        => 'PhD Programme in Management Sciences',
                'school'      => 'College of Business',
                'description' => 'Research programme focusing on operations research, management science, and supply chain analytics.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Research Grant Project',
                'school'      => 'Research Grants Council',
                'description' => 'For submissions related to externally funded research grants (RGC, GRF, ECS, ITF, etc.).',
                'is_active'   => true,
            ],
            [
                'name'        => 'Conference Paper',
                'school'      => 'Academic Affairs Office',
                'description' => 'Submission track for conference papers and presentations.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Journal Publication',
                'school'      => 'Academic Affairs Office',
                'description' => 'Submission track for journal articles and peer-reviewed publications.',
                'is_active'   => true,
            ],
            [
                'name'        => 'Student Research Project',
                'school'      => 'College of Engineering',
                'description' => 'For undergraduate and postgraduate student research projects.',
                'is_active'   => true,
            ],
        ];

        foreach ($programs as $program) {
            // Skip if already exists by name
            $exists = DB::table('programs')->where('name', $program['name'])->exists();
            if (!$exists) {
                DB::table('programs')->insert(array_merge($program, [
                    'id'         => \Illuminate\Support\Str::uuid()->toString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
}
