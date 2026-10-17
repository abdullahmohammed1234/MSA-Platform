<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class GenerateVapidKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webpush:vapid {--write : Write the generated keys directly into backend/.env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate and display Voluntary Application Server Identification (VAPID) keys for Web Push';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating VAPID keypair for Web Push (NIST P-256)...');

        $process = new Process(['npx', '--yes', 'web-push', 'generate-vapid-keys']);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('Failed to generate keys via npx web-push: ' . $process->getErrorOutput());
            return self::FAILURE;
        }

        $output = $process->getOutput();

        $publicKey = null;
        $privateKey = null;

        if (preg_match('/Public Key:\s+([A-Za-z0-9_\-]+)/', $output, $pubMatches)) {
            $publicKey = trim($pubMatches[1]);
        }
        if (preg_match('/Private Key:\s+([A-Za-z0-9_\-]+)/', $output, $privMatches)) {
            $privateKey = trim($privMatches[1]);
        }

        if (! $publicKey || ! $privateKey) {
            $this->error('Could not parse generated VAPID keys from output:');
            $this->line($output);
            return self::FAILURE;
        }

        $this->newLine();
        $this->info('====================================================');
        $this->info('  VAPID Public Key:  ' . $publicKey);
        $this->info('  VAPID Private Key: ' . $privateKey);
        $this->info('====================================================');
        $this->newLine();

        if ($this->option('write')) {
            $envPath = base_path('.env');
            if (! file_exists($envPath)) {
                $this->error('.env file does not exist at ' . $envPath);
                return self::FAILURE;
            }

            $content = file_get_contents($envPath);

            if (str_contains($content, 'VAPID_PUBLIC_KEY=')) {
                $content = preg_replace('/VAPID_PUBLIC_KEY=.*$/m', 'VAPID_PUBLIC_KEY="' . $publicKey . '"', $content);
            } else {
                $content .= "\nVAPID_PUBLIC_KEY=\"" . $publicKey . "\"";
            }

            if (str_contains($content, 'VAPID_PRIVATE_KEY=')) {
                $content = preg_replace('/VAPID_PRIVATE_KEY=.*$/m', 'VAPID_PRIVATE_KEY="' . $privateKey . '"', $content);
            } else {
                $content .= "\nVAPID_PRIVATE_KEY=\"" . $privateKey . "\"";
            }

            file_put_contents($envPath, $content);
            $this->info('Updated backend/.env with the new VAPID keys.');
        } else {
            $this->line('Add the following to your backend/.env:');
            $this->comment('VAPID_SUBJECT="mailto:admin@sfumsa.ca"');
            $this->comment('VAPID_PUBLIC_KEY="' . $publicKey . '"');
            $this->comment('VAPID_PRIVATE_KEY="' . $privateKey . '"');
            $this->newLine();
            $this->line('And optionally to connect/.env:');
            $this->comment('VITE_VAPID_PUBLIC_KEY="' . $publicKey . '"');
            $this->newLine();
            $this->line('Tip: Run with --write to automatically update backend/.env.');
        }

        return self::SUCCESS;
    }
}
