<?php

namespace App\Support\Http;

use App\Domains\Devices\Requests\SaveDeviceRequest;
use App\Domains\Devices\Services\DeviceSchemaCompatibilityService;
use App\Domains\Devices\Services\ProvisioningCatalogSelectionService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Stringable;
use Throwable;

final class ApiContractInventory
{
    private const ROUTE_PARAMETER_PLACEHOLDER = '00000000-0000-4000-8000-000000000000';

    public function __construct(private readonly Router $router) {}

    /**
     * @param  list<string>  $domains
     * @return array<string, mixed>
     */
    public function build(array $domains = []): array
    {
        $domainFilters = collect($domains)
            ->filter(static fn (mixed $domain): bool => is_string($domain) && trim($domain) !== '')
            ->map(static fn (string $domain): string => mb_strtolower(trim($domain)))
            ->values();
        $operations = [];

        foreach ($this->apiRoutes() as $route) {
            $reflection = $this->actionReflection($route);
            $domain = $reflection === null
                ? null
                : $this->domain($reflection->getDeclaringClass()->getName());

            if ($domainFilters->isNotEmpty()
                && ! $domainFilters->contains(mb_strtolower($domain ?? 'support'))) {
                continue;
            }

            $requestContracts = $reflection === null
                ? []
                : $this->requestContracts($route, $reflection);
            $response = $reflection === null
                ? ['declared_type' => null, 'serializers' => []]
                : $this->responseContract($reflection);

            foreach ($this->documentedMethods($route) as $method) {
                $operations[] = [
                    'operation' => $method.' /'.$route->uri(),
                    'method' => $method,
                    'path' => '/'.$route->uri(),
                    'name' => $route->getName(),
                    'domain' => $domain ?? 'Support',
                    'controller' => $route->getActionName(),
                    'middleware' => array_values($route->gatherMiddleware()),
                    'requests' => $requestContracts,
                    'response' => $response,
                ];
            }
        }

        usort(
            $operations,
            static fn (array $left, array $right): int => $left['operation'] <=> $right['operation'],
        );

        $inspectionErrors = collect($operations)
            ->flatMap(static fn (array $operation): array => $operation['requests'])
            ->filter(static fn (array $request): bool => isset($request['inspection_error']))
            ->count();

        return [
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'source' => 'Laravel route collection and FormRequest rules',
            'scope' => [
                'domains' => $domainFilters->all(),
                'operation_count' => count($operations),
                'inspection_error_count' => $inspectionErrors,
            ],
            'notes' => [
                'Fields are GridPBX public API fields, not every field in a connected Switch schema.',
                'Conditional rules reflect the currently configured Switch compatibility profile.',
                'Response serializers identify the authoritative API Resource or response boundary.',
            ],
            'operations' => $operations,
        ];
    }

