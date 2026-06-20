<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\CertificateTemplateFactory.php

namespace Database\Factories;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateTemplateFactory extends Factory
{
    protected $model = CertificateTemplate::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => $this->faker->words(2, true) . ' Template',
            'title_template' => 'Certificate of Completion',
            'description_template' => 'This certifies that {{name}} has completed {{course}}.',
            'layout' => 'landscape',
            'branding' => [
                'primary_color' => '#1e3a8a',
                'secondary_color' => '#10b981',
                'logo_url' => 'https://example.com/logo.png',
            ],
            'signatures' => [
                [
                    'name' => 'John Doe',
                    'title' => 'Director',
                    'signature_image_path' => 'signatures/director.png',
                ]
            ],
            'background_asset' => 'backgrounds/default.png',
            'status' => 'active',
        ];
    }
}
