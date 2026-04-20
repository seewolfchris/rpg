<?php

declare(strict_types=1);

namespace App\StaticAnalysis\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleError;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * @implements Rule<Node\Expr>
 */
final class NoTransactionInControllersRule implements Rule
{
    /** @var list<string> */
    private const FORBIDDEN_METHOD_CALLS = [
        'transaction',
        'begintransaction',
        'lockforupdate',
    ];

    /** @var list<string> */
    private const FORBIDDEN_STATIC_CALLS = [
        'transaction',
        'begintransaction',
    ];

    /** @var list<string> */
    private const FORBIDDEN_RECEIVER_TYPES = [
        'Illuminate\\Database\\DatabaseManager',
        'Illuminate\\Database\\Connection',
        'Illuminate\\Database\\ConnectionInterface',
        'Illuminate\\Database\\Eloquent\\Builder',
        'Illuminate\\Database\\Query\\Builder',
        'Illuminate\\Database\\Eloquent\\Relations\\Relation',
    ];

    private string $controllersPath;

    /** @var array<string, true> */
    private array $whitelist = [];

    /** @var list<ObjectType> */
    private array $forbiddenReceiverObjectTypes = [];

    /**
     * @param  list<string>  $controllerGuardrailWhitelist
     */
    public function __construct(string $projectRoot, array $controllerGuardrailWhitelist = [])
    {
        $root = rtrim($projectRoot, '/\\');
        $this->controllersPath = $this->normalizePath($root.'/app/Http/Controllers').'/';

        foreach ($controllerGuardrailWhitelist as $file) {
            $this->whitelist[$this->normalizePath($this->absolutizePath($root, $file))] = true;
        }

        foreach (self::FORBIDDEN_RECEIVER_TYPES as $typeClass) {
            $this->forbiddenReceiverObjectTypes[] = new ObjectType($typeClass);
        }
    }

    public function getNodeType(): string
    {
        return Node\Expr::class;
    }

    /**
     * @return list<RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (! $this->shouldInspectScope($scope)) {
            return [];
        }

        if ($node instanceof MethodCall) {
            return $this->processMethodCall($node, $scope);
        }

        if ($node instanceof StaticCall) {
            return $this->processStaticCall($node, $scope);
        }

        return [];
    }

    /**
     * @return list<RuleError>
     */
    private function processMethodCall(MethodCall $node, Scope $scope): array
    {
        $methodName = $this->resolveMethodName($node->name);

        if ($methodName === null || ! in_array($methodName, self::FORBIDDEN_METHOD_CALLS, true)) {
            return [];
        }

        if (! $this->isForbiddenReceiverType($scope->getType($node->var))) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Database transaction/lock call %s() is forbidden in controllers. Move it into an Action.',
                $methodName,
            ))
                ->identifier('architecture.controller.transaction')
                ->build(),
        ];
    }

    /**
     * @return list<RuleError>
     */
    private function processStaticCall(StaticCall $node, Scope $scope): array
    {
        $methodName = $this->resolveMethodName($node->name);

        if ($methodName === null || ! in_array($methodName, self::FORBIDDEN_STATIC_CALLS, true)) {
            return [];
        }

        if ($this->isDbFacadeCall($node)) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'DB::%s() is forbidden in controllers. Move it into an Action.',
                    $methodName,
                ))
                    ->identifier('architecture.controller.transaction')
                    ->build(),
            ];
        }

        $receiverType = $node->class instanceof Node\Name
            ? $scope->resolveTypeByName($node->class)
            : $scope->getType($node->class);

        if (! $this->isForbiddenReceiverType($receiverType)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Database static call %s() is forbidden in controllers. Move it into an Action.',
                $methodName,
            ))
                ->identifier('architecture.controller.transaction')
                ->build(),
        ];
    }

    private function shouldInspectScope(Scope $scope): bool
    {
        $classReflection = $scope->getClassReflection();

        if ($classReflection === null || ! str_ends_with($classReflection->getName(), 'Controller')) {
            return false;
        }

        $file = $this->normalizePath($scope->getFile());
        if ($file === '' || ! str_starts_with($file, $this->controllersPath)) {
            return false;
        }

        return ! isset($this->whitelist[$file]);
    }

    private function isDbFacadeCall(StaticCall $node): bool
    {
        if (! $node->class instanceof Node\Name) {
            return false;
        }

        $className = ltrim(strtolower($node->class->toString()), '\\');

        return $className === 'db' || $className === 'illuminate\\support\\facades\\db';
    }

    private function isForbiddenReceiverType(Type $type): bool
    {
        foreach ($this->forbiddenReceiverObjectTypes as $forbiddenType) {
            if ($forbiddenType->isSuperTypeOf($type)->yes()) {
                return true;
            }
        }

        return false;
    }

    private function resolveMethodName(Node $methodName): ?string
    {
        if (! $methodName instanceof Identifier) {
            return null;
        }

        return strtolower($methodName->toString());
    }

    private function absolutizePath(string $projectRoot, string $file): string
    {
        if ($this->isAbsolutePath($file)) {
            return $file;
        }

        return $projectRoot.'/'.ltrim($file, '/\\');
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1;
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        return (string) preg_replace('#/+#', '/', $normalized);
    }
}
