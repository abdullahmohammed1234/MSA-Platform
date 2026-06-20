<?php
# d:\projects\msa + dawah\MSA Platform\backend\database\factories\CertificateAwardFactory.php

namespace Database\Factories;

use App\Models\CertificateAward;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CertificateAwardFactory extends Factory
{
    protected $model = CertificateAward::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'certificate_id' => Certificate::factory(),
            'title' => $this->faker->words(3, true) . ' Certificate Award',
            'description' => $this->faker->sentence(),
            'code' => 'CERT-' . strtoupper(Str::random(6)) . '-' . strtoupper(Str::random(6)),
            'verification_token' => Str::random(32),
            'pdf_path' => 'certificates/pdfs/test.pdf',
            'issued_by' => null,
            'issued_at' => now(),
        ];
    }
}
