<?php

declare(strict_types=1);

namespace ILIAS\Plugin\TestArchiveCreator;

use ILIAS\Setup;
use ILIAS\Setup\Config;
use ILIAS\Setup\Agent;
use ILIAS\Setup\Objective;
use ILIAS\Refinery\Transformation;
use ILIAS\Setup\Metrics;
use LogicException;
use ILIAS\Setup\Objective\NullObjective;

/**
 * New SetupAgent for the plugin LongEssayAssessment
 * Provides new DBUpdateSteps from ILIAS 11 onwards.
 */
class SetupAgent implements Agent
{
    public function __construct()
    {
    }

    public function hasConfig(): bool
    {
        return false;
    }

    public function getArrayToConfigTransformation(): Transformation
    {
        throw new LogicException(self::class . " has no Config.");
    }

    public function getInstallObjective(Config $config = null): Objective
    {
        return new Setup\ObjectiveCollection(
            'ILIAS\Plugin\TestArchiveCreator',
            true,
            new \ilDatabaseUpdateStepsExecutedObjective(new DBUpdateSteps11()),
            new \ilComponentInstallPluginObjective("TestArchiveCreator"),
            new \ilComponentUpdatePluginObjective("TestArchiveCreator"),
            new \ilComponentActivatePluginsObjective("TestArchiveCreator")
        );
    }

    public function getUpdateObjective(Config $config = null): Objective
    {

        return new Setup\ObjectiveCollection(
            'ILIAS\Plugin\TestArchiveCreator',
            true,
            new \ilDatabaseUpdateStepsExecutedObjective(new DBUpdateSteps11()),
            new \ilComponentInstallPluginObjective("TestArchiveCreator"),
            new \ilComponentUpdatePluginObjective("TestArchiveCreator"),
            new \ilComponentActivatePluginsObjective("TestArchiveCreator"),
            new \ilPluginLanguageUpdatedObjective("TestArchiveCreator")
        );

    }

    public function getBuildObjective(): Objective
    {
        return new NullObjective();
    }

    public function getStatusObjective(Metrics\Storage $storage): Objective
    {
        return new Setup\ObjectiveCollection(
            'ILIAS\Plugin\TestArchiveCreator',
            true,
            new \ilDatabaseUpdateStepsMetricsCollectedObjective($storage, new DBUpdateSteps11())
        );
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getNamedObjectives(?Config $config = null): array
    {
        return [];
    }
}
