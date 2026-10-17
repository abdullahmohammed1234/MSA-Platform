<?php

namespace Tests\Feature\Phase27;

use App\Models\Question;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * 2026_10_14_150300 changes questions.correct_answer from json to text so the
 * encrypted:array ciphertext can be stored on MySQL/MariaDB.
 */
class QuestionAnswerColumnMigrationTest extends TestCase
{
    use RefreshDatabase;

    /** ALTER TABLE implicitly commits on MySQL/MariaDB, so rows created after it outlive the test transaction. */
    private const FACTORY_TABLES = ['questions', 'quizzes', 'courses', 'users'];

    private array $watermarks = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'sqlite') {
            foreach (self::FACTORY_TABLES as $table) {
                $this->watermarks[$table] = (int) DB::table($table)->max('id');
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->watermarks !== []) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->watermarks as $table => $max) {
                DB::table($table)->where('id', '>', $max)->delete();
            }
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        parent::tearDown();
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_10_14_150300_change_questions_correct_answer_to_text.php');
    }

    private function restoreLegacyJsonColumn(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->json('correct_answer')->change();
        });
    }

    public function test_existing_rows_stay_readable_across_the_column_change(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            // A json column on MySQL/MariaDB can never have held ciphertext, so there is no
            // legacy data to preserve there; the empty-table path is covered below.
            $this->markTestSkipped('Legacy encrypted rows can only exist where json is not validated (SQLite).');
        }

        $this->restoreLegacyJsonColumn();
        $question = Question::factory()->create(['correct_answer' => ['3/4', '0.75']]);
        $storedBefore = DB::table('questions')->where('id', $question->id)->value('correct_answer');

        $this->migration()->up();

        $this->assertSame($storedBefore, DB::table('questions')->where('id', $question->id)->value('correct_answer'), 'ciphertext untouched');
        $this->assertSame(['3/4', '0.75'], Question::findOrFail($question->id)->correct_answer);
    }

    public function test_migrated_column_accepts_and_decrypts_answers_on_every_driver(): void
    {
        $this->restoreLegacyJsonColumn();
        $this->migration()->up();

        $question = Question::factory()->create(['correct_answer' => ['true']]);

        $this->assertSame(['true'], Question::findOrFail($question->id)->correct_answer);
        $this->assertNotSame(json_encode(['true']), DB::table('questions')->where('id', $question->id)->value('correct_answer'), 'stored encrypted, not as plain JSON');
    }

    public function test_up_is_idempotent_on_an_already_migrated_schema(): void
    {
        $question = Question::factory()->create(['correct_answer' => ['A']]);

        $this->migration()->up();

        $this->assertSame(['A'], Question::findOrFail($question->id)->correct_answer);
    }
}