    /** @return list<Route> */
    private function apiRoutes(): array
    {
        return collect($this->router->getRoutes()->getRoutes())
            ->filter(static fn (Route $route): bool => str_starts_with($route->uri(), 'api/'))
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function documentedMethods(Route $route): array
    {
        return array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));
    }

    private function actionReflection(Route $route): ?ReflectionMethod
    {
        $action = $route->getActionName();

        if ($action === 'Closure') {
            return null;
        }

        if (str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            return new ReflectionMethod($controller, $method);
        }

        return new ReflectionMethod($action, '__invoke');
    }

    private function domain(string $controller): ?string
    {
        return preg_match('/^App\\\\Domains\\\\([^\\\\]+)/', $controller, $matches) === 1
            ? $matches[1]
            : null;
    }

    /** @return list<array<string, mixed>> */
    private function requestContracts(Route $route, ReflectionMethod $reflection): array
    {
        $contracts = [];

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType
                || $type->isBuiltin()
                || ! is_a($type->getName(), FormRequest::class, true)) {
                continue;
            }

            $requestClass = $type->getName();

            try {
                $rules = $this->resolveRules($route, $requestClass);
                $contracts[] = [
                    'class' => $requestClass,
                    'fields' => collect($rules)
                        ->map(fn (mixed $fieldRules): array => $this->normalizeRules($fieldRules))
                        ->all(),
                ];
            } catch (Throwable $exception) {
                $contracts[] = [
                    'class' => $requestClass,
                    'fields' => [],
                    'inspection_error' => $exception::class,
                ];
            }
        }

        return $contracts;
    }

    /**
     * @param  class-string<FormRequest>  $requestClass
     * @return array<string, mixed>
     */
    private function resolveRules(Route $route, string $requestClass): array
    {
        $method = $this->documentedMethods($route)[0] ?? 'GET';
        $uri = preg_replace(
            '/\{[^}]+}/',
            self::ROUTE_PARAMETER_PLACEHOLDER,
            '/'.$route->uri(),
        ) ?? '/'.$route->uri();
        $httpRequest = Request::create($uri, $method);
        $syntheticRoute = new Route([$method], $route->uri(), static fn (): null => null);
        $syntheticRoute->bind($httpRequest);

        /** @var FormRequest $formRequest */
        $formRequest = $requestClass::createFrom($httpRequest);
        $formRequest->setContainer(app());
        $formRequest->setRouteResolver(static fn (): Route => $syntheticRoute);

        /** @var array<string, mixed> $rules */
        $rules = app()->call(
            [$formRequest, 'rules'],
            $this->ruleDependencies($requestClass),
        );

        return $rules;
    }

    /**
     * Contract generation must remain available before external Switch credentials are configured.
     * The unconstructed compatibility service safely selects its bundled fallback in current().
     *
     * @param  class-string<FormRequest>  $requestClass
     * @return array<string, object>
     */
    private function ruleDependencies(string $requestClass): array
    {
        if ($requestClass !== SaveDeviceRequest::class) {
            return [];
        }

        try {
            return [
                'schemaCompatibility' => app(DeviceSchemaCompatibilityService::class),
                'catalogSelections' => app(ProvisioningCatalogSelectionService::class),
            ];
        } catch (Throwable) {
            // Contract export must also work before external Switch credentials are configured.
        }

        return [
            'schemaCompatibility' => (new ReflectionClass(DeviceSchemaCompatibilityService::class))
                ->newInstanceWithoutConstructor(),
            'catalogSelections' => (new ReflectionClass(ProvisioningCatalogSelectionService::class))
                ->newInstanceWithoutConstructor(),
        ];
    }

    /** @return list<string> */
    private function normalizeRules(mixed $rules): array
    {
        $rules = is_array($rules) ? $rules : [$rules];

        return collect($rules)
            ->map(static function (mixed $rule): string {
                if (is_string($rule)) {
                    return $rule;
                }

                if ($rule instanceof Closure) {
                    return 'closure';
                }

                if ($rule instanceof Stringable) {
                    try {
                        return (string) $rule;
                    } catch (Throwable) {
                        return $rule::class;
                    }
                }

                return is_object($rule) ? $rule::class : get_debug_type($rule);
            })
            ->values()
            ->all();
    }

    /** @return array{declared_type: string|null, serializers: list<string>} */
    private function responseContract(ReflectionMethod $reflection): array
    {
        $returnType = $reflection->getReturnType();
        $declaredType = $returnType instanceof ReflectionNamedType ? $returnType->getName() : null;
        $file = $reflection->getFileName();

        if ($file === false) {
            return ['declared_type' => $declaredType, 'serializers' => []];
        }

        $source = file_get_contents($file);
        $lines = file($file);

        if ($source === false || $lines === false) {
            return ['declared_type' => $declaredType, 'serializers' => []];
        }

        $methodSource = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1,
        ));
        preg_match_all(
            '/^use\s+((?:App|GridPbx)\\\\[^;]+Resource);$/m',
            $source,
            $matches,
        );

        $serializers = collect($matches[1] ?? [])
            ->filter(static function (string $resource) use ($methodSource): bool {
                $shortName = class_basename($resource);

                return preg_match('/\\b'.preg_quote($shortName, '/').'\\b/', $methodSource) === 1;
            })
            ->values()
            ->all();

        return [
            'declared_type' => $declaredType,
            'serializers' => $serializers,
        ];
    }
}
