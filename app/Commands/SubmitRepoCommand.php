<?php

declare(strict_types=1);

namespace App\Commands;

use App\Services\ApiClient;
use App\Services\ConfigStore;
use Illuminate\Http\Client\RequestException;
use LaravelZero\Framework\Commands\Command;

use function Termwind\render;

class SubmitRepoCommand extends Command
{
    protected $signature = 'submit-repo
        {repo_url : Public GitHub repository URL containing your artisan commands}
        {cv : Path to your CV (PDF)}';

    protected $description = 'Final step: submit your GitHub repository and CV';

    public function handle(ConfigStore $config): int
    {
        $token = $config->getToken();
        if (! $token) {
            return $this->renderError('No token configured. Run: illuminate --token=&lt;your-token&gt;');
        }

        $repoUrl = (string) $this->argument('repo_url');
        if (! preg_match('#^https://github\.com/[\w.-]+/[\w.-]+/?$#', $repoUrl)) {
            return $this->renderError('repo_url must look like https://github.com/&lt;user&gt;/&lt;repo&gt;');
        }

        $cv = (string) $this->argument('cv');
        if (! is_file($cv) || ! is_readable($cv) || mime_content_type($cv) !== 'application/pdf') {
            return $this->renderError('CV must be a readable PDF file: '.e($cv));
        }

        try {
            $result = (new ApiClient($token))->submitRepo($repoUrl, $cv);
        } catch (RequestException $e) {
            return $this->renderError("Submission rejected (HTTP {$e->response->status()}): ".e($e->response->body()));
        }

        $content = $result['content'] ?? $result['message'] ?? 'Submitted.';
        render(is_string($content) && str_starts_with(ltrim($content), '<')
            ? $content
            : '<div class="mx-2 mb-1"><span class="px-1 bg-green text-white uppercase">info</span> <span class="ml-1">'.e((string) json_encode($result)).'</span></div>');

        return self::SUCCESS;
    }

    private function renderError(string $message): int
    {
        render('<div class="mx-2 mb-1"><span class="px-1 bg-red text-white uppercase">error</span> <span class="ml-1">'.$message.'</span></div>');

        return self::FAILURE;
    }
}
