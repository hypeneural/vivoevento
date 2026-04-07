<?php

namespace App\Modules\Billing\Console\Commands;

use App\Modules\Billing\Services\Pagarme\PagarmeHomologationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PagarmeHomologationCommand extends Command
{
    protected $signature = 'billing:pagarme:homologate
        {--scenario=all : all|pix-cancel|card-refund|simulator-dossier}
        {--amount=19900 : Amount in cents for the probe}
        {--poll-attempts=4 : Number of GET /orders and GET /charges snapshots}
        {--poll-sleep-ms=1500 : Delay in milliseconds between snapshots}
        {--card=4000000000000010 : Credit card number for card-refund}
        {--cvv=123 : Credit card CVV for card-refund or simulator dossier}';

    protected $description = 'Run direct Pagar.me v5 homologation probes for cancel/refund and simulator dossiers.';

    public function handle(PagarmeHomologationService $service): int
    {
        $scenario = strtolower((string) $this->option('scenario'));
        $amount = (int) $this->option('amount');
        $pollAttempts = (int) $this->option('poll-attempts');
        $pollSleepMs = (int) $this->option('poll-sleep-ms');
        $card = (string) $this->option('card');
        $cvv = (string) $this->option('cvv');

        $payload = match ($scenario) {
            'all' => [
                'pix_cancel' => $service->runPixCancelProbe($amount, null, $pollAttempts, $pollSleepMs),
                'card_refund' => $service->runCreditCardRefundProbe($card, $cvv, $amount, null, $pollAttempts, $pollSleepMs),
                'simulator_dossier' => $service->runGatewaySimulatorDossier($amount, $cvv, $pollAttempts, $pollSleepMs),
            ],
            'pix-cancel' => $service->runPixCancelProbe($amount, null, $pollAttempts, $pollSleepMs),
            'card-refund' => $service->runCreditCardRefundProbe($card, $cvv, $amount, null, $pollAttempts, $pollSleepMs),
            'simulator-dossier' => $service->runGatewaySimulatorDossier($amount, $cvv, $pollAttempts, $pollSleepMs),
            default => null,
        };

        if ($payload === null) {
            $this->error('Invalid scenario. Use all, pix-cancel, card-refund or simulator-dossier.');

            return self::INVALID;
        }

        $path = $this->storeReport($scenario, $payload);

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $this->newLine();
        $this->info("Report saved to {$path}");

        return self::SUCCESS;
    }

    private function storeReport(string $scenario, array $payload): string
    {
        $directory = storage_path('app/pagarme-homologation');

        File::ensureDirectoryExists($directory);

        $filename = sprintf(
            '%s-%s.json',
            now()->format('Ymd-His'),
            str_replace('_', '-', $scenario),
        );
        $path = $directory . DIRECTORY_SEPARATOR . $filename;

        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $path;
    }
}
