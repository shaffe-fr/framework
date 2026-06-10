<?php

namespace Illuminate\Tests\Integration\Foundation\Exceptions;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\ProvidesExceptionSolutions;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Contracts\SolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\AccessDeniedSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\ClassMorphViolationSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\ConnectionRefusedSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\LazyLoadingViolationSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\MassAssignmentSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\MissingAppKeySolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\MissingColumnSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\RelationNotFoundSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\SQLiteDatabaseNotFoundSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Providers\TableNotFoundSolutionProvider;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\RunnableSolution;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\Solution;
use Illuminate\Foundation\Exceptions\Renderer\Solutions\SolutionProviderRepository;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use Throwable;

class ErrorSolutionsTest extends TestCase
{
    protected function defineEnvironment($app)
    {
        $app['config']->set('app.key', 'base64:IUHRqAQ99pZ0A1MPjbuv1D6ff3jxv0GIvS2qIW4JNU4=');
    }

    protected function defineRoutes($router)
    {
        $router->get('missing-key', fn () => throw new \Illuminate\Encryption\MissingAppKeyException());
        $router->get('table-not-found', fn () => throw new QueryException('mysql', 'select * from `posts`', [], new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'app.posts' doesn't exist")));
        $router->get('no-solution', fn () => throw new RuntimeException('Some random error'));
        $router->get('has-solutions', fn () => throw new ExceptionWithSolutions());
    }

    #[WithConfig('app.debug', true)]
    public function testSolutionProviderRepositoryIsRegistered()
    {
        $this->assertTrue($this->app->bound(SolutionProviderRepository::class));
    }

    #[WithConfig('app.debug', true)]
    public function testSolutionsAreDisplayedForMissingAppKey()
    {
        $this->get('/missing-key')
            ->assertInternalServerError()
            ->assertSee('Suggested solutions')
            ->assertSee('Generate an application key');
    }

    #[WithConfig('app.debug', true)]
    public function testSolutionsAreDisplayedForTableNotFound()
    {
        $this->get('/table-not-found')
            ->assertInternalServerError()
            ->assertSee('Suggested solutions')
            ->assertSee('Run database migrations');
    }

    #[WithConfig('app.debug', true)]
    public function testNoSolutionsSectionWhenNoSolutionsApply()
    {
        $this->get('/no-solution')
            ->assertInternalServerError()
            ->assertDontSee('Suggested solutions');
    }

    #[WithConfig('app.debug', true)]
    public function testExceptionImplementingHasSolutionsDisplaysSolutions()
    {
        $this->get('/has-solutions')
            ->assertInternalServerError()
            ->assertSee('Suggested solutions')
            ->assertSee('Custom solution title');
    }

    #[WithConfig('app.debug', true)]
    public function testDevelopersCanRegisterCustomProviders()
    {
        $repository = $this->app->make(SolutionProviderRepository::class);
        $repository->register([CustomSolutionProvider::class]);

        $this->assertContains(CustomSolutionProvider::class, $repository->getProviders());
    }

    #[WithConfig('app.debug', true)]
    public function testFrameworkDefaultProvidersAreRegistered()
    {
        $repository = $this->app->make(SolutionProviderRepository::class);

        $this->assertContains(MissingAppKeySolutionProvider::class, $repository->getProviders());
    }

    #[WithConfig('app.debug', true)]
    public function testCustomProvidersRegisteredOnHandlerAreUsedByRepository()
    {
        $handler = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        $handler->solutionProviders([CustomSolutionProvider::class]);

        // Rebuild the repository to pick up the newly registered provider.
        $this->app->forgetInstance(SolutionProviderRepository::class);
        $repository = $this->app->make(SolutionProviderRepository::class);

        $this->assertContains(CustomSolutionProvider::class, $repository->getProviders());
        $this->assertContains(MissingAppKeySolutionProvider::class, $repository->getProviders());
    }

    #[WithConfig('app.debug', true)]
    public function testRunSolutionControllerRequiresDebugMode()
    {
        config(['app.debug' => false]);

        $this->postJson('/_error-solutions/run', ['command' => 'migrate'])
            ->assertForbidden();
    }

    #[WithConfig('app.debug', true)]
    public function testRunSolutionControllerRejectsNonLocalRequests()
    {
        $this->postJson('/_error-solutions/run', ['command' => 'migrate'], [
            'REMOTE_ADDR' => '203.0.113.1',
        ])->assertForbidden();
    }

    #[WithConfig('app.debug', true)]
    public function testRunSolutionControllerRejectsInvalidCommands()
    {
        $this->postJson('/_error-solutions/run', ['command' => ''])
            ->assertStatus(422);
    }

    #[WithConfig('app.debug', true)]
    public function testRunSolutionControllerRejectsDisallowedCommands()
    {
        $this->postJson('/_error-solutions/run', ['command' => 'db:wipe'])
            ->assertForbidden();
    }

    #[WithConfig('app.debug', true)]
    public function testRunSolutionControllerRunsAllowedCommands()
    {
        $this->postJson('/_error-solutions/run', ['command' => 'optimize:clear'])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    #[WithConfig('app.debug', true)]
    public function testSolutionProviderRepositoryWalksExceptionChain()
    {
        $repository = new SolutionProviderRepository($this->app);
        $repository->register([CustomSolutionProvider::class]);

        $inner = new RuntimeException('Custom error');
        $outer = new RuntimeException('Wrapper', 0, $inner);

        $solutions = $repository->getSolutions($outer);

        $this->assertNotEmpty($solutions);
        $this->assertSame('Custom solution', $solutions[0]->title());
    }

    #[WithConfig('app.debug', true)]
    public function testSolutionWithLinks()
    {
        $solution = Solution::create('Title', 'Description')
            ->withLinks(['Docs' => 'https://laravel.com']);

        $this->assertSame('Title', $solution->title());
        $this->assertSame('Description', $solution->description());
        $this->assertSame(['Docs' => 'https://laravel.com'], $solution->links());
    }

    #[WithConfig('app.debug', true)]
    public function testRunnableSolutionHasCommandAndArguments()
    {
        $solution = RunnableSolution::artisan('Run migrate', 'Run migrations', 'migrate', ['--force']);

        $this->assertSame('migrate', $solution->command());
        $this->assertSame(['--force'], $solution->commandArguments());
    }

    #[WithConfig('app.debug', false)]
    public function testSolutionProviderRepositoryIsNotRegisteredWhenDebugDisabled()
    {
        $this->assertFalse($this->app->bound(SolutionProviderRepository::class));
    }

    #[WithConfig('app.debug', true)]
    public function testTableNotFoundProviderMatchesByMessage()
    {
        $provider = new TableNotFoundSolutionProvider();

        $exception = new QueryException('mysql', 'select * from `posts`', [], new \PDOException("SQLSTATE[42S02]: Base table or view not found: 1146 Table 'app.posts' doesn't exist"));

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertInstanceOf(RunnableSolution::class, $solutions[0]);
        $this->assertSame('migrate', $solutions[0]->command());
    }

    #[WithConfig('app.debug', true)]
    public function testMissingColumnProviderMatchesByMessage()
    {
        $provider = new MissingColumnSolutionProvider();

        $exception = new QueryException('mysql', 'select `foo` from `posts`', [], new \PDOException("SQLSTATE[42S22]: Column not found: 1054 Unknown column 'foo' in 'field list'"));

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertInstanceOf(RunnableSolution::class, $solutions[0]);
        $this->assertSame('migrate', $solutions[0]->command());
    }

    #[WithConfig('app.debug', true)]
    public function testAccessDeniedProviderMatchesByMessage()
    {
        $provider = new AccessDeniedSolutionProvider();

        $exception = new QueryException('mysql', 'select 1', [], new \PDOException("SQLSTATE[28000]: Access denied for user 'root'@'localhost'"));

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertInstanceOf(Solution::class, $solutions[0]);
    }

    #[WithConfig('app.debug', true)]
    public function testConnectionRefusedProviderMatchesByMessage()
    {
        $provider = new ConnectionRefusedSolutionProvider();

        $exception = new QueryException('mysql', 'select 1', [], new \PDOException('SQLSTATE[HY000] [2002] Connection refused'));

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertInstanceOf(Solution::class, $solutions[0]);
    }

    #[WithConfig('app.debug', true)]
    public function testProvidersDoNotMatchUnrelatedExceptions()
    {
        $exception = new QueryException('mysql', 'select 1', [], new \PDOException('Some unrelated error'));

        $this->assertFalse((new TableNotFoundSolutionProvider())->canSolve($exception));
        $this->assertFalse((new MissingColumnSolutionProvider())->canSolve($exception));
        $this->assertFalse((new AccessDeniedSolutionProvider())->canSolve($exception));
        $this->assertFalse((new ConnectionRefusedSolutionProvider())->canSolve($exception));
    }

    #[WithConfig('app.debug', true)]
    public function testRelationNotFoundProvider()
    {
        $provider = new RelationNotFoundSolutionProvider();

        $model = new \stdClass;
        $exception = \Illuminate\Database\Eloquent\RelationNotFoundException::make($model, 'posts');

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertStringContainsString('posts', $solutions[0]->title());
    }

    #[WithConfig('app.debug', true)]
    public function testLazyLoadingViolationProvider()
    {
        $provider = new LazyLoadingViolationSolutionProvider();

        $model = new \stdClass;
        $exception = new \Illuminate\Database\LazyLoadingViolationException($model, 'comments');

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertStringContainsString('comments', $solutions[0]->title());
    }

    #[WithConfig('app.debug', true)]
    public function testClassMorphViolationProvider()
    {
        $provider = new ClassMorphViolationSolutionProvider();

        $model = new \stdClass;
        $exception = new \Illuminate\Database\ClassMorphViolationException($model);

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertStringContainsString('morphMap', $solutions[0]->description());
    }

    #[WithConfig('app.debug', true)]
    public function testMassAssignmentProvider()
    {
        $provider = new MassAssignmentSolutionProvider();

        $exception = new \Illuminate\Database\Eloquent\MassAssignmentException('email');

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertStringContainsString('fillable', $solutions[0]->description());
    }

    #[WithConfig('app.debug', true)]
    public function testSQLiteDatabaseNotFoundProvider()
    {
        $provider = new SQLiteDatabaseNotFoundSolutionProvider();

        $exception = new \Illuminate\Database\SQLiteDatabaseDoesNotExistException('/path/to/db.sqlite');

        $this->assertTrue($provider->canSolve($exception));

        $solutions = $provider->getSolutions($exception);
        $this->assertCount(1, $solutions);
        $this->assertStringContainsString('touch', $solutions[0]->description());
    }
}

class ExceptionWithSolutions extends RuntimeException implements ProvidesExceptionSolutions
{
    public function __construct()
    {
        parent::__construct('Exception with solutions');
    }

    public function getSolutions(): array
    {
        return [
            new Solution('Custom solution title', 'Custom solution description'),
        ];
    }
}

class CustomSolutionProvider implements SolutionProvider
{
    public function canSolve(Throwable $throwable): bool
    {
        return str_contains($throwable->getMessage(), 'Custom error');
    }

    public function getSolutions(Throwable $throwable): array
    {
        return [
            new Solution('Custom solution', 'Fix this custom error'),
        ];
    }
}
