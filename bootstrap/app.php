<?php

use App\Http\Middleware\EnsureApiKeyIsValid;
use App\Support\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [EnsureApiKeyIsValid::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'VALIDATION_ERROR',
                'Não foi possível processar os dados informados. Verifique os campos e tente novamente.',
                ['fields' => $exception->errors()],
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_UNAUTHORIZED,
                'UNAUTHENTICATED',
                'Você precisa estar autenticado para acessar este recurso.',
            );
        });

        $exceptions->render(function (AuthorizationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_FORBIDDEN,
                'FORBIDDEN',
                'Você não tem permissão para realizar esta operação.',
            );
        });

        $exceptions->render(function (ModelNotFoundException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_NOT_FOUND,
                'RESOURCE_NOT_FOUND',
                'O recurso solicitado não foi encontrado.',
            );
        });

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_METHOD_NOT_ALLOWED,
                'METHOD_NOT_ALLOWED',
                'O método HTTP utilizado não é permitido para este recurso.',
                ['allowed_methods' => $exception->getHeaders()['Allow'] ?? null],
                $exception->getHeaders(),
            );
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $routeWasMatched = $request->route() !== null;

            return ApiError::response(
                Response::HTTP_NOT_FOUND,
                $routeWasMatched ? 'RESOURCE_NOT_FOUND' : 'ENDPOINT_NOT_FOUND',
                $routeWasMatched
                    ? 'O recurso solicitado não foi encontrado.'
                    : 'O endpoint solicitado não foi encontrado.',
            );
        });

        $exceptions->render(function (QueryException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
            $driverCode = (string) ($exception->errorInfo[1] ?? $exception->getCode());

            $connectionIsUnavailable = str_starts_with($sqlState, '08')
                || in_array($driverCode, ['2002', '2003', '2006', '2013'], true);

            if ($connectionIsUnavailable) {
                return ApiError::response(
                    Response::HTTP_SERVICE_UNAVAILABLE,
                    'DATABASE_UNAVAILABLE',
                    'Não foi possível acessar os dados no momento. Aguarde alguns instantes e tente novamente.',
                    null,
                    ['Retry-After' => '5'],
                );
            }

            if (in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
                return ApiError::response(
                    Response::HTTP_CONFLICT,
                    'RESOURCE_CONFLICT',
                    'A operação conflita com um registro existente ou relacionado.',
                );
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = $exception->getStatusCode();
            [$code, $message] = match ($status) {
                Response::HTTP_UNPROCESSABLE_ENTITY => [
                    'INSTANCE_REQUIRED',
                    $exception->getMessage() ?: 'A valid instance must be selected.',
                ],
                Response::HTTP_TOO_MANY_REQUESTS => [
                    'TOO_MANY_REQUESTS',
                    'Muitas requisições foram realizadas. Aguarde e tente novamente.',
                ],
                Response::HTTP_REQUEST_TIMEOUT => [
                    'REQUEST_TIMEOUT',
                    'A requisição excedeu o tempo limite. Tente novamente.',
                ],
                Response::HTTP_SERVICE_UNAVAILABLE => [
                    'SERVICE_UNAVAILABLE',
                    'O serviço está temporariamente indisponível. Tente novamente mais tarde.',
                ],
                default => [
                    'HTTP_ERROR',
                    'Não foi possível concluir a requisição.',
                ],
            };

            return ApiError::response($status, $code, $message, null, $exception->getHeaders());
        });

        $exceptions->render(function (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
            return $exception->getResponse();
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiError::response(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'INTERNAL_SERVER_ERROR',
                'Ocorreu um erro inesperado. Tente novamente mais tarde.',
            );
        });
    })->create();
