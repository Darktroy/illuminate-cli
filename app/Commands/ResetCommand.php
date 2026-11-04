<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ApiClient;
use App\Services\ConfigStore;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Termwind\render;

class ResetCommand extends Command
{
    protected $signature = 'reset';

    protected $description = 'Reset your challenge progress or token';

    public function handle(ConfigStore $config): int
    {
        $token = $config->getToken();

        if (! $token) {
            render('<div class="mx-2 mb-1"><span class="px-1 bg-yellow text-black uppercase">alert</span> <span class="ml-1">No token configured. Nothing to reset.</span></div>');

            return self::FAILURE;
        }

        $choice = select(
            label: 'What would you like to reset?',
            options: [
                'stage' => 'Reset stage progress only (keep your token, resubmit flags from the start)',
                'full' => 'Full reset (invalidate token, start completely fresh)',
            ],
        );

        $confirmMessage = $choice === 'full'
            ? 'This will invalidate your token. You will need to visit illuminate.bitech.com.sa to get a new one. Type "yes" to confirm.'
            : 'This will reset your stage progress. You will need to resubmit all flags from Stage 0. Type "yes" to confirm.';

        $confirmed = confirm(
            label: $confirmMessage,
            default: false,
            yes: 'yes',
            no: 'no',
        );

        if (! $confirmed) {
            render('<div class="mx-2 mb-1"><span class="px-1 bg-blue text-white uppercase">info</span> <span class="ml-1">Reset cancelled.</span></div>');

            return self::SUCCESS;
        }

        $client = new ApiClient($token);
        $result = $client->reset((string) $choice);

        /** @var string $message */
        $message = $result['message'] ?? 'Reset complete.';

        render('<div class="mx-2 mb-1"><span class="px-1 bg-green text-white uppercase">info</span> <span class="ml-1">'.htmlspecialchars($message).'</span></div>');

        if ($choice === 'full') {
            $config->setToken('');
            render('<div class="mx-2 mb-1"><span class="px-1 bg-yellow text-black uppercase">next</span> <span class="ml-1">Visit https://illuminate.bitech.com.sa to re-authenticate and get a new token.</span></div>');
        } else {
            render('<div class="mx-2 mb-1"><span class="px-1 bg-yellow text-black uppercase">next</span> <span class="ml-1">Run `illuminate` to start over. Submit your flags again from Stage 0.</span></div>');
        }

        return self::SUCCESS;
    }
}
