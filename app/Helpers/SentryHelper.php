<?php

namespace App\Helpers;

use Sentry\State\Scope;

class SentryHelper
{
    /**
     * Log error sa dodatnim kontekstom
     */
    public static function logError(\Throwable $exception, array $context = []): void
    {
        if (app()->bound('sentry')) {
            \Sentry\withScope(function (Scope $scope) use ($exception, $context) {
                // Add custom context
                foreach ($context as $key => $value) {
                    $scope->setContext($key, $value);
                }

                \Sentry\captureException($exception);
            });
        }
    }

    /**
     * Log message sa severity level
     */
    public static function logMessage(string $message, string $level = 'info', array $context = []): void
    {
        if (app()->bound('sentry')) {
            \Sentry\withScope(function (Scope $scope) use ($message, $level, $context) {
                foreach ($context as $key => $value) {
                    $scope->setContext($key, $value);
                }

                \Sentry\captureMessage($message, \Sentry\Severity::fromString($level));
            });
        }
    }

    /**
     * Track performance
     */
    public static function startTransaction(string $name, string $op = 'http.request'): ?\Sentry\Tracing\Transaction
    {
        if (app()->bound('sentry')) {
            $transactionContext = new \Sentry\Tracing\TransactionContext();
            $transactionContext->setName($name);
            $transactionContext->setOp($op);

            return \Sentry\startTransaction($transactionContext);
        }

        return null;
    }
}
