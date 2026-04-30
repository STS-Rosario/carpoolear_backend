<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Drop views before tables during migrate:fresh so wipes stay consistent when SQL views exist (e.g. legacy rating aggregates).
     */
    protected bool $dropViews = true;

    /**
     * Same as RefreshDatabase::beginDatabaseTransaction(), including marking the schema stale when
     * PDO reports no transaction at teardown (so the next test re-runs migrate:fresh). Without that
     * branch, a lost transaction can leave committed rows from the previous test and break isolation.
     */
    public function beginDatabaseTransaction(): void
    {
        $database = $this->app->make('db');

        $connections = $this->connectionsToTransact();

        $this->app->instance('db.transactions', $transactionsManager = new DatabaseTransactionsManager($connections));

        foreach ($connections as $name) {
            $connection = $database->connection($name);

            $connection->setTransactionManager($transactionsManager);

            if ($this->usingInMemoryDatabase($name)) {
                RefreshDatabaseState::$inMemoryConnections[$name] ??= $connection->getPdo();
            }

            $dispatcher = $connection->getEventDispatcher();

            $connection->unsetEventDispatcher();
            $connection->beginTransaction();
            $connection->setEventDispatcher($dispatcher);
        }

        $this->beforeApplicationDestroyed(function () use ($database): void {
            foreach ($this->connectionsToTransact() as $name) {
                $connection = $database->connection($name);
                $dispatcher = $connection->getEventDispatcher();

                $connection->unsetEventDispatcher();

                if ($connection->getPdo() && ! $connection->getPdo()->inTransaction()) {
                    RefreshDatabaseState::$migrated = false;
                }

                $connection->rollBack();
                $connection->setEventDispatcher($dispatcher);
                $connection->disconnect();
            }
        });
    }

    protected function actingAsApiUser($user)
    {
        return $this->actingAs($user, 'api');
    }

    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null): static
    {
        parent::actingAs($user, $guard);

        // Also set on default guard so the UserLoggin middleware can find the user
        if ($guard === 'api') {
            $this->app['auth']->guard()->setUser($user);
        }

        return $this;
    }
}
