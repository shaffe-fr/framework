<?php

namespace Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers;

use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Throwable;

class LazyLoadingViolationSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return $throwable instanceof LazyLoadingViolationException;
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution(
                title: "Eager load the [{$throwable->relation}] relationship",
                description: "The [{$throwable->relation}] relationship is being lazy loaded, but lazy loading is disabled.\n"
                    ."- Add ->with('{$throwable->relation}') to your query\n"
                    ."- Or use ->load('{$throwable->relation}') on the model\n"
                    .'- Or call Model::automaticallyEagerLoadRelationships() in your AppServiceProvider',
            ),
        ];
    }
}
