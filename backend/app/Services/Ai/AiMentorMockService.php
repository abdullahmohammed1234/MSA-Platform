<?php

namespace App\Services\Ai;

class AiMentorMockService
{
    private array $scripted;

    public function __construct()
    {
        $this->scripted = [
            'sheikh-yusuf' => [
                'booth' => "### Starting with Wisdom (Hikmah)\n\nAssalamu Alaikum. Anchor your heart to Surah An-Nahl 16:125. Greet passersby warmly, listen more than you speak, and choose words that elevate rather than crush.",
                'suffering' => "### The Problem of Suffering\n\nValidate their empathy first. Explain that this life is a place of testing (Al-Ibtilaa). Human sight is limited; Allah's wisdom is absolute. Hardship can cleanse sins and build resilience.",
                'sincerity' => "### Cultivating Ikhlas\n\nWhen pride creeps in, pause, seek refuge, and reassert your intention. Step back from adversarial dialogue with humility.",
                'prayers' => "### The Sanctuary of Salah\n\nFrame Salah as a soul recharge five times daily — an anchor in chaotic time and a physical-spiritual unity through sujud.",
            ],
            'sister-yasmin' => [
                'booth' => "### Practical Campus Dawah\n\nStand open, start with warmth not pamphlets, listen 80% of the time, and avoid the debater trap on controversial topics.",
                'suffering' => "### Empathetic Dialogue on Suffering\n\nBe human first. Share rather than preach. This life is a short training ground; hardship refines us like friction on gold.",
                'sincerity' => "### Managing Sincerity\n\nWatch body language, check whether you're guiding a heart or proving you're smarter, and admit ignorance gracefully when needed.",
                'prayers' => "### Prayer as Mindful Sanctuary\n\nFrame Salah as a digital detox and stress-relief ritual — a global family standing shoulder to shoulder.",
            ],
            'dr-tariq' => [
                'booth' => "### Cognitive Logistics of Engagement\n\nEstablish epistemic common ground, use Socratic questions, avoid strawmen, and reference classical intellectual schemas.",
                'suffering' => "### Philosophical Inquiry into Evil\n\nAddress the false dichotomy between mercy and wisdom, the limits of finite judgment, and suffering as an epistemic catalyst.",
                'sincerity' => "### Epistemic Humility\n\nReframe companions as minds to serve, not opponents to defeat. Adopt al-Ghazali's hope that truth may appear on your companion's tongue.",
                'prayers' => "### Epistemology of Salah\n\nSalah is a neuro-spiritual alignment: cognitive reset, ritual hygiene (wudu), status equalization, and linguistic re-centering.",
            ],
        ];
    }

    public function generate(string $mentorId, string $query): string
    {
        $normQuery = strtolower($query);
        $mentorResponses = $this->scripted[$mentorId] ?? $this->scripted['sheikh-yusuf'];

        if ($this->matches($normQuery, ['booth', 'table', 'etiquette', 'start', 'open', 'conversation'])) {
            return $mentorResponses['booth'];
        }
        if ($this->matches($normQuery, ['suffering', 'evil', 'pain', 'bad things', 'cancer'])) {
            return $mentorResponses['suffering'];
        }
        if ($this->matches($normQuery, ['sincerity', 'ikhlas', 'winning', 'win', 'ego', 'proud', 'competitive'])) {
            return $mentorResponses['sincerity'];
        }
        if ($this->matches($normQuery, ['pray', 'salah', 'prayer', 'five times', 'worship'])) {
            return $mentorResponses['prayers'];
        }

        return match ($mentorId) {
            'sister-yasmin' => "### Let's break down your question!\n\nHey! I love that you're thinking about \"{$query}\". Make it relatable, stay approachable, and practice in our Scenario Trainer module.",
            'dr-tariq' => "### Analytical Response\n\nYour query on \"{$query}\" touches core epistemological considerations. Deconstruct assumptions, apply academic rigor, and simulate the dialogue in practice scenarios.",
            default => "### Reflecting on Your Question\n\nThank you for asking about \"{$query}\". Anchor in sincerity, seek knowledge patiently, and implement what you learn. Explore our Foundations of Dawah modules for deeper study.",
        };
    }

    private function matches(string $query, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($query, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
