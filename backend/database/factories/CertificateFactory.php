<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\CertificateFactory.php

namespace Database\Factories;

use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'certificate_template_id' => CertificateTemplate::factory(),
            'title' => $this->faker->words(3, true) . ' Certificate',
            'description' => $this->faker->sentence(),
            'type' => 'course',
            'course_id' => Course::factory(),
            'learning_path_id' => null,
        ];
    }
}
