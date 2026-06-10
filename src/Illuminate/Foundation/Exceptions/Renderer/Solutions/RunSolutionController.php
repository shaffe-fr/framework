<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

class RunSolutionController
{
    /**
     * The commands that are allowed to be run.
     *
     * @var array<int, string>
     */
    protected static array $allowedCommands = [
        'migrate',
        'key:generate',
        'config:clear',
        'cache:clear',
        'view:clear',
        'route:clear',
        'optimize:clear',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        if (! app()->hasDebugModeEnabled()) {
            abort(403);
        }

        if (! $this->isLocalRequest($request)) {
            abort(403);
        }

        $command = $request->input('command');

        if (! is_string($command) || blank($command)) {
            return response()->json(['success' => false, 'output' => 'Invalid command.'], 422);
        }

        if (! in_array($command, static::$allowedCommands, true)) {
            return response()->json(['success' => false, 'output' => 'Command not allowed.'], 403);
        }

        try {
            $outputBuffer = new BufferedOutput();

            $exitCode = Artisan::call($command, [], $outputBuffer);

            return response()->json([
                'success' => $exitCode === 0,
                'output' => trim($outputBuffer->fetch()),
                'exitCode' => $exitCode,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'output' => $e->getMessage(),
            ], 500);
        }
    }

    private function isLocalRequest(Request $request): bool
    {
        return in_array($request->ip(), ['127.0.0.1', '::1'], true);
    }
}
