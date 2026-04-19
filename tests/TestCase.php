<?php

namespace IllumaLaw\VectorSchema\Tests;

use IllumaLaw\VectorSchema\VectorSchemaServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'IllumaLaw\\VectorSchema\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            VectorSchemaServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $connection = $event->connection;

            if ($connection->getDriverName() !== 'sqlite') {
                return;
            }

            $extensionPath = '/usr/local/lib/sqlite-vec/vec0.so';

            if (! file_exists($extensionPath)) {
                return;
            }

            $pdo = $connection->getPdo();

            try {
                $pdo->exec('SELECT load_extension('.$pdo->quote($extensionPath).')');
            } catch (\PDOException $e) {
                //
            }
        });
    }
}
