<?php

namespace App\Support;

use Illuminate\Support\ConfigurationUrlParser;
use InvalidArgumentException;

class MySqlConnectionConfiguration
{
    public static function resolve(array $configuration): array
    {
        $configuration = (new ConfigurationUrlParser)->parseConfiguration($configuration);

        // A nested URL would be parsed again by Laravel's database manager.
        if (isset($configuration['url'])) {
            throw new InvalidArgumentException('Nested database connection URLs are not supported.');
        }

        $configuration['timezone'] = '+00:00';

        foreach (['read', 'write'] as $type) {
            if (! isset($configuration[$type]) || ! is_array($configuration[$type])) {
                continue;
            }

            if (array_is_list($configuration[$type])) {
                foreach ($configuration[$type] as &$connection) {
                    $connection['timezone'] = '+00:00';
                }
                unset($connection);
            } else {
                $configuration[$type]['timezone'] = '+00:00';
            }
        }

        return $configuration;
    }
}
